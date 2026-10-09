<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020-2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service\Finance;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

use OCP\IDateTimeFormatter;
use OCP\IL10N;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Common\TimeFactory;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumSepaTransaction;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaDebitNote as DebitNote;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Service\Finance\SepaBulkTransactionService\EnumExportFormat;
use OCA\CAFEVDB\Service\Finance\SepaBulkTransactionService\EventDataDTO;
use OCA\CAFEVDB\Service\VCalendarService;
use OCA\CAFEVDB\Storage\Database\BankTransactionsStorage;
use OCA\CAFEVDB\Toolkit\Common\DecimalRationalMonetary as MonetaryNumberType;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

/**
 * Service class for generating bulk-transactions for submittance to the
 * respective bank.
 */
class SepaBulkTransactionService
{
  use \OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Traits\TimeStampTrait;

  /**
   * @var int
   * The hard bank deadline, 1 working day in advance, otherwise the debit
   * note will not be accepted.
   *
   * Update: our preferred client banking tool seemingly has this hard-coded
   * to 2 days. Mmmh.
   */
  public const DEBIT_NOTE_SUBMISSION_DEADLINE = 2;

  // fancy, just to have some reminders and a deadline on the task-list
  public const BANK_TRANSFER_SUBMISSION_DEADLINE = 1;

  /**
   * @var int
   *
   * Allow for that many extra working days.
   */
  public const DEBIT_NOTE_SUBMISSION_EXTRA_WORKING_DAYS = 1;

  public const TRANSACTION_TYPE_DEBIT_NOTE = EnumSepaTransaction::DEBIT_NOTE->value;
  public const TRANSACTION_TYPE_BANK_TRANSFER = EnumSepaTransaction::BANK_TRANSFER->value;

  private const SUBMISSION_EVENT = 'submissionEvent';
  private const SUBMISSION_TASK = 'submisisonTask';
  private const DUE_EVENT = 'dueEvent';
  private const PRE_NOTIFICATION_EVENT = 'preNotificationEvent';
  private const PRE_NOTIFICATION_TASK = 'preNotificationTask';

  /** @var array Calendar event types. */
  private const CALENDAR_EVENTS = [
    self::SUBMISSION_EVENT,
    self::SUBMISSION_TASK,
    self::DUE_EVENT,
    self::PRE_NOTIFICATION_EVENT,
    self::PRE_NOTIFICATION_TASK,
  ];

  /**
   * @var int
   *
   * Alert one day before at 9:00.
   */
  public const BULK_TRANSACTION_REMINDER_SECONDS = - 15 * 60 * 60; /* alert one day in advance at */

  /**
   * @var int
   *
   * Alert early two days before at 9:00,.
   */
  public const BULK_TRANSACTION_EARLY_REMINDER_SECONDS = - (15 + 24) * 60 * 60; /* alert one day in advance */

  /** @var string Export format AqBanking. */
  const EXPORT_AQBANKING = EnumExportFormat::AQBANKING->value;

  /** @var string Export format GnuCash e.g. for balancing items. */
  const EXPORT_GNU_CASH = EnumExportFormat::GNU_CASH->value;

  /** @var array All supported exporters. */
  public const EXPORTERS = [
    self::EXPORT_AQBANKING,
  ];

  public const BALANCING_ITEMS_EXPORTERS = [
    self::EXPORT_GNU_CASH,
  ];

  public const EXPORT_SERVICE_ALIAS = 'export:bank-bulk-transaction:';
  public const EXPORT_BALANCING_ITEMS_SERVICE_ALIAS = self::EXPORT_SERVICE_ALIAS . 'balancing-items:';

  const SUBJECT_PREFIX_LIMIT = 16;
  const SUBJECT_PREFIX_SEPARATOR = ' / ';
  const SUBJECT_GROUP_SEPARATOR = ' / ';
  const SUBJECT_ITEM_SEPARATOR = ', ';
  const SUBJECT_OPTION_SEPARATOR = ': ';

  /** {@inheritdoc} */
  public function __construct(
    private ConfigService $configService,
    private EventsService $eventsService,
    private FinanceService $financeService,
    private IDateTimeFormatter $dateTimeFormatter,
    private TimeFactory $timeFactory,
    protected ContainerInterface $appContainer,
    protected EntityManager $entityManager,
    protected IL10N $l,
    protected ILogger $logger,
  ) {
  }

