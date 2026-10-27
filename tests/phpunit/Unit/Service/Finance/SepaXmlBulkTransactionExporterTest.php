<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
namespace OCA\CAFEVDB\Tests\Unit\Service\Finance;

use DOMDocument;
use DOMXPath;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Service;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\EntityGeneratorTrait;

/** Test the SEPA-XML export, including validation against the ISO 20022 schemas. */
#[Attributes\CoversClass(Service\Finance\SepaXmlBulkTransactionExporter::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Common\AbstractDecimalRational::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Common\RationalNumber::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\CamelCaseString::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\TimeFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Transliterator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\CompositePayment::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DatabaseStorageDirEntry::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DatabaseStorageFile::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianEmailAddress::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipant::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipantField::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipantFieldDataOption::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipantFieldDatum::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectPayment::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBankAccount::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBankTransfer::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBulkTransaction::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaDebitMandate::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaDebitNote::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\TranslationNotFoundListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\AuthorizationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Finance\SepaTransliterator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\WordSplitterService::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\CreatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FactoryTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\SoftDeleteableEntity::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\TranslatableTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UuidTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\EntityManagerTrait::class)]
class SepaXmlBulkTransactionExporterTest extends TestCase
{
  use EntityGeneratorTrait;

  private const FIXTURE_DIR = __DIR__ . '/../../../fixtures/sepa-xml';
  private const CONFIG_MOCK = [
    ConfigConstants::BANK_ACCOUNT_OWNER => 'Örchester e.V.',
    ConfigConstants::BANK_ACCOUNT_IBAN => 'DE07123412341234123412',
    ConfigConstants::BANK_ACCOUNT_CREDITOR_IDENTIFIER => 'DE98ZZZ09999999999',
    ConfigConstants::BANK_ACCOUNT_BIC => 'MARKDEFFXXX',
  ];

  private Service\Finance\SepaXmlBulkTransactionExporter $exporter;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $this->generateProjectParticipant();

    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $configService = $this->mockProvider->getConfigService();
    foreach (self::CONFIG_MOCK as $key => $value) {
      $configService->setConfigValue($key, $value);
    }

    $appContainer = $this->mockProvider->getAppContainer();

