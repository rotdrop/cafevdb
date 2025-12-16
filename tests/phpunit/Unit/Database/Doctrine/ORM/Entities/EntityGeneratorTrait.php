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

use Closure;
use DateTimeInterface;

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
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Tests\MockProvider;

/** Trait class in order to generate entities without database access. */
trait EntityGeneratorTrait
{
  protected Entities\Musician $musician;
  protected Entities\Project $project;
  protected Entities\ProjectParticipant $participant;

  protected const MUSICIAN_IBAN = 'DE02120300000000202051';
  protected const MUSICIAN_BIC = 'BYLADEM1001';
  protected const MUSICIAN_BLZ = '12030000';
  protected const MUSICIAN_BANK_ACCOUNT_OWNER = 'Inhaber*in, Konto';

  private EntityManager $entityManager;

  private InstrumentationService $instrumentationService;

  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function setup(bool $persist = false, ?DateTimeInterface $now = null): void
  {
    parent::setup();

    /** @var MockProvider $mockProvider */
    $mockProvider = MockProvider::create($this);

    if ($persist) {
      $this->entityManager = $mockProvider->getEntityManager($this);
    } else {
      $this->entityManager = $this->createStub(EntityManager::class);
    }

    $appContainer = $mockProvider->getAppContainer($this);

    /** @var InstrumentationService $this->instrumentationService */
    $this->instrumentationService = new InstrumentationService(
      configService: $appContainer->get(ConfigService::class),
      toolTipsService: $appContainer->get(ToolTipsService::class),
      entityManager: $this->entityManager,
    );

    $this->project = new Entities\Project()
      ->setId(1)
      ->setName('TestProject2099')
      ->setYear(2099);
    $this->musician = $this->instrumentationService->getDummyMusician(
      $this->project,
      persist: $persist,
      now: $now,
    );
    $this->musician->setId(1);
    $this->participant = $this->musician->getProjectParticipantOf(
      $this->project,
    );
  }

  /** @return Entities\ProjectParticipantFieldDatum */
  protected function generateReceivable(): Entities\ProjectParticipantFieldDatum
  {
    /** @var Entities\ProjectParticipantField $field */
    $field = new Entities\ProjectParticipantField()
      ->setId(0)
      ->setProject($this->project)
      ->setDataType(Types\EnumParticipantFieldDataType::LIABILITIES())
      ->setMultiplicity(
        Types\EnumParticipantFieldMultiplicity::RECURRING(),
      )
      ->setName('Forderungen');
    $generator = new Entities\ProjectParticipantFieldDataOption()
      ->setField($field)
      ->setKey(Entities\ProjectParticipantFieldDataOption::GENERATOR_KEY)
      ->setLabel(
        Entities\ProjectParticipantFieldDataOption::GENERATOR_LABEL,
      );
    $field
      ->setDefaultValue($generator)
      ->getDataOptions()
      ->set($generator->getKey(), $generator);
    $option = new Entities\ProjectParticipantFieldDataOption()
      ->setField($field)
      ->setKey(Uuid::create())
      ->setLabel('ReNr RE25/01354 Aktenzeichen 25-01258 Ümläüteß');
    $field->getDataOptions()->set($option->getKey(), $option);
    $datum = new Entities\ProjectParticipantFieldDatum()
      ->setDataOption($option)
      ->setProjectParticipant($this->participant)
      ->setOptionValue(RationalNumber::fromDecimal('12.23'));
    $option->getFieldData()->set($this->musician->getId(), $datum);
    $field->getFieldData()->add($datum);

    return $datum;
  }

  /** @return Entities\CompositePayment */
  protected function generateCompositePayment(?Closure $transliterate = null): Entities\CompositePayment
  {
    $datum = $this->generateReceivable();

    /** @var Entities\ProjectPayment $projectPayment */
    $projectPayment = new Entities\ProjectPayment()
      ->setProjectParticipant($this->participant)
      ->setReceivable($datum)
      ->setAmount($datum->getOptionValue());

    /** @var Entities\CompositePayment $compositePayment */
    $compositePayment = new Entities\CompositePayment()->setProjectParticipant(
      $this->participant,
    );
    $compositePayment->getProjectPayments()->add($projectPayment);
    $compositePayment->updateSubject($transliterate);

    return $compositePayment;
  }