  /**

   * @param Entities\SepaBulkTransaction $bulkTransaction The bulk-transaction to modify.
   *
   * @param ?DateTimeInterface $submitDate If \null generate data for a new
   * event in order to remind the treasurer that this task has to be done. If
   * not \null generate data in order to modify the event title and
   * description to reflect the "task completed" state.
   *
   * @return EventDataDTO
   */
  public function createSubmissionEventData(
    Entities\SepaBulkTransaction $bulkTransaction,
    ?DateTimeInterface $submitDate = null,
  ): EventDataDTO {
    if ($submitDate === null) {
      // new event, or submit date has been reset
      $projectName = $bulkTransaction->getProject()->getName();
      if ($bulkTransaction instanceof Entities\SepaDebitNote) {
        $alarmTimes = [
          [ EventsService::VALARM_FROM_START => self::BULK_TRANSACTION_REMINDER_SECONDS ],
          [ EventsService::VALARM_FROM_START => 9 * 60 * 60 ],
          [ EventsService::VALARM_FROM_START => self::BULK_TRANSACTION_EARLY_REMINDER_SECONDS ],
        ];
        $summary = $this->l->t('Debit notes submission hard-deadline for %s', $projectName);
        $submissionDeadline = $this->financeService->targetDeadline(
          fromDate: $bulkTransaction->getDueDate(),
          businessOffset: -self::DEBIT_NOTE_SUBMISSION_DEADLINE,
        );
      } else {
        $alarmTimes = [
          [ EventsService::VALARM_FROM_START => self::BULK_TRANSACTION_REMINDER_SECONDS ],
          [ EventsService::VALARM_FROM_START => 9 * 60 * 60 ],
        ];
        $summary = $this->l->t('Bank transfers submission deadline for %s', $projectName);
        $submissionDeadline = $this->financeService->targetDeadline(
          fromDate: $bulkTransaction->getDueDate(),
          businessOffset: -self::BANK_TRANSFER_SUBMISSION_DEADLINE,
        );
      }
      $description = $this->l->t(
        'Due date: %s.',
        $this->dateTimeFormatter->formatDate($bulkTransaction->getDueDate(), 'long')
      )
        . $this->l->t('Bulk-transaction-id: %d', $bulkTransaction->getId());

      return new EventDataDTO(
        summary: $summary,
        description: $description,
        start: $submissionDeadline,
        alarm: $alarmTimes,
      );
    } else {
      // existing event, submit date as been set
      $project = $bulkTransaction->getPayments()->first()->getProject();
      if ($bulkTransaction instanceof Entities\SepaDebitNote) {
        $summary = $this->l->t('Debit-notes submitted for %s', $project->getName());
        $description = $this->l->t(
          'Debit-notes have been submitted on %s, due-date is %s.', [
            $this->dateTimeFormatter->formatDateo($bulkTransaction->getSubmitDate(), 'long'),
            $this->dateTimeFormatter->formatDate($bulkTransaction->getDueDate(), 'long'),
          ])
          . "\n"
          . $this->l->t('Total amount to receive: %s.', $this->configService->moneyValue($bulkTransaction->totals()));
        /** @var Entities\CompositePayment $payment */
        foreach ($bulkTransaction->getPayments() as $payment) {
          $description .= "\n"
            . $this->l->t('%s pays %s.', [
              $payment->getMusician()->getPublicName(firstNameFirst: false),
              $this->configService->moneyValue($payment->getAmount())
            ]);
        }
      } else {
        $summary = $this->l->t('Bank-transfers submitted for %s', $project->getName());
        $description = $this->l->t(
          'Bank-transfers have been submitted, due-date is %s.', $this->dateTimeFormatter->formatDate($bulkTransaction->getDueDate(), 'long'))
          . "\n"
          . $this->l->t('Total amount to pay: %s.', $this->configService->moneyValue($bulkTransaction->totals()->neg()));
        /** @var Entities\CompositePayment $payment */
        foreach ($bulkTransaction->getPayments() as $payment) {
          $description .= "\n"
            . $this->l->t('%s receives %s.', [
              $payment->getMusician()->getPublicName(firstNameFirst: false),
              $this->configService->moneyValue($payment->getAmount()->neg())
            ]);
        }
      }

      return new EventDataDTO(
        summary: $summary,
        description: $description,
        start: $submitDate,
        alarm: 0,
      );
    }
  }