    $this->exporter = new Service\Finance\SepaXmlBulkTransactionExporter(
      configService: $configService,
      sepaTransliterator: $appContainer->get(Service\Finance\SepaTransliterator::class),
    );
  }

  /**
   * @return void
   */
  public function testMiscInfo(): void
  {
    $transfer = $this->generateSepaBankTransfer();
    $this->assertSame(Service\Finance\SepaXmlBulkTransactionExporter::IDENTIFIER, $this->exporter->identifier());
    $this->assertSame('application/xml', $this->exporter->mimeType($transfer));
    $this->assertSame('xml', $this->exporter->fileExtension($transfer));
  }

  /**
   * @return void
   */
  public function testTransferValidatesAgainstSchema(): void
  {
    $dom = $this->loadXml($this->exporter->fileData($this->generateSepaBankTransfer()));
    $this->assertSchemaValid($dom, 'pain.001.001.03.xsd');
  }

  /**
   * @return void
   */
  public function testTransferContent(): void
  {
    $transfer = $this->generateSepaBankTransfer();
    $payment = $transfer->getPayments()->first();
    $account = $payment->getSepaBankAccount();
    $amount = $payment->getAmount()->abs()->toDecimal(2);

    $dom = $this->loadXml($this->exporter->fileData($transfer));
    $xpath = $this->xpath($dom, Service\Finance\SepaXmlBulkTransactionExporter::TRANSFER_NAMESPACE);

    $this->assertSame('1', $xpath->evaluate('string(//s:GrpHdr/s:NbOfTxs)'));
    $this->assertSame($amount, $xpath->evaluate('string(//s:GrpHdr/s:CtrlSum)'));
    $this->assertStringEndsWith('rchester e.V.', $xpath->evaluate('string(//s:GrpHdr/s:InitgPty/s:Nm)'));
    $this->assertSame('TRF', $xpath->evaluate('string(//s:PmtInf/s:PmtMtd)'));
    $this->assertSame('2099-01-01', $xpath->evaluate('string(//s:PmtInf/s:ReqdExctnDt)'));
    $this->assertSame('DE07123412341234123412', $xpath->evaluate('string(//s:PmtInf/s:DbtrAcct/s:Id/s:IBAN)'));
    $this->assertSame('MARKDEFFXXX', $xpath->evaluate('string(//s:PmtInf/s:DbtrAgt/s:FinInstnId/s:BIC)'));
    $this->assertSame($account->getIban(), $xpath->evaluate('string(//s:CdtTrfTxInf/s:CdtrAcct/s:Id/s:IBAN)'));
    $this->assertSame($amount, $xpath->evaluate('string(//s:CdtTrfTxInf/s:Amt/s:InstdAmt)'));
    $this->assertSame('EUR', $xpath->evaluate('string(//s:CdtTrfTxInf/s:Amt/s:InstdAmt/@Ccy)'));
    $this->assertSame(1.0, $xpath->evaluate('count(//s:CdtTrfTxInf)'));
  }

  /**
   * @return void
   */
  public function testDebitNoteValidatesAgainstSchema(): void
  {
    $dom = $this->loadXml($this->exporter->fileData($this->createSepaDebitNote()));
    $this->assertSchemaValid($dom, 'pain.008.001.02.xsd');
  }

  /**
   * @return void
   */
  public function testDebitNoteContent(): void
  {
    $debitNote = $this->createSepaDebitNote();
    $amount = $debitNote->getPayments()->first()->getAmount()->abs()->toDecimal(2);

    $dom = $this->loadXml($this->exporter->fileData($debitNote));
    $xpath = $this->xpath($dom, Service\Finance\SepaXmlBulkTransactionExporter::DEBIT_NAMESPACE);

    $this->assertSame('DD', $xpath->evaluate('string(//s:PmtInf/s:PmtMtd)'));
    $this->assertSame('OOFF', $xpath->evaluate('string(//s:PmtInf/s:PmtTpInf/s:SeqTp)'));
    $this->assertSame('2099-01-01', $xpath->evaluate('string(//s:PmtInf/s:ReqdColltnDt)'));
    $this->assertSame('DE98ZZZ09999999999', $xpath->evaluate('string(//s:PmtInf/s:CdtrSchmeId//s:Othr/s:Id)'));
    $this->assertSame('MANDATE-0001', $xpath->evaluate('string(//s:DrctDbtTxInf//s:MndtId)'));
    $this->assertSame('2024-02-29', $xpath->evaluate('string(//s:DrctDbtTxInf//s:DtOfSgntr)'));
    $this->assertSame($amount, $xpath->evaluate('string(//s:DrctDbtTxInf/s:InstdAmt)'));
  }

  /**
   * @return void
   */
  public function testDebitNoteSequenceTypesAreSplit(): void
  {
    $debitNote = $this->createSepaDebitNote(nonRecurring: false);

    $dom = $this->loadXml($this->exporter->fileData($debitNote));
    $xpath = $this->xpath($dom, Service\Finance\SepaXmlBulkTransactionExporter::DEBIT_NAMESPACE);

    $this->assertSame('RCUR', $xpath->evaluate('string(//s:PmtInf/s:PmtTpInf/s:SeqTp)'));
    $this->assertSame(1.0, $xpath->evaluate('count(//s:PmtInf)'));
    $this->assertSchemaValid($dom, 'pain.008.001.02.xsd');
  }

  /**
   * @param bool $nonRecurring
   *
   * @return Entities\SepaDebitNote
   */
  private function createSepaDebitNote(bool $nonRecurring = true): Entities\SepaDebitNote
  {
    $bankAccount = $this->generateSepaBankAccount();
    $payment = $this->generateCompositePayment();
    $payment->setSepaBankAccount($bankAccount);

    $mandate = new Entities\SepaDebitMandate()
      ->setMusician($this->musician)
      ->setSepaBankAccount($bankAccount)
      ->setSequence(1)
      ->setMandateReference('MANDATE-0001')
      ->setMandateDate('2024-02-29')
      ->setNonRecurring($nonRecurring);
    $payment->setSepaDebitMandate($mandate);

    $debitNote = new Entities\SepaDebitNote()
      ->setId(self::FAKED_ENTITY_ID)
      ->setDueDate('2099-01-01');
    $debitNote->addPayment($payment);

    return $debitNote;
  }

  /**
   * @param string $data
   *
   * @return DOMDocument
   */
  private function loadXml(string $data): DOMDocument
  {
    $dom = new DOMDocument();
    $this->assertTrue($dom->loadXML($data));
    return $dom;
  }

  /**
   * @param DOMDocument $dom
   *
   * @param string $namespace
   *
   * @return DOMXPath
   */
  private function xpath(DOMDocument $dom, string $namespace): DOMXPath
  {
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('s', $namespace);
    return $xpath;
  }

  /**
   * @param DOMDocument $dom
   *
   * @param string $schema
   *
   * @return void
   */
  private function assertSchemaValid(DOMDocument $dom, string $schema): void
  {
    $useErrors = libxml_use_internal_errors(true);
    $valid = $dom->schemaValidate(self::FIXTURE_DIR . '/' . $schema);
    $messages = array_map(fn($e) => trim($e->message), libxml_get_errors());
    libxml_clear_errors();
    libxml_use_internal_errors($useErrors);
    $this->assertTrue($valid, implode("\n", $messages));
  }
}
