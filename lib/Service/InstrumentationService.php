<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Service;

use Ramsey\Uuid\Uuid;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Legacy\Util as DataBaseUtil;

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
  private $toolTipsService;

  public function __construct(
    ConfigService $configService
    , ToolTipsService $toolTipsService
    , EntityManager $entityManager
  ) {
    $this->configService = $configService;
    $this->toolTipsService = $toolTipsService;
    $this->entityManager = $entityManager;
    $this->connection = $this->entityManager->getConnection();
    $this->l = $this->l10n();
  }

  /**
   * Generate a dummy musician entity which can be used during
   * (email-)form validation and similar things.
   *
   * The musician will be created as disabled or soft deleted.
   *
   * @return Entities\Musician
   */
  public function getDummyMusician():Entities\Musician
  {
    // disable "deleted" filter
    $this->disableFilter('soft-deleteable');

    $musiciansRepository = $this->getDatabaseRepository(Entities\Musician::class);
    /** @var Entities\Musician $dummy */
    $dummy = $musiciansRepository->findOneBy([ 'uuid' => Uuid::NIL ]);
    if (empty($dummy)) {
      $dummy = Entities\Musician::create()
             ->setUuid(Uuid::NIL)
             ->setSurName($this->l->t('Doe'))
             ->setFirstName($this->l->t('John'))
             ->setCountry('AQ')
             ->setCity($this->l->t('Nowhere'))
             ->setStreet($this->l->t('42, Undiscoverable'))
             ->setPostalCode('Z-7')
             ->setEmail($this->getConfigValue('emailtestaddress', 'john.doe@nowhere.tld'))
             ->setDeleted(new \DateTimeImmutable);
      $this->persist($dummy);
    }
    if ($dummy->getSepaBankAccounts()->count() == 0) {
      // also generate a dummy bank account
      $bankAccount = (new Entities\SepaBankAccount)
                   ->setMusician($dummy)
                   ->setIban('DE02700100800030876808')
                   ->setBic('PBNKDEFF')
                   ->setBlz('70010080')
                   ->setBankAccountOwner($dummy->getPublicName())
                   ->setSequence(1)
                   ->setDeleted(new \DateTimeImmutable);
      $dummy->getSepaBankAccounts()->add($bankAccount);
      $this->persist($bankAccount);
    }
    $this->flush();

    return $dummy;
  }

  /**
   * @todo DetailedInstrumentationService? Maybe overkill
   */
  public function tableTabId($idOrName)
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
   */
  public function defaultTableTabs($useFinanceTab = false)
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

  /**Export the description for the table tabs. */
  public function tableTabs($participantFields = false, $useFinanceTab = false)
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

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
