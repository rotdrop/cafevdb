<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Controller;

use \PHP_IBAN\IBAN;

use OCP\AppFramework\Controller;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IDateTimeFormatter;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Service\Finance\SepaBulkTransactionService;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Common\UndoableRunQueue;
use OCA\CAFEVDB\Common\GenericUndoable;
use OCA\CAFEVDB\Common\IUndoable;
use OCA\CAFEVDB\Common\UndoableFolderRename;

class SepaDebitNotesController extends Controller {
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var RequestParameterService */
  private $parameterService;

  /** @var SepaBulkTransactionService */
  private $bulkTransactionService;

  /** @var FinanceService */
  private $financeService;

  /** @var ProjectService */
  private $projectService;

  /** @var IDateTimeFormatter */
  private $dateTimeFormatter;

  /** @var PHPMyEdit */
  protected $pme;

  /** @var EntityManager */
  protected $entityManager;

  public function __construct(
    $appName
    , IRequest $request
    , RequestParameterService $parameterService
    , ConfigService $configService
    , FinanceService $financeService
    , ProjectService $projectService
    , SepaBulkTransactionService $bulkTransactionService
    , IDateTimeFormatter $dateTimeFormatter
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
  ) {
    parent::__construct($appName, $request);
    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->financeService = $financeService;
    $this->projectService = $projectService;
    $this->bulkTransactionService = $bulkTransactionService;
    $this->dateTimeFormatter = $dateTimeFormatter;
    $this->entityManager = $entityManager;
    $this->pme = $phpMyEdit;
    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   */
  public function serviceSwitch($topic, $projectId = 0, $sepaBulkTransactions = [])
  {
    switch ($topic) {
    case 'create':
      $sepaBulkTransactions = array_values(array_unique($sepaBulkTransactions));
      // PME_sys_mrecs[] = "{\"musician_id\":\"1\",\"sequence\":\"1\"}"
      $bankAccountRecords = $this->parameterService->getParam($this->pme->cgiSysName('mrecs'), []);
      return $this->generateBulkTransactions(
        $projectId,
        $bankAccountRecords,
        $sepaBulkTransactions);
    default:
      break;
    }
    return self::grumble($this->l->t('Unknown Request: "%s".', $topic));
  }

  /**
   * Generate the SEPA-bulk-transactions as requested by the submitted
   * parameters.
   *
   * @bug This function is too long.
   */
  private function generateBulkTransactions($projectId, $bankAccountRecords, $bulkTransactions, $dueDeadline = null)
  {
    /** @var Entities\Project $project */
    $project = $this->getDatabaseRepository(Entities\Project::class)->find($projectId);
    if (empty($project)) {
      return self::grumble($this->l->t('Unable to retrieve project with id %d from data-base.', $projectId));
    }

    // Decode all mandate ids and fetch the mandates from the
    // data-base.  The data-base transactions could be optimized and
    // retrieve all in a single query.
    $accountsRepository = $this->getDatabaseRepository(Entities\SepaBankAccount::class);
    $debitMandatesRepository = $this->getDatabaseRepository(Entities\SepaDebitMandate::class);
    $participantsRepository = $this->getDatabaseRepository(Entities\ProjectParticipant::class);

    $bankAccounts = [];
    $debitMandates = [];
    $participants = [];
    foreach ($bankAccountRecords as $accountRecord) {
      $accountId = json_decode($accountRecord, true);
      $musicianId = $accountId['musician_id'];
      $sequence = $accountId['sequence'];
      $mandateSequence = $accountId['SepaDebitMandates_key'];

      /** @var Entities\SepaBankAccount $account */
      $account = $accountsRepository->find([
        'musician' => $musicianId,
        'sequence' => $sequence,
      ]);
      if (empty($account)) {
        return self::grumble($this->l->t('Bank account for musician-id %d, sequence %d not found.',
                                         [ $musicianId, $sequence ]));
      }
      if (!empty($bankAccounts[$musicianId])) {
        return self::grumble(
          $this->l->t('More than one bank account submitted for musician %s, multiple IBANs %s, %s.',
                      [
                        $bankAccounts[$musicianId]->getMusician()->getPublicName(),
                        $bankAccounts[$musicianId]->getIban(),
                        $account->getIban(),
                      ]));
      }
      $bankAccounts[$musicianId] = $account;

      $participant = $participantsRepository->find(['project' => $project, 'musician' => $musicianId]);
      if (empty($participant)) {
        return self::grumble(
          $this->l->t('Musician "%s" does not seem to belong to project "%s".', [
            $account->getMusician()->getPublicName(), $project->getName()
          ]));
      }
      $participants[$musicianId] = $participant;

      if (!empty($mandateSequence)) {
        /** @var Entities\SepaDebitMandate $mandate */
        $mandate = $debitMandatesRepository->find([
          'musician' => $musicianId,
          'sequence' => $mandateSequence,
        ]);
        if (empty($mandate)) {
        return self::grumble($this->l->t('Debit-mandate for musician-id %d, sequence %d not found.',
                                         [ $musicianId, $mandateSequence ]));
        }
        if (!empty($debitMandates[$musicianId])) {
          return self::grumble(
            $this->l->t('More than one debit-mandate submitted for musician %s, multiple references %s, %s.',
                        [
                          $mandate[$musicianId]->getMusician()->getPublicName(),
                          $debitMandates[$musicianId]->getMandateReference(),
                          $mandate->getMandateReference(),
                        ]));
        }
        if ($mandate->getSepaBankAccount() != $account) {
          return self::grumble(
            $this->l->t('Debit-mandate is for bank-account "%s", but the current bank-account is "%s".', [
              $mandate->getSepaBankAccount()->getIban(), $account->getIban()
            ]));
        }
        $debitMandates[$musicianId] = $mandate;
        // $this->logInfo('MANDATE '.\OCA\CAFEVDB\Common\Functions\dump($mandate));
      }
    }

    // foreach ($bankAccounts as $musicianId => $account) {
    //   $this->logInfo('ACCOUNT: '.$account->getBankAccountOwner().' '.$account->getIban());
    // }

    // Fetch all desired field options. We have two cases: for most
    // field-types all options are charged at once, but for recurring
    // service-fees only the selected options are taken into account.

    $fieldRepository = $this->getDatabaseRepository(Entities\ProjectParticipantField::class);
    $receivablesRepository = $this->getDatabaseRepository(Entities\ProjectParticipantFieldDataOption::class);
    $receivables = [];
    foreach ($bulkTransactions as $bulkTransaction) {
      $fieldId = filter_var($bulkTransaction, FILTER_VALIDATE_INT, ['min_range' => 1]);
      if ($fieldId !== false) {
        // all options from this field
        /** @var Entities\ProjectParticipantField $field */
        $field = $fieldRepository->find($fieldId);
        if ($field->getProject() != $project) {
          return self::grumble($this->l->t('Internal data inconsistency, field-project "%s" does not match current project "%s".',
                                           [ $project->getName(), $field->getProject()->getName() ]));
        }
        foreach ($field->getSelectableOptions() as $receivable) {
          $receivables[] = $receivable;
        }
      } else if (Uuid::isValid($bulkTransaction)) {
        // just this option, should be a recurring receivable
        /** @var Entities\ProjectParticipantFieldDataOption $receivable */
        $receivable = $receivablesRepository->findOneBy(['key' => Uuid::asUuid($bulkTransaction) ]);
        if ($receivable->getField()->getProject() != $project) {
          return self::grumble($this->l->t('Internal data inconsistency, field-project "%s" does not match current project "%s".',
                                           [ $project->getName(), $receivable->getField()->getProject()->getName() ]));
        }
        $receivables[] = $receivable;
      } else {
        return self::grumble($this->l->t('Submitted debit-job id "%s" is neither a participant field nor a field-option uuid.', $bulkTransaction));
      }
    }

    // foreach ($receivables as $receivable) {
    //   $this->logInfo('RECEIVABLE '.$receivable->getKey().' / '.$receivable->getLabel().' / '.$receivable->getData());
    // }

    // At this point the submitted data should be consistent, start to generate the payments.

    $now = (new \DateTimeImmutable())->setTimezone($this->getDateTimeZone());
    if (empty($dueDeadline)) {
      $earliestDueDate = $now; // will increase
      $latestNotification = $now; // fixed
    } else {
      $earliestDueDate = $dueDeadline; // fixed
      $latestNotification = $dueDeadline; // will shrink
    }

    /** @var Entities\SepaDebitNote $debitNote */
    $debitNote = new Entities\SepaDebitNote;

    /** @var Entities\SepaBankTransfer $bankTransfer */
    $bankTransfer = new Entities\SepaBankTransfer;

    /** @var Entities\ProjectParticipant $participant */
    foreach ($participants as $musicianId => $participant) {
      /** @var Entities\CompositePayment $compositePayment */
      $compositePayment = $this->bulkTransactionService->generateProjectPayments($participant, $receivables);
      $this->logInfo('PAYMENTS FOR '
                     . $participant->getMusician()->getPublicName()
                     . ' ' . $compositePayment->getProjectPayments()->count()
                     . ' ' . $compositePayment->getAmount());

      if ($compositePayment->getAmount() == 0.0) {
        // @todo Check whether this should be communicated to the musician anyway
        continue;
      }
      $compositePayment->setSepaBankAccount($bankAccounts[$musicianId]);
      if ($compositePayment->getAmount() > 0.0) {
        /** @var Entities\SepaDebitMandate $debitMandate */
        $debitMandate = $debitMandates[$musicianId];

        // payment, try debit note, bail out if there is none.
        // @todo We could relay this and just send a reminder to the musician.
        if (empty($debitMandate)) {
          return self::grumble(
            $this->l->t('Musician "%s" has to pay an amount of "%f", but there is no debit-note-mandate for the musician.', [
              $participant->getMusician()->getPublicName(), $compositePayment->getAmount(),
            ]));
        }
        $compositePayment->setSepaDebitMandate($debitMandate);
        $compositePayment->setSepaTransaction($debitNote);
        $debitNote->getPayments()->set($musicianId, $compositePayment);

        if (empty($dueDeadline)) {
          // count forward from now, just take the maximum
          $earliestDueDate = max(
            $earliestDueDate,
            $this->financeService->targetDeadline(
              $debitMandate->getPreNotificationBusinessDays()?:0,
              $debitMandate->getPreNotificationCalendarDays(),
              $now)
          );
        } else {
          // count backwards from desired deadline
          $notificationDeadline = $this->financeService->targetDeadline(
            $debitMandate->getPreNotificationBusinessDays()?:0,
            $debitMandate->getPreNotificationCalendarDays(),
            $dueDeadline);
          if ($notificationDeadline < $now) {
            return self::grumble($this->l->t(
              'Due-deadline %s conflicts with the pre-notification dead-line %s for the debit-mandate "%s".', [
                $this->dateTimeFormatter->formatDate($dueDeadline, 'medium'),
                $this->dateTimeFormatter->formatDate($notificationDeadline, 'medium'),
                $debitMandate->getMandateReference(),
              ]));
          }
          $latestNotification = min($latestNotification, $notificationDeadline);
        }
      } else {
        $compositePayment->setSepaTransaction($bankTransfer);
        $bankTransfer->getPayments()->set($musicianId, $compositePayment);
      }
    }

    if ($debitNote->getPayments()->count() > 0) {
      $submissionDeadline = $this->financeService->targetDeadline(
        -SepaBulkTransactionService::DEBIT_NOTE_SUBMISSION_DEADLINE,
        null,
        $earliestDueDate);
      $debitNote->setDueDate($earliestDueDate)
                ->setSubmissionDeadline($submissionDeadline)
                ->setPreNotificationDeadline($latestNotification);

    } else {
      $debitNote = null;
    }

    if ($bankTransfer->getPayments()->count() > 0) {
      if (empty($dueDeadline)) {
        $dueDeadline = $this->financeService->targetDeadline(
          SepaBulkTransactionService::BANK_TRANSFER_SUBMISSION_DEADLINE,
          null,
          $now);
      }
      $submissionDeadline = $this->financeService->targetDeadline(
          -SepaBulkTransactionService::BANK_TRANSFER_SUBMISSION_DEADLINE,
          null,
          $dueDeadline
      );

      $bankTransfer->setDueDate($dueDeadline)
                   ->setSubmissionDeadline($submissionDeadline);
    } else {
      $bankTransfer = null;
    }

    // Up to here everything was just in memory. The actual data-base
    // stuff should possibly be moved into the FinanceService.

    $preCommitActions = new UndoableRunQueue($this->logger(), $this->l10n());

    $bulkSubmissionNames = [
      'debitNotes' => [
        'submission' => $this->l->t('Debit notes submission deadline for %s', $project->getName()),
        'due' => $this->l->t('Debit notes due for %s', $project->getName()),
        'notification' => $this->l->t('Debit notes pre-notification deadline for %s', $project->getName()),
      ],
      'bankTransfers' => [
        'submission' => $this->l->t('Bank transfers submission deadline for %s', $project->getName()),
        'due' => $this->l->t('Bank transfers due for %s', $project->getName()),
      ],
    ];

    /** @var Entities\SepaBulkTransaction $bulkTransaction */
    foreach ([ 'debitNotes' => $debitNote, 'bankTransfers' => $bankTransfer] as $bulkTag => $bulkTransaction) {
      if (!empty($bulkTransaction)) {
        $preCommitActions->register(new GenericUndoable(
          function() use ($bulkTag, $bulkTransaction, $project, $bulkSubmissionNames) {
            $calendarUri = $this->financeService->financeEvent(
              $bulkSubmissionNames[$bulkTag]['submission'],
              $this->l->t('Due date: %s',
                          $this->dateTimeFormatter->formatDate($bulkTransaction->getDueDate(), 'long')),
              $project,
              $bulkTransaction->getSubmissionDeadline(),
              SepaBulkTransactionService::BULK_TRANSACTION_REMINDER_SECONDS);
            $bulkTransaction->setSubmissionEventUri($calendarUri);
            return $calendarUri;
          },
          function($done) {
            $this->financeService->deleteFinanceCalendarEntry($done);
          }
        ));
        $preCommitActions->register(new GenericUndoable(
          function() use ($bulkTag, $bulkTransaction, $project, $bulkSubmissionNames) {
            $calendarUri = $this->financeService->financeTask(
              $bulkSubmissionNames[$bulkTag]['submission'],
              $this->l->t('Due date: %s',
                          $this->dateTimeFormatter->formatDate($bulkTransaction->getDueDate(), 'long')),
              $project,
              $bulkTransaction->getSubmissionDeadline(),
              SepaBulkTransactionService::BULK_TRANSACTION_REMINDER_SECONDS);
            $bulkTransaction->setSubmissionTaskUri($calendarUri);
            return $calendarUri;
          },
          function($done) {
            $this->financeService->deleteFinanceCalendarEntry($done);
          }
        ));
        $preCommitActions->register(new GenericUndoable(
          function() use ($bulkTag, $bulkTransaction, $project, $bulkSubmissionNames) {
            $calendarUri = $this->financeService->financeEvent(
              $bulkSubmissionNames[$bulkTag]['due'],
              $this->l->t('TODO: add more information like the list of export-files, totals etc.'),
              $project,
              $bulkTransaction->getDueDate());
            $bulkTransaction->setDueEventUri($calendarUri);
            return $calendarUri;
          },
          function($done) {
            $this->financeService->deleteFinanceCalendarEntry($done);
          }
        ));
      }
    }

    // add also the notification deadline
    if (!empty($debitNote)) {
        $preCommitActions->register(new GenericUndoable(
          function() use ($debitNote, $project, $bulkSubmissionNames) {
            $calendarUri = $this->financeService->financeEvent(
              $bulkSubmissionNames['debitNotes']['notification'],
              $this->l->t('Submission-deadline: %s, due date: %s.', [
                $this->dateTimeFormatter->formatDate($debitNote->getSubmissionDeadline(), 'long'),
                $this->dateTimeFormatter->formatDate($debitNote->getDueDate(), 'long'),
              ]),
              $project,
              $debitNote->getPreNotificationDeadline(),
              SepaBulkTransactionService::BULK_TRANSACTION_REMINDER_SECONDS);
            $debitNote->setPreNotificationEventUri($calendarUri);
            return $calendarUri;
          },
          function($done) {
            $this->financeService->deleteFinanceCalendarEntry($done);
          }
        ));
        $preCommitActions->register(new GenericUndoable(
          function() use ($debitNote, $project, $bulkSubmissionNames) {
            $calendarUri = $this->financeService->financeTask(
              $bulkSubmissionNames['debitNotes']['notification'],
              $this->l->t('Submission-deadline: %s, due date: %s.', [
                $this->dateTimeFormatter->formatDate($debitNote->getSubmissionDeadline(), 'long'),
                $this->dateTimeFormatter->formatDate($debitNote->getDueDate(), 'long'),
              ]),
              $project,
              $debitNote->getPreNotificationDeadline(),
              SepaBulkTransactionService::BULK_TRANSACTION_REMINDER_SECONDS);
            $debitNote->setPreNotificationTaskUri($calendarUri);
            return $calendarUri;
          },
          function($done) {
            $this->financeService->deleteFinanceCalendarEntry($done);
          }
        ));
    }

    $this->entityManager->beginTransaction();
    try {

      // action must come before persist
      $preCommitActions->executeActions();

      if (!empty($debitMandate)) {
        $this->persist($debitNote);
      }
      if (!empty($bankTransfer)) {
        $this->persist($bankTransfer);
      }

      $this->flush();

      $this->entityManager->commit();
    } catch (\Throwable $t) {
      $preCommitActions->executeUndo();
      $this->entityManager->rollback();
      $this->entityManager->reopen();
      $this->logException($t);

      return self::grumble($this->exceptionChainData($t));
    }

    // report back the generated bulk-transactions, as these are the
    // (at most) two top-level objects.

    $messages = [];
    if (!empty($bankTransfer)) {
      $messages[] = $this->l->t('Scheduled %d bank-transfers, due on %s', [
        $bankTransfer->getPayments()->count(),
        $this->dateTimeFormatter($bankTransfer->getDueDate(), 'long'),
      ]);
    }
    if (!empty($debitNote)) {
      $messages[] = $this->l->t('Scheduled %d debit-notes, due on %s', [
        $debitNote->getPayments()->count(),
        $this->dateTimeFormatter($debitNote->getDueDate(), 'long'),
      ]);
    }

    $responseData = [
      'message' => $messages,
      'bankTransferId' => empty($bankTransfer) ? 0 : $bankTransfer->getId(),
      'debitMandateId' => empty($debitMandate) ? 0 : $debitMandate->getId(),
    ];
    return self::dataResponse($responseData);
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
