<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use UnexpectedValueException;
use Throwable;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Tests\MockProvider;

/** Test aspects of the Instrument and InstrumentFamily entity. */
#[Attributes\CoversClass(Entities\Instrument::class)]
#[Attributes\CoversClass(Entities\InstrumentFamily::class)]
#[Attributes\CoversClass(Entities\Musician::class)]
#[Attributes\CoversClass(Entities\MusicianEmailAddress::class)]
#[Attributes\CoversClass(Entities\MusicianInstrument::class)]
#[Attributes\CoversClass(Entities\Project::class)]
#[Attributes\CoversClass(Entities\ProjectInstrument::class)]
#[Attributes\CoversClass(Entities\ProjectInstrumentationNumber::class)]
#[Attributes\CoversClass(Entities\ProjectParticipant::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBankAccount::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsService::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\LoggerTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\ConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\EntityManagerTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\CreatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UuidTrait::class)]
class InstrumentInstrumentFamilyTest extends TestCase
{
  use EntityGeneratorTrait;

  /** @return void */
  public function testAddInstrument(): void
  {
    $instrument = new Entities\Instrument()->setName('instrument');
    $family = new Entities\InstrumentFamily()->setFamily('family');

    try {
      $family->addInstrument($instrument);
    } catch (Throwable $t) {
      $this->assertInstanceOf(UnexpectedValueException::class, $t);
    }
    $family->setId(1);
    try {
      $family->addInstrument($instrument);
    } catch (Throwable $t) {
      $this->assertInstanceOf(UnexpectedValueException::class, $t);
    }
    $instrument->setId(1);

    $family->addInstrument($instrument);
    $this->assertEquals($instrument, $family->getInstruments()->first());
    $this->assertEquals($family, $instrument->getFamilies()->first());
  }

  /** @return void */
  public function testAddFamily(): void
  {
    $instrument = new Entities\Instrument()->setName('instrument');
    $family = new Entities\InstrumentFamily()->setFamily('family');

    try {
      $instrument->addFamily($family);
    } catch (Throwable $t) {
      $this->assertInstanceOf(UnexpectedValueException::class, $t);
    }
    $family->setId(1);
    try {
      $instrument->addFamily($family);
    } catch (Throwable $t) {
      $this->assertInstanceOf(UnexpectedValueException::class, $t);
    }
    $instrument->setId(1);

    $instrument->addFamily($family);
    $this->assertEquals($instrument, $family->getInstruments()->first());
    $this->assertEquals($family, $instrument->getFamilies()->first());
  }

  /** @return void */
  public function testProjectInstrumentChain(): void
  {
    $this->generateProjectParticipant(persist: false);
    $this->generateInstruments();

    $this->assertEquals(2, $this->musician->getInstruments()->count());

    /** @var Entities\MusicianInstrument $musicianInstrument */
    foreach ($this->musician->getInstruments() as $musicianInstrument) {
      $this->assertEquals(
        $musicianInstrument,
        $musicianInstrument->getInstrument()->getMusicianInstruments()->get($musicianInstrument->getMusician()->getId()),
      );
      $this->assertEquals($this->musician, $musicianInstrument->getMusician());
    }

    $this->assertEquals(1, $this->participant->getProjectInstruments()->count());

    /** @var Entities\ProjectInstrument $projectInstrument */
    foreach ($this->participant->getProjectInstruments() as $projectInstrument) {
      $this->assertEquals($this->participant, $projectInstrument->getProjectParticipant());
      $this->assertNotNull($projectInstrument->getInstrument());
      $this->assertTrue($projectInstrument->getInstrument()->getProjectInstruments()->contains($projectInstrument));
      $this->assertTrue($projectInstrument->getMusicianInstrument()->getProjectInstruments()->contains($projectInstrument));
      $this->assertTrue($projectInstrument->getProject()->getParticipantInstruments()->contains($projectInstrument));
      $this->assertTrue($projectInstrument->getInstrumentationNumber()->getProjectInstruments()->contains($projectInstrument));
    }
  }
}
