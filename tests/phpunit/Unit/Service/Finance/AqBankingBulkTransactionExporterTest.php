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

use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Service;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\EntityGeneratorTrait;

/** Test the CSV export for AqBanking. */
#[Attributes\CoversClass(Service\Finance\AqBankingBulkTransactionExporter::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBankTransfer::class)]
#[Attributes\CoversMethod(Service\Finance\AqBankingBulkTransactionExporter::class, 'fileData')]
#[Attributes\CoversMethod(Service\Finance\AqBankingBulkTransactionExporter::class, 'fileExtension')]
#[Attributes\CoversMethod(Service\Finance\AqBankingBulkTransactionExporter::class, 'identifier')]
#[Attributes\CoversMethod(Service\Finance\AqBankingBulkTransactionExporter::class, 'mimeType')]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Common\AbstractDecimalRational::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Common\RationalNumber::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\TimeFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\CompositePayment::class)]
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

    $app = \OCP\Server::get(\OCA\CAFEVDB\AppInfo\Application::class);
    // $appName = $app->get('AppName');
    $l = $app->get(IL10N::class);

    $configService = $this->getMockBuilder(Service\ConfigService::class)
      ->disableOriginalConstructor()
      ->getMock();

    $configService->method('getL10n')->willReturn($l);
    $configService->method('getConfigValue')->willReturnCallback(
      function(string $configKey, mixed $default): string {
        if (empty(self::CONFIG_MOCK[$configKey])) {
          throw new UnexpectedValueException('Unexpected config-mock key: ' . $configKey);
        }
        return self::CONFIG_MOCK[$configKey];
      },
    );
    $configService->expects($this->atLeastOnce())->method('getConfigValue');

    $this->exporter = new Service\Finance\AqBankingBulkTransactionExporter(
      $configService,
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
MARKDEFFXXX;DE07123412341234123412;"Örchester e.V.";BYLADEM1001;DE02120300000000202051;"Inhaber:in, Konto";2099/01/01;12.23;EUR;"TestProject / Forderungen: ReNr RE25/01354 Aktenzeichen 25-01258 Ümläüteß";;;
EOF;
    $this->assertEquals($expectedData, $data);
  }

  /** @return void */
  public function testPurposeGeneration(): void
  {
    // $this->expectNotToPerformAssertions(); assertions already in setup

    $fieldData =[
      [
        'id' => '481',
        'project_id' => '156',
        'display_order' => '90',
        'name' => 'Unkostenerstattungen',
        'tooltip' => null,
        'tab' => null,
        'encrypted' => null,
        'default_value' => null,
        'multiplicity' => 'recurring',
        'data_type' => 'liabilities',
        'due_date' => '2026-09-13 00:00:00.000000',
        'deposit_due_date' => null,
        'deleted' => null,
        'participant_access' => 'none',
        'participation_context' => 'unrestricted',
        'balancing_account' => null,
      ],
    ];

    $fieldOptionsData = [
      [
        'field_id' => '481',
        'key' => '1b62be48-ac4a-11f1-8f7a-09053123d944',
        'label' => NULL,
        'data' => NULL,
        'deposit' => NULL,
        'limit' => NULL,
        'tooltip' => NULL,
        'deleted' => NULL,
        'created' => '2026-09-09 12:29:31.221563',
        'updated' => '2026-10-21 00:07:17.806675',
        'balancing_account' => 'Aufwendungen:Zweckbetrieb:KostLogis:Verpflegung:Agatharied2026',
      ],
      [
        'field_id' => '481',
        'key' => '21c890a0-aec0-11f1-ab76-4908b1c1d503',
        'label' => NULL,
        'data' => NULL,
        'deposit' => NULL,
        'limit' => NULL,
        'tooltip' => NULL,
        'deleted' => NULL,
        'created' => '2026-09-12 15:39:24.894941',
        'updated' => '2026-10-21 00:07:17.777836',
        'balancing_account' => 'Aufwendungen:Zweckbetrieb:KostLogis:Verpflegung:Agatharied2026',
      ],
      [
        'field_id' => '481',
        'key' => '353c24e6-6d55-11f1-b797-1d5ae83cae79',
        'label' => NULL,
        'data' => NULL,
        'deposit' => NULL,
        'limit' => NULL,
        'tooltip' => NULL,
        'deleted' => NULL,
        'created' => '2026-06-21 09:40:15.798507',
        'updated' => '2026-10-21 00:07:17.820847',
        'balancing_account' => 'Aufwendungen:Zweckbetrieb:KostLogis:Verpflegung:Agatharied2026',
      ],
      [
        'field_id' => '481',
        'key' => '6a45a9da-ac4a-11f1-8bd4-f575da26e540',
        'label' => NULL,
        'data' => NULL,
        'deposit' => NULL,
        'limit' => NULL,
        'tooltip' => NULL,
        'deleted' => NULL,
        'created' => '2026-09-09 12:31:43.571022',
        'updated' => '2026-10-21 00:07:17.794457',
        'balancing_account' => 'Aufwendungen:Zweckbetrieb:KostLogis:Verpflegung:Agatharied2026',
      ],
      [
        'field_id' => '481',
        'key' => '9259fa58-ad16-11f1-ae14-cf8df5192a51',
        'label' => NULL,
        'data' => NULL,
        'deposit' => NULL,
        'limit' => NULL,
        'tooltip' => NULL,
        'deleted' => NULL,
        'created' => '2026-09-10 12:53:08.145734',
        'updated' => '2026-10-21 00:07:17.921441',
        'balancing_account' => 'Aufwendungen:Zweckbetrieb:KostLogis:Verpflegung:Agatharied2026',
      ],
      [
        'field_id' => '481',
        'key' => 'b8875ba8-ad16-11f1-a1de-a53514e7b0bc',
        'label' => NULL,
        'data' => NULL,
        'deposit' => NULL,
        'limit' => NULL,
        'tooltip' => NULL,
        'deleted' => NULL,
        'created' => '2026-09-10 12:54:12.196515',
        'updated' => '2026-10-20 23:04:03.323706',
        'balancing_account' => 'Aufwendungen:Zweckbetrieb:KostLogis:Verpflegung:Agatharied2026',
      ],
      [
        'field_id' => '481',
        'key' => 'c5fea978-add2-11f1-98b6-3d2c5188727d',
        'label' => NULL,
        'data' => NULL,
        'deposit' => NULL,
        'limit' => NULL,
        'tooltip' => NULL,
        'deleted' => NULL,
        'created' => '2026-09-11 11:20:20.174178',
        'updated' => '2026-10-20 23:15:34.348463',
        'balancing_account' => 'Aufwendungen:Zweckbetrieb:KostLogis:Verpflegung:Agatharied2026',
      ],
      [
        'field_id' => '481',
        'key' => 'ef0cbdf0-add2-11f1-8625-bdb55e20bfd1',
        'label' => NULL,
        'data' => NULL,
        'deposit' => NULL,
        'limit' => NULL,
        'tooltip' => NULL,
        'deleted' => NULL,
        'created' => '2026-09-11 11:21:29.052899',
        'updated' => '2026-10-21 00:07:17.848173',
        'balancing_account' => 'Aufwendungen:Zweckbetrieb:KostLogis:Verpflegung:Agatharied2026',
      ],
    ];

    $fieldDataData = [
      [
        'field_id' => '481',
        'option_key' => '1b62be48-ac4a-11f1-8f7a-09053123d944',
        'project_id' => '156',
        'musician_id' => '1215',
        'supporting_document_id' => '72017235',
        'option_value' => '150.45',
        'deposit' => NULL,
        'created' => '2026-09-09 12:29:31.331857',
        'updated' => '2026-09-10 12:46:21.632520',
        'deleted' => NULL,
      ],
      [
        'field_id' => '481',
        'option_key' => '21c890a0-aec0-11f1-ab76-4908b1c1d503',
        'project_id' => '156',
        'musician_id' => '1215',
        'supporting_document_id' => '72017303',
        'option_value' => '-21.71',
        'deposit' => NULL,
        'created' => '2026-09-12 15:39:25.010550',
        'updated' => '2026-09-12 15:41:52.106889',
        'deleted' => NULL,
      ],
      [
        'field_id' => '481',
        'option_key' => '353c24e6-6d55-11f1-b797-1d5ae83cae79',
        'project_id' => '156',
        'musician_id' => '1215',
        'supporting_document_id' => '72017236',
        'option_value' => '161.73',
        'deposit' => NULL,
        'created' => '2026-09-09 12:28:15.422842',
        'updated' => '2026-09-10 12:52:19.971078',
        'deleted' => NULL,
      ],
      [
        'field_id' => '481',
        'option_key' => '6a45a9da-ac4a-11f1-8bd4-f575da26e540',
        'project_id' => '156',
        'musician_id' => '1215',
        'supporting_document_id' => '72017234',
        'option_value' => '29.80',
        'deposit' => NULL,
        'created' => '2026-09-09 12:31:43.681170',
        'updated' => '2026-09-10 12:45:48.315941',
        'deleted' => NULL,
      ],
      [
        'field_id' => '481',
        'option_key' => '9259fa58-ad16-11f1-ae14-cf8df5192a51',
        'project_id' => '156',
        'musician_id' => '1215',
        'supporting_document_id' => '72017269',
        'option_value' => '26.20',
        'deposit' => NULL,
        'created' => '2026-09-10 12:53:08.253851',
        'updated' => '2026-09-11 10:15:02.476433',
        'deleted' => NULL,
      ],
      [
        'field_id' => '481',
        'option_key' => 'b8875ba8-ad16-11f1-a1de-a53514e7b0bc',
        'project_id' => '156',
        'musician_id' => '1215',
        'supporting_document_id' => '72017268',
        'option_value' => '131.65',
        'deposit' => NULL,
        'created' => '2026-09-10 12:54:12.281799',
        'updated' => '2026-09-11 10:14:00.079206',
        'deleted' => NULL,
      ],
      [
        'field_id' => '481',
        'option_key' => 'c5fea978-add2-11f1-98b6-3d2c5188727d',
        'project_id' => '156',
        'musician_id' => '1215',
        'supporting_document_id' => '72017285',
        'option_value' => '1.27',
        'deposit' => NULL,
        'created' => '2026-09-11 11:20:20.276506',
        'updated' => '2026-09-11 11:26:33.959816',
        'deleted' => NULL,
      ],
      [
        'field_id' => '481',
        'option_key' => 'ef0cbdf0-add2-11f1-8625-bdb55e20bfd1',
        'project_id' => '156',
        'musician_id' => '1215',
        'supporting_document_id' => '72017286',
        'option_value' => '-6.12',
        'deposit' => NULL,
        'created' => '2026-09-11 11:21:29.140878',
        'updated' => '2026-09-11 11:27:46.240619',
        'deleted' => NULL,
      ],
    ];
  }
}
