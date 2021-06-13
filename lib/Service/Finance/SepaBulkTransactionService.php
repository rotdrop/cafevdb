<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Common\Util;

class SepaBulkTransactionService
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  // ordinary submission and notification deadlines
  const DEBIT_NOTE_SUBMISSION_DEADLINE = 1;
  const DEBIT_NOTE_NOTIFICATION_DEADLINE = 14;

  // fancy, just to have some reminders and a deadline on the task-list
  const BANK_TRANSFER_SUBMISSION_DEADLINE = 1; // 1 should be enough ...

  const BULK_TRANSACTION_REMINDER_SECONDS = 24 * 60 * 60; /* alert one day in advance */

  const EXPORT_AQBANKING = 'aqbanking';

  const SUBJECT_GROUP_SEPARATOR = '; ';
  const SUBJECT_ITEM_SEPARATOR = ', ';
  const SUBJECT_OPTION_SEPARATOR = Entities\ProjectParticipantFieldDatum::PAYMENT_REFERENCE_SEPARATOR;

  /** @var IAppContainer */
  private $appContainer;

  public function __construct(
    EntityManager $entityManager
    , IAppContainer $appContainer
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->entityManager = $entityManager;
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
    $slugParts = array_keys($slugParts);
    if (count($slugParts) > 2) {
      return $slugParts[0];
    } else {
      return implode('-', $slugParts);
    }
  }

  /**
   * Generate the payments for the specified service-fee options. The
   * payments are returned unpersisted.
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
   */
  public function generateProjectPayments(Entities\ProjectParticipant $participant, array $receivableOptions, ?\DateTimeInterface $transactionDueDate = null):Entities\CompositePayment
  {
    $payments = new ArrayCollection();
    $totalAmount = 0.0;
    $subjects = [];
    $project = $participant->getProject();
    $musician = $participant->getMusician();

    $compositePayment = (new Entities\CompositePayment)
                      ->setMusician($musician)
                      ->setProjectPayments($payments)
                      ->setAmount($totalAmount)
                      ->setSubject(implode('; ', $subjects));

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
            } else if ($depositDueDate <= $transactionDueDate) {
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

    $compositePayment->setMusician($participant->getMusician())
                     ->setAmount($totalAmount)
                     ->setSubject($purpose);

    return $compositePayment;
  }

  static public function generateCompositeSubject(array $subjects)
  {
    natsort($subjects);
    $oldPrefix = false;
    $postfix = [];
    $purpose = '';
    foreach ($subjects as $subject) {
      $parts = Util::explode(self::SUBJECT_OPTION_SEPARATOR, $subject);
      $prefix = $parts[0];
      if (count($parts) < 2 || $oldPrefix != $prefix) {
        $purpose .= implode(self::SUBJECT_ITEM_SEPARATOR, $postfix);
        if (strlen($purpose) > 0) {
          $purpose .= self::SUBJECT_GROUP_SEPARATOR;
        }
        $purpose .= $prefix;
        if (count($parts) >= 2) {
          $purpose .= self::SUBJECT_OPTION_SEPARATOR;
          $oldPrefix = $prefix;
        } else {
          $oldPrefix = false;
        }
        $postfix = [];
      }
      if (count($parts) >= 2) {
        $postfix = array_merge($postfix, array_splice($parts, 1));
      }
    }
    if (!empty($postfix)) {
      $purpose .= implode(self::SUBJECT_ITEM_SEPARATOR, $postfix);
    }

    return $purpose;
  }

  // public static function removeDebitNote(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
  // {
  //   if ($op !== 'delete') {
  //     return false;
  //   }

  //   if (empty($oldvals['Id'])) {
  //     return false;
  //   }

  //   if (!empty($oldvals['SubmitDate'])) {
  //     return false;
  //   }

  //   $debitNoteId = $oldvals['Id'];

  //   $result = true;

  //   // remove all associated payments
  //   $result = ProjectPayments::deleteDebitNotePayments($debitNoteId, $pme->dbh);

  //   // remove all the data (one item, probably)
  //   $result = self::deleteDebitNoteData($debitNoteId, $pme->dbh);

  //   try {
  //     // remove the associated OwnCloud events and task.
  //     $result = \OC_Calendar_Object::delete($oldvals['SubmissionEvent']);
  //   } catch (\Exception $e) {}

  //   try {
  //     $result = \OC_Calendar_Object::delete($oldvals['DueEvent']);
  //   } catch (\Exception $e) {}

  //   try {
  //     $result = Util::postToRoute('tasks.tasks.deleteTask',
  //                                 array('taskID' => $oldvals['SubmissionTask']));
  //   } catch (\Exception $e) {}

  //   return true;
  // }

  // /** */
  // public function recordDebitNote($project, $job, $dateIssued, $submissionDeadline, $dueDate, $calObjIds):DebitNote
  // {
  //   $debitNote = (new DebitNote)
  //              ->setProject($project)
  //              ->setJob($job)
  //              ->setDateIssued($dateIssued)
  //              ->setSubmissionDeadline($submissionDeadline)
  //              ->setDueDate($dueDate)
  //              ->setSubmissionEventUri($calObjIds[0])
  //              ->setSubmissionTaskUri($calObjIds[1])
  //              ->setDueEventUri($calObjIds[2]);
  //   return $debitNote;
  // }

  // /**
  //  * Generate the data-entity for the given debit-note
  //  *
  //  * @param DebitNote $debitNote
  //  *
  //  * @param string $fileName Download file-name.
  //  *
  //  * @param string $mimeType Download mime-type.
  //  *
  //  * @param string $exportData Download data.
  //  *
  //  * @return DebitNote
  //  */
  // public function recordDebitNoteData(DebitNote $debitNote, $fileName, $mimeType, $exportData):DebitNote
  // {
  //   /** @var DataEntity */
  //   $debitNoteData = (new DataEntity)
  //                  ->setDebitNote($debitNote)
  //                  ->setFileName($fileName)
  //                  ->setMimeType($mimeType)
  //                  ->setData($exportData);
  //   $debitNote->setSepaDebitNoteData($debitNoteData);

  //   return $debitNote;
  // }

  // /**
  //  * Generate the payment entities for the given debit-note
  //  *
  //  * @param DebitNote $debitNote
  //  *
  //  * @param array<int, SepaDebitNoteData> $payments Exported debit-note payments.
  //  *
  //  * @param DateTime $dueDate
  //  *
  //  * @return DebitNote
  //  */
  // public function recordDebitNotePayments(DebitNote $debitNote, array $payments, DateTime $dueDate):DebitNote
  // {
  //   foreach ($payments as $paymentData) {
  //     $debitNotePayment = (new Entities\ProjectPayment)
  //                       ->setProject($debitNote->getProject())
  //                       ->setMusician($paymentData['musicianId'])
  //                       ->setAmount($paymentData['amount'])
  //                       ->setDateOfReceipt($dueDate)
  //                       ->setSubject(implode("\n", $paymentData['purpose']))
  //                       ->setDebitNote($debitNote)
  //                       ->setMandateReference($paymentData['mandateReference']);
  //     $debitNote->getProjectPayments()->add($debitNotePayment);
  //   }
  //   return $debitNote;
  // }

  // /** Return the name for the default email-template for the given job-type. */
  // public function emailTemplate($debitNoteJob)
  // {
  //   switch($debitNoteJob) {
  //   case 'remaining':
  //     return $this->l->t('DebitNoteAnnouncementProjectRemaining');
  //   case 'amount':
  //     return $this->l->t('DebitNoteAnnouncementProjectAmount');
  //   case 'deposit':
  //     return $this->l->t('DebitNoteAnnouncementProjectDeposit');
  //   case 'insurance':
  //     return $this->l->t('DebitNoteAnnouncementInsurance');
  //   case 'membership-fee':
  //     return $this->l->t('DebitNoteAnnouncementMembershipFee');
  //   default:
  //     return $this->l->t('DebitNoteAnnouncementUnknown');
  //   }
  // }

};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