  /**
   * Mark the bulk-transaction as submitted. This will resolve all submittance
   * related tasks, remove all alarms from the submit deadline-event. The
   * submit deadline-event will be renamed in order not to disturb the
   * spectator and its date will be change to the given date.
   *
   * @param Entities\SepaBulkTransaction $bulkTransaction The bulk-transaction to modify.
   *
   * @param ?DateTimeInterface $submitDate The date of submittance. This is a
   * UTC-date-time at mignight UTC if non null. If null the associated tasks
   * and calendar events are reset to their "unsubmitted" state.
   *
   * @return void
   */
  public function markBulkTransactionSubmitted(
    Entities\SepaBulkTransaction $bulkTransaction,
    ?DateTimeInterface $submitDate,
  ): void {

    if ($submitDate !== null) {
      // $submitDate is in UTC but means the current date in local time.
      $today = self::getCurrentDate();
      $submitDate = self::convertToTimezoneDate($submitDate, $today->getTimezone());

      if ($submitDate > $today) {
        throw new UnexpectedValueException($this->l->t(
          'Given submit date "%s" points to the future.',
          $this->l->l('date', $submitDate),
        ));
      }
    }

    $bulkTransaction->setSubmitDate($submitDate);

    $this->entityManager->registerPreCommitAction(
      function() use ($bulkTransaction, $submitDate, $today) {
        $submissionTaskUri = $bulkTransaction->getSubmissionTaskUri();
        $submissionTask = $this->financeService->findFinanceCalendarEntry($submissionTaskUri);
        if ($submissionTask === null) {
          return [];
        }
        $stash = [ self::SUBMISSION_TASK => Util::cloneArray($submissionTask) ];
        if ($submitDate === null) {
          $this->eventsService->setCalendarTaskStatus($submissionTask, percentComplete: 0);
        } else {
          $this->eventsService->setCalendarTaskStatus($submissionTask, dateCompleted: $submitDate);
        }
        return $stash;
      },
      function($stash) {
        $this->restoreCalendarObjects($stash);
      },
    )->register(
      function() use ($bulkTransaction, $submitDate, $today) {
        $submissionEventUri = $bulkTransaction->getSubmissionEventUri();
        $submissionEvent = $this->financeService->findFinanceCalendarEntry($submissionEventUri);
        if ($submissionEvent === null) {
          return [];
        }
        $stash = [ self::SUBMISSION_EVENT => Util::cloneArray($submissionEvent) ];
        $eventData = $this->createSubmissionEventData($bulkTransaction, $submitDate);
        $this->eventsService->updateCalendarEvent($submissionEvent, $eventData->toArray());
        return $stash;
      },
      function($stash) {
        $this->restoreCalendarObjects($stash);
      }
    );
  }

  /**
   * Handle bulk transaction pre notifications and gradually complete the
   * pre-notification task.
   *
   * @param Entities\SepaDebitNote $debitNote The bulk-transaction to modify.
   *
   * @param Entities\CompositePayment $payment The composite payment which was announced.
   *
   * @return void
   */
  public function handlePreNotification(
    Entities\SepaDebitNote $debitNote,
    Entities\CompositePayment $payment,
  ): void {

    $notifiedCount = $debitNote->getPayments()->filter(
      fn(Entities\CompositePayment $payment) => !empty($payment->getNotificationMessageId())
    )->count();
    $totalCount = $debitNote->getPayments()->count();

    if ($notifiedCount == $totalCount) {
      $percentage = 100;
    } else {
      $percentage = (int)round((float)$notifiedCount * 100.0 / (float)$totalCount);
    }

    try {
      $this->logInfo('TWEAK PRE NOTIFICATION TASK');
      $preNotificationTaskUri = $debitNote->getPreNotificationTaskUri();
      $preNotificationTask = $this->financeService->findFinanceCalendarEntry($preNotificationTaskUri);
      if (!empty($preNotificationTask)) {
        $this->eventsService->setCalendarTaskStatus($preNotificationTask, percentComplete: $percentage);
      }
    } catch (Throwable $t) {
      $this->logException($t, 'Unable to tweak pre-notification task ' . $preNotificationTaskUri);
    }

    try {
      $this->logInfo('TWEAK PRE NOTIFICATION EVENT');
      $preNotificationEventUri = $debitNote->getPreNotificationEventUri();
      $preNotificationEvent = $this->financeService->findFinanceCalendarEntry($preNotificationEventUri);
      if (!empty($preNotificationEvent)) {
        $vCalendar = VCalendarService::getVCalendar($preNotificationEvent);
        $description = VCalendarService::getDescription($vCalendar);
        $musician = $payment->getMusician();
        $description .= "\n----\n"
          . $this->l->t('PRENOTIFICATION HAS BEEN SENT:')
          . "\n"
          . $this->l->t('Person: %s', $musician->getPublicName(true) . ' <' . $musician->getEmail() . '>')
          . "\n"
          . $this->l->t('Date: %s', $this->dateTimeFormatter->formatDateTime($this->timeFactory->now()))
          . "\n"
          . $this->l->t('MessageId: %s', $payment->getNotificationMessageId());
        $updateData = [
          'description' => $description,
        ];
        if ($percentage == 100) {
          $updateData['alarm'] = 0;
        }
        $this->eventsService->updateCalendarEntry($preNotificationEvent, $updateData);
      }
    } catch (Throwable $t) {
      $this->logException($t, 'Unable to tweak pre-notification event ' . $preNotificationEventUri);
    }
  }

