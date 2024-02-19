<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service;

use DateTimeImmutable;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Legacy\Util as DataBaseUtil;
use OCA\CAFEVDB\Common\Uuid;

/**
 * General support service, kind of inconsequent glue between
 * Doctrine\ORM and CAFEVDB\PageRenderer.
 */
class InstrumentationService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var EntityManager */
  protected $entityManager;

  /** @var ToolTipsService */
  protected $toolTipsService;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ConfigService $configService,
    ToolTipsService $toolTipsService,
    EntityManager $entityManager,
  ) {
    $this->configService = $configService;
    $this->toolTipsService = $toolTipsService;
    $this->entityManager = $entityManager;
    $this->connection = $this->entityManager->getConnection();
    $this->l = $this->l10n();
  }
  // phpcs:enable

  /**
   * Generate a dummy musician entity which can be used during
   * (email-)form validation and similar things.
   *
   * The musician will be created as disabled or soft deleted.
   *
   * @param null|Entities\Project $project A project to attach the dummy to.
   *
   * @param bool $persist Whether to persist the dummy to the
   * data-base. If true the dummy person will be persisted as deleted
   * in order not to interfere with the real data.
   *
   * @return Entities\Musician
   */
  public function getDummyMusician(?Entities\Project $project = null, bool $persist = true):Entities\Musician
  {
    // disable "deleted" filter
    $softDeleteableState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    $musiciansRepository = $this->getDatabaseRepository(Entities\Musician::class);
    if ($persist) {
      /** @var Entities\Musician $dummy */
      $dummy = $musiciansRepository->findOneBy([ 'uuid' => Uuid::NIL ]);
    }
    if (empty($dummy)) {
      $dummy = Entities\Musician::create();
    }
    $dummy->setSurName($this->l->t('Doe'))
      ->setFirstName($this->l->t('John'))
      ->setAddressSupplement('Igloo 13')
      ->setStreet($this->l->t('Undiscoverable'))
      ->setStreetNumber(42)
      ->setPostalCode('Z-7')
      ->setCity($this->l->t('Nowhere'))
      ->setCountry('AQ')
      ->setEmail($this->getConfigValue('emailtestaddress', 'john.doe@nowhere.tld'))
      ->setBirthday(new DateTimeImmutable)
      ->setMobilePhone('0815')
      ->setFixedLinePhone('4711')
      ->setDeleted(new DateTimeImmutable)
      ->setUuid(Uuid::NIL);
    if ($persist) {
      $this->persist($dummy);
    }
    if ($dummy->getSepaBankAccounts()->isEmpty()) {
      // also generate a dummy bank account
      $bankAccount = (new Entities\SepaBankAccount)
                   ->setMusician($dummy)
                   ->setIban('DE02700100800030876808')
                   ->setBic('PBNKDEFF')
                   ->setBlz('70010080')
                   ->setBankAccountOwner($dummy->getPublicName())
                   ->setSequence(1)
                   ->setDeleted(new DateTimeImmutable);
      $dummy->getSepaBankAccounts()->add($bankAccount);
      if ($persist) {
        $this->persist($bankAccount);
      }
    }
    if ($persist) {
      $this->flush();
    }

    if (!empty($project)) {
      $participant = (new Entities\ProjectParticipant)
                   ->setMusician($dummy)
                   ->setProject($project);
      $dummy->getProjectParticipation()->set($project->getId(), $participant);
    }

    $person = new Entities\LegalPerson($dummy);

    $dummy->setLegalPerson($person);

    $softDeleteableState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    return $dummy;
  }

  /**
   * @param string $idOrName
   *
   * @return string
   *
   * @todo DetailedInstrumentationService? Maybe overkill
   */
  public function tableTabId(string $idOrName):string
  {
    $dflt = $this->defaultTableTabs();
    foreach ($dflt as $tab) {
      if ($idOrName === $tab['name']) {
        return $idOrName;
      }
    }
    return $idOrName;
  }

  /**
   * Export the default tabs family.
   *
   * @param bool $useFinanceTab
   *
   * @return array
   *
   * @todo This is almost nowhere used, but different to be joined other
   * implementations exist.
   */
  public function defaultTableTabs(bool $useFinanceTab = false):array
  {
    $pre = [
      [
        'id' => 'instrumentation',
        'default' => true,
        'tooltip' => $this->toolTipsService['project-instrumentation-tab'],
        'name' => $this->l->t('Instrumentation related data'),
      ],
      [
        'id' => 'project',
        'tooltip' => $this->toolTipsService['project-metadata-tab'],
        'name' => $this->l->t('Project related data'),
      ],
    ];
    $finance = [
      [
        'id' => 'finance',
        'tooltip' => $this->toolTipsService['project-finance-tab'],
        'name' => $this->l->t('Finance related data'),
      ],
    ];
    $post = [
      [
        'id' => 'file-attachments',
        'tooltip' => $this->toolTipsService['project-file-attachments-tab'],
        'name' => $this->l->t('Project file attachments'),
      ],
      [
        'id' => 'musician',
        'tooltip' => $this->toolTipsService['project-personaldata-tab'],
        'name' => $this->l->t('Personal data'),
      ],
      [
        'id' => 'tab-all',
        'tooltip' => $this->toolTipsService['pme-showall-tab'],
        'name' => $this->l->t('Display all columns'),
      ],
    ];
    if ($useFinanceTab) {
      return array_merge($pre, $finance, $post);
    } else {
      return array_merge($pre, $post);
    }
  }

  /**
   * Export the description for the table tabs.
   *
   * @param null|array $participantFields
   *
   * @param bool $useFinanceTab
   *
   * @return array
   */
  public function tableTabs(?array $participantFields = null, bool $useFinanceTab = false)
  {
    $dfltTabs = $this->defaultTableTabs($useFinanceTab);

    if (!is_array($participantFields)) {
      return $dfltTabs;
    }

    $extraTabs = array();
    foreach ($participantFields as $field) {
      if (empty($field['Tab'])) {
        continue;
      }

      $extraTab = $field['Tab'];
      foreach ($dfltTabs as $tab) {
        if ($extraTab === $tab['id'] ||
            $extraTab === (string)$tab['name']) {
          $extraTab = false;
          break;
        }
      }
      if ($extraTab !== false) {
        $extraTabs[] = [
          'id' => $extraTab,
          'name' => $this->l->t($extraTab),
          'tooltip' => $this->toolTipsService['participant-fields-extra-tab'],
        ];
      }
    }

    return array_merge($dfltTabs, $extraTabs);
  }
}
