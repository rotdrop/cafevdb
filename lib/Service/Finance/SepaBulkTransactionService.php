<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine
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
 */

namespace OCA\CAFEVDB\Service\Finance;

use \RuntimeException;
use \InvalidArgumentException;
use \DateTimeImmutable;
use \DateTimeInterface;

use OCP\AppFramework\IAppContainer;
use OCP\IDateTimeFormatter;

use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaDebitNote as DebitNote;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaDebitNoteData as DataEntity;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldDataType;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Common\GenericUndoable;
use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Service\VCalendarService;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Service;

/**
 * Service class for generating bulk-transactions for submittance to the
 * respective bank.
 */
class SepaBulkTransactionService
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /**
   * @var int
   * The hard bank deadline, 1 working day in advance, otherwise the debit
   * note will not be accepted.
   */
  const DEBIT_NOTE_SUBMISSION_DEADLINE = 1;

  /**
   * @var int
   *
   * Allow for that many extra working days.
   */
  const DEBIT_NOTE_SUBMISSION_EXTRA_WORKING_DAYS = 1;

  const TRANSACTION_TYPE_DEBIT_NOTE = 'debitnote';
  const TRANSACTION_TYPE_BANK_TRANSFER = 'banktransfer';

  // fancy, just to have some reminders and a deadline on the task-list
  const BANK_TRANSFER_SUBMISSION_DEADLINE = 1;

  const TRANSACTION_TYPES = [
    self::TRANSACTION_TYPE_BANK_TRANSFER,
    self::TRANSACTION_TYPE_DEBIT_NOTE,
  ];

  const SUBMISSION_EVENT = 'submissionEvent';
  const SUBMISSION_TASK = 'submisisonTask';
  const DUE_EVENT = 'dueEvent';
  const PRE_NOTIFICATION_EVENT = 'preNotificationEvent';
  const PRE_NOTIFICATION_TASK = 'preNotificationTask';

  /** @var array Calendar event types. */
  const CALENDAR_EVENTS = [
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
  const BULK_TRANSACTION_REMINDER_SECONDS = - 15 * 60 * 60; /* alert one day in advance at */

  /**
   * @var int
   *
   * Alert early two days before at 9:00,.
   */
  const BULK_TRANSACTION_EARLY_REMINDER_SECONDS = - (15 + 24) * 60 * 60; /* alert one day in advance */

  const EXPORT_AQBANKING = 'aqbanking';

  const SUBJECT_PREFIX_LIMIT = 16;
  const SUBJECT_PREFIX_SEPARATOR = ' / ';
  const SUBJECT_GROUP_SEPARATOR = ' / ';
  const SUBJECT_ITEM_SEPARATOR = ', ';
  const SUBJECT_OPTION_SEPARATOR = ': ';

  /** @var IAppContainer */
  private $appContainer;

  /** @var FinanceService */
  private $financeService;

  /** @var EventsService */
  private $eventsService;

  /** {@inheritdoc} */
  public function __construct(
    EntityManager $entityManager,
    FinanceService $financeService,
    EventsService $eventsService,
    IAppContainer $appContainer,
    ILogger $logger,
    IL10N $l10n,
  ) {
    $this->entityManager = $entityManager;
    $this->financeService = $financeService;
    $this->eventsService = $eventsService;
    $this->appContainer = $appContainer;
    $this->logger = $logger;
    $this->l = $l10n;
  }

  /**
   * Mark the bulk-transaction as submitted. This will resolve all submittance
   * related tasks, remove all alarms from the submit deadline-event. The
   * submit deadline-event will be renamed in order not to disturb the
   * spectator and its date will be change to the given date.
   *
   * @param Entities\SepaBulkTransaction $bulkTransaction The bulk-transaction to modify.
   *
   * @param DateTimeInterface $submitDate The date of submittance.
   *
   * @return void
   */
  public function markBulkTransactionSubmitted(
    Entities\SepaBulkTransaction $bulkTransaction,
    ?DateTimeInterface $submitDate,
  ):void {

    $now = (new DateTimeImmutable)->setTime(0, 0, 0);

    $bulkTransaction->setSubmitDate($submitDate);

    $this->entityManager->registerPreCommitAction(
      function() use ($bulkTransaction, $submitDate, $now) {
        $submissionTaskUri = $bulkTransaction->getSubmissionTaskUri();
        $submissionTask = $this->financeService->findFinanceCalendarEntry($submissionTaskUri);
        if (!empty($submissionTask)) {
          $stash = [ self::SUBMISSION_TASK => $this->eventsService->cloneCalendarEntry($submissionTask) ];
          if (empty($submitDate) || $submitDate > $now) {
            $this->eventsService->setCalendarTaskStatus($submissionTask, percentComplete: 0);
          } else {
            $this->eventsService->setCalendarTaskStatus($submissionTask, dateCompleted: $submitDate);
          }
        }
        return $stash ?? [];
      },
      function($stash) {
        $this->restoreCalendarObjects($stash);
      },
    )->register(
      function() use ($bulkTransaction, $submitDate, $now) {
        $submissionEventUri = $bulkTransaction->getSubmissionEventUri();
        $submissionEvent = $this->financeService->findFinanceCalendarEntry($submissionEventUri);
        if (!empty($submissionEvent)) {
          $stash = [ self::SUBMISSION_EVENT => $this->eventsService->cloneCalendarEntry($submissionEvent) ];
          if (empty($submitDate) || $submitDate > $now) {
            $this->logInfo('SUBMIT > NOW ' . print_r($submitDate, true) . ' ' . print_r($now, true));
            // leave as is for the moment
          } else {
            $project = $bulkTransaction->getPayments()->first()->getProject();
            /** @var Service\ConfigService $configService */
            $configService = $this->appContainer->get(Service\ConfigService::class);
            if ($bulkTransaction instanceof Entities\SepaDebitNote) {
              $summary = $this->l->t('Debit-notes submitted for %s', $project->getName());
              $description = $this->l->t(
                'Debit-notes have been submitted, due-date is %s.', $configService->dateTimeFormatter()->formatDate($bulkTransaction->getDueDate(), 'long'))
                . "\n"
                . $this->l->t('Total amount to receive: %s.', $configService->moneyValue($bulkTransaction->totals()));
              /** @var Entities\CompositePayment $payment */
              foreach ($bulkTransaction->getPayments() as $payment) {
                $description .= "\n"
                  . $this->l->t('%s pays %s.', [
                    $payment->getMusician()->getPublicName(firstNameFirst: false),
                    $configService->moneyValue($payment->getAmount())
                  ]);
              }
            } else {
              $summary = $this->l->t('Bank-transfers submitted for %s', $project->getName());
              $description = $this->l->t(
                'Bank-transfers have been submitted, due-date is %s.', $configService->dateTimeFormatter()->formatDate($bulkTransaction->getDueDate(), 'long'))
                . "\n"
                . $this->l->t('Total amount to pay: %s.', $configService->moneyValue(-$bulkTransaction->totals()));
              /** @var Entities\CompositePayment $payment */
              foreach ($bulkTransaction->getPayments() as $payment) {
                $description .= "\n"
                  . $this->l->t('%s receives %s.', [
                    $payment->getMusician()->getPublicName(firstNameFirst: false),
                    $configService->moneyValue(-$payment->getAmount())
                  ]);
              }
            }

            $this->eventsService->updateCalendarEvent(
              $submissionEvent, [
                'start' => $submitDate,
                'end' => $submitDate,
                'allday' => true,
                'alarm' => 0,
                'summary' => $summary,
                'description' => $description,
              ]);
          }
        }
        return $stash ?? [];
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
   * @param Entities\SepaDebitNote $bulkTransaction The bulk-transaction to modify.
   *
   * @param Entities\CompositePayment $payment The composite payment which was announced.
   *
   * @return void
   */
  public function handlePreNotification(
    Entities\SepaDebitNote $debitNote,
    Entities\CompositePayment $payment,
  ):void {

    try  {
      $this->logInfo('TWEAK PRE NOTIFICATION TASK');
      $preNotificationTaskUri = $debitNote->getPreNotificationTaskUri();
      $preNotificationTask = $this->financeService->findFinanceCalendarEntry($preNotificationTaskUri);
      if (!empty($preNotificationTask)) {
        $notifiedCount = $debitNote->getPayments()->filter(
          fn(Entities\CompositePayment $payment) => !empty($payment->getNotificationMessageId())
        )->count();
        $totalCount = $debitNote->getPayments()->count();

        if ($notifiedCount == $totalCount) {
          $percentage = 100;
        } else {
          $percentage = (int)round((float)$notifiedCount * 100.0 / (float)$totalCount);
        }
        $this->eventsService->setCalendarTaskStatus($preNotificationTask, percentComplete: $percentage);
      }
    }  catch (Throwable $t) {
      $this->logException($t, 'Unable to tweak pre-notification task ' . $preNotificationTaskUri);
    }

    try {
      $this->logInfo('TWEAK PRE NOTIFICATION EVENT');
      $preNotificationEventUri = $debitNote->getPreNotificationEventUri();
      $preNotificationEvent = $this->financeService->findFinanceCalendarEntry($preNotificationEventUri);
      if (!empty($preNotificationEvent)) {
        /** @var Service\ConfigService $configService */
        $configService = $this->appContainer->get(Service\ConfigService::class);
        $vCalendar = VCalendarService::getVCalendar($preNotificationEvent);
        $description = VCalendarService::getDescription($vCalendar);
        $musician = $payment->getMusician();
        $description .= "\n----\n"
          . $this->l->t('PRENOTIFICATION HAS BEEN SENT:')
          . "\n"
          . $this->l->t('Person: %s', $musician->getPublicName(true) . ' <' . $musician->getEmail() . '>')
          . "\n"
          . $this->l->t('Date: %s', $configService->dateTimeFormatter()->formatDateTime(new DateTimeImmutable))
          . "\n"
          . $this->l->t('MessageId: %s', $payment->getNotificationMessageId());
        $this->eventsService->updateCalendarEntry($preNotificationEvent, [
          'description' => $description,
        ]);
      }
    } catch (Throwable $t) {
      $this->logException($t, 'Unable to tweak pre-notification event ' . $preNotificationEventUri);
    }
  }

  /**
   * Given the raw due-data calculate the deadlines for submission and
   * pre-notification. We allow for extra "space" between the resulting
   * due-date and the submission date. So the idea is to allow two more
   * business days by adding another work-day between due-date and hard
   * bank-submission dead-line.
   *
   * @param Entities\SepaDebitMandate $debitMandate Database entity.
   *
   * @param null|DateTimeImmutable $baseDate Either pre-notificatio date or
   * due-date, es determined by $fromDueDate. If omitted now() is assumed.
   *
   * @param bool $fromDueDate \true means that $baseDate is the due-date of
   * the debit-node. \false is the default and means that $baseDate is the
   * pre-notification date.
   *
   * @return array
   * ```
   * [
   *   dueDate => DATE,
   *   preNotificationDeadline => DATE,
   *   submissionDeadline => DATE,
   *   hardSubmissionDeadline => DATE,
   * ]
   * ```
   */
  public function calculateDebitNoteDeadlines(
    Entities\SepaDebitMandate $debitMandate,
    ?DateTimeImmutable $baseDate = null,
    bool $fromDueDate = false,
  ) {
    if ($baseDate === null) {
      $baseDate = new DateTimeImmutable;
    }
    $preNotificationBusinessDays = $debitMandate->getPreNotificationBusinessDays()
      + self::DEBIT_NOTE_SUBMISSION_EXTRA_WORKING_DAYS
      + self::DEBIT_NOTE_SUBMISSION_DEADLINE;
    if ($fromDueDate) {
      $due = $baseDate;
      $preNotification = $this->financeService->targetDeadline(
        -$preNotificationBusinessDays,
        -$debitMandate->getPreNotificationCalendarDays() ?: 0,
        $due);
    } else {
      $preNotification = $baseDate;
      $due = $this->financeService->targetDeadline(
        $preNotificationBusinessDays,
        $debitMandate->getPreNotificationCalendarDays() ?: 0,
        $preNotification);
    }

    return [
      'dueDate' => $due,
      'preNofificationDeadline' => $preNotification,
    ];
  }

  /**
   * @param string $format Export format specifier such as 'aqbanking'.
   *
   * @return IBulkTransactionExporter
   */
  public function getTransactionExporter(string $format):?IBulkTransactionExporter
  {
    $serviceName = 'export:' . 'bank-bulk-transactions:' . $format;
    return $this->appContainer->get($serviceName);
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
  public function generateProjectPayments(Entities\ProjectParticipant $participant, array $receivableOptions, ?\DateTimeInterface $transactionDueDate = null):Entities\CompositePayment
  {
    $payments = new ArrayCollection();
    $totalAmount = 0.0;
    $project = $participant->getProject();
    $musician = $participant->getMusician();

    /** @var Entities\CompositePayment $compositePayment */
    $compositePayment = (new Entities\CompositePayment)
                      ->setMusician($musician)
                      ->setProjectPayments($payments)
                      ->setAmount($totalAmount)
                      ->setSubject('');

    if (empty($receivableOptions)) {
      return [ $payments, $totalAmount ];
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
        $payableAmount = (float)$receivable->amountPayable();
        $depositAmount = (float)$receivable->depositAmount();
        if ((float)$payableAmount * (float)$depositAmount < 0) {
          throw new RuntimeException($this->l->t('Payable amount "%f" and deposit amount "%f" should have the compatible signs.', [ $payableAmount, $depositAmount ]));
        }
        if (!empty($transactionDueDate) && !empty($receivableDueDate)) {
          if ($payableAmount > 0) {
            // debit note
            if ($receivableDueDate <= $transactionDueDate) {
              // past due-date, just keep billing the entire amount
            } elseif ($depositDueDate <= $transactionDueDate) {
              // past deposit-due date, charge the deposit
              $payableAmount = $depositAmount;
            } else {
              // too early, just don't charge anything
              $payableAmount = 0.0;
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
        $debitAmount = round($payableAmount - $paidAmount, 2);
        if ($debitAmount == 0.0) {
          // No need to debit empty amounts
          // FIXME. Perhaps empty amounts should also be recorded.
          continue;
        }
        /** @var Entities\ProjectPayment $payment */
        $payment = (new Entities\ProjectPayment)
                 ->setProject($project)
                 ->setMusician($musician)
                 ->setReceivable($receivable)
                 ->setReceivableOption($receivableOption)
                 ->setAmount($debitAmount)
                 ->setCompositePayment($compositePayment)
                 ->setSubject($receivable->paymentReference());

        $payments->add($payment);
        $totalAmount += $debitAmount;
      }
    }

    $compositePayment
      ->setMusician($participant->getMusician())
      ->setProject($participant->getProject())
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
            $calendarObjects[$eventKind][$objectType] = $this->eventsService->cloneCalendarEntry($calendarObject);
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
      /** @var Entities\EncryptedFile $transactionData */
      foreach ($bulkTransaction->getSepaTransactionData() as $transactionData) {
        $bulkTransaction->removeTransactionData($transactionData);
      }
      $this->remove($bulkTransaction, flush: true);

      $this->entityManager->commit();

    } catch (\Throwable $t) {
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
      } catch (\Throwable $t) {
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
   * @return null|Entities\EncryptedFile The generated export set.
   */
  public function generateTransactionData(
    Entities\SepaBulkTransaction $bulkTransaction,
    ?Entities\Project $project,
    string $format = self::EXPORT_AQBANKING,
  ):?Entities\EncryptedFile {

    // as a safe-guard regenerate the subject in order to catch changes in
    // linked supporting documents.
    $this->updateBulkTransaction($bulkTransaction, flush: true);

    $transcationData = $bulkTransaction->getSepaTransactionData();
    /** @var Entities\EncryptedFile $exportFile */
    foreach ($transcationData as $exportFile) {
      if (strpos($exportFile->getFileName(), $format) !== false) {
        break;
      }
      $exportFile = null;
    }
    if (empty($exportFile)
        || $bulkTransaction->getUpdated() > $exportFile->getUpdated()
        || $bulkTransaction->getSepaTransactionDataChanged() > $exportFile->getUpdated()) {
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

      // FIXME: just for the timeStamp() function ...
      $timeStamp = $this->appContainer->get(Service\ConfigService::class)->timeStamp();

      $fileName = implode('-', array_filter([
        $timeStamp,
        $transactionType,
        !empty($project) ? $project->getName() : null,
        $format,
      ])) . '.' . $exporter->fileExtension($bulkTransaction);

      $fileData = $exporter->fileData($bulkTransaction);

      if (empty($exportFile)) {
        $exportFile = new Entities\EncryptedFile(
          fileName: $fileName,
          data: $fileData,
          mimeType: $exporter->mimeType($bulkTransaction)
        );
      } else {
        $exportFile
          ->setFileName($fileName)
          ->setMimeType($exporter->mimeType($bulkTransaction))
          ->setSize(strlen($fileData))
          ->getFileData()->setData($fileData);
      }

      $this->entityManager->beginTransaction();
      try {
        $this->persist($exportFile);
        $this->flush();
        $bulkTransaction->addTransactionData($exportFile);
        $this->flush();
        $this->entityManager->commit();
      } catch (\Throwable $t) {
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
    return $exportFile;
  }
}