  /**
   * Given the due-date calculate the pre-notification deadline for the debit
   * mandate.  We allow for extra "space" between the resulting due-date and
   * the submission date. So the idea is to allow two more business days by
   * adding another work-day between due-date and hard bank-submission
   * dead-line.
   *
   * @param Entities\SepaDebitMandate $debitMandate Database entity.
   *
   * @param DateTimeImmutable $dueDate The due-date of the debit mandate.
   *
   * @return DateTimeImmutable
   */
  public function calculateDebitNotePreNotificationDeadline(
    Entities\SepaDebitMandate $debitMandate,
    DateTimeImmutable $dueDate,
  ): DateTimeImmutable {
    $preNotificationBusinessDays = $debitMandate->getPreNotificationBusinessDays()
      + self::DEBIT_NOTE_SUBMISSION_EXTRA_WORKING_DAYS
      + self::DEBIT_NOTE_SUBMISSION_DEADLINE;
    // compute pre-notification dead-line
    $deadline = $this->financeService->targetDeadline(
      -$preNotificationBusinessDays,
      -$debitMandate->getPreNotificationCalendarDays() ?: 0,
      $dueDate);

    return $deadline;
  }

  /**
   * Calculate the debit-note due-date based on the current date.  We allow
   * for extra "space" between the resulting due-date and the submission
   * date. So the idea is to allow two more business days by adding another
   * work-day between due-date and hard bank-submission dead-line.
   *
   * @param Entities\SepaDebitMandate $debitMandate Database entity.
   *
   * @return DateTimeImmutable
   */
  public function calculateDebitNoteDueDate(
    Entities\SepaDebitMandate $debitMandate,
  ): DateTimeImmutable {
    $preNotification = $this->timeFactory->now();
    $preNotificationBusinessDays = $debitMandate->getPreNotificationBusinessDays()
      + self::DEBIT_NOTE_SUBMISSION_EXTRA_WORKING_DAYS
      + self::DEBIT_NOTE_SUBMISSION_DEADLINE;
    $dueDate = $this->financeService->targetDeadline(
      $preNotificationBusinessDays,
      $debitMandate->getPreNotificationCalendarDays() ?: 0,
      $preNotification);

    return $dueDate;
  }

  /**
   * @param string $format Export format specifier such as 'aqbanking'.
   *
   * @return null|IBulkTransactionExporter
   */
  public function getTransactionExporter(string $format):?IBulkTransactionExporter
  {
    return $this->appContainer->get(self::EXPORT_SERVICE_ALIAS . $format);
  }

  /**
   * @param string $format Export format specifier such as 'gnucash'.
   *
   * @return null|IBulkTransactionExporter
   */
  public function getBalancingItemsExporter(string $format):?IBulkTransactionExporter
  {
    return $this->appContainer->get(self::EXPORT_BALANCING_ITEMS_SERVICE_ALIAS . $format);
  }

  /**
   * @param Entities\SepaBulkTransaction $transaction Given bank transaction database entity.
   *
   * @return string A slug for the given bulk-transaction which can for
   * example be used to tag email-templates.
   *
   * The strategy is to return the string self::TRANSACTION_TYPE_BANK_TRANSFER for
   * bank-transfers and 'debitnote-UNIQUEOPTIONSLUG' if the
   * bulk-transaction refers to a single payment kind (e.g. only
   * insurance fees) and otherwise just self::TRANSACTION_TYPE_DEBIT_NOTE.
   */
  public function getBulkTransactionSlug(Entities\SepaBulkTransaction $transaction):string
  {
    if ($transaction instanceof Entities\SepaBankTransfer) {
      return self::TRANSACTION_TYPE_BANK_TRANSFER;
    }
    $slugParts = [ self::TRANSACTION_TYPE_DEBIT_NOTE => true ];

    $filterState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    /** @var Entities\CompositePayment $compositePayment */
    foreach ($transaction->getPayments() as $compositePayment) {
      /** @var Entities\ProjectPayment $projectPayment */
      foreach ($compositePayment->getProjectPayments() as $projectPayment) {
        /** @var Entities\ProjectParticipantField $field */
        $field = $projectPayment->getReceivable()->getField();

        if ($field->getMultiplicity() == FieldMultiplicity::RECURRING) {
          $generator = $field->getManagementOption()->getData();
          $optionSlug = method_exists($generator, 'slug')
                      ? $generator::slug() : $field->getName();
        } else {
          $optionSlug = $field->getName();
        }
        $slugParts[$optionSlug] = true;
      }
    }

    $filterState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    $slugParts = array_keys($slugParts);
    if (count($slugParts) > 2) {
      return $slugParts[0];
    } else {
      return implode('-', $slugParts);
    }
  }

