<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IDateTimeFormatter;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Service\Finance\SepaBulkTransactionService;
use OCA\CAFEVDB\Service\Finance\IBulkTransactionExporter;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Common\GenericUndoable;
use OCA\CAFEVDB\Common\IUndoable;
use OCA\CAFEVDB\Common\UndoableFolderRename;

class SepaBulkTransactionsController extends Controller {
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
  public function serviceSwitch($topic, $projectId = 0, $sepaBulkTransactions = [], $sepaDueDeadline = null, $bulkTransactionId = 0)
  {
    switch ($topic) {
    case 'create':
      $sepaBulkTransactions = array_values(array_unique($sepaBulkTransactions));
      // PME_sys_mrecs[] = "{\"musician_id\":\"1\",\"sequence\":\"1\"}"
      $bankAccountRecords = $this->parameterService->getParam($this->pme->cgiSysName('mrecs'), []);
      if (!empty($sepaDueDeadline)) {
        // kludgy, but should work
        $sepaDueDeadline = (new \DateTimeImmutable)
                         ->setTimezone($this->getDateTimeZone())
                         ->setTimestamp(strtotime($sepaDueDeadline));
      }
      return $this->generateBulkTransactions(
        $projectId,
        $bankAccountRecords,
        $sepaBulkTransactions,
        $sepaDueDeadline);
    case 'export':
      return $this->exportBulkTransaction($bulkTransactionId, $projectId);
    default:
      break;
    }
    return self::grumble($this->l->t('Unknown Request: "%s".', $topic));
  }

  /**
   * Generate the SEPA-bulk-transactions as requested by the submitted
   * parameters.
   *
   * @param int $projectId
   *
   * @param string $bankAccoutRecords JSON-data with musician_id,
   * sequence and debit-mandate sequence as SepaDebitMandates_key
   *
   * @param array $bulkTransactions Actually the options from
   * Entities\ProjectParticipantFieldDataOption to take into account.
   *
   * @bug This function is too long; the functionality should be splitted and
   * moved to a service class.
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
      if ($account->isDeleted()) {
        // This can happen when forcing display of soft-deleted rows in expert mode.
        return self::grumble($this->l->t('Refusing to use a revoked or disabled bank account. The bank-account %1$s for %2$s (musician-id %3$d, sequence %4$d) has been revoked or deleted on %5$s.',
                                         [ $account->getIban(), $account->getMusician()->getPublicName(), $musicianId, $sequence, $this->formatDate($account->getDeleted()) ]));
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
        if ($mandate->isDeleted()) {
          // This can happen when forcing display of soft-deleted rows in expert mode.
          return self::grumble($this->l->t('Refusing to use a revoked or disabled debit-mandate. The debit-mandate %1$s for the bank-account %2$s of %3$s (musician-id %4$d, mandate-sequence %5$d) has been revoked or deleted on %6$s.', [
            $mandate->getMandateReference(),
            $account->getIban(),
            $account->getMusician()->getPublicName(),
            $musicianId,
            $mandateSequence,
            $this->formatDate($mandate->getDeleted()),
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

    // One pre-run over all affected participants in order to
    // determine the earliest due-date possible.

    if (empty($dueDeadline)) {
      // Count forward from now, just take the maximum. Note that
      // bank-transfers are actually issued immediately. But in the
      // case of debit-notes the due-deadline makes sure that deposits
      // and total fees are not charged too early.
      //
      // If we really have amounts to pay with a registered deposit
      // amount, then this means that the recipient may receive its
      // full amount a bit too early as the debit-note
      // pre-notification delay is up to 14 days, roughly.
      //
      // If we have some debit-mandates with a reduced
      // pre-notification time then the orchestra may receive the
      // money a bit late.
      //
      // In edge cases (deposit deadline and due-deadline closer than
      // the pre-notification delay) this could result in charging the
      // full amount instead of the deposit. But then the actual
      // due-deadline would be past the deadline for the full amount,
      // so this would still be in accordance with the payment
      // negotiations of the participant.

      $dueDateEstimate = $now;
      foreach ($participants as $musicianId => $participant) {
        $debitMandate = $debitMandates[$musicianId];
        $dueDateEstimate = max(
          $dueDateEstimate,
          $this->financeService->targetDeadline(
              $debitMandate->getPreNotificationBusinessDays()?:0,
              $debitMandate->getPreNotificationCalendarDays(),
              $now)
        );
      }

      // don't set $dueDeadline to $dueDateEstimate as we do not yet
      // know whether we arrive at debot-notes or bank-transfers.
    } else {
      $dueDateEstimate = $dueDeadline;
    }

    /** @var Entities\SepaDebitNote $debitNote */
    $debitNote = new Entities\SepaDebitNote;

