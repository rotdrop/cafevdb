<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Traits;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/** Convert some entities to array representations */
trait FlattenEntityTrait
{
  private function flattenMusician(Entities\Musician $musician)
  {
    $musicianData = $musician->toArray();

    $musicianData['personalPublicName'] = $musician->getPublicName(firstNameFirst: true);

    // duplicate some fields for easier matching in document templates
    $musicianData['name'] = $musicianData['personalPublicName'];
    $musicianData['phone'] = $musicianData['fixedLinePhone'];
    $musicianData['mobile'] = $musicianData['mobilePhone'];

    $musicianData['instruments'] = [];
    /** @var Entities\Instrument $instrument */
    /** @var Entities\MusicianInstrument $musicianInstrument */
    foreach ($musician->getInstruments() as $musicianInstrument) {
      $instrument = $musicianInstrument->getInstrument();
      $flatInstrument = $instrument->toArray();
      unset($flatInstrument['musicianInstruments']);
      $flatInstrument['ranking'] = $musicianInstrument->getRanking();
      $flatInstrument['families'] = [];
      foreach ($instrument->getFamilies() as $family) {
        $flatFamily = $family->toArray();
        unset($flatFamily['instruments']);
        $flatInstrument['families'][] = $flatFamily;
      }
      $musicianData['instruments'][] = $flatInstrument;
    }

    /** @var Entities\SepaBankAccount $bankAccount */
    $musicianData['sepaBankAccounts'] = [];
    unset($musicianData['sepaDebitMandates']);
    foreach ($musician->getSepaBankAccounts() as $bankAccount) {
      $flatBankAccount = $bankAccount->toArray();
      unset($flatBankAccount['musician']);
      $flatBankAccount['musicianId'] = $musician->getId();
      $flatBankAccount['sepaDebitMandates'] = [];
      /** @var Entities\SepaDebitMandate $debitMandate */
      foreach ($bankAccount->getSepaDebitMandates() as $debitMandate) {
        $flatDebitMandate = $debitMandate->toArray();
        unset($flatDebitMandate['sepaBankAccount']);
        $flatDebitMandate['bankAccountSequence'] = $bankAccount->getSequence();
        unset($flatDebitMandate['musician']);
        $flatDebitMandate['musicianId'] = $musician->getId();
        $flatDebitMandate['project'] = $this->flattenProject($debitMandate->getProject());
        $flatBankAccount['sepaDebitMandates'][] = $flatDebitMandate;
      }
      $musicianData['sepaBankAccounts'][] = $flatBankAccount;
    }

    /** @var Entities\ProjectParticipant $participant */
    $musicianData['projectParticipation'] = [];
    foreach ($musician->getProjectParticipation() as $participant) {
      /** @var Entities\Project $project */
      $project = $participant->getProject();
      $flatParticipant = $participant->toArray();
      unset($flatParticipant['musician']);
      $flatParticipant['musicianId'] = $musician->getId();
      $flatParticipant['project'] = $this->flattenProject($participant->getProject());
      unset($flatParticipant['musicianInstruments']);
      unset($flatParticipant['sepaBankAccount']);
      unset($flatParticipant['sepaDebitMandate']);

      /** @var Entities\ProjectInstrument $projectInstrument */
      $flatParticipant['projectInstruments'] = [];
      foreach ($participant->getProjectInstruments() as $projectInstrument) {
        $instrument = $projectInstrument->getInstrument();
        $flatInstrument = $instrument->toArray();
        unset($flatInstrument['projectInstruments']);
        $flatInstrument['voice'] = $projectInstrument->getVoice();
        $flatInstrument['sectionLeader'] = $projectInstrument->getSectionLeader();
        // unset most of the instrument, too much Data
        unset($flatInstrument['families']);
        unset($flatInstrument['sortOrder']);
        unset($flatInstrument['deleted']);
        unset($flatInstrument['musicianInstruments']);

        $flatParticipant['projectInstruments'][] = $flatInstrument;
      }

      $projectFields = [];
      /** @var Entities\ProjectParticipantField $projectField */
      foreach ($project->getParticipantFields() as $projectField) {
        $flatProjectField = $projectField->toArray();
        unset($flatProjectField['project']);
        unset($flatProjectField['dataOptions']);
        $defaultValue = $projectField->getDefaultValue();
        if (!empty($defaultValue)) {
          $flatDefaultValue = array_filter($defaultValue->toArray());
          foreach (['field', 'fieldData', 'payments'] as $key) {
            unset($flatDefaultValue[$key]);
          }
        } else {
          $flatDefaultValue = null;
        }
        $flatProjectField['untranslatedName'] = $projectField->getUntranslatedName();
        $flatProjectField['defaultValue'] = $flatDefaultValue;
        $flatProjectField['fieldData'] = [];
        /** @var Entities\ProjectParticipantFieldDatum $projectDatum */
        foreach ($projectField->getFieldData() as $projectDatum) {
          $flatProjectDatum = $projectDatum->toArray();
          unset($flatProjectDatum['musician']);
          unset($flatProjectDatum['project']);
          unset($flatProjectDatum['field']);
          unset($flatProjectDatum['projectParticipant']);
          $dataOption = $projectDatum->getDataOption();
          $flatDataOption = array_filter($dataOption->toArray());
          foreach (['field', 'fieldData', 'payments'] as $key) {
            unset($flatDataOption[$key]);
          }
          $flatDataOption['untranslatedLabel'] = $dataOption->getUntranslatedLabel();
          $flatProjectDatum['dataOption'] = $flatDataOption;
          $supportingDocument = $projectDatum->getSupportingDocument();
          unset($flatProjectDatum['supportingDocument']);
          if (!empty($supportingDocument)) {
            $flatProjectDatum['supportingDocumentId'] = $supportingDocument->getId();
          }
          $payments = [];
          /** @var Entities\ProjectPayment $payment */
          foreach ($projectDatum->getPayments() as $payment) {
            $flatPayment = $payment->toArray();
            foreach (['receivable', 'receivableOption', 'project', 'musician', 'projectParticipant'] as $key) {
              unset($flatPayment[$key]);
            }
            $compositePayment = $payment->getCompositePayment();
            $flatCompositePayment = $compositePayment->toArray();
            foreach (['projectPayments', 'musician'] as $key) {
              unset($flatCompositePayment[$key]);
            }
            $bankAccount = $compositePayment->getSepaBankAccount();
            $flatCompositePayment['sepaBankAccount'] = empty($bankAccount) ? null : $bankAccount->getIban();
            $debitMandate = $compositePayment->getSepaDebitMandate();
            $flatCompositePayment['sepaDebitMandate'] = empty($debitMandate) ? null : $debitMandate->getMandateReference();
            $flatPayment['compositePayment'] = array_filter($flatCompositePayment);
            $payments[] = array_filter($flatPayment);
          }
          $flatProjectDatum['payments'] = $payments;
          $flatProjectField['fieldData'][(string)$projectDatum->getOptionKey()] = array_filter($flatProjectDatum);
        }
        $projectFields[$projectField->getId()] = array_filter($flatProjectField);
      }
      $flatParticipant['participantFields'] = $projectFields;
      unset($flatParticipant['participantFieldsData']);

      $musicianData['projectParticipation'][] = $flatParticipant;
    }

    usort($musicianData['projectParticipation'], function($pp1, $pp2) {
      $p1 = $pp1['project'];
      $p2 = $pp2['project'];
      $t1 = $p1['type'];
      $t2 = $p2['type'];
      if ($t1 == $t2) {
        $y1 = $p1['year'];
        $y2 = $p2['year'];
        if ($y1 == $y2) {
          return strcmp($p1['name'], $p2['name']);
        } else {
          return $y2 < $y1 ? -1 : 1;
        }
      } else {
        if ($t1 == 'template') {
          return 1;
        } else if ($t1 == 'permanent') {
          return -1;
        } else {
          // $t1 == 'temporary'
          if ($t2 == 'template') {
            return -1;
          } else {
            // $t2 == 'permanent'
            return 1;
          }
        }
      }
    });

    /** @var Entities\InstrumentInsurance $insurance */
    $musicianData['instrumentInsurances'] = [];
    foreach ($musician->getInstrumentInsurances() as $insurance) {
      $flatInsurance = $insurance->toArray();
      unset($flatInsurance['musician']);
      $flatInsurance['musicianId'] = $musician->getId();

      $insuranceRate = $insurance->getInsuranceRate();
      $flatInsurance['insuranceRate'] = $insuranceRate->toArray();
      unset($flatInsurance['insuranceRate']['instrumentInsurances']);
      $flatInsurance['insuranceRate']['broker'] = $insuranceRate->getBroker()->toArray();
      unset($flatInsurance['insuranceRate']['broker']['insuranceRates']);
      $musicianData['instrumentInsurances'][] = $flatInsurance;
    }

    return $musicianData;
  }

  private function flattenProject(Entities\Project $project)
  {
    $flatProject = $project->toArray();
    $skippedProperties = [
      'participants',
      'participantFields',
      'participantFieldsData',
      'sepaDebitMandates',
      'payments',
      'calendarEvents',
      'instrumentationNumbers',
      'webPages',
      'participantInstruments',
      'sentEmail',
    ];
    foreach($skippedProperties as $key) {
      unset($flatProject[$key]);
    }
    return $flatProject;
  }
}