  /**
   * Generate the payments for the specified service-fee options. The payments
   * are returned unpersisted. Although composite-payments may in principle
   * contain payments for different projects this is not implemented here.
   *
   * @param Entities\ProjectParticipant $participant
   *
   * @param array<int, Entities\ProjectParticipantFieldDataOption> $receivableOptions
   *
   * @param \DateTimeInterface|null $transactionDueDate Targeted
   * transaction due-date. It set the amount debited for receivables
   * will be capped according to their receivable-due-date and deposit-due-date.
   *
   * @return Entities\CompositePayment
   *
   * @todo Check
   * Entities\ProjectParticipantFieldDataOption::getMusicianFieldData(), it
   * should only return more than one item if there are also deleted items.
   */
  public function generateProjectPayments(
    Entities\ProjectParticipant $participant,
    array $receivableOptions,
    ?\DateTimeInterface $transactionDueDate = null,
  ): Entities\CompositePayment {
    $payments = new ArrayCollection();
    $totalAmount = MonetaryNumberType::zero();
    $project = $participant->getProject();
    $musician = $participant->getMusician();

    /** @var Entities\CompositePayment $compositePayment */
    $compositePayment = (new Entities\CompositePayment)
      ->setProjectParticipant($participant)
      ->setProjectPayments($payments)
      ->setAmount($totalAmount)
      ->setSubject('');

    if (empty($receivableOptions)) {
      return $compositePayment;
    }

    /** @var Entities\ProjectParticipantFieldDataOption $receivableOption */
    foreach ($receivableOptions as $receivableOption) {
      if ($project != $receivableOption->getField()->getProject()) {
        throw new RuntimeException(
          $this->l->t('Refusing to generate payments for mismatching projects, current is "%s", but the receivable belongs to project "%s".', [
            $project->getName(),
            $receivableOption->getField()->getProject()->getName(),
          ]));
      }
      $receivableDueDate = $receivableOption->getField()->getDueDate();
      $depositDueDate = $receivableOption->getField()->getDepositDueDate();
      /** @var Entities\ProjectParticipantFieldDatum $receivable */
      foreach ($receivableOption->getMusicianFieldData($musician) as $receivable) {
        $paidAmount = $receivable->amountPaid();
        $payableAmount = $receivable->amountPayable();
        $depositAmount = $receivable->depositAmount() ?? MonetaryNumberType::zero();
        if ($payableAmount->sign() * $depositAmount->sign() < 0) {
          throw new RuntimeException(
            $this->l->t(
              'Payable amount "%1$s" and deposit amount "%2$s" should have the compatible signs.', [
                $payableAmount->toDecimal(2), $depositAmount->toDecimal(2),
              ]));
        }
        if (!empty($transactionDueDate) && !empty($receivableDueDate)) {
          if ($payableAmount->gt(0)) {
            // debit note
            if ($receivableDueDate <= $transactionDueDate) {
              // past due-date, just keep billing the entire amount
            } elseif ($depositDueDate <= $transactionDueDate) {
              // past deposit-due date, charge the deposit
              $payableAmount = $depositAmount;
            } else {
              // too early, just don't charge anything
              $payableAmount->assign(0);
              $this->logInfo('NOT YET DUE; DOING NOTHING');
            }
          } else {
            // bank transfer
            if ($transactionDueDate <= $depositDueDate) {
              // early payment, only up to deposit
              $payableAmount = $depositAmount;
            } else {
              // just transfer anything, i.e. keep the amount as is
            }
          }
        }
        $debitAmount = $payableAmount->sub($paidAmount)->round(2);
        if ($debitAmount->eq(0)) {
          // No need to debit empty amounts
          // @todo Perhaps empty amounts should also be recorded.
          continue;
        }
        $this->logInfo('RECEIVABLE FIELD ID ' . $receivable->getField()->getId());
        $this->logInfo('RECEIVABLE FIELD MULTIPLICITY ' . $receivable->getField()->getMultiplicity()->value);
        $this->refreshEntity($receivable->getField());
        $this->logInfo('RECEIVABLE FIELD NAME ' . $receivable->getField()->getName());
        /** @var Entities\ProjectPayment $payment */
        $payment = (new Entities\ProjectPayment)
                 ->setProject($project)
                 ->setMusician($musician)
                 ->setReceivable($receivable)
                 ->setReceivableOption($receivableOption)
                 ->setAmount($debitAmount)
                 ->setCompositePayment($compositePayment)
                 ->setSubject($receivable->paymentReference())
                 ->setIsDonation(false)
          ;

        $payments->add($payment);
        $totalAmount->addEq($debitAmount);
      }
    }

    $compositePayment
      ->setAmount($totalAmount)
      ->updateSubject(fn($x) => $this->financeService->sepaTranslit($x));

    return $compositePayment;
  }

