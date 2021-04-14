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

use OCA\CAFEVDB\Service\ConfigService;

use \DateTimeImmutable as DateTime;

/**
 * Export the respective debit-mandates and generate a flat table
 * view which can be exported 1-to-1 into a CSV table suitable to
 * finally issue the debit mandates to the respective credit
 * institutes.
 */
class AqDebitNoteExporter implements IDebitNoteExporter
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var FinanceService */
  private $financeService;

  public function __construct(
    ConfigService $configService
    , FinanceService $financeService
  ) {
    $this->configService = $configService;
    $this->financeService = $financeService;

    $iban = new \IBAN($this->getConfigValue('bankAccountIBAN'));
    $this->iban = $iban->MachineFormat();
    $this->bic = $this->getConfigValue('bankAccountBIC');
    $this->owner = $this->getConfigValue('bankAccountOwner');
    $this->ci = $this->getConfigValue('bankAccountCreditorIdentifier');
  }

  /**
   * @inheritdoc
   */
  public function columnHeadings():array
  {
    return [
      'localBic',
      'localIban',
      'remoteBic',
      'remoteIban',
      'date',
      'value/value',
      'value/currency',
      'localName',
      'remoteName',
      'creditorSchemeId',
      'mandateId',
      'mandateDate/dateString',
      'mandateDebitorName',
      'sequenceType',
      'purpose[0]',
      'purpose[1]',
      'purpose[2]',
      'purpose[3]',
    ];
  }

  /**
   * Export the given data-set into another format, filling the
   * missing field like CI etc.
   *
   * @param SepaDebitNoteDTO $debitNoteData
   *
   * @param DateTime $executionDate
   */
  public function exportRow(SepaDebitNoteDTO $debitNoteData, DateTime $executionDate):array
  {
    $executionDate = $executionDate->format('Y/m/d');

    $result = [
      'localBic' => $this->bic,
      'localIBan' => $this->iban,
      'remoteBic' => $debitNoteData['bic'],
      'remoteIban' => $debitNoteData['iban'],
      'date' => $executionDate,
      'value/value' => $debitNoteData['amount'],
      'value/currency' => 'EUR',
      'localName' => $this->owner,
      'remoteName' => $debitNoteData['bankAccountOwner'],
      'creditorSchemeId' => $this->ci,
      'mandateId' => $debitNoteData['mandateReference'],
      'mandateDate/dateString' => $debitNoteData['mandateDate']->format('Ymd'),
      'mandateDebitorName' => $debitNoteData['mandateDebitorName'],
      'sequenceType' => $debitNoteData['mandateSequenceType'],
      'purpose[0]' => $debitNoteData['purpose'][0],
      'purpose[1]' => $debitNoteData['purpose'][1],
      'purpose[2]' => $debitNoteData['purpose'][2],
      'purpose[3]' => $debitNoteData['purpose'][3]
    ];

    return $result;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
