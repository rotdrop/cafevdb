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

namespace OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

use Psr\Container\ContainerInterface;

use OCA\CAFEVDB\Toolkit\Common\RationalNumber;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Crypto;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Listener;
use OCA\CAFEVDB\Service;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Service\Finance\SepaTransliterator;
use OCA\CAFEVDB\Service\InstrumentationService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Tests\Unit\Service\WordSplitterServiceTrait;

/** Test the Entities\CompositePayment entity. */
#[Attributes\CoversClass(Entities\CompositePayment::class)]
#[Attributes\CoversClass(Entities\Musician::class)]
#[Attributes\CoversClass(Entities\Project::class)]
#[Attributes\CoversClass(Entities\ProjectParticipant::class)]
#[Attributes\CoversClass(Entities\ProjectParticipantField::class)]
#[Attributes\CoversClass(Entities\ProjectParticipantFieldDataOption::class)]
#[Attributes\CoversClass(Entities\ProjectParticipantFieldDatum::class)]
#[Attributes\CoversClass(Entities\ProjectPayment::class)]
#[Attributes\CoversClass(FinanceService::class)]
#[Attributes\CoversClass(InstrumentationService::class)]
#[Attributes\CoversClass(SepaTransliterator::class)]
#[Attributes\CoversMethod(Entities\CompositePayment::class, '__construct')]
#[Attributes\CoversMethod(Entities\CompositePayment::class, 'generateSubject')]
#[Attributes\CoversMethod(Entities\CompositePayment::class, 'updateSubject')]
#[Attributes\CoversMethod(InstrumentationService::class, 'getDummyMusician')]
#[Attributes\UsesClass(ConfigService::class)]
#[Attributes\UsesClass(Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(Crypto\Registration::class)]
#[Attributes\UsesClass(Entities\DatabaseStorageFile::class)]
#[Attributes\UsesClass(Entities\DatabaseStorageFolder::class)]
#[Attributes\UsesClass(Entities\EncryptedFile::class)]
#[Attributes\UsesClass(Entities\File::class)]
#[Attributes\UsesClass(Entities\FileData::class)]
#[Attributes\UsesClass(Entities\MusicianEmailAddress::class)]
#[Attributes\UsesClass(Entities\SepaBankAccount::class)]
#[Attributes\UsesClass(Listener\TranslationNotFoundListener::class)]
#[Attributes\UsesClass(RationalNumber::class)]
#[Attributes\UsesClass(Service\AuthorizationService::class)]
#[Attributes\UsesClass(Service\EncryptionService::class)]
#[Attributes\UsesClass(Service\ToolTipsService::class)]
#[Attributes\UsesClass(Util::class)]
#[Attributes\UsesClass(Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\CamelCaseString::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\WordSplitterService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\TimeFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Transliterator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
class CompositePaymentTest extends TestCase
{
  use EntityGeneratorTrait;
  use WordSplitterServiceTrait;

  private FinanceService $financeService;

  private SepaTransliterator $sepaTransliterator;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $this->generateProjectParticipant();

