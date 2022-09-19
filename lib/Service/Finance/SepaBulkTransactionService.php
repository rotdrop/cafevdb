<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Service\Finance;

use \DateTimeImmutable as DateTime;

use OCP\AppFramework\IAppContainer;
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

  // ordinary submission and notification deadlines
  const DEBIT_NOTE_SUBMISSION_DEADLINE = 1;
  const DEBIT_NOTE_NOTIFICATION_DEADLINE = 14; // unused

  // fancy, just to have some reminders and a deadline on the task-list
  const BANK_TRANSFER_SUBMISSION_DEADLINE = 1; // 1 should be enough ...

  const BULK_TRANSACTION_REMINDER_SECONDS = 24 * 60 * 60; /* alert one day in advance */

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

  public function __construct(
    EntityManager $entityManager,
    FinanceService $financeService,
    IAppContainer $appContainer,
    ILogger $logger,
    IL10N $l10n,
  ) {
    $this->entityManager = $entityManager;
    $this->financeService = $financeService;
    $this->appContainer = $appContainer;
    $this->logger = $logger;
    $this->l = $l10n;
  }

  /**
   * @return IBulkTransactionExporter
   */
  public function getTransactionExporter($format):?IBulkTransactionExporter
  {
    $serviceName = 'export:' . 'bank-bulk-transactions:' . $format;
    return $this->appContainer->get($serviceName);
  }

  /**
   * @return A slug for the given bulk-transaction which can for
   * example be used to tag email-templates.
   *
   * The strategy is to return the string 'banktransfer' for
   * bank-transfers and 'debitnote-UNIQUEOPTIONSLUG' if the
   * bulk-transaction refers to a single payment kind (e.g. only
   * insurance fees) and otherwise just 'debitnote'.
   */
  public function getBulkTransactionSlug(Entities\SepaBulkTransaction $transaction)
  {
    if ($transaction instanceof Entities\SepaBankTransfer) {
      return 'banktransfer';
    }
    $slugParts = [ 'debitnote' => true ];

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
    $subjects = [];
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
        throw new \RuntimeException(
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
          throw new \RuntimeException($this->l->t('Payable amount "%f" and deposit amount "%f" should have the compatible signs.', [ $payableAmount, $depositAmount ]));
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
        $subjects[] = $payment->getSubject();
      }
    }

    // try to compact the subject ...
    $purpose = self::generateCompositeSubject($subjects);

    // prefix the subject with the project-slug
    $purposePrefix = $this->generateSubjectPrefix($project);
    $purpose = $purposePrefix . self::SUBJECT_PREFIX_SEPARATOR . $purpose;

    $compositePayment->setMusician($participant->getMusician())
      ->setAmount($totalAmount)
      ->updateSubject(fn($x) => $this->financeService->sepaTranslit($x));

    return $compositePayment;
  }

  /**
   * Remove the given bulk-transaction if it is essentially unused.
   *
   * @param Entities\SepaBulkTransaction $bulkTransaction
   *
   * @param bool $force Disable security checks and just delete it. Defaults to \false.
   *
   * @throws Exceptions\DatabaseReadonlyException, Exceptions\DatabaseException
   */
  public function removeBulkTransaction(Entities\SepaBulkTransaction $bulkTransaction, bool $force = false)
  {
    if (!$force && $bulkTransaction->getSubmitDate() !== null) {
      throw new Exceptions\DatabaseReadonlyException(
        $this->l->t(
          'The bulk-transaction with id "%1$d" has already been submitted to the bank, it cannot be deleted.', $bulkTransaction->getId())
      );
    }

    // Undoing calendar entry deletion would be a bit hard ... so just
    // delete them after we have successfully removed the bulk-transaction.
    $calendarObjects = [
      [
        'uri' => $bulkTransaction->getSubmissionEventUri(),
        'uid' => $bulkTransaction->getSubmissionEventUid(),
      ], [
        'uri' => $bulkTransaction->getSubmissionTaskUri(),
        'uid' => $bulkTransaction->getSubmissionTaskUid(),
      ], [
        'uri' => $bulkTransaction->getDueEventUri(),
        'uid' => $bulkTransaction->getDueEventUid(),
      ],
    ];

    if ($bulkTransaction instanceof Entities\SepaDebitNote) {
      /** @var Entities\SepaDebitNote $bulkTransaction */
      $calendarObjects[] = [
        'uri' => $bulkTransaction->getPreNotificationEventUri(),
        'uid' => $bulkTransaction->getPreNotificationEventUid(),
      ];
      $calendarObjects[] = [
        'uri' => $bulkTransaction->getPreNotificationTaskUri(),
        'uid' => $bulkTransaction->getPreNotificationTaskUid(),
      ];
    }

    $this->entityManager->beginTransaction();
    try {

      $bulkTransactionData = $bulkTransaction->getSepaTransactionData();
      /** @var Entities\EncryptedFile $transactionData */
      foreach ($bulkTransactionData as $transactionData) {
        $bulkTransactionData->removeElement($transactionData);
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

    // ok try to remove the remaining artifacts ...
    foreach ($calendarObjects as $calIds) {
      if (empty($calIds['uri']) && empty($calIds['uid'])) {
        continue;
      }
      try {
        $this->financeService->deleteFinanceCalendarEntry($calIds);
      } catch (\Throwable $t) {
        $this->logException($t, 'Unable to remove calendar entry "' . implode(', ', $calIds) . '".');
      }
    }
  }

  /**
   * Update the given bulk-transaction. ATM this "just" updates the
   * automatically generated payment subject.
   *
   * @param Entities\SepaBulkTransaction $bulkTransactio The transaction to update.
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
   * @param string $format Format of the export file, defaults to self::EXPORT_AQBANKING
   *
   * @return null|Entities\EncryptedFile The generated export set.
   */
  public function generateTransactionData(
    Entities\SepaBulkTransaction $bulkTransaction,
    ?Entities\Project $project,
    string $format = self::EXPORT_AQBANKING
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
        throw new \InvalidArgumentException($this->l->t('Unable to find exporter for format "%s".', $format));
      }
      if ($bulkTransaction instanceof Entities\SepaBankTransfer) {
        $transactionType = 'banktransfer';
      } elseif ($bulkTransaction instanceof Entities\SepaDebitNote) {
        $transactionType = 'debitnote';
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
        $bulkTransaction->getSepaTransactionData()->add($exportFile);
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
        $this->entityManager->commit();
      } catch (\Throwable $t) {
        $this->entityManager->rollback();
        throw new Exceptions\DatabaseException($this->l->t('Unable to generate export data for bulk-transaction id %1$d, format "%2$s".', [ $bulkTransaction->getId(), $format ]), $t->getCode(), $t);
      }
    }
    return $exportFile;
  }

};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
