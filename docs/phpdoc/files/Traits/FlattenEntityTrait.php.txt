<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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
  /**
   * @param Entities\Musician $musician
   *
   * @param null|array $only
   *
   * @return array
   */
  private function flattenMusician(Entities\Musician $musician, ?array $only = null):array
  {
    $musicianData = $musician->toArray();

    $musicianData['personalPublicName'] = $musician->getPublicName(firstNameFirst: true);
    if (empty($musicianData['displayName'])) {
      $musicianData['displayName'] = $musician->getPublicName(firstNameFirst: false);
    }

    $skippedKeys = [
      'userPassphrase',
      'rowAccessToken',
      'cloudAccountDeactivated',
      'cloudAccountDisabled',
      'paymentsChanged',

    ];
    foreach ($skippedKeys as $key) {
      unset($musicianData[$key]);
    }

    // duplicate some fields for easier matching in document templates
    $musicianData['name'] = $musicianData['personalPublicName'];
    $musicianData['phone'] = $musicianData['fixedLinePhone'];
    $musicianData['mobile'] = $musicianData['mobilePhone'];
    $musicianData['streetAndNumber'] = $musicianData['street'] . ' ' . $musicianData['streetNumber'];
    $musicianData['numberAndStreet'] = $musicianData['streetNumber'] . ' ' . $musicianData['street'];

    if ($only === null) {
      $only = [
        'instruments' => true,
        'sepaBankAccounts' => true,
        'projectParticipation' => true,
        'instrumentInsurances' => true,
      ];
    }

    if (!($only['instruments'] ?? false)) {
      unset($musicianData['instruments']);
    } else {
      $musicianData['instruments'] = [];
      $skippedKeys = ['musicianInstruments', 'projectInstruments', 'projectInstrumentationNumbers'];
      /** @var Entities\Instrument $instrument */
      /** @var Entities\MusicianInstrument $musicianInstrument */
      foreach ($musician->getInstruments() as $musicianInstrument) {
        $instrument = $musicianInstrument->getInstrument();
        $flatInstrument = $instrument->toArray();
        foreach ($skippedKeys as $key) {
          unset($flatInstrument[$key]);
        }
        $flatInstrument['ranking'] = $musicianInstrument->getRanking();
        $flatInstrument['families'] = [];
        foreach ($instrument->getFamilies() as $family) {
          $flatFamily = $family->toArray();
          unset($flatFamily['instruments']);
          $flatInstrument['families'][] = $flatFamily;
        }
        $musicianData['instruments'][] = $flatInstrument;
      }
    }

    $key = 'sepaBankAccounts';
    if (!($only[$key] ?? false)) {
      unset($musicianData[$key]);
    } else {
      $musicianData[$key] = [];
      unset($musicianData['sepaDebitMandates']);
      $skippedKeys = [
        'musician',
        'payments',
        'writtenMandates',
      ];
      /** @var Entities\SepaBankAccount $bankAccount */
      foreach ($musician->getSepaBankAccounts() as $bankAccount) {
        $flatBankAccount = $bankAccount->toArray();
        foreach ($skippedKeys as $key) {
          unset($flatBankAccount[$key]);
        }
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
    }

    $key = 'projectParticipation';
    if (!($only[$key] ?? false)) {
      unset($musicianData[$key]);
    } else {
      $musicianData[$key] = [];
      $skippedKeys = ['musician', 'payments'];
      /** @var Entities\ProjectParticipant $participant */
      foreach ($musician->getProjectParticipation() as $participant) {
        /** @var Entities\Project $project */
        $project = $participant->getProject();
        $flatParticipant = $participant->toArray();
        foreach ($skippedKeys as $key) {
          unset($flatParticipant[$key]);
        }
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
          unset($flatInstrument['projectInstrumentationNumbers']);
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
        $pr1 = $pp1['project'];
        $pr2 = $pp2['project'];
        $tp1 = $pr1['type'];
        $tp2 = $pr2['type'];
        if ($tp1 == $tp2) {
          $yr1 = $pr1['year'];
          $yr2 = $pr2['year'];
          if ($yr1 == $yr2) {
            return strcmp($pr1['name'], $pr2['name']);
          } else {
            return $yr2 < $yr1 ? -1 : 1;
          }
        } else {
          if ($tp1 == 'template') {
            return 1;
          } elseif ($tp1 == 'permanent') {
            return -1;
          } else {
            // $tp1 == 'temporary'
            if ($tp2 == 'template') {
              return -1;
            } else {
              // $tp2 == 'permanent'
              return 1;
            }
          }
        }
      });
    }

    $key = 'instrumentInsurances';
    if (!($only[$key] ?? false)) {
      unset($musicianData[$key]);
    } else {
      $musicianData[$key] = [];
      /** @var Entities\InstrumentInsurance $insurance */
      foreach ($musician->getInstrumentInsurances() as $insurance) {
        $flatInsurance = $insurance->toArray();

        // unset($flatInsurance['musician']);
        // $flatInsurance['musicianId'] = $musician->getId();
        unset($flatInsurance['billToParty']);
        $flatInsurance['billToPartyId'] = $insurance->getBillToParty()->getId();
        unset($flatInsurance['instrumentHolder']);
        $flatInsurance['instrumentHolder'] = $insurance->getInstrumentHolder()->getId();

        $insuranceRate = $insurance->getInsuranceRate();
        $flatInsurance['insuranceRate'] = $insuranceRate->toArray();
        unset($flatInsurance['insuranceRate']['instrumentInsurances']);
        $flatInsurance['insuranceRate']['broker'] = $insuranceRate->getBroker()->toArray();
        unset($flatInsurance['insuranceRate']['broker']['insuranceRates']);
        $musicianData['instrumentInsurances'][] = $flatInsurance;
      }
    }

    return array_filter($musicianData, fn($value) => !is_object($value));
  }

  /**
   * @param Entities\Project $project
   *
   * @return array
   */
  private function flattenProject(Entities\Project $project):array
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
    foreach ($skippedProperties as $key) {
      unset($flatProject[$key]);
    }
    return $flatProject;
  }
}
