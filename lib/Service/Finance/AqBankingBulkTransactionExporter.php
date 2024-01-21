<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022, 2024 Claus-Justus Heine
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

use RuntimeException;
use DateTimeImmutable as DateTime;
use PHP_IBAN\IBAN as PHP_IBAN;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Common\Util;

/** Generate AqBanking data-sets for submission to the bank. */
class AqBankingBulkTransactionExporter implements IBulkTransactionExporter
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  const IDENTIFIER = 'aqbanking';

  const CSV_DELIMITER = ';';
  const PURPOSE_LINE_LENGTH = FinanceService::SEPA_PURPOSE_LENGTH / 4;
  const CURRENCY = 'EUR';
  const MANDATE_DATE_FORMAT = 'Y/m/d';
  const DUE_DATE_FORMAT = 'Y/m/d';
  const NON_RECURRING = [
    true => 'once',
    false => 'following',
  ];

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected ConfigService $configService,
    private FinanceService $financeService,
  ) {
    $this->l = $this->l10n();

    $iban = new PHP_IBAN($this->getConfigValue('bankAccountIBAN'));
    $this->iban = $iban->MachineFormat();
    $this->bic = $this->getConfigValue('bankAccountBIC');
    $this->owner = $this->getConfigValue('bankAccountOwner');
    $this->ci = $this->getConfigValue('bankAccountCreditorIdentifier');
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public static function identifier():string
  {
    return self::IDENTIFIER;
  }

  /** {@inheritdoc} */
  public function mimeType(Entities\SepaBulkTransaction $transaction):string
  {
    return 'text/csv';
  }

  /** {@inheritdoc} */
  public function fileExtension(Entities\SepaBulkTransaction $transaction):string
  {
    return 'csv';
  }

  /** {@inheritdoc} */
  public function fileData(Entities\SepaBulkTransaction $transaction):string
  {
    if ($transaction instanceof Entities\SepaDebitNote) {
      return $this->debitNoteFileData($transaction);
    } elseif ($transaction instanceof Entities\SepaBankTransfer) {
      return $this->bankTransferFileData($transaction);
    } else {
      throw new RuntimeException(
        $this->l->t('Unsupported bulk-transaction class: "%s".', get_class($transaction)));
    }
  }

  /**
   * @param Entities\SepaBulkTransaction $transaction
   *
   * @return string
   */
  private function bankTransferFileData(Entities\SepaBulkTransaction $transaction):string
  {
    $transactionTable = [];
    $transactionTable[] = $this->bankTransferColumnHeadings();

    /** @var Entities\CompositePayment $compositePayment */
    foreach ($transaction->getPayments() as $compositePayment) {

      $purpose = $this->generatePurpose($compositePayment->getSubject());

      $transactionTable[] = [
        'localBic' => $this->bic,
        'localIBan' => $this->iban,
        'localName' => $this->owner,

        'remoteBic' => $compositePayment->getSepaBankAccount()->getBic(),
        'remoteIban' => $compositePayment->getSepaBankAccount()->getIban(),
        'remoteName' => $compositePayment->getSepaBankAccount()->getBankAccountOwner(),

        'date' => $transaction->getDueDate()->format(self::DUE_DATE_FORMAT),
        'value/value' => abs($compositePayment->getAmount()),
        'value/currency' => self::CURRENCY,

        'purpose[0]' => $purpose[0],
        'purpose[1]' => $purpose[1],
        'purpose[2]' => $purpose[2],
        'purpose[3]' => $purpose[3],
      ];
    }

    return implode("\n", array_map(
      function($value) {
        return implode(self::CSV_DELIMITER, $value);
      },
      $transactionTable));
  }

  /**
   * @return array
   */
  private function bankTransferColumnHeadings():array
  {
    return [
      1 => 'localBic', // no longer needed AQB 6.3.0
      2 => 'localIban',
      3 => 'localName',

      4 => 'remoteBic', // not needed AQB 6.3.0
      5 => 'remoteIban',
      6 => 'remoteName', // max 70

      7 => 'executionDate', // NOT date, leave free for non-dated transfers
      8 => 'value/value',
      9 => 'value/currency',

     10 => 'purpose[0]', // max 35
     11 => 'purpose[1]',
     12 => 'purpose[2]',
     13 => 'purpose[3]',
    ];
  }

  /**
   * @param Entities\SepaBulkTransaction $transaction
   *
   * @return string
   */
  private function debitNoteFileData(Entities\SepaBulkTransaction $transaction):string
  {
    $transactionTable = [];
    $transactionTable[] = $this->debitNoteColumnHeadings();

    /** @var Entities\CompositePayment $compositePayment */
    foreach ($transaction->getPayments() as $compositePayment) {

      $purpose = $this->generatePurpose($compositePayment->getSubject());

      $transactionTable[] = [
        'localBic' => $this->bic,
        'localIBan' => $this->iban,
        'remoteBic' => $compositePayment->getSepaBankAccount()->getBic(),
        'remoteIban' => $compositePayment->getSepaBankAccount()->getIban(),
        'date' => $transaction->getDueDate()->format(self::DUE_DATE_FORMAT),
        'value/value' => abs($compositePayment->getAmount()),
        'value/currency' => self::CURRENCY,
        'localName' => $this->owner,
        'remoteName' => $compositePayment->getSepaBankAccount()->getBankAccountOwner(),
        'creditorSchemeId' => $this->ci,
        'mandateId' => $compositePayment->getSepaDebitMandate()->getMandateReference(),
        'mandateDate/dateString' => $compositePayment->getSepaDebitMandate()->getMandateDate()->format(self::MANDATE_DATE_FORMAT),
        'mandateDebitorName' => $compositePayment->getSepaDebitMandate()->getMusician()->getPublicName(),
        'sequence' => self::NON_RECURRING[$compositePayment->getSepaDebitMandate()->getNonRecurring()],
        'purpose[0]' => $purpose[0],
        'purpose[1]' => $purpose[1],
        'purpose[2]' => $purpose[2],
        'purpose[3]' => $purpose[3],
      ];
    }

    return implode("\n", array_map(
      function($value) {
        return implode(self::CSV_DELIMITER, $value);
      },
      $transactionTable));
  }

  /**
   * @return array
   */
  private function debitNoteColumnHeadings():array
  {
    return [
      1 => 'localBic',
      2 => 'localIban',
      3 => 'remoteBic',
      4 => 'remoteIban',
      5 => 'date',
      6 => 'value/value',
      7 => 'value/currency',
      8 => 'localName',
      9 => 'remoteName',
      10 => 'creditorSchemeId',
      11 => 'mandateId',
      12 => 'mandateDate/dateString',
      13 => 'mandateDebitorName',
      14 => 'sequence',
      15 => 'purpose[0]',
      16 => 'purpose[1]',
      17 => 'purpose[2]',
      18 => 'purpose[3]',
    ];
  }

  /**
   * @param string $subject
   *
   * @return array
   */
  private function generatePurpose(string $subject):array
  {
    if (strlen($subject) > FinanceService::SEPA_PURPOSE_LENGTH) {
      $subject = Util::removeSpaces($subject);
    }
    if (strlen($subject) > FinanceService::SEPA_PURPOSE_LENGTH) {
      $subject = Util::shortenCamelCaseString($subject, FinanceService::SEPA_PURPOSE_LENGTH, 4);
    }

    $purpose = [];
    for ($i = 0; $i < FinanceService::SEPA_PURPOSE_LENGTH; $i += self::PURPOSE_LINE_LENGTH) {
      $purpose[] = '"' . substr($subject, $i, self::PURPOSE_LINE_LENGTH) . '"';
    }
    return $purpose;
  }
}
