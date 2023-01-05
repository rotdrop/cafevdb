<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Controller;

use \PHP_IBAN\IBAN;
use \DateTimeImmutable;

use OCP\AppFramework\Controller;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\DataResponse;
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
use OCA\CAFEVDB\PageRenderer\PMETableViewBase;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Common\GenericUndoable;
use OCA\CAFEVDB\Common\IUndoable;
use OCA\CAFEVDB\Common\UndoableFolderRename;

/** AJAX backend for managing bank bulk transactions. */
class SepaBulkTransactionsController extends Controller
{
  use \OCA\RotDrop\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const TRANSACTION_TYPE_DEBIT_NOTE = SepaBulkTransactionService::TRANSACTION_TYPE_DEBIT_NOTE;
  const TRANSACTION_TYPE_BANK_TRANSFER = SepaBulkTransactionService::TRANSACTION_TYPE_BANK_TRANSFER;
  const TRANSACTION_TYPES = [
    self::TRANSACTION_TYPE_DEBIT_NOTE,
    self::TRANSACTION_TYPE_BANK_TRANSFER,
  ];

  const ALARM_FROM_START = FinanceService::VALARM_FROM_START;
  const ALARM_FROM_END = FinanceService::VALARM_FROM_END;

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

  /** {@inheritdoc} */
  public function __construct(
    string $appName,
    IRequest $request,
    RequestParameterService $parameterService,
    ConfigService $configService,
    FinanceService $financeService,
    ProjectService $projectService,
    SepaBulkTransactionService $bulkTransactionService,
    IDateTimeFormatter $dateTimeFormatter,
    EntityManager $entityManager,
    PHPMyEdit $phpMyEdit,
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
   * @param string $topic What to do.
   *
   * @param int $projectId Entity id.
   *
   * @param array $sepaBulkTransactions Actually the options from
   * Entities\ProjectParticipantFieldDataOption to take into account.
   *
   * @param null|string $sepaDueDeadline Desired due deadline of bulk-transaction,
   * automatically determined if null.
   *
   * @param int $bulkTransactionId Existing bulk transaction entity id.
   *
   * @return DataResponse
   *
   * @NoAdminRequired
   */
  public function serviceSwitch(
    string $topic,
    int $projectId = 0,
    array $sepaBulkTransactions = [],
    ?string $sepaDueDeadline = null,
    int $bulkTransactionId = 0,
  ):Response {
    switch ($topic) {
      case 'create':
        $sepaBulkTransactions = array_values(array_unique($sepaBulkTransactions));
        // PME_sys_mrecs[] = "{\"musician_id\":\"1\",\"sequence\":\"1\"}"
        $bankAccountRecords = $this->parameterService->getParam($this->pme->cgiSysName('mrecs'), []);
        if (!empty($sepaDueDeadline)) {
          // kludgy, but should work
          $sepaDueDeadline = (new DateTimeImmutable)
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
   * @param array $bankAccountRecords Array of JSON-data with musician_id,
   * sequence and debit-mandate sequence as SepaDebitMandates_key.
   *
   * @param array $bulkTransactions Actually the options from
   * Entities\ProjectParticipantFieldDataOption to take into account.
   *
   * @param null|string $dueDeadline Desired due deadline of bulk-transaction,
   * automatically determined if null.
   *
   * @return DataResponse
   *
   * @bug This function is too long; the functionality should be splitted and
   * moved to a service class.
   */
  private function generateBulkTransactions(
    int $projectId,
    array $bankAccountRecords,
    array $bulkTransactions = [],
    mixed $dueDeadline = null,
  ):DataResponse {
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
      $mandateSequence = $accountId[PMETableViewBase::joinTableMasterFieldName(PMETableViewBase::SEPA_DEBIT_MANDATES_TABLE)];

      /** @var Entities\SepaBankAccount $account */
      $account = $accountsRepository->find([
        'musician' => $musicianId,
        'sequence' => $sequence,
      ]);
      if (empty($account)) {
        return self::grumble($this->l->t(
          'Bank account for musician-id %d, sequence %d not found.',
          [ $musicianId, $sequence ]));
      }
      if ($account->isDeleted()) {
        // This can happen when forcing display of soft-deleted rows in expert mode.
        return self::grumble($this->l->t(
          'Refusing to use a revoked or disabled bank account. The bank-account %1$s for %2$s (musician-id %3$d, sequence %4$d) has been revoked or deleted on %5$s.',
          [ $account->getIban(), $account->getMusician()->getPublicName(), $musicianId, $sequence, $this->formatDate($account->getDeleted()) ]));
      }
      if (!empty($bankAccounts[$musicianId])) {
        return self::grumble($this->l->t(
          'More than one bank account submitted for musician %s, multiple IBANs %s, %s.', [
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
          return self::grumble($this->l->t(
            'Debit-mandate for musician-id %d, sequence %d not found.',
            [ $musicianId, $mandateSequence ]));
        }
        if (!empty($debitMandates[$musicianId])) {
          return self::grumble(
            $this->l->t(
              'More than one debit-mandate submitted for musician %s, multiple references %s, %s.', [
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
          return self::grumble($this->l->t(
            'Refusing to use a revoked or disabled debit-mandate.'
            . ' The debit-mandate %1$s for the bank-account %2$s of %3$s (musician-id %4$d, mandate-sequence %5$d) has been revoked or deleted on %6$s.', [
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
          return self::grumble($this->l->t(
            'Internal data inconsistency, field-project "%s" does not match current project "%s".',
            [ $project->getName(), $field->getProject()->getName() ]));
        }
        foreach ($field->getSelectableOptions() as $receivable) {
          $receivables[] = $receivable;
        }
      } elseif (Uuid::isValid($bulkTransaction)) {
        // just this option, should be a recurring receivable
        /** @var Entities\ProjectParticipantFieldDataOption $receivable */
        $receivable = $receivablesRepository->findOneBy(['key' => Uuid::asUuid($bulkTransaction) ]);
        if ($receivable->getField()->getProject() != $project) {
          return self::grumble($this->l->t(
            'Internal data inconsistency, field-project "%s" does not match current project "%s".',
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

    $now = (new DateTimeImmutable())->setTimezone($this->getDateTimeZone());
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

      $dueDateEstimate = $this->financeService->targetDeadline(0);
      foreach ($participants as $musicianId => $participant) {
        $debitMandate = $debitMandates[$musicianId] ?? null;
        if (!empty($debitMandate)) {
          $dueDateEstimate = max(
            $dueDateEstimate,
            $this->financeService->targetDeadline(
              $debitMandate->getPreNotificationBusinessDays()?:0,
              $debitMandate->getPreNotificationCalendarDays(),
              $now)
          );
        }
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
            $this->l->t(
              'The debit-mandate for a bulk-transaction must either be all recurring or all one-time-only.'
              . ' The conflicting mandate of musician "%s", mandate-reference "%s" is %s, the previous mandates were %s.', [
                $participant->getMusician()->getPublicName(),
                $debitMandate->getMandateReference(),
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
          list('dueDate' => $dueDate,) = $this->bulkTransactionService->calculateDebitNoteDeadlines($debitMandate);
          $earliestDueDate = max($earliestDueDate, $dueDate);
        } else {
          // count backwards from desired deadline
          list(
            'preNotificationDeadline' => $notificationDeadline,
          ) = $this->bulkTransactionService->calculateDebitNoteDeadlines($debitMandate, $dueDeadline, fromDueDate:true);
          if ($notificationDeadline < $now) {
            $preNotificationConflicts[] = [
              'mandate' => $debitMandate,
              'notification' => $notificationDeadline,
            ];
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
      foreach ($preNotificationConflicts as list('mandate' => $debitMandate, 'noticiation' => $notificationDeadline)) {
        $messages[] = $this->l->t(
          'Due-deadline %s conflicts with the pre-notification dead-line %s for the debit-mandate "%s".', [
            $this->dateTimeFormatter->formatDate($dueDeadline, 'medium'),
            $this->dateTimeFormatter->formatDate($notificationDeadline, 'medium'),
            $debitMandate->getMandateReference(),
          ]);
      }
      return self::grumble([ 'message' => $messages ]);
    }

    // The "hard submission deadline" ATM only supplies to debit notes, As of
    // now the bank just adjusts the due-date to the future if we submit
    // bank-transfers too late. Still: handle it for both, debit notes and
    // bank tansfers.
    //
    // The "hard submission deadline" is somewhat artificial: it adds one
    // workday grace time in order to relax the pressure on the treasurer.

    $hardSubmissionDeadline = [
      self::TRANSACTION_TYPE_BANK_TRANSFER => null,
      self::TRANSACTION_TYPE_DEBIT_NOTE => null,
    ];

    if ($debitNote->getPayments()->count() > 0) {
      $submissionDeadline = $this->financeService->targetDeadline(
        fromDate: $earliestDueDate,
        businessOffset: -(SepaBulkTransactionService::DEBIT_NOTE_SUBMISSION_DEADLINE
                          + SepaBulkTransactionService::DEBIT_NOTE_SUBMISSION_EXTRA_WORKING_DAYS)
      );
      $hardSubmissionDeadline[self::TRANSACTION_TYPE_DEBIT_NOTE] = $this->financeService->targetDeadline(
        fromDate: $earliestDueDate,
        businessOffset: -SepaBulkTransactionService::DEBIT_NOTE_SUBMISSION_DEADLINE
      );
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
      $hardSubmissionDeadline[self::TRANSACTION_TYPE_BANK_TRANSFER] = $submissionDeadline;

      $bankTransfer->setDueDate($dueDeadline)
        ->setSubmissionDeadline($submissionDeadline);
    } else {
      $bankTransfer = null;
    }

    // Up to here everything was just in memory. The actual data-base
    // stuff should possibly be moved into the FinanceService.

    $bulkSubmissionNames = [
      self::TRANSACTION_TYPE_DEBIT_NOTE => [
        'submission' => $this->l->t('Debit notes submission deadline for %s', $project->getName()),
        'submissionHard' => $this->l->t('Debit notes submission hard-deadline for %s', $project->getName()),
        'due' => $this->l->t('Debit notes due for %s', $project->getName()),
        'notification' => $this->l->t('Debit notes pre-notification deadline for %s', $project->getName()),
      ],
      self::TRANSACTION_TYPE_BANK_TRANSFER => [
        'submission' => $this->l->t('Bank transfers submission deadline for %s', $project->getName()),
        'submissionHard' => $this->l->t('Bank transfers submission deadline for %s', $project->getName()),
        'due' => $this->l->t('Bank transfers due for %s', $project->getName()),
      ],
    ];

    $this->entityManager->beginTransaction();

    $calendarData = [
      self::TRANSACTION_TYPE_DEBIT_NOTE => [],
      self::TRANSACTION_TYPE_BANK_TRANSFER => [],
    ];

    /** @var Entities\SepaBulkTransaction $bulkTransaction */
    foreach ([ self::TRANSACTION_TYPE_DEBIT_NOTE => $debitNote, self::TRANSACTION_TYPE_BANK_TRANSFER => $bankTransfer, ] as $bulkTag => $bulkTransaction) {
      if (empty($bulkTransaction)) {
        continue;
      }
      $this->entityManager
        ->registerPreCommitAction(
          function() use (
            $bulkTag,
            $bulkTransaction,
            $project,
            $bulkSubmissionNames,
            $hardSubmissionDeadline,
            &$calendarData,
          ) {
            if ($bulkTag == self::TRANSACTION_TYPE_DEBIT_NOTE) {
              $alarmTimes = [
                [ self::ALARM_FROM_START => SepaBulkTransactionService::BULK_TRANSACTION_REMINDER_SECONDS ],
                [ self::ALARM_FROM_START => 9 * 60 * 60 ],
                [ self::ALARM_FROM_START => SepaBulkTransactionService::BULK_TRANSACTION_EARLY_REMINDER_SECONDS ],
              ];
            } else {
              $alarmTimes = [
                [ self::ALARM_FROM_START => SepaBulkTransactionService::BULK_TRANSACTION_REMINDER_SECONDS ],
                [ self::ALARM_FROM_START => 9 * 60 * 60 ],
              ];
            }
            $description = $this->l->t(
              'Due date: %s.',
              $this->dateTimeFormatter->formatDate($bulkTransaction->getDueDate(), 'long'))
              . $this->l->t('Bulk-transaction-id: %d', $bulkTransaction->getId());

            list(
              'uri' => $eventUri, 'uid' => $eventUid, 'event' => $object,
            ) = $this->financeService->financeEvent(
              title: $bulkSubmissionNames[$bulkTag]['submissionHard'],
              description: $description,
              project: $project,
              start: $hardSubmissionDeadline[$bulkTag],
              alarm: $alarmTimes,
            );
            $bulkTransaction->setSubmissionEventUri($eventUri);
            $bulkTransaction->setSubmissionEventUid($eventUid);
            $calendarData[$bulkTag][] = $object;
            return $eventUri;
          },
          function($uri) {
            $this->financeService->deleteFinanceCalendarEntry($uri);
          },
        )
        ->register(
          function() use (
            $bulkTag,
            $bulkTransaction,
            $project,
            $bulkSubmissionNames,
            $hardSubmissionDeadline,
            &$calendarData,
          ) {
            if ($bulkTag == self::TRANSACTION_TYPE_DEBIT_NOTE) {
              $alarmTimes = [
                [ self::ALARM_FROM_END => SepaBulkTransactionService::BULK_TRANSACTION_REMINDER_SECONDS ],
                [ self::ALARM_FROM_START => 9 * 60 * 60 ],
                [ self::ALARM_FROM_START => SepaBulkTransactionService::BULK_TRANSACTION_REMINDER_SECONDS ],
              ];
            } else {
              $alarmTimes = [
                [ self::ALARM_FROM_END => SepaBulkTransactionService::BULK_TRANSACTION_REMINDER_SECONDS ],
                [ self::ALARM_FROM_START => 9 * 60 * 60 ],
              ];
            }
            $description = $this->l->t(
              'Due date: %s.',
              $this->dateTimeFormatter->formatDate($bulkTransaction->getDueDate(), 'long'))
              . $this->l->t('Bulk-transaction-id: %d', $bulkTransaction->getId());

            list(
              'uri' => $taskUri, 'uid' => $taskUid, 'task' => $object
            ) = $this->financeService->financeTask(
              title: $bulkSubmissionNames[$bulkTag]['submission'],
              description: $description,
              project: $project,
              start: $bulkTransaction->getSubmissionDeadline(),
              due: $hardSubmissionDeadline[$bulkTag],
              alarm: $alarmTimes,
            );
            $bulkTransaction->setSubmissionTaskUri($taskUri);
            $bulkTransaction->setSubmissionTaskUid($taskUid);
            $calendarData[$bulkTag][] = $object;
            return $taskUri;
          },
          function($uri) {
            $this->financeService->deleteFinanceCalendarEntry($uri);
            $bulkTransaction->setSubmissionTaskUri(null);
            $bulkTransaction->setSubmissionTaskUid(null);
          },
        )
        ->register(
          function() use (
            $bulkTag,
            $bulkTransaction,
            $project,
            $bulkSubmissionNames,
            &$calendarData,
          ) {

            if ($bulkTag == self::TRANSACTION_TYPE_DEBIT_NOTE) {
              $description = $this->l->t(
                'Total amount to receive: %s.',
                $this->moneyValue($bulkTransaction->totals()));
              /** @var Entities\CompositePayment $payment */
              foreach ($bulkTransaction->getPayments() as $payment) {
                $description .= "\n"
                  . $this->l->t('%s pays %s.', [
                    $payment->getMusician()->getPublicName(firstNameFirst: false),
                    $this->moneyValue($payment->getAmount())
                  ]);
              }
            } else {
              $description = $this->l->t(
                'Total amount to pay: %s.',
                $this->moneyValue(-$bulkTransaction->totals()));
              /** @var Entities\CompositePayment $payment */
              foreach ($bulkTransaction->getPayments() as $payment) {
                $description .= "\n"
                  . $this->l->t('%s receives %s.', [
                    $payment->getMusician()->getPublicName(firstNameFirst: false),
                    $this->moneyValue(-$payment->getAmount())
                  ]);
              }
            }

            list(
              'uri' => $eventUri, 'uid' => $eventUid, 'event' => $object
            ) = $this->financeService->financeEvent(
              title: $bulkSubmissionNames[$bulkTag]['due'],
              description: $description,
              project: $project,
              start: $bulkTransaction->getDueDate(),
              payments: $bulkTransaction->getPayments(),
            );
            $bulkTransaction->setDueEventUri($eventUri);
            $bulkTransaction->setDueEventUid($eventUid);
            $calendarData[$bulkTag][] = $object;
            return $eventUri;
          },
          function($uri) {
            $this->financeService->deleteFinanceCalendarEntry($uri);
            $bulkTransaction->setDueEventUri(null);
            $bulkTransaction->setDueEventUid(null);
          },
        );

      if ($bulkTag == self::TRANSACTION_TYPE_DEBIT_NOTE) {
        // add also the notification deadline
        $title = $bulkSubmissionNames[self::TRANSACTION_TYPE_DEBIT_NOTE]['notification'];
        $description = $this->l->t(
          'Submission-deadline: %1$s, hard submission-deadline: %2$s, due date: %3$s.', [
            $this->dateTimeFormatter->formatDate($debitNote->getSubmissionDeadline(), 'long'),
            $this->dateTimeFormatter->formatDate($hardSubmissionDeadline[$bulkTag], 'long'),
            $this->dateTimeFormatter->formatDate($debitNote->getDueDate(), 'long'),
          ]);
        $this->entityManager
          ->registerPreCommitAction(
            function() use (
              $debitNote,
              $project,
              $bulkSubmissionNames,
              $title,
              $description,
              &$calendarData,
            ) {
              list(
                'uri' => $eventUri, 'uid' => $eventUid, 'event' => $object
              ) = $this->financeService->financeEvent(
                $title,
                $description,
                $project,
                start: $debitNote->getPreNotificationDeadline(),
                alarm: SepaBulkTransactionService::BULK_TRANSACTION_REMINDER_SECONDS,
                payments: $debitNote->getPayments(),
              );
              $debitNote->setPreNotificationEventUri($eventUri);
              $debitNote->setPreNotificationEventUid($eventUid);
              $calendarData[$bulkTag][] = $object;
              return $eventUri;
            },
            function($uri) use ($debitNote) {
              $this->financeService->deleteFinanceCalendarEntry($uri);
              $debitNote->setPreNotificationEventUri(null);
              $debitNote->setPreNotificationEventUid(null);
            },
          )
          ->register(
            function() use (
              $debitNote,
              $project,
              $bulkSubmissionNames,
              $title,
              $description,
              &$calendarData,
            ) {
              list(
                'uri' => $taskUri, 'uid' => $taskUid, 'task' => $object
              ) = $this->financeService->financeTask(
                $title,
                $description,
                $project,
                due: $debitNote->getPreNotificationDeadline(),
                alarm: SepaBulkTransactionService::BULK_TRANSACTION_REMINDER_SECONDS,
              );
              $debitNote->setPreNotificationTaskUri($taskUri);
              $debitNote->setPreNotificationTaskUid($taskUid);
              $calendarData[$bulkTag][] = $object;
              return $taskUri;
            },
            function($uri) use ($debitNote) {
              $this->financeService->deleteFinanceCalendarEntry($uri);
              $debitNote->setPreNotificationTaskUri(null);
              $debitNote->setPreNotificationTaskUid(null);
            },
          );
      } // debit-note

      // update relations between all calendar objects
      $this->entityManager->registerPreCommitAction(
        function() use ($bulkTransaction, $debitNote, $bulkTag, &$calendarData) {
          $related = [
            $bulkTransaction->getDueEventUid(),
            $bulkTransaction->getSubmissionEventUid(),
            $bulkTransaction->getSubmissionTaskUid(),
          ];
          if ($bulkTag == self::TRANSACTION_TYPE_DEBIT_NOTE) {
            $related[] = $debitNote->getPreNotificationEventUid();
            $related[] = $debitNote->getPreNotificationTaskUid();
          }
          $related = array_filter($related);
          $changeSet = [ 'related' => [ 'SIBLING' => $related ] ];
          $this->logInfo('RELATIONS ' . print_r($related, true));
          foreach ($calendarData[$bulkTag] as $object) {
            $this->financeService->patchFinanceCalendarEntry($object, $changeSet);
          }
        });
    }

    $this->entityManager->registerPreCommitAction(fn() => $this->flush());

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
   * @param int $bulkTransactionId Bulk transaction entity id.
   *
   * @param int $projectId Project endity id.
   *
   * @param string $format Export format.
   *
   * @return Response
   */
  private function exportBulkTransaction(
    int $bulkTransactionId,
    int $projectId = 0,
    string $format = SepaBulkTransactionService::EXPORT_AQBANKING,
  ):Response {
    $id = filter_var($bulkTransactionId, FILTER_VALIDATE_INT, ['min_range' => 1]);
    if ($id === false) {
      return self::grumble($this->l->t(
        'Submitted value "%s" is not a positive integer.', $bulkTransactionId));
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
