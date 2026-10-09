<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2026 Claus-Justus Heine
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

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;
use UnexpectedValueException;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute as CoreAttributes;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\IDateTimeFormatter;
use OCP\IRequest;

use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\PageRenderer\PMETableViewBase;
use OCA\CAFEVDB\PageRenderer\DatabaseTables;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Service\Finance\SepaBulkTransactionService;
use OCA\CAFEVDB\Service\ProjectService;

/** AJAX backend for managing bank bulk transactions. */
#[TSAttributes\TypeScript]
class SepaBulkTransactionsController extends Controller
{
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  public const END_POINT = 'finance/sepa/bulk-transactions';

  /** {@inheritdoc} */
  public function __construct(
    string $appName,
    IRequest $request,
    private FinanceService $financeService,
    private IDateTimeFormatter $dateTimeFormatter,
    private ProjectService $projectService,
    private SepaBulkTransactionService $bulkTransactionService,
    protected ConfigService $configService,
    protected EntityManager $entityManager,
    protected PHPMyEdit $pme,
  ) {
    parent::__construct($appName, $request);
    $this->l = $this->l10N();
  }

  /**
   * @param string|EnumSepaBulkTransactionsTopic $topic What to do.
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
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(verb: 'POST', url: '/' . self::END_POINT . '/{topic}')]
  public function serviceSwitch(
    string|EnumSepaBulkTransactionsTopic $topic,
    int $projectId = 0,
    array $sepaBulkTransactions = [],
    ?string $sepaDueDeadline = null,
    int $bulkTransactionId = 0,
  ): Response {
    $topic = EnumSepaBulkTransactionsTopic::get($topic);
    switch ($topic) {
      case EnumSepaBulkTransactionsTopic::CREATE:
        $sepaBulkTransactions = array_values(array_unique($sepaBulkTransactions));
        // PME_sys_mrecs[] = "{\"musician_id\":\"1\",\"sequence\":\"1\"}"
        $bankAccountRecords = $this->request->getParam($this->pme->cgiSysName(PHPMyEdit::MRECS_KEY), []);
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
      case EnumSepaBulkTransactionsTopic::EXPORT:
        return $this->exportBulkTransaction(
          $bulkTransactionId,
          $projectId,
          purpose: $this->request->getParam('purpose', null),
          format: $this->request->getParam('format', null),
        );
    }
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
   * @throws Exceptions\EnduserNotificationException
   *
   * @bug This function is too long; the functionality should be splitted and
   * moved to a service class.
   */
  private function generateBulkTransactions(
    int $projectId,
    array $bankAccountRecords,
    array $bulkTransactions = [],
    mixed $dueDeadline = null,
  ): DataResponse {
    /** @var Entities\Project $project */
    $project = $this->getDatabaseRepository(Entities\Project::class)->find($projectId);
    if (empty($project)) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('Unable to retrieve project with id %d from data-base.', $projectId),
      );
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
      $mandateSequence = $accountId[PMETableViewBase::joinTableMasterFieldName(DatabaseTables::SEPA_DEBIT_MANDATES_TABLE)];

      /** @var Entities\SepaBankAccount $account */
      $account = $accountsRepository->find([
        'musician' => $musicianId,
        'sequence' => $sequence,
      ]);
      if (empty($account)) {
        throw new Exceptions\EnduserNotificationException(
          $this->l->t(
            'Bank account for musician-id %1$d, sequence %2$d not found.', [
              $musicianId,
              $sequence,
            ]),
        );
      }
      if ($account->isDeleted()) {
        // This can happen when forcing display of soft-deleted rows in expert mode.
        throw new Exceptions\EnduserNotificationException(
          $this->l->t(
            'Refusing to use a revoked or disabled bank account. The bank-account %1$s for %2$s (musician-id %3$d, sequence %4$d) has been revoked or deleted on %5$s.', [
              $account->getIban(),
              $account->getMusician()->getPublicName(),
              $musicianId,
              $sequence,
              $this->formatDate($account->getDeleted()),
            ]),
        );
      }
      if (!empty($bankAccounts[$musicianId])) {
        throw new Exceptions\EnduserNotificationException(
          $this->l->t(
            'More than one bank account submitted for musician %1$s, multiple IBANs %2$s, %3$s.', [
              $bankAccounts[$musicianId]->getMusician()->getPublicName(),
              $bankAccounts[$musicianId]->getIban(),
              $account->getIban(),
            ]),
        );
      }
      $bankAccounts[$musicianId] = $account;

