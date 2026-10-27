<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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
use DOMDocument;
use DOMElement;
use RuntimeException;

use PHP_IBAN\IBAN as PHP_IBAN;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Settings\ConfigConstants;

/**
 * Generate SEPA-XML (ISO 20022) data-sets for submission to the bank:
 * pain.001.001.03 for credit transfers and pain.008.001.02 for direct debits.
 */
class SepaXmlBulkTransactionExporter implements IBulkTransactionExporter
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  const IDENTIFIER = 'sepa-xml';

  const CURRENCY = 'EUR';

  const TRANSFER_NAMESPACE = 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.03';
  const DEBIT_NAMESPACE = 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.02';

  const SEQUENCE_ONCE = 'OOFF';
  const SEQUENCE_FOLLOWING = 'RCUR';

  const NAME_LENGTH = 70;
  const PURPOSE_LENGTH = 140;
  const MESSAGE_ID_LENGTH = 35;

  private string $iban;

  private ?string $bic;

  private string $owner;

  private string $ci;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected ConfigService $configService,
    private SepaTransliterator $sepaTransliterator,
  ) {
    $this->l = $this->l10n();

    $iban = new PHP_IBAN($this->getConfigValue(ConfigConstants::BANK_ACCOUNT_IBAN));
    $this->iban = $iban->MachineFormat();
    $this->bic = $this->getConfigValue(ConfigConstants::BANK_ACCOUNT_BIC);
    $this->owner = $this->getConfigValue(ConfigConstants::BANK_ACCOUNT_OWNER);
    $this->ci = $this->getConfigValue(ConfigConstants::BANK_ACCOUNT_CREDITOR_IDENTIFIER);
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
    return 'application/xml';
  }

  /** {@inheritdoc} */
  public function fileExtension(Entities\SepaBulkTransaction $transaction):string
  {
    return 'xml';
  }

  /** {@inheritdoc} */
  public function fileData(Entities\SepaBulkTransaction $transaction):string
  {
    if ($transaction instanceof Entities\SepaDebitNote) {
      $isDebit = true;
    } elseif ($transaction instanceof Entities\SepaBankTransfer) {
      $isDebit = false;
    } else {
      throw new RuntimeException(
        $this->l->t('Unsupported bulk-transaction class: "%s".', get_class($transaction)));
    }

    $namespace = $isDebit ? self::DEBIT_NAMESPACE : self::TRANSFER_NAMESPACE;
    $rootName = $isDebit ? 'CstmrDrctDbtInitn' : 'CstmrCdtTrfInitn';

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;

    $document = $dom->createElementNS($namespace, 'Document');
    $dom->appendChild($document);
    $root = $this->append($dom, $document, $rootName);

    $now = new DateTimeImmutable();
    $payments = $transaction->getPayments()->toArray();
    $messageId = $this->messageId($now);

    // group header
    $header = $this->append($dom, $root, 'GrpHdr');
    $this->append($dom, $header, 'MsgId', $messageId);
    $this->append($dom, $header, 'CreDtTm', $now->format('Y-m-d\TH:i:s'));
    $this->append($dom, $header, 'NbOfTxs', (string)count($payments));
    $this->append($dom, $header, 'CtrlSum', $this->decimal($this->controlSum($payments)));
    $initiator = $this->append($dom, $header, 'InitgPty');
    $this->append($dom, $initiator, 'Nm', $this->text($this->owner, self::NAME_LENGTH));

    $dueDate = ($transaction->getDueDate() ?? $now)->format('Y-m-d');

    if ($isDebit) {
      // one payment-information block per sequence type
      $groups = [];
      foreach ($payments as $payment) {
        $nonRecurring = $payment->getSepaDebitMandate()->getNonRecurring();
        $sequence = $nonRecurring ? self::SEQUENCE_ONCE : self::SEQUENCE_FOLLOWING;
        $groups[$sequence][] = $payment;
      }
      foreach ($groups as $sequence => $groupPayments) {
        $this->appendDebitBlock($dom, $root, $messageId . '-' . $sequence, $sequence, $dueDate, $groupPayments);
      }
    } elseif (!empty($payments)) {
      $this->appendTransferBlock($dom, $root, $messageId . '-1', $dueDate, $payments);
    }

    return $dom->saveXML();
  }

  /**
   * @param DOMDocument $dom
   *
   * @param DOMElement $root
   *
   * @param string $paymentInfoId
   *
   * @param string $dueDate
   *
   * @param array $payments
   *
   * @return void
   */
  private function appendTransferBlock(
    DOMDocument $dom,
    DOMElement $root,
    string $paymentInfoId,
    string $dueDate,
    array $payments,
  ):void {
    $info = $this->append($dom, $root, 'PmtInf');
    $this->append($dom, $info, 'PmtInfId', $paymentInfoId);
    $this->append($dom, $info, 'PmtMtd', 'TRF');
    $this->append($dom, $info, 'BtchBookg', 'true');
    $this->append($dom, $info, 'NbOfTxs', (string)count($payments));
    $this->append($dom, $info, 'CtrlSum', $this->decimal($this->controlSum($payments)));
    $this->appendServiceLevel($dom, $info);
    $this->append($dom, $info, 'ReqdExctnDt', $dueDate);
    $debtor = $this->append($dom, $info, 'Dbtr');
    $this->append($dom, $debtor, 'Nm', $this->text($this->owner, self::NAME_LENGTH));
    $this->appendAccount($dom, $info, 'DbtrAcct', $this->iban);
    $this->appendAgent($dom, $info, 'DbtrAgt', $this->bic);
    $this->append($dom, $info, 'ChrgBr', 'SLEV');

    $index = 0;
    /** @var Entities\CompositePayment $payment */
    foreach ($payments as $payment) {
      $account = $payment->getSepaBankAccount();
      $tx = $this->append($dom, $info, 'CdtTrfTxInf');
      $id = $this->append($dom, $tx, 'PmtId');
      $this->append($dom, $id, 'EndToEndId', $this->endToEndId($payment, ++$index));
      $amount = $this->append($dom, $tx, 'Amt');
      $this->append($dom, $amount, 'InstdAmt', $this->decimal($payment->getAmount()->abs()->toDecimal(2)))
        ->setAttribute('Ccy', self::CURRENCY);
      $this->appendAgent($dom, $tx, 'CdtrAgt', $account->getBic());
      $creditor = $this->append($dom, $tx, 'Cdtr');
      $this->append($dom, $creditor, 'Nm', $this->text($account->getBankAccountOwner(), self::NAME_LENGTH));
      $this->appendAccount($dom, $tx, 'CdtrAcct', $account->getIban());
      $this->appendPurpose($dom, $tx, $payment);
    }
  }

  /**
   * @param DOMDocument $dom
   *
   * @param DOMElement $root
   *
   * @param string $paymentInfoId
   *
   * @param string $sequence
   *
   * @param string $dueDate
   *
   * @param array $payments
   *
   * @return void
   */
  private function appendDebitBlock(
    DOMDocument $dom,
    DOMElement $root,
    string $paymentInfoId,
    string $sequence,
    string $dueDate,
    array $payments,
  ):void {
    $info = $this->append($dom, $root, 'PmtInf');
    $this->append($dom, $info, 'PmtInfId', $paymentInfoId);
    $this->append($dom, $info, 'PmtMtd', 'DD');
    $this->append($dom, $info, 'BtchBookg', 'true');
    $this->append($dom, $info, 'NbOfTxs', (string)count($payments));
    $this->append($dom, $info, 'CtrlSum', $this->decimal($this->controlSum($payments)));
    $typeInfo = $this->append($dom, $info, 'PmtTpInf');
    $level = $this->append($dom, $typeInfo, 'SvcLvl');
    $this->append($dom, $level, 'Cd', 'SEPA');
    $instrument = $this->append($dom, $typeInfo, 'LclInstrm');
    $this->append($dom, $instrument, 'Cd', 'CORE');
    $this->append($dom, $typeInfo, 'SeqTp', $sequence);
    $this->append($dom, $info, 'ReqdColltnDt', $dueDate);
    $creditor = $this->append($dom, $info, 'Cdtr');
    $this->append($dom, $creditor, 'Nm', $this->text($this->owner, self::NAME_LENGTH));
    $this->appendAccount($dom, $info, 'CdtrAcct', $this->iban);
    $this->appendAgent($dom, $info, 'CdtrAgt', $this->bic);
    $this->append($dom, $info, 'ChrgBr', 'SLEV');

    $schemeId = $this->append($dom, $info, 'CdtrSchmeId');
    $this->appendCreditorIdentifier($dom, $schemeId);

    $index = 0;
    /** @var Entities\CompositePayment $payment */
    foreach ($payments as $payment) {
      $account = $payment->getSepaBankAccount();
      $mandate = $payment->getSepaDebitMandate();
      $tx = $this->append($dom, $info, 'DrctDbtTxInf');
      $id = $this->append($dom, $tx, 'PmtId');
      $this->append($dom, $id, 'EndToEndId', $this->endToEndId($payment, ++$index));
      $this->append($dom, $tx, 'InstdAmt', $this->decimal($payment->getAmount()->abs()->toDecimal(2)))
        ->setAttribute('Ccy', self::CURRENCY);
      $mandateInfo = $this->append($dom, $this->append($dom, $tx, 'DrctDbtTx'), 'MndtRltdInf');
      $this->append($dom, $mandateInfo, 'MndtId', $this->text($mandate->getMandateReference(), 35));
      $this->append($dom, $mandateInfo, 'DtOfSgntr', $mandate->getMandateDate()->format('Y-m-d'));
      $this->appendAgent($dom, $tx, 'DbtrAgt', $account->getBic());
      $debtor = $this->append($dom, $tx, 'Dbtr');
      $this->append($dom, $debtor, 'Nm', $this->text($account->getBankAccountOwner(), self::NAME_LENGTH));
      $this->appendAccount($dom, $tx, 'DbtrAcct', $account->getIban());
      $this->appendPurpose($dom, $tx, $payment);
    }
  }

  /**
   * @param DOMDocument $dom
   *
   * @param DOMElement $schemeId
   *
   * @return void
   */
  private function appendCreditorIdentifier(DOMDocument $dom, DOMElement $schemeId):void
  {
    $other = $this->append($dom, $this->append($dom, $this->append($dom, $schemeId, 'Id'), 'PrvtId'), 'Othr');
    $this->append($dom, $other, 'Id', $this->ci);
    $this->append($dom, $this->append($dom, $other, 'SchmeNm'), 'Prtry', 'SEPA');
  }

  /**
   * @param DOMDocument $dom
   *
   * @param DOMElement $parent
   *
   * @return void
   */
  private function appendServiceLevel(DOMDocument $dom, DOMElement $parent):void
  {
    $typeInfo = $this->append($dom, $parent, 'PmtTpInf');
    $this->append($dom, $this->append($dom, $typeInfo, 'SvcLvl'), 'Cd', 'SEPA');
  }

  /**
   * @param DOMDocument $dom
   *
   * @param DOMElement $parent
   *
   * @param string $name
   *
   * @param string $iban
   *
   * @return void
   */
  private function appendAccount(DOMDocument $dom, DOMElement $parent, string $name, string $iban):void
  {
    $iban = (new PHP_IBAN($iban))->MachineFormat();
    $this->append($dom, $this->append($dom, $this->append($dom, $parent, $name), 'Id'), 'IBAN', $iban);
  }

  /**
   * Agents without BIC are allowed in the SEPA area (NOTPROVIDED).
   *
   * @param DOMDocument $dom
   *
   * @param DOMElement $parent
   *
   * @param string $name
   *
   * @param null|string $bic
   *
   * @return void
   */
  private function appendAgent(DOMDocument $dom, DOMElement $parent, string $name, ?string $bic):void
  {
    $institution = $this->append($dom, $this->append($dom, $parent, $name), 'FinInstnId');
    if (!empty($bic)) {
      $this->append($dom, $institution, 'BIC', strtoupper(preg_replace('/\s+/', '', $bic)));
    } else {
      $this->append($dom, $this->append($dom, $institution, 'Othr'), 'Id', 'NOTPROVIDED');
    }
  }

  /**
   * @param DOMDocument $dom
   *
   * @param DOMElement $tx
   *
   * @param Entities\CompositePayment $payment
   *
   * @return void
   */
  private function appendPurpose(DOMDocument $dom, DOMElement $tx, Entities\CompositePayment $payment):void
  {
    $purpose = $this->text((string)$payment->getSubject(), self::PURPOSE_LENGTH);
    if ($purpose !== '') {
      $this->append($dom, $this->append($dom, $tx, 'RmtInf'), 'Ustrd', $purpose);
    }
  }

  /**
   * @param array $payments
   *
   * @return string
   */
  private function controlSum(array $payments):string
  {
    $cents = 0;
    /** @var Entities\CompositePayment $payment */
    foreach ($payments as $payment) {
      $cents += (int)round((float)$payment->getAmount()->abs()->toDecimal(2) * 100);
    }
    return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
  }

  /**
   * @param string $amount
   *
   * @return string
   */
  private function decimal(string $amount):string
  {
    return number_format((float)$amount, 2, '.', '');
  }

  /**
   * @param DateTimeImmutable $now
   *
   * @return string
   */
  private function messageId(DateTimeImmutable $now):string
  {
    $id = 'CAFEVDB' . $now->format('YmdHis') . bin2hex(random_bytes(2));
    return substr($id, 0, self::MESSAGE_ID_LENGTH - 8);
  }

  /**
   * @param Entities\CompositePayment $payment
   *
   * @param int $index
   *
   * @return string
   */
  private function endToEndId(Entities\CompositePayment $payment, int $index):string
  {
    return 'NOTPROVIDED';
  }

  /**
   * Transliterate to the SEPA character set, strip control characters
   * and truncate.
   *
   * @param string $text
   *
   * @param int $length
   *
   * @return string
   */
  private function text(string $text, int $length):string
  {
    $text = $this->sepaTransliterator->translit($text);
    $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text);
    return trim(mb_substr($text, 0, $length));
  }

  /**
   * @param DOMDocument $dom
   *
   * @param DOMElement $parent
   *
   * @param string $name
   *
   * @param null|string $value
   *
   * @return DOMElement
   */
  private function append(DOMDocument $dom, DOMElement $parent, string $name, ?string $value = null):DOMElement
  {
    $element = $dom->createElementNS($parent->namespaceURI, $name);
    if ($value !== null) {
      $element->appendChild($dom->createTextNode($value));
    }
    $parent->appendChild($element);
    return $element;
  }
}