    /** @var MockProvider $mockProvider */
    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);
    $this->registerWordSplitterService();

    $appContainer = $this->mockProvider->getAppContainer();

    $configService = $this->mockProvider->getConfigService();

    $eventsService = $this->createStub(EventsService::class);
    $organizationalRolesService = $this->createStub(OrganizationalRolesService::class);

    $this->sepaTransliterator = $appContainer->get(SepaTransliterator::class);

    $this->financeService = new FinanceService(
      configService: $configService,
      entityManager: $this->entityManager,
      eventsService: $eventsService,
      rolesService: $organizationalRolesService,
      sepaTransliterator: $this->sepaTransliterator,
    );

    self::$uuidIndex = 0;
  }

  /** @return void */
  public function testCompositePaymentConstruction(): void
  {
    $compositePayment = $this->generateCompositePayment();
    $this->assertEquals($this->project, $compositePayment->getProject());
    $this->assertEquals($this->musician, $compositePayment->getMusician());
    $this->assertEquals($this->participant, $compositePayment->getProjectParticipant());
  }

  /** @return void */
  public function testLiabilityConstruction(): void
  {
    /** @var Entities\CompositePayment $compositePayment */
    $compositePayment = $this->generateCompositePayment();
    /** @var Entities\ProjectPayment $projectPayment */
    $projectPayment = $compositePayment->getProjectPayments()->first();
    $liability = $projectPayment->getReceivable();
    $this->assertEquals($this->project, $liability->getProject());
    $this->assertEquals($this->musician, $liability->getMusician());
    $this->assertEquals($this->participant, $liability->getProjectParticipant());
  }

  /** @return void */
  public function testSubjectGenerationWithoutDocuments(): void
  {
    $compositePayment = $this->generateCompositePayment();
    $this->assertEquals(
      'TestProject / Forderungen: RechnungsNummer RE25/01354 AktenZeichen 25-01258 ümläüteßWeitereÜmläüteß',
      $compositePayment->getSubject(),
    );
  }

  /** @return void */
  public function testSubjectGenerationWithDocuments(): void
  {
    $folder = (new Entities\DatabaseStorageFolder)
      ->setName($this->project->getName() . '-005')
      ;
    $compositePayment = $this->generateCompositePayment();
    $compositePayment
      ->setBalanceDocumentsFolder($folder)
      ->updateSubject()
      ;
    $this->assertEquals(
      'TestProject99-5 / Forderungen: RechnungsNummer RE25/01354 AktenZeichen 25-01258 ümläüteßWeitereÜmläüteß',
      $compositePayment->getSubject(),
    );
  }

  /** @return void */
  public function testSubjectGenerationWithoutDocumentsWithTransliterate(): void
  {
    $compositePayment = $this->generateCompositePayment(fn(string $x) => $this->sepaTransliterator->translit($x));
    $this->assertEquals(
      'TestProject / Forderungen: RechnungsNummer RE25/01354 AktenZeichen 25-01258 uemlaeuetessWeitereUemlaeuetess',
      $compositePayment->getSubject(),
    );
  }

  /** @return void */
  public function testSubjectGenerationWithDocumentsWithTransliterate(): void
  {
    $folder = (new Entities\DatabaseStorageFolder)
      ->setName($this->project->getName() . '-005')
      ;
    $compositePayment = $this->generateCompositePayment(fn(string $x) => $this->sepaTransliterator->translit($x));
    $compositePayment
      ->setBalanceDocumentsFolder($folder)
      ->updateSubject(fn(string $x) => $this->sepaTransliterator->translit($x))
      ;
    $this->assertEquals(
      'TestProject99-5 / Forderungen: RechnungsNummer RE25/01354 AktenZeichen 25-01258 uemlaeuetessWeitereUemlaeuetess',
      $compositePayment->getSubject(),
    );
  }

  /** @return void */
  public function testSubjectGenerationWithManyItems(): void
  {
    $compositePayment = $this->generateMultiItemPayment();

    $subject = $compositePayment->generateSubject();
    $this->assertEquals(
      'TestProject99-19 / Unkostenbeiträge: Regulär / Unkostenerstattungen: Edeka 09.09., Edeka 11.09. Leergut vs. Milch, Edeka 12.09., EinkäufeEdeka 08.09., EinkäufeGetränkeFristo 08.09., EinkäufeREWE 08.09., Fristo 11.09. Leergut, VeselinPeykov e.K. 09.09.',
      $subject,
     );

    $subject = $compositePayment->generateSubject(transliterate: fn(string $x) => $this->sepaTransliterator->translit($x));
    $this->assertEquals(
      'TestProject99-19 / Unkostenbeitraege: Regulaer / Unkostenerstattungen: Edeka 09.09., Edeka 11.09. Leergut vs. Milch, Edeka 12.09., EinkaeufeEdeka 08.09., EinkaeufeGetraenkeFristo 08.09., EinkaeufeREWE 08.09., Fristo 11.09. Leergut, VeselinPeykov e.K. 09.09.',
      $subject,
    );

    $subject = $compositePayment->generateSubject(
      maxLength: FinanceService::SEPA_PURPOSE_LENGTH,
      transliterate: fn(string $x) => $this->sepaTransliterator->translit($x),
    );
    $this->assertLessThanOrEqual(FinanceService::SEPA_PURPOSE_LENGTH, strlen($subject));
    $this->assertEquals(
      'TestProj99-19/UnkoBeit:Regu/UnkoErst:Edek09.09.,Edek11.09.LeerVs.Milc,Edek12.09.,EinkEdek08.09.,EinkGetrFris08.09.,EinkREWE08.09.,Fris11.09.',
      $subject,
    );
  }
}