  /**
   * @return Entities\SepaBankTransfer
   */
  protected function generateSepaBankAccount(): Entities\SepaBankAccount
  {
    $entity = new Entities\SepaBankAccount()
      ->setMusician($this->musician)
      ->setSequence(1)
      ->setIban(self::MUSICIAN_IBAN)
      ->setBic(self::MUSICIAN_BIC)
      ->setBlz(self::MUSICIAN_BLZ)
      ->setBankAccountOwner(self::MUSICIAN_BANK_ACCOUNT_OWNER);
    $this->musician->getSepaBankAccounts()->add($entity);
    return $entity;
  }

  /**
   * @return Entities\SepaBankTransfer
   */
  protected function generateSepaBankTransfer(): Entities\SepaBankTransfer
  {
    $entity = new Entities\SepaBankTransfer()->setDueDate('2099-01-01');
    $bankAccount = $this->generateSepaBankAccount();
    $payment = $this->generateCompositePayment();
    $payment->setSepaBankAccount($bankAccount);
    $entity->getPayments()->set($this->musician->getId(), $payment);

    return $entity;
  }

  const PROJECT_EVENT_DATA = [
    [
      'id' => '1344',
      'project_id' => '225',
      'calendar_uri' => ConfigConstants::OTHER_CALENDAR_URI,
      'calendar_id' => '47',
      'event_uid' => 'bf37e37b-b7f4-4b27-b605-00c64c6d86b9',
      'event_uri' => '5E61858A-32E0-11EE-8B79-F745B3CCF2A6.ics',
      'recurrence_id' => '0',
      'sequence' => '0',
      'series_uid' => null,
      'type' => 'VEVENT',
      'absence_field_id' => null,
      'deleted' => null,
      'series_uid' => null,
    ],
    [
      'id' => '1345',
      'project_id' => '225',
      'calendar_uri' => ConfigConstants::CONCERTS_CALENDAR_URI,
      'calendar_id' => '46',
      'event_uid' => '3d481bd5-9ea4-4b2d-a1c4-9247467456c1',
      'event_uri' => '6DDC62D6-32E1-11EE-87EF-3D2D258F32B7.ics',
      'recurrence_id' => '0',
      'sequence' => '0',
      'series_uid' => null,
      'type' => 'VEVENT',
      'absence_field_id' => '475',
      'deleted' => null,
      'series_uid' => null,
    ],
    [
      'id' => '1346',
      'project_id' => '225',
      'calendar_uri' => ConfigConstants::REHEARSALS_CALENDAR_URI,
      'calendar_id' => '45',
      'event_uid' => '51009caf-5ca5-410c-bede-0fc4f432359c',
      'event_uri' => '74DF0E58-32E1-11EE-B087-4BC75144380D.ics',
      'recurrence_id' => '0',
      'sequence' => '1',
      'series_uid' => null,
      'type' => 'VEVENT',
      'absence_field_id' => '476',
      'deleted' => null,
      'series_uid' => null,
    ],
    [
      'id' => '1350',
      'project_id' => '225',
      'calendar_uri' => ConfigConstants::OTHER_CALENDAR_URI,
      'calendar_id' => '47',
      'event_uid' => 'faaad3ca-1d14-48cc-a5bc-8594f6570a7e',
      'event_uri' => '863FA79C-E984-11EF-803F-D77076E61BF8.ics',
      'recurrence_id' => '0',
      'sequence' => '0',
      'series_uid' => null,
      'type' => 'VEVENT',
      'absence_field_id' => null,
      'deleted' => null,
      'series_uid' => null,
    ],
    [
      'id' => '1351',
      'project_id' => '225',
      'calendar_uri' => ConfigConstants::MANAGEMENT_CALENDAR_URI,
      'calendar_id' => '48',
      'event_uid' => '3cae242f-b1b5-4e16-8195-707e1ee156ec',
      'event_uri' => '521BA9B5-18A2-42D5-8E70-7BAD13376E9E.ics',
      'recurrence_id' => '0',
      'sequence' => '2',
      'series_uid' => null,
      'type' => 'VEVENT',
      'absence_field_id' => null,
      'deleted' => null,
      'series_uid' => null,
    ],
    [
      'id' => '1356',
      'project_id' => '225',
      'calendar_uri' => ConfigConstants::FINANCE_CALENDAR_URI,
      'calendar_id' => '49',
      'event_uid' => 'fe9c8f84-f2ae-40fb-b56a-779a39b06989',
      'event_uri' => 'C9D7987C-F8D7-11EF-B5E3-8FE1B384B391.ics',
      'recurrence_id' => '0',
      'sequence' => '2',
      'type' => 'VEVENT',
      'absence_field_id' => null,
      'deleted' => null,
      'series_uid' => 'c9d250ec-f8d7-11ef-a72e-136efd88df16',
    ],
    [
      'id' => '1357',
      'project_id' => '225',
      'calendar_uri' => ConfigConstants::FINANCE_CALENDAR_URI,
      'calendar_id' => '49',
      'event_uid' => '0007215b-1f0c-4b56-8f0a-70acfc0f63da',
      'event_uri' => 'C9D92912-F8D7-11EF-892C-3F353C6D504D.ics',
      'recurrence_id' => '0',
      'sequence' => '2',
      'type' => 'VEVENT',
      'absence_field_id' => null,
      'deleted' => null,
      'series_uid' => 'c9d250ec-f8d7-11ef-a72e-136efd88df16',
    ],
    [
      'id' => '1359',
      'project_id' => '225',
      'calendar_uri' => ConfigConstants::FINANCE_CALENDAR_URI,
      'calendar_id' => '49',
      'event_uid' => 'c3f638fa-118d-4c40-b43a-6c278b6490a3',
      'event_uri' => '30734E6C-487E-4059-9C4E-BFACC83F82AB.ics',
      'recurrence_id' => '0',
      'sequence' => '2',
      'series_uid' => null,
      'type' => 'VEVENT',
      'absence_field_id' => null,
      'deleted' => null,
      'series_uid' => null,
    ],
    [
      'id' => '1360',
      'project_id' => '225',
      'calendar_uri' => ConfigConstants::REHEARSALS_CALENDAR_URI,
      'calendar_id' => '45',
      'event_uid' => '9a372304-33fb-46f5-a3c3-c5730a79cf9a',
      'event_uri' => '3C02F464-70B4-4772-99E3-B6A41BFEDA31.ics',
      'recurrence_id' => '1742256000',
      'sequence' => '1',
      'type' => 'VEVENT',
      'absence_field_id' => '508',
      'deleted' => null,
      'series_uid' => '89c56590-fb28-11ef-ae27-ef007d703f66',
    ],
    [
      'id' => '1361',
      'project_id' => '225',
      'calendar_uri' => ConfigConstants::REHEARSALS_CALENDAR_URI,
      'calendar_id' => '45',
      'event_uid' => '9a372304-33fb-46f5-a3c3-c5730a79cf9a',
      'event_uri' => '3C02F464-70B4-4772-99E3-B6A41BFEDA31.ics',
      'recurrence_id' => '1742342400',
      'sequence' => '1',
      'type' => 'VEVENT',
      'absence_field_id' => '509',
      'deleted' => null,
      'series_uid' => '89c56590-fb28-11ef-ae27-ef007d703f66',
    ],
    [
      'id' => '1362',
      'project_id' => '225',
      'calendar_uri' => ConfigConstants::REHEARSALS_CALENDAR_URI,
      'calendar_id' => '45',
      'event_uid' => '9a372304-33fb-46f5-a3c3-c5730a79cf9a',
      'event_uri' => '3C02F464-70B4-4772-99E3-B6A41BFEDA31.ics',
      'recurrence_id' => '1742428800',
      'sequence' => '1',
      'type' => 'VEVENT',
      'absence_field_id' => '510',
      'deleted' => null,
      'series_uid' => '89c56590-fb28-11ef-ae27-ef007d703f66',
    ],
    [
      'id' => '1364',
      'project_id' => '225',
      'calendar_uri' => ConfigConstants::REHEARSALS_CALENDAR_URI,
      'calendar_id' => '45',
      'event_uid' => '7b97c299-7e7c-4ec5-ac58-fd68b90e53db',
      'event_uri' => '89D17B5A-FB28-11EF-9900-4D17E2143159.ics',
      'recurrence_id' => '0',
      'sequence' => '2',
      'type' => 'VEVENT',
      'absence_field_id' => '511',
      'deleted' => null,
      'series_uid' => '89c56590-fb28-11ef-ae27-ef007d703f66',
    ],
    [
      'id' => '1365',
      'project_id' => '225',
      'calendar_uri' => ConfigConstants::FINANCE_CALENDAR_URI,
      'calendar_id' => '49',
      'event_uid' => '3156a6ba-d4a2-418d-a2da-366d5388632e',
      'event_uri' => '952C1F14-E786-48A2-A3EB-13F8AE2C4EE9.ics',
      'recurrence_id' => '1741219200',
      'sequence' => '1',
      'type' => 'VEVENT',
      'absence_field_id' => null,
      'deleted' => null,
      'series_uid' => 'c9d250ec-f8d7-11ef-a72e-136efd88df16',
    ],
    [
      'id' => '1366',
      'project_id' => '225',
      'calendar_uri' => ConfigConstants::FINANCE_CALENDAR_URI,
      'calendar_id' => '49',
      'event_uid' => '3156a6ba-d4a2-418d-a2da-366d5388632e',
      'event_uri' => '952C1F14-E786-48A2-A3EB-13F8AE2C4EE9.ics',
      'recurrence_id' => '1741132800',
      'sequence' => '1',
      'type' => 'VEVENT',
      'absence_field_id' => null,
      'deleted' => null,
      'series_uid' => 'c9d250ec-f8d7-11ef-a72e-136efd88df16',
    ],
    [
      'id' => '1367',
      'project_id' => '225',
      'calendar_uri' => ConfigConstants::OTHER_CALENDAR_URI,
      'calendar_id' => '47',
      'event_uid' => '1ceae044-424c-4984-b851-1c1014262fa3',
      'event_uri' => 'D927B3C3-90F5-4A1D-8B52-589A63A3B138.ics',
      'recurrence_id' => '1745280000',
      'sequence' => '2',
      'type' => 'VEVENT',
      'absence_field_id' => null,
      'deleted' => null,
      'series_uid' => '5ce27978-1ee6-11f0-9848-75f619d9b11d',
    ],
    [
      'id' => '1368',
      'project_id' => '225',
      'calendar_uri' => ConfigConstants::OTHER_CALENDAR_URI,
      'calendar_id' => '47',
      'event_uid' => '1ceae044-424c-4984-b851-1c1014262fa3',
      'event_uri' => 'D927B3C3-90F5-4A1D-8B52-589A63A3B138.ics',
      'recurrence_id' => '1745366400',
      'sequence' => '2',
      'type' => 'VEVENT',
      'absence_field_id' => null,
      'deleted' => null,
      'series_uid' => '5ce27978-1ee6-11f0-9848-75f619d9b11d',
    ],
    [
      'id' => '1369',
      'project_id' => '225',
      'calendar_uri' => ConfigConstants::OTHER_CALENDAR_URI,
      'calendar_id' => '47',
      'event_uid' => '1ceae044-424c-4984-b851-1c1014262fa3',
      'event_uri' => 'D927B3C3-90F5-4A1D-8B52-589A63A3B138.ics',
      'recurrence_id' => '1745452800',
      'sequence' => '2',
      'type' => 'VEVENT',
      'absence_field_id' => null,
      'deleted' => null,
      'series_uid' => '5ce27978-1ee6-11f0-9848-75f619d9b11d',
    ],
    [
      'id' => '1370',
      'project_id' => '225',
      'calendar_uri' => ConfigConstants::OTHER_CALENDAR_URI,
      'calendar_id' => '47',
      'event_uid' => '1ceae044-424c-4984-b851-1c1014262fa3',
      'event_uri' => 'D927B3C3-90F5-4A1D-8B52-589A63A3B138.ics',
      'recurrence_id' => '1745539200',
      'sequence' => '2',
      'type' => 'VEVENT',
      'absence_field_id' => null,
      'deleted' => null,
      'series_uid' => '5ce27978-1ee6-11f0-9848-75f619d9b11d',
    ],
    [
      'id' => '1371',
      'project_id' => '225',
      'calendar_uri' => ConfigConstants::OTHER_CALENDAR_URI,
      'calendar_id' => '47',
      'event_uid' => '1ceae044-424c-4984-b851-1c1014262fa3',
      'event_uri' => 'D927B3C3-90F5-4A1D-8B52-589A63A3B138.ics',
      'recurrence_id' => '1745625600',
      'sequence' => '2',
      'type' => 'VEVENT',
      'absence_field_id' => null,
      'deleted' => null,
      'series_uid' => '5ce27978-1ee6-11f0-9848-75f619d9b11d',
    ],
    [
      'id' => '1372',
      'project_id' => '225',
      'calendar_uri' => ConfigConstants::OTHER_CALENDAR_URI,
      'calendar_id' => '47',
      'event_uid' => '1bb239f6-6110-4d3f-a84b-ab0c9bfcab64',
      'event_uri' => '5CE69A6C-1EE6-11F0-ADAE-9F26E247CE42.ics',
      'recurrence_id' => '0',
      'sequence' => '2',
      'type' => 'VEVENT',
      'absence_field_id' => null,
      'deleted' => null,
      'series_uid' => '5ce27978-1ee6-11f0-9848-75f619d9b11d',
    ],
    [
      'id' => '1373',
      'project_id' => '225',
      'calendar_uri' => ConfigConstants::OTHER_CALENDAR_URI,
      'calendar_id' => '47',
      'event_uid' => '6ad26e58-6c3a-48bf-9d0b-0579dfee252e',
      'event_uri' => '5CE8528A-1EE6-11F0-8CB2-C77D3281A19A.ics',
      'recurrence_id' => '0',
      'sequence' => '2',
      'type' => 'VEVENT',
      'absence_field_id' => null,
      'deleted' => null,
      'series_uid' => '5ce27978-1ee6-11f0-9848-75f619d9b11d',
    ],
  ];

  /**
   * Augment the given project by a couple of project events. The data is
   * compatible with OCA\CAFEVDB\Tests\Unit\Service\CalendarObjects.
   *
   * @param Entities\Project $project
   *
   * @param array $calendars List of calendar ids, indexed by uri.
   *
   * @return void
   */
  public static function addProjectEvents(Entities\Project $project, array $calendars): void
  {
    $calendarEvents = $project->getCalendarEvents();
    $index = 1;
    foreach (self::PROJECT_EVENT_DATA as $row) {
      $projectEvent = new Entities\ProjectEvent();
      $projectEvent->setId($index++)
        ->setProject($project)
        ->setCalendarUri($row['calendar_uri'])
        ->setCalendarId($calendars[$row['calendar_uri']])
        ->setEventUid($row['event_uid'])
        ->setEventUri($row['event_uri'])
        ->setRecurrenceId($row['recurrence_id'])
        ->setSequence($row['sequence'])
        ->setType($row['type'])
        ->setAbsenceField(null /** @todo */)
        ->setDeleted($row['deleted'])
        ->setSeriesUid($row['series_uid'])
        ;
      $calendarEvents->add($projectEvent);
    }
  }
}