      $participant = $participantsRepository->find(['project' => $project, 'musician' => $musicianId]);
      if (empty($participant)) {
        throw new Exceptions\EnduserNotificationException(
          $this->l->t('Musician "%1$s" does not seem to belong to project "%2$s".', [
            $account->getMusician()->getPublicName(),
            $project->getName()
          ]),
        );
      }
      $participants[$musicianId] = $participant;

      if (!empty($mandateSequence)) {
        /** @var Entities\SepaDebitMandate $mandate */
        $mandate = $debitMandatesRepository->find([
          'musician' => $musicianId,
          'sequence' => $mandateSequence,
        ]);
        if (empty($mandate)) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t(
              'Debit-mandate for musician-id %1$d, sequence %2$d not found.', [
                $musicianId,
                $mandateSequence,
              ]),
          );
        }
        if (!empty($debitMandates[$musicianId])) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t(
              'More than one debit-mandate submitted for musician %1$s, multiple references %2$s, %3$s.', [
                $mandate[$musicianId]->getMusician()->getPublicName(),
                $debitMandates[$musicianId]->getMandateReference(),
                $mandate->getMandateReference(),
              ]),
          );
        }
        if ($mandate->getSepaBankAccount() != $account) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('Debit-mandate is for bank-account "%1$s", but the current bank-account is "%2$s".', [
              $mandate->getSepaBankAccount()->getIban(),
              $account->getIban(),
            ]),
          );
        }
        if ($mandate->isDeleted()) {
          // This can happen when forcing display of soft-deleted rows in expert mode.
          throw new Exceptions\EnduserNotificationException(
            $this->l->t(
              'Refusing to use a revoked or disabled debit-mandate.'
              . ' The debit-mandate %1$s for the bank-account %2$s of %3$s (musician-id %4$d, mandate-sequence %5$d) has been revoked or deleted on %6$s.', [
                $mandate->getMandateReference(),
                $account->getIban(),
                $account->getMusician()->getPublicName(),
                $musicianId,
                $mandateSequence,
                $this->formatDate($mandate->getDeleted()),
              ]),
          );
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
      $fieldId = filter_var($bulkTransaction, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
      if ($fieldId !== false) {
        // all options from this field
        /** @var Entities\ProjectParticipantField $field */
        $field = $fieldRepository->find($fieldId);
        if ($field->getProject() != $project) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t(
              'Internal data inconsistency, field-project "%1$s" does not match current project "%2$s".', [
                $project->getName(),
                $field->getProject()->getName(),
              ]),
          );
        }
        foreach ($field->getSelectableOptions() as $receivable) {
          $receivables[] = $receivable;
        }
      } elseif (Uuid::isValid($bulkTransaction)) {
        // just this option, should be a recurring receivable
        /** @var Entities\ProjectParticipantFieldDataOption $receivable */
        $receivable = $receivablesRepository->findOneBy(['key' => Uuid::asUuid($bulkTransaction) ]);
        if ($receivable->getField()->getProject() != $project) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t(
              'Internal data inconsistency, field-project "%1$s" does not match current project "%2$s".', [
                $project->getName(),
                $receivable->getField()->getProject()->getName(),
              ]),
          );
        }
        $receivables[] = $receivable;
      } else {
        throw new Exceptions\EnduserNotificationException(
          $this->l->t('Submitted debit-job id "%s" is neither a participant field nor a field-option uuid.', $bulkTransaction),
        );
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
              $debitMandate->getPreNotificationBusinessDays() ?: 0,
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
      if ($compositePayment->getAmount()->eq(0)) {
        $this->logDebug('AMOUNT IS 0 ' . $participant->getPublicName());
        continue;
      }
      $compositePayment->setSepaBankAccount($bankAccounts[$musicianId]);
      if ($compositePayment->getAmount()->gt(0)) {
        /** @var Entities\SepaDebitMandate $debitMandate */
        $debitMandate = $debitMandates[$musicianId];

        // payment, try debit note, bail out if there is none.
        // @todo We could relay this and just send a reminder to the musician.
        if (empty($debitMandate)) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('Musician "%1$s" has to pay an amount of %2$s, but there is no debit-note-mandate for the musician.', [
              $participant->getPublicName(),
              $this->moneyValue($compositePayment->getAmount()),
            ]),
          );
        }

        // In general we do not use non-recurring, but play
        // safe. Mixing recurring and non-recurring debit-notes may
        // lead to errors when submitting the data to the bank.
        if ($nonRecurring !== ngull && $debitMandate->getNonRecurring() != $nonRecurring) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t(
              'The debit-mandate for a bulk-transaction must either be all recurring or all one-time-only.'
              . ' The conflicting mandate of musician "%1$s", mandate-reference "%2$s" is %3$s, the previous mandates were %4$s.', [
                $participant->getPublicName(),
                $debitMandate->getMandateReference(),
                ($nonRecurring ? $this->l->t('recurring') : $this->l->t('non-recurring')),
                ($nonRecurring ? $this->l->t('non-recurring') : $this->l->t('recurring')),
              ]),
          );
        }
        $nonRecurring = $debitMandate->getNonRecurring();

        $compositePayment->setSepaDebitMandate($debitMandate);
        $debitNote->addPayment($compositePayment);

        if (empty($dueDeadline)) {
          // count forward from now, just take the maximum
          $dueDate = $this->bulkTransactionService->calculateDebitNoteDueDate($debitMandate);
          $earliestDueDate = max($earliestDueDate, $dueDate);
        } else {
          // count backwards from desired deadline
          $notificationDeadline = $this->bulkTransactionService->calculateDebitNotePreNotificationDeadline($debitMandate, $dueDeadline);
          if ($notificationDeadline < $now) {
            $preNotificationConflicts[] = [
              'mandate' => $debitMandate,
              'notification' => $notificationDeadline,
            ];
          }
          $latestNotification = min($latestNotification, $notificationDeadline);
        }
      } else {
        $bankTransfer->addPayment($compositePayment);
      }
    }

    // notify the operator about all conflicts.
    if (!empty($preNotificationConflicts)) {
      $messages = [];
      foreach ($preNotificationConflicts as list('mandate' => $debitMandate, 'noticiation' => $notificationDeadline)) {
        $messages[] = $this->l->t(
          'Due-deadline %1$s conflicts with the pre-notification dead-line %2$s for the debit-mandate "%3$s".', [
            $this->dateTimeFormatter->formatDate($dueDeadline, 'medium'),
            $this->dateTimeFormatter->formatDate($notificationDeadline, 'medium'),
            $debitMandate->getMandateReference(),
          ]);
      }
      throw new Exceptions\EnduserNotificationException(
        implode(' ', $messages),
        context: [
          'messages' => $messages,
        ],
      );
    }

    // The "hard submission deadline" ATM only supplies to debit notes, As of
    // now the bank just adjusts the due-date to the future if we submit
    // bank-transfers too late. Still: handle it for both, debit notes and
    // bank tansfers.
    //
    // The "hard submission deadline" is somewhat artificial: it adds one
    // workday grace time in order to relax the pressure on the treasurer.

    if ($debitNote->getPayments()->count() > 0) {
      $submissionDeadline = $this->financeService->targetDeadline(
        fromDate: $earliestDueDate,
        businessOffset: -SepaBulkTransactionService::DEBIT_NOTE_SUBMISSION_DEADLINE
          - SepaBulkTransactionService::DEBIT_NOTE_SUBMISSION_EXTRA_WORKING_DAYS, // extra days
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

      $bankTransfer->setDueDate($dueDeadline)
        ->setSubmissionDeadline($submissionDeadline);
    } else {
      $bankTransfer = null;
    }

    // Up to here everything was just in memory. The actual data-base
    // stuff should possibly be moved into the FinanceService.

    $this->entityManager->beginTransaction();

    /** @var Entities\SepaBulkTransaction $bulkTransaction */
    foreach ([$debitNote, $bankTransfer] as $bulkTransaction) {

      if (empty($bulkTransaction)) {
        continue;
      }

      $calendarObjects = [];

      $eventData = $this->bulkTransactionService->createSubmissionEventData($bulkTransaction);

      $this->entityManager
        ->registerPreCommitAction(
          function() use (
            $bulkTransaction,
            $eventData,
            $project,
            &$calendarObjects,
          ) {
            list(
              'uri' => $eventUri, 'uid' => $eventUid, 'event' => $calendarObject,
            ) = $this->financeService->financeEvent(
              title: $eventData->summary,
              description: $eventData->description,
              project: $project,
              start: $eventData->start,
              alarm: $eventData->alarm,
            );
            if ($calendarObject === null) {
              throw new UnexpectedValueException('NULL CALENDAR OBJECT');
            }
            $bulkTransaction->setSubmissionEventUri($eventUri);
            $bulkTransaction->setSubmissionEventUid($eventUid);
            $calendarObjects[] = $calendarObject;

            return $eventUri;
          },
          function($eventUri) {
            $this->financeService->deleteFinanceCalendarEntry($eventUri);
          },
        )
        ->register(
          function() use (
            $bulkTransaction,
            $eventData,
            $project,
            &$calendarObjects,
          ) {
            $summary = $bulkTransaction instanceof Entities\SepaDebitNote
              ? $this->l->t('Debit notes submission for %s', $project->getName())
              : $this->l->t('Bank transfers submission for %s', $project->getName());
            list(
              'uri' => $taskUri, 'uid' => $taskUid, 'task' => $calendarObject
            ) = $this->financeService->financeTask(
              title: $summary,
              description: $eventData->description,
              project: $project,
              start: $bulkTransaction->getSubmissionDeadline(), // hard - extra days
              due: $eventData->start, // hard
              alarm: $eventData->alarm,
            );
            if ($calendarObject === null) {
              throw new UnexpectedValueException('NULL CALENDAR OBJECT');
            }
            $bulkTransaction->setSubmissionTaskUri($taskUri);
            $bulkTransaction->setSubmissionTaskUid($taskUid);
            $calendarObjects[] = $calendarObject;

            return $taskUri;
          },
          function($taskUri) {
            $this->financeService->deleteFinanceCalendarEntry($taskUri);
            $bulkTransaction->setSubmissionTaskUri(null);
            $bulkTransaction->setSubmissionTaskUid(null);
          },
        )
        ->register(
          function() use (
            $bulkTransaction,
            $project,
            &$calendarObjects,
          ) {
            if ($bulkTransaction instanceof Entities\SepaDebitNote) {
              $summary = $this->l->t('Debit notes due for %s', $project->getName());
              $description = $this->l->t(
                'Total amount to receive: %s.',
                $this->moneyValue($bulkTransaction->totals()));
              /** @var Entities\CompositePayment $payment */
              foreach ($bulkTransaction->getPayments() as $payment) {
                $description .= "\n"
                  . $this->l->t('%1$s pays %2$s.', [
                    $payment->getMusician()->getFunctionalName(firstNameFirst: false),
                    $this->moneyValue($payment->getAmount())
                  ]);
              }
            } else {
              $summary = $this->l->t('Bank transfers due for %s', $project->getName());
              $description = $this->l->t(
                'Total amount to pay: %s.',
                $this->moneyValue($bulkTransaction->totals()->neg()));
              /** @var Entities\CompositePayment $payment */
              foreach ($bulkTransaction->getPayments() as $payment) {
                $description .= "\n"
                  . $this->l->t('%1$s receives %2$s.', [
                    $payment->getMusician()->getFunctionalName(firstNameFirst: false),
                    $this->moneyValue($payment->getAmount()->neg())
                  ]);
              }
            }

            list(
              'uri' => $eventUri, 'uid' => $eventUid, 'event' => $calendarObject
            ) = $this->financeService->financeEvent(
              title: $summary,
              description: $description,
              project: $project,
              start: $bulkTransaction->getDueDate(),
              payments: $bulkTransaction->getPayments(),
            );
            if ($calendarObject === null) {
              throw new UnexpectedValueException('NULL CALENDAR OBJECT');
            }
            $bulkTransaction->setDueEventUri($eventUri);
            $bulkTransaction->setDueEventUid($eventUid);
            $calendarObjects[] = $calendarObject;

            return $eventUri;
          },
          function($eventUri) {
            $this->financeService->deleteFinanceCalendarEntry($eventUri);
            $bulkTransaction->setDueEventUri(null);
            $bulkTransaction->setDueEventUid(null);
          },
        );

      if ($bulkTransaction instanceof Entities\SepaDebitNote) {
        // add also the notification deadline
        $summary = $this->l->t('Debit notes pre-notification deadline for %s', $project->getName());
        $description = $this->l->t(
          'Submission-deadline: %1$s, hard submission-deadline: %2$s, due date: %3$s.', [
            $this->dateTimeFormatter->formatDate($bulkTransaction->getSubmissionDeadline(), 'long'),
            $this->dateTimeFormatter->formatDate($eventData->start, 'long'),
            $this->dateTimeFormatter->formatDate($bulkTransaction->getDueDate(), 'long'),
          ]);
        $this->entityManager
          ->registerPreCommitAction(
            function() use (
              $bulkTransaction,
              $description,
              $project,
              $title,
              &$calendarObjects,
            ) {
              list(
                'uri' => $eventUri, 'uid' => $eventUid, 'event' => $calendarObject
              ) = $this->financeService->financeEvent(
                title: $summary,
                description: $description,
                procted: $project,
                start: $bulkTransaction->getPreNotificationDeadline(),
                alarm: SepaBulkTransactionService::BULK_TRANSACTION_REMINDER_SECONDS,
                payments: $bulkTransaction->getPayments(),
              );
              if ($calendarObject === null) {
                throw new UnexpectedValueException('NULL CALENDAR OBJECT');
              }
              $bulkTransaction->setPreNotificationEventUri($eventUri);
              $bulkTransaction->setPreNotificationEventUid($eventUid);
              $calendarObjects[] = $calendarObject;
              return $eventUri;
            },
            function($uri) use ($bulkTransaction) {
              $this->financeService->deleteFinanceCalendarEntry($uri);
              $bulkTransaction->setPreNotificationEventUri(null);
              $bulkTransaction->setPreNotificationEventUid(null);
            },
          )
          ->register(
            function() use (
              $bulkTransaction,
              $description,
              $project,
              $summary,
              &$calendarObjects,
            ) {
              list(
                'uri' => $taskUri, 'uid' => $taskUid, 'task' => $calendarObject
              ) = $this->financeService->financeTask(
                title: $summary,
                description: $description,
                project: $project,
                due: $bulkTransaction->getPreNotificationDeadline(),
                alarm: SepaBulkTransactionService::BULK_TRANSACTION_REMINDER_SECONDS,
              );
              if ($calendarObject === null) {
                throw new UnexpectedValueException('NULL CALENDAR OBJECT');
              }
              $bulkTransaction->setPreNotificationTaskUri($taskUri);
              $bulkTransaction->setPreNotificationTaskUid($taskUid);
              $calendarObjects[] = $calendarObject;
              return $taskUri;
            },
            function($taskUri) use ($bulkTransaction) {
              $this->financeService->deleteFinanceCalendarEntry($taskUri);
              $bulkTransaction->setPreNotificationTaskUri(null);
              $bulkTransaction->setPreNotificationTaskUid(null);
            },
          );
      } // debit-note

      // update relations between all calendar objects
      $this->entityManager->registerPreCommitAction(
        function() use (
          $bulkTransaction,
          &$calendarObjects,
        ) {
          $related = [
            $bulkTransaction->getDueEventUid(),
            $bulkTransaction->getSubmissionEventUid(),
            $bulkTransaction->getSubmissionTaskUid(),
          ];
          if ($bulkTransaction instanceof Entities\SepaDebitNote) {
            $related[] = $bulkTransaction->getPreNotificationEventUid();
            $related[] = $bulkTransaction->getPreNotificationTaskUid();
          }
          $related = array_filter($related);
          $changeSet = [ 'related' => [ 'SIBLING' => $related ] ];
          $this->logInfo('RELATIONS ' . print_r($related, true));
          foreach ($calendarObjects as $calendarObject) {
            $this->financeService->patchFinanceCalendarEntry($calendarObject, $changeSet);
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
    } catch (Throwable $t) {
      $this->entityManager->rollback();
      $this->clearDatabaseRepository();
      $this->entityManager->reopen();
      $accountsRepository = $this->getDatabaseRepository(Entities\SepaBankAccount::class);
      $debitMandatesRepository = $this->getDatabaseRepository(Entities\SepaDebitMandate::class);
      $participantsRepository = $this->getDatabaseRepository(Entities\ProjectParticipant::class);
      throw new Exceptions\EnduserNotificationException(
        message: $this->l->t('Unable to schedule the bank transactions.'),
        previous: $t,
      );
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
      'messages' => $messages,
      'bankTransferId' => empty($bankTransfer) ? 0 : $bankTransfer->getId(),
      'debitNoteId' => empty($debitNote) ? 0 : $debitNote->getId(),
    ];
    return new DataResponse($responseData);
  }

  /**
   * Generate export-sets for the given bulk-transaction.
   *
   * @param int $bulkTransactionId Bulk transaction entity id.
   *
   * @param int $projectId Project endity id.
   *
   * @param null|string|EnumSepaBulkTransactionsExportPuropose $purpose
   *
   * @param null|string $format Export format, e.g. 'gnucash' or 'aqbanking'.
   *
   * @return Response
   */
  private function exportBulkTransaction(
    int $bulkTransactionId,
    int $projectId = 0,
    ?string $purpose = null,
    ?string $format = null,
  ): Response {
    $id = filter_var($bulkTransactionId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
      throw new Exceptions\EnduserNotificationException($this->l->t('Submitted value "%s" is not a positive integer.', $bulkTransactionId));
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
      throw new Exceptions\EnduserNotificationException($this->l->t('Unable to find bulk-transaction with id %d.', $id));
    }

    try {
      $purpose = EnumSepaBulkTransactionsExportPurpose::get($purpose ?? EnumSepaBulkTransactionsExportPurpose::BANK_IMPORT);
      switch ($purpose) {
        case EnumSepaBulkTransactionsExportPurpose::BANK_IMPORT:
          $exportFile = $this->bulkTransactionService->generateTransactionData($bulkTransaction, $project, $format);
          break;
        case EnumSepaBulkTransactionsExportPurpose::BALANCING_ITEMS:
          $exportFile = $this->bulkTransactionService->generateBalancingItems($bulkTransaction, $format);
          break;
        default:
          throw new InvalidArgumentException($this->l->t('Unknown export purpose: "%s".', $purpose->value));
          break;
      }
    } catch (Throwable $t) {
      throw new Exceptions\EnduserNotificationException(
        message: $this->l->t('Unable to export the bulktransaction with id "%d".', $bulkTransactionId),
        previous: $t,
      );
    }

    return new RedirectResponse(
      $this->urlGenerator()->linkToRoute($this->appName().'.downloads.get', [
        'section' => DownloadsController::SECTION_DATABASE,
        'object' => $exportFile->getId(),
      ])
      . '?fileName=' . urlencode($exportFile->getFileName())
      . '&requesttoken=' . urlencode(\OCP\Util::callRegister())
    );
  }
}
