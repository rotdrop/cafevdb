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

use UnexpectedValueException;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\IL10N;

use OCA\CAFEVDB\Common\CamelCaseString;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Service;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\EntityGeneratorTrait;
use OCA\CAFEVDB\Tests\Unit\Service\WordSplitterServiceTrait;

/** Test the CSV export for AqBanking. */
#[Attributes\Group('nextcloud')]
#[Attributes\CoversClass(Service\Finance\AqBankingBulkTransactionExporter::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBankTransfer::class)]
#[Attributes\CoversMethod(Service\Finance\AqBankingBulkTransactionExporter::class, 'fileData')]
#[Attributes\CoversMethod(Service\Finance\AqBankingBulkTransactionExporter::class, 'fileExtension')]
#[Attributes\CoversMethod(Service\Finance\AqBankingBulkTransactionExporter::class, 'identifier')]
#[Attributes\CoversMethod(Service\Finance\AqBankingBulkTransactionExporter::class, 'mimeType')]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Common\AbstractDecimalRational::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Common\RationalNumber::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\CamelCaseString::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\WordSplitterService::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBulkTransaction::class)]
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
class AqBankingBulkTransactionExporterTest extends TestCase
{
  use EntityGeneratorTrait;
  use WordSplitterServiceTrait;

  private const CONFIG_MOCK = [
    ConfigConstants::BANK_ACCOUNT_OWNER => 'Örchester e.V.',
    ConfigConstants::BANK_ACCOUNT_IBAN => 'DE07123412341234123412',
    ConfigConstants::BANK_ACCOUNT_CREDITOR_IDENTIFIER => 'DEPPZZZ0NNNNNNNNNN',
    ConfigConstants::BANK_ACCOUNT_BIC => 'MARKDEFFXXX',
  ];
  private Service\Finance\AqBankingBulkTransactionExporter $exporter;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $this->generateProjectParticipant();

    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);
    $this->registerWordSplitterService();

    $configService = $this->mockProvider->getConfigService();

    foreach (self::CONFIG_MOCK as $key => $value) {
      $configService->setConfigValue($key, $value);
    }

    $appContainer = $this->mockProvider->getAppContainer();

    $this->exporter = new Service\Finance\AqBankingBulkTransactionExporter(
      camelCaseString: $appContainer->get(CamelCaseString::class),
      configService: $configService,
      sepaTransliterator: $appContainer->get(Service\Finance\SepaTransliterator::class),
    );
  }

  /**
   * Test the uninteresting stuff.
   *
   * @return void
   */
  public function testMiscinfo(): void
  {
    /** @var Entities\SepaBankTransfer $transfer */
    $transfer = $this->generateSepaBankTransfer();
    $this->assertEquals($this->exporter->identifier(), Service\Finance\AqBankingBulkTransactionExporter::IDENTIFIER);
    $this->assertEquals($this->exporter->mimeType($transfer), 'text/csv');
    $this->assertEquals($this->exporter->fileExtension($transfer), 'csv');
  }

  /**
   * Test the actual generation of the file-data.
   *
   * @return void
   */
  public function testFileData(): void
  {
    /** @var Entities\SepaBankTransfer $transfer */
    $transfer = $this->generateSepaBankTransfer();

    $data = $this->exporter->fileData($transfer);
    $expectedData =<<< EOF
localBic;localIban;localName;remoteBic;remoteIban;remoteName;executionDate;value/value;value/currency;purpose[0];purpose[1];purpose[2];purpose[3]
MARKDEFFXXX;DE07123412341234123412;"Örchester e.V.";BYLADEM1001;DE02120300000000202051;"Inhaber:in, Konto";2099/01/01;12.23;EUR;"TestProject / Forderungen: RechnungsNummer RE25/01354 AktenZeichen 25-01258 ümläüteßWeitereÜmläüteß";;;
EOF;
    $this->assertEquals($expectedData, $data);
  }
}