    /** @var Entities\SepaBankTransfer $bankTransfer */
    $bankTransfer = new Entities\SepaBankTransfer;

    // book-keeping in order to avoid mixed bulk debit-notes
    $nonRecurring = null;

    // array of conflicting debit mandates
    $preNotificationConflicts = [];

    /** @var Entities\ProjectParticipant $participant */
    foreach ($participants as $musicianId => $participant) {
      /** @var Entities\CompositePayment $compositePayment */
      $compositePayment = $this->bulkTransactionService->generateProjectPayments($participant, $receivables, $dueDateEstimate);
      if ($compositePayment->getAmount() == 0.0) {
        // $this->logInfo('AMOUNT IS 0 ' . $participant->getMusician()->getPublicName());
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
            $this->l->t('Musician "%s" has to pay an amount of %s, but there is no debit-note-mandate for the musician.', [
              $participant->getMusician()->getPublicName(), $this->moneyValue($compositePayment->getAmount()),
            ]));
        }

        // In general we do not use non-recurring, but play
        // safe. Mixing recurring and non-recurring debit-notes may
        // lead to errors when submitting the data to the bank.
        if ($nonRecurring !== null && $debitMandate->getNonRecurring() != $nonRecurring) {
          return self::grumble(
            $this->l->t('The debit-mandate for a bulk-transaction must either be all recurring or all one-time-only. The conflicting mandate of musician "%s", mandate-reference "%s" is %s, the previous mandates were %s.', [
              $participant->getMusician()->getPublicName(),
              $mandate->getMandateReference(),
              ($nonRecurring ? $this->l->t('recurring') : $this->l->t('non-recurring')),
              ($nonRecurring ? $this->l->t('non-recurring') : $this->l->t('recurring')),
            ]));
        }
        $nonRecurring = $debitMandate->getNonRecurring();

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
            $preNotificationConflicts[] = [
              'mandate' => $debitMandate,
              'notification' => $notificationDeadline,
            ];
            // return self::grumble($this->l->t(
            //   'Due-deadline %s conflicts with the pre-notification dead-line %s for the debit-mandate "%s".', [
            //     $this->dateTimeFormatter->formatDate($dueDeadline, 'medium'),
            //     $this->dateTimeFormatter->formatDate($notificationDeadline, 'medium'),
            //     $debitMandate->getMandateReference(),
            //   ]));
          }
          $latestNotification = min($latestNotification, $notificationDeadline);
        }
      } else {
        $compositePayment->setSepaTransaction($bankTransfer);
        $bankTransfer->getPayments()->set($musicianId, $compositePayment);
      }
    }

    // notify the operator about all conflicts.
    if (!empty($preNotificationConflicts)) {
      $messages = [];
      foreach ($preNotificationConflicts as list($debitMandate, $notifictationDeadline)) {
        $messages[] = $this->l->t(
          'Due-deadline %s conflicts with the pre-notification dead-line %s for the debit-mandate "%s".', [
            $this->dateTimeFormatter->formatDate($dueDeadline, 'medium'),
            $this->dateTimeFormatter->formatDate($notificationDeadline, 'medium'),
            $debitMandate->getMandateReference(),
          ]);
      }
      return self::grumble([ 'message' => $messages ]);
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
        $this->entityManager
          ->registerPreFlushAction(new GenericUndoable(
            function() use ($bulkTag, $bulkTransaction, $project, $bulkSubmissionNames) {
              list('uri' => $eventUri, 'uid' => $eventUid) = $this->financeService->financeEvent(
                $bulkSubmissionNames[$bulkTag]['submission'],
                $this->l->t('Due date: %s',
                            $this->dateTimeFormatter->formatDate($bulkTransaction->getDueDate(), 'long')),
                $project,
                $bulkTransaction->getSubmissionDeadline(),
                SepaBulkTransactionService::BULK_TRANSACTION_REMINDER_SECONDS);
              $bulkTransaction->setSubmissionEventUri($eventUri);
              $bulkTransaction->setSubmissionEventUid($eventUid);
              return $eventUri;
            },
            function($uri) {
              $this->financeService->deleteFinanceCalendarEntry($uri);
            }
          ))
          ->register(new GenericUndoable(
            function() use ($bulkTag, $bulkTransaction, $project, $bulkSubmissionNames) {
              list('uri' => $taskUri, 'uid' => $taskUid) = $this->financeService->financeTask(
                $bulkSubmissionNames[$bulkTag]['submission'],
                $this->l->t('Due date: %s',
                            $this->dateTimeFormatter->formatDate($bulkTransaction->getDueDate(), 'long')),
                $project,
                $bulkTransaction->getSubmissionDeadline(),
                SepaBulkTransactionService::BULK_TRANSACTION_REMINDER_SECONDS);
              $bulkTransaction->setSubmissionTaskUri($taskUri);
              $bulkTransaction->setSubmissionTaskUid($taskUid);
              return $taskUri;
            },
            function($uri) {
              $this->financeService->deleteFinanceCalendarEntry($uri);
            }
          ))
          ->register(new GenericUndoable(
            function() use ($bulkTag, $bulkTransaction, $project, $bulkSubmissionNames) {
              list('uri' => $eventUri, 'uid' => $eventUid) = $this->financeService->financeEvent(
                $bulkSubmissionNames[$bulkTag]['due'],
                $this->l->t('TODO: add more information like the list of export-files, totals etc.'),
                $project,
                $bulkTransaction->getDueDate());
              $bulkTransaction->setDueEventUri($eventUri);
              $bulkTransaction->setDueEventUid($eventUid);
              return $eventUri;
            },
            function($uri) {
              $this->financeService->deleteFinanceCalendarEntry($uri);
            }
          ));
      }
    }

    // add also the notification deadline
    if (!empty($debitNote)) {
      $this->entityManager
        ->registerPreFlushAction(new GenericUndoable(
          function() use ($debitNote, $project, $bulkSubmissionNames) {
            list('uri' => $eventUri, 'uid' => $eventUid) = $this->financeService->financeEvent(
              $bulkSubmissionNames['debitNotes']['notification'],
              $this->l->t('Submission-deadline: %s, due date: %s.', [
                $this->dateTimeFormatter->formatDate($debitNote->getSubmissionDeadline(), 'long'),
                $this->dateTimeFormatter->formatDate($debitNote->getDueDate(), 'long'),
              ]),
              $project,
              $debitNote->getPreNotificationDeadline(),
              SepaBulkTransactionService::BULK_TRANSACTION_REMINDER_SECONDS);
            $debitNote->setPreNotificationEventUri($eventUri);
            $debitNote->setPreNotificationEventUid($eventUid);
            return $eventUri;
          },
          function($uri) {
            $this->financeService->deleteFinanceCalendarEntry($uri);
          }
        ))
        ->register(new GenericUndoable(
          function() use ($debitNote, $project, $bulkSubmissionNames) {
            list('uri' => $taskUri, 'uid' => $taskUid) = $this->financeService->financeTask(
              $bulkSubmissionNames['debitNotes']['notification'],
              $this->l->t('Submission-deadline: %s, due date: %s.', [
                $this->dateTimeFormatter->formatDate($debitNote->getSubmissionDeadline(), 'long'),
                $this->dateTimeFormatter->formatDate($debitNote->getDueDate(), 'long'),
              ]),
              $project,
              $debitNote->getPreNotificationDeadline(),
              SepaBulkTransactionService::BULK_TRANSACTION_REMINDER_SECONDS);
            $debitNote->setPreNotificationTaskUri($taskUri);
            $debitNote->setPreNotificationTaskUid($taskUid);
            return $taskUri;
          },
          function($uri) {
            $this->financeService->deleteFinanceCalendarEntry($uri);
          }
        ));
    }

    $this->entityManager->beginTransaction();
    try {

      // action must come before persist
      $this->entityManager->executePreFlushActions();

      if (!empty($debitNote)) {
        $this->persist($debitNote);
      }
      if (!empty($bankTransfer)) {
        $this->persist($bankTransfer);
      }

      $this->flush();

      $this->entityManager->commit();
    } catch (\Throwable $t) {
      $this->entityManager->rollback();
      $this->entityManager->reopen();
      $this->logException($t);

      return self::grumble($this->exceptionChainData($t));
    }

    // report back the generated bulk-transactions, as these are the
    // (at most) two top-level objects.

    $messages = [];
    if (!empty($bankTransfer)) {
      $messages[] = $this->l->n(
        'Scheduled %n bank-transfer, due on %s',
        'Scheduled %n bank-transfers, due on %s',
        $bankTransfer->getPayments()->count(),
        [ $this->dateTimeFormatter->formatDate($bankTransfer->getDueDate(), 'long'), ]);
    }
    if (!empty($debitNote)) {
      $messages[] = $this->l->n(
        'Scheduled %n debit-note, due on %s',
        'Scheduled %n debit-notes, due on %s',
        $debitNote->getPayments()->count(),
        [ $this->dateTimeFormatter->formatDate($debitNote->getDueDate(), 'long'), ]
      );
    }

    $responseData = [
      'message' => $messages,
      'bankTransferId' => empty($bankTransfer) ? 0 : $bankTransfer->getId(),
      'debitMandateId' => empty($debitNote) ? 0 : $debitNote->getId(),
    ];
    return self::dataResponse($responseData);
  }

  /**
   * Generate export-sets for the given bulk-transaction.
   *
   * $param int $bulkTransactionId
   */
  private function exportBulkTransaction($bulkTransactionId, $projectId = 0, $format = SepaBulkTransactionService::EXPORT_AQBANKING)
  {
    $id = filter_var($bulkTransactionId, FILTER_VALIDATE_INT, ['min_range' => 1]);
    if ($id === false) {
      return self::grumble(
        $this->l->t('Submitted value "%s" is not a positive integer.',
                    $bulkTransactionId));
    }

    if ((int)$projectId > 0) {
      /** @var Entities\Project $project */
      $project = $this->getDatabaseRepository(Entities\Project::class)->find($projectId);
    } else {
      $project = null;
    }

    /** @var Entities\SepaBulkTransaction $bulkTransaction */
    $bulkTransaction = $this->getDatabaseRepository(Entities\SepaBulkTransaction::class)
                            ->find($id);
    if (empty($bulkTransaction)) {
      return self::grumble($this->l->t('Unable to find bulk-transaction with id %d.', $id));
    }

    try {
      $exportFile = $this->bulkTransactionService->generateTransactionData($bulkTransaction, $project, $format);
    } catch (\Throwable $t) {
      $this->logException($t);
      return self::grumble($this->exceptionChainData($t));
    }

    return new RedirectResponse(
      $this->urlGenerator()->linkToRoute($this->appName().'.downloads.get', [
        'section' => 'database', 'object' => $exportFile->getId()
      ])
      . '?fileName=' . urlencode($exportFile->getFileName())
      . '&requesttoken=' . urlencode(\OCP\Util::callRegister())
    );
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
