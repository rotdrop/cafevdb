<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Common\RationalNumber;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\InstrumentationService;
use OCA\CAFEVDB\Service\ToolTipsService;

/** Trait class in order to generate entities without database access. */
trait EntityGeneratorTrait
{
  protected Entities\Musician $musician;
  protected Entities\Project $project;
  protected Entities\ProjectParticipant $participant;

  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function setup():void
  {
    parent::setup();

    $entityManager = $this->getMockBuilder(EntityManager::class)
      ->disableOriginalConstructor()
      ->getMock();

    /** @var InstrumentationService $instrumentationService */
    $instrumentationService = new InstrumentationService(
      configService: \OCP\Server::get(ConfigService::class),
      toolTipsService: \OCP\Server::get(ToolTipsService::class),
      entityManager: $entityManager,
    );

    $this->project = (new Entities\Project)
      ->setId(0)
      ->setName('TestProject2099')
      ->setYear(2099);
    $this->musician = $instrumentationService->getDummyMusician($this->project, persist: false);
    $this->participant = $this->musician->getProjectParticipantOf($this->project);
  }

  /**
   * @return Entitiers\CompositePayment
   */
  protected function generateCompositePayment(): Entities\CompositePayment
  {
    /** @var Entities\ProjectParticipantField $field */
    $field = (new Entities\ProjectParticipantField)
      ->setId(0)
      ->setProject($this->project)
      ->setDataType(Types\EnumParticipantFieldDataType::LIABILITIES())
      ->setMultiplicity(Types\EnumParticipantFieldMultiplicity::RECURRING())
      ->setName('Forderungen')
      ;
    $generator = (new Entities\ProjectParticipantFieldDataOption)
      ->setField($field)
      ->setKey(Entities\ProjectParticipantFieldDataOption::GENERATOR_KEY)
      ->setLabel(Entities\ProjectParticipantFieldDataOption::GENERATOR_LABEL)
      ;
    $field
      ->setDefaultValue($generator)
      ->getDataOptions()->set($generator->getKey(), $generator)
      ;
    $option = (new Entities\ProjectParticipantFieldDataOption)
      ->setField($field)
      ->setKey(Uuid::create())
      ->setLabel('ReNr RE25/01354 Aktenzeichen 25-01258')
      ;
    $field->getDataOptions()->set($option->getKey(), $option);
    $datum = (new Entities\ProjectParticipantFieldDatum)
      ->setDataOption($option)
      ->setProjectParticipant($this->participant)
      ->setOptionValue(RationalNumber::fromDecimal('12.23'))
      ;
    /** @var Entities\ProjectPayment $projectPayment */
    $projectPayment = (new Entities\ProjectPayment)
      ->setProjectParticipant($this->participant)
      ->setReceivable($datum)
      ->setAmount($datum->getOptionValue())
      ;

    /** @var Entities\CompositePayment $compositePayment */
    $compositePayment = (new Entities\CompositePayment)
      ->setProjectParticipant($this->participant)
      ;
    $compositePayment->getProjectPayments()->add($projectPayment);
    $compositePayment->updateSubject();

    return $compositePayment;
  }
}