  /**
   * @param Entities\SepaBulkTransaction $bulkTransaction Database entity.
   *
   * @return array Return an array
   * ```
   * [
   *   'submissionEvent' => [ 'uri' => URI, 'uid' => UID ],
   *   'submisisonTask' => [ 'uri' => URI, 'uid' => UID ],
   *   'dueEvent' => [ 'uri' => URI, 'uid' => UID ],
   *   'preNotificationEvent' => [ 'uri' => URI, 'uid' => UID ],
   *   'preNotificationTask' => [ 'uri' => URI, 'uid' => UID ],
   * ]
   * ```
   */
  public function getCalendarObjects(
    Entities\SepaBulkTransaction $bulkTransaction,
  ):array {
    // Undoing calendar entry deletion would be a bit hard ... so just
    // delete them after we have successfully removed the bulk-transaction.
    $calendarObjects = [
      'submission' => [
        'event' => [
          'uri' => $bulkTransaction->getSubmissionEventUri(),
          'uid' => $bulkTransaction->getSubmissionEventUid(),
        ],
        'task' => [
          'uri' => $bulkTransaction->getSubmissionTaskUri(),
          'uid' => $bulkTransaction->getSubmissionTaskUid(),
        ],
      ],
      'due' => [
        'event' => [
          'uri' => $bulkTransaction->getDueEventUri(),
          'uid' => $bulkTransaction->getDueEventUid(),
        ],
      ],
    ];

    if ($bulkTransaction instanceof Entities\SepaDebitNote) {
      /** @var Entities\SepaDebitNote $bulkTransaction */
      $calendarObjects['preNotification'] = [
        'event' => [
          'uri' => $bulkTransaction->getPreNotificationEventUri(),
          'uid' => $bulkTransaction->getPreNotificationEventUid(),
        ],
        'task' => [
          'uri' => $bulkTransaction->getPreNotificationTaskUri(),
          'uid' => $bulkTransaction->getPreNotificationTaskUid(),
        ]
      ];
    }
    return $calendarObjects;
  }

  /**
   * Make a backup-copy of the associated calendar entries in order to restore
   * them after failed database transactions and the like.
   *
   * @param Entities\SepaBulkTransaction $bulkTransaction Database entity.
   *
   * @param null|array $only Stash only the given objects.
   *
   * @return array Return an array
   * ```
   * [
   *   'submissionEvent' => EVENT_DATA,
   *   'submisisonTask' => TASK_DATA,
   *   'dueEvent' => EVENT_DATA,
   *   'preNotificationEvent' => EVENT_DATA,
   *   'preNotificationTask' => TASK_DATA,
   * ]
   * ```
   *
   * @see CALENDAR_EVENTS
   */
  public function stashCalendarObjects(Entities\SepaBulkTransaction $bulkTransaction, ?array $only = null):array
  {
    $calendarIds = $this->getCalendarObjects($bulkTransaction);

    // $this->logInfo('CALIDS ' . print_r($calendarIds, true));

    if ($only === null) {
      $only = array_keys($calendarIds);
    } else {
      $only = array_intersect($only, array_keys($calendarIds));
    }

    $calendarObjects = [];
    foreach ($only as $eventKind) {
      $eventIds = $calendarIds[$eventKind];
      foreach ($eventIds as $objectType => $objectIds) {
        foreach (array_filter($objectIds) as $objectId) {
          $calendarObject = $this->financeService->findFinanceCalendarEntry($objectId);
          if (!empty($calendarObject)) {
            $calendarObjects[$eventKind][$objectType] = Util::cloneArray($calendarObject);
            break;
          }
        }
      }
    }

    return $calendarObjects;
  }

  /**
   * @param array $calendarObjects Previously stashed calendar objects.
   *
   * @return void
   *
   * @see stashCalendarObjects()
   */
  public function restoreCalendarObjects(array $calendarObjects):void
  {
    foreach ($calendarObjects as $object) {
      $this->financeService->updateFinanceCalendarEntry($object);
    }
  }

  /**
   * Remove the given bulk-transaction if it is essentially unused.
   *
   * @param Entities\SepaBulkTransaction $bulkTransaction Database entity.
   *
   * @param bool $force Disable security checks and just delete it. Defaults to \false.
   *
   * @return void
   *
   * @throws Exceptions\DatabaseReadonlyException
   * @throws Exceptions\DatabaseException
   */
  public function removeBulkTransaction(Entities\SepaBulkTransaction $bulkTransaction, bool $force = false):void
  {
    if (!$force && $bulkTransaction->getSubmitDate() !== null) {
      throw new Exceptions\DatabaseReadonlyException(
        $this->l->t(
          'The bulk-transaction with id "%1$d" has already been submitted to the bank, it cannot be deleted.', $bulkTransaction->getId())
      );
    }

    $this->entityManager->beginTransaction();

    $this->entityManager->registerPreCommitAction(
      function() use ($bulkTransaction) {
        $stash = $this->stashCalendarObjects($bulkTransaction);
        $calendarObjects = $this->getCalendarObjects($bulkTransaction);
        foreach ($calendarObjects as $eventIds) {
          foreach ($eventIds as $calIds) {
            if (empty($calIds['uri']) && empty($calIds['uid'])) {
              continue;
            }
            $this->financeService->deleteFinanceCalendarEntry($calIds);
          }
        }
        return $stash;
      },
      function($stash) {
        $this->restoreCalendarObjects($stash);
      },
    );

    try {
      // transaction data is handled in a separate listener.
      $this->remove($bulkTransaction, flush: true);
      $this->entityManager->commit();

    } catch (Throwable $t) {
      $this->entityManager->rollback();
      throw new Exceptions\DatabaseException(
        $this->l->t('Failed to remove bulk-transaction with id %d', $bulkTransaction->getId()),
        $t->getCode(),
        $t
      );
    }
  }

