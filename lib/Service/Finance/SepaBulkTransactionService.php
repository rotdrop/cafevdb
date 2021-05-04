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

use OCP\ILogger;
use OCP\IL10N;

use Doctrine\ORM;
use Doctrine\Common\Collections\ArrayCollection;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaDebitNote as DebitNote;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaDebitNoteData as DataEntity;

class SepaBulkTransactionService
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** ORM\EntityRepository */
  private $debitNoteRepository;

  public function __construct(
    EntityManager $entityManager
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->entityManager = $entityManager;
    $this->logger = $logger;
    $this->l = $l10n;

    $this->debitNoteRepository = $this->getDatabaseRepository(DebitNote::class);
  }

  /**
   * Generate the payments for the specified service-fee options. The
   * payments are returned unpersisted.
   *
   * @param Entities\ProjectParticipant $participant
   *
   * @param array<int, Entities\ProjectParticipantFieldDataOption> $receivableOptions
   *
   * @return array
   */
  public function generateProjectPayments(Entities\ProjectParticipant $participant, array $receivableOptions):ArrayCollection
  {
    $payments = new ArrayCollection();
    if (empty($receivableOptions)) {
      return $payments;
    }
    $project = $participant->getProject();
    $musician = $participant->getMusician();

    /** @var Entities\ProjectParticipantFieldDataOption $receivableOption */
    foreach ($receivableOptions as $receivableOption) {
      if ($project != $receivableOption->getField()->getProject()) {
        throw new \RuntimeException(
          $this->l->t('Refusing to generate payments for mismatching projects, current is "%s", but the receivable belongs to project "%s".', [
            $project->getName(),
            $receivableOption->getField()->getProject()->getName(),
          ]));
      }
      /** @var Entities\ProjectParticipantFieldDatum $receivable */
      foreach ($receivableOption->getMusicianFieldData($musician) as $receivable) {
        $payableAmount = $receivable->amountPayable();
        $paidAmount = $receivable->amountPaid();
        $debitAmount = round($payableAmount - $paidAmount, 2);
        if ($debitAmount == 0.0) {
          // No need to debit empty amounts
          // FIXME. Perhaps empty amounts should also be recorded.
          continue;
        }
        $payment = (new Entities\ProjectPayment)
                 ->setProject($project)
                 ->setMusician($musician)
                 ->setAmount($debitAmount)
                 ->setSubject($receivable->paymentReference());
        // the following are set later:
        // ->setDateOfReceipt() set to due-date of debit note
        // ->setDebitNote($debitNote)
        // ->setDebitMessageId($debitMessageId)
        $payments->add($payment);
      }
    }
    return $payments;
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