  /**
   * Update the given bulk-transaction. ATM this "just" updates the
   * automatically generated payment subject.
   *
   * @param Entities\SepaBulkTransaction $bulkTransaction The transaction to update.
   *
   * @param boolean $flush Whether to flush the result to the data-base. The
   * routine may through if set to \true.
   *
   * @return Entities\SepaBulkTransaction Just the argument $bulkTransaction
   * in case of success.
   */
  public function updateBulkTransaction(Entities\SepaBulkTransaction $bulkTransaction, bool $flush = false):Entities\SepaBulkTransaction
  {
    /** @var Entities\CompositePayment $compositePayment */
    foreach ($bulkTransaction->getPayments() as $compositePayment) {
      $compositePayment->updateSubject(fn($x) => $this->financeService->sepaTranslit($x));
    }

    if ($flush) {
      $this->entityManager->beginTransaction();
      try {
        $this->flush();
        $this->entityManager->commit();
      } catch (Throwable $t) {
        $this->logException($t);
        $this->entityManager->rollback();
        throw new Exceptions\DatabaseException(
          $this->l->t('Unable to update payment subject while generating bank export data.'),
          $t->getCode(),
          $t);
      }
    }

    return $bulkTransaction;
  }

  /**
   * Generate the export data for the given bulk-transaction and project.
   *
   * @param Entities\SepaBulkTransaction $bulkTransaction
   *
   * @param null|Entities\Project $project Project the transaction belongs to.
   *
   * @param string $format Format of the export file, defaults to self::EXPORT_AQBANKING.
   *
   * @return null|Entities\DatabaseStorageFile The generated export set.
   */
  public function generateTransactionData(
    Entities\SepaBulkTransaction $bulkTransaction,
    ?Entities\Project $project = null,
    string $format = self::EXPORT_AQBANKING,
  ): ?Entities\DatabaseStorageFile {
    // as a safe-guard regenerate the subject in order to catch changes in
    // linked supporting documents.
    $this->updateBulkTransaction($bulkTransaction, flush: true);

    $exportDocument = null;
    $transcationData = $bulkTransaction->getSepaTransactionData();
    /** @var Entities\DatabaseStorageFile $exportDocument */
    foreach ($transcationData as $exportDocument) {
      if (strpos($exportDocument->getName(), $format) !== false) {
        break;
      }
      $exportDocument = null;
    }

    if (true || empty($exportDocument) || $bulkTransaction->getUpdated() > $exportDocument->getUpdated()) {

      /** @var BankTransactionsStorage $storage */
      $storage = $this->appContainer->get(BankTransactionsStorage::class);

      /** @var IBulkTransactionExporter $exporter */
      $exporter = $this->getTransactionExporter($format);
      if (empty($exporter)) {
        throw new InvalidArgumentException($this->l->t('Unable to find exporter for format "%s".', $format));
      }
      if ($bulkTransaction instanceof Entities\SepaBankTransfer) {
        $transactionType = self::TRANSACTION_TYPE_BANK_TRANSFER;
      } elseif ($bulkTransaction instanceof Entities\SepaDebitNote) {
        $transactionType = self::TRANSACTION_TYPE_DEBIT_NOTE;
      }

      $timeStamp = $this->timeStamp();

      /** @var Entities\CompositePayment $payment */
      foreach ($bulkTransaction->getPayments() as $payment) {
        $paymentProject = $payment->getProject();
        if (empty($project)) {
          $project = $paymentProject;
        } elseif ($project != $paymentProject) {
          throw new UnexpectedValueException($this->l->t(
            'Conflicting projects "%1$s" vs. "%2$s".', [
              (string)$project,  (string)$paymentProject
            ]));
        }
      }

      $fileName = implode('-', array_filter([
        $timeStamp,
        $transactionType,
        !empty($project) ? $project->getName() : null,
        $format,
      ])) . '.' . $exporter->fileExtension($bulkTransaction);

      $fileData = $exporter->fileData($bulkTransaction);

      if (empty($exportDocument)) {
        $exportFile = new Entities\EncryptedFile(
          fileName: $fileName,
          data: $fileData,
          mimeType: $exporter->mimeType($bulkTransaction)
        );
        $this->persist($exportFile);
      } else {
        $exportDocument
          ->setName($fileName);
        $exportFile = $exportDocument->getFile();
        $exportFile
          ->setFileName($fileName)
          ->setMimeType($exporter->mimeType($bulkTransaction))
          ->setSize(strlen($fileData))
          ->getFileData()->setData($fileData);

      }

      $this->entityManager->beginTransaction();
      try {
        $exportDocument = $storage->addDocument($bulkTransaction, $exportFile, flush: false);
        $bulkTransaction->addTransactionData($exportDocument);
        $this->flush();
        $this->entityManager->commit();
      } catch (Throwable $t) {
        $this->entityManager->rollback();
        throw new Exceptions\DatabaseException(
          $this->l->t(
            'Unable to generate export data for bulk-transaction id %1$d, format "%2$s".', [
              $bulkTransaction->getId(), $format
            ]
          ),
          $t->getCode(),
          $t
        );
      }
    }
    return $exportDocument;
  }

  /**
   * Generate import data for accounting software.
   *
   * @param Entities\SepaBulkTransaction $bulkTransaction
   *
   * @param string $format Format of the export file, defaults to self::EXPORT_GNU_CASH.
   *
   * @return null|Entities\DatabaseStorageFile The generated export set.
   *
   * @throws Exceptions\EnduserNotificationException
   */
  public function generateBalancingItems(
    Entities\SepaBulkTransaction $bulkTransaction,
    string $format = self::EXPORT_GNU_CASH,
  ): ?Entities\DatabaseStorageFile {
    $exportDocument = null;
    $balancingItemsData = $bulkTransaction->getBalancingItemsData();
    /** @var Entities\DatabaseStorageFile $exportDocument */
    foreach ($balancingItemsData as $exportDocument) {
      if (strpos($exportDocument->getName(), $format) !== false) {
        break;
      }
      $exportDocument = null;
    }

    if (true || empty($exportDocument) || $bulkTransaction->getUpdated() > $exportDocument->getUpdated()) {

      /** @var BankTransactionsStorage $storage */
      $storage = $this->appContainer->get(BankTransactionsStorage::class);

      /** @var IBulkTransactionExporter $exporter */
      $exporter = $this->getBalancingItemsExporter($format);
      if (empty($exporter)) {
        throw new InvalidArgumentException($this->l->t('Unable to find a balancing items exporter for format "%s".', $format));
      }
      if ($bulkTransaction instanceof Entities\SepaBankTransfer) {
        $transactionType = self::TRANSACTION_TYPE_BANK_TRANSFER;
      } elseif ($bulkTransaction instanceof Entities\SepaDebitNote) {
        $transactionType = self::TRANSACTION_TYPE_DEBIT_NOTE;
      }

      $timeStamp = $this->timeStamp();

      $project = null;
      /** @var Entities\CompositePayment $payment */
      foreach ($bulkTransaction->getPayments() as $payment) {
        $paymentProject = $payment->getProject();
        if (empty($project)) {
          $project = $paymentProject;
        } elseif ($project != $paymentProject) {
          throw new UnexpectedValueException($this->l->t(
            'Conflicting projects "%1$s" vs. "%2$s".', [
              (string)$project,  (string)$paymentProject
            ]));
        }
      }

      $fileName = implode('-', array_filter([
        $timeStamp,
        $transactionType,
        !empty($project) ? $project->getName() : null,
        'balancing',
        'items',
        $format,
      ])) . '.' . $exporter->fileExtension($bulkTransaction);

      $fileData = $exporter->fileData($bulkTransaction);

      if (empty($exportDocument)) {
        $exportFile = new Entities\EncryptedFile(
          fileName: $fileName,
          data: $fileData,
          mimeType: $exporter->mimeType($bulkTransaction)
        );
        $this->persist($exportFile);
      } else {
        $exportDocument
          ->setName($fileName);
        $exportFile = $exportDocument->getFile();
        $exportFile
          ->setFileName($fileName)
          ->setMimeType($exporter->mimeType($bulkTransaction))
          ->setSize(strlen($fileData))
          ->getFileData()->setData($fileData);

      }

      $this->entityManager->beginTransaction();
      try {
        $exportDocument = $storage->addDocument($bulkTransaction, $exportFile, flush: false);
        $bulkTransaction->addBalancingItemsData($exportDocument);
        $this->flush();
        $this->entityManager->commit();
      } catch (Throwable $t) {
        $this->entityManager->rollback();
        throw new Exceptions\DatabaseException(
          $this->l->t(
            'Unable to generate export data for the balancing items for bulk-transaction id %1$d, format "%2$s".', [
              $bulkTransaction->getId(), $format
            ]
          ),
          $t->getCode(),
          $t
        );
      }
    }

    return $exportDocument;
  }
}
