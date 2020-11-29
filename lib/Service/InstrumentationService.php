<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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
   * Returns an associative array which describes a view which
   * collects various information for the instrumentation / line-up of
   * a project. This is rather a maintenance / setup / migration
   * service function.
   *
   * This structure is also used in the PME-stuff in
   * detailed-instrumentation.php to group the fields of the view
   * s.t. update queries can be split into updates for single tables
   * (either Besetzungen or Musiker). mySQL allows write-through
   * through certain views, but only if the target is a single table.
   *
   * Information about particular projects can be selected with a
   * WHERE query with the project Id as search criterion.
   */
  public static function instrumentationJoinStructure()
  {
    $viewStructure = [
      // Principal key is still the key from the Besetzungen ==
      // Instrumentation table.
      'Id' => [ 'table' => 'Besetzungen',
                'tablename' => 'b',
                'column' => true,
                'key' => true,
                'groupby' => true,
                'join' => [ 'type' => 'LEFT' ],
      ],

      'MusicianId' => [
        'table' => 'Musicians',
        'tablename' => 'm',
        'column' => 'Id',
        'key' => true,
        'join' => [
          'type' => 'LEFT',
          'condition' => 'm.Id = b.MusikerId'
        ],
      ],

      'ProjectId' => [
        'table' => 'b',
        'column' => 'ProjektId',
        'join' => [ 'type' => 'LEFT' ],
      ],

      'Projects' => [
        'table' => 'Besetzungen',
        'tablename' => 'b2',
        'column' => "GROUP_CONCAT(DISTINCT p.Name ORDER BY p.Name ASC SEPARATOR ',')",
        'verbatim' => true,
        'join' => [
          'type' => 'LEFT',
          'condition' => 'm.Id = b2.MusikerId
  LEFT JOIN Projects p
  ON b2.ProjektId = p.Id'
        ],
      ],

      'ProjectCount' => [
        'tablename' => 'b2',
        'column' => 'COUNT(DISTINCT p.Id)',
        'verbatim' => true,
      ],

      'MusicianInstrumentKey' => [
        'table' => 'MusicianInstrument',
        'tablename' => 'mi',
        'key' => true,
        'column' => "GROUP_CONCAT(DISTINCT mi.id ORDER BY i2.Sortierung ASC SEPARATOR ',')",
        'verbatim' => true,
        'join' => [
          'type' => 'LEFT',
          'condition' => 'mi.musician_id = b.MusikerId'
        ],
      ],

      'MusicianInstrumentId' => [
        'table' => 'Instruments',
        'tablename' => 'i2',
        'column' => "GROUP_CONCAT(DISTINCT i2.Id ORDER BY i2.Sortierung ASC SEPARATOR ',')",
        'verbatim' => true,
        'join' => [
          'type' => 'LEFT',
          'condition' => 'mi.instrument_id = i2.Id',
        ],
      ],

      'MusicianInstrument' => [
        'table' => 'i2',
        'column' => "GROUP_CONCAT(DISTINCT i2.Instrument ORDER BY i2.Sortierung ASC SEPARATOR ',')",
        'verbatim' => true,
        'join' => [ 'type' => 'LEFT' ],
      ],

      'MusicianInstrumentCount' => [
        'table' => 'i2',
        'column' => "COUNT(DISTINCT i2.Id)",
        'verbatim' => true,
        'join' => [ 'type' => 'LEFT' ],
      ],

      'ProjectInstrumentKey' => [
        'table' => 'ProjectInstruments',
        'tablename' => 'pi',
        'key' => true,
        'column' => 'Id',
        'join' => [
          'type' =>'LEFT',
          'condition' => 'pi.InstrumentationId = b.Id'
        ],
      ],

      'ProjectInstrumentId' => [
        'table' => 'pi',
        'column' => 'InstrumentId',
        'groupby' => true,
        'join' => [ 'type' =>'LEFT' ],
      ],

      'Voice' => [
        'table' => 'pi',
        'column' => true,
        'join' => [ 'type' => 'LEFT' ],
        'sort' => 'ASC',
      ],

      'SectionLeader' => [
        'table' => 'pi',
        'column' => true,
        'join' => [ 'type' => 'LEFT' ],
        'sort' => 'DESC',
      ],

      'ProjectInstrument' => [
        'table' => 'Instruments',
        'tablename' => 'i',
        'column' => 'Instrument',
        'join' => [
          'type' =>'LEFT',
          'condition' => 'pi.InstrumentId = i.Id',
        ],
      ],

      'InstrumentFamily' => [
        'table' => 'instrument_family',
        'tablename' => 'if_link',
        'column' => "GROUP_CONCAT(DISTINCT if_tb.family ORDER BY if_tb.family ASC SEPARATOR ',')",
        'verbatim' => true,
        'join' => [
          'type' => 'LEFT',
          'condition' => 'i.Id = if_link.instrument_id
  LEFT JOIN InstrumentFamilies if_tb
  ON if_link.family_id = if_tb.id AND NOT if_tb.disabled = 1'
        ],
      ],

      'InstrumentOrdering' => [
        'table' => 'i',
        'column' => 'Sortierung',
        'join' => [ 'type' => 'LEFT' ],
        'sort' => 'ASC',
      ],

      'Registration' => [
        'table' => 'b',
        'column' => 'Anmeldung',
        'join' => [ 'type' => 'LEFT' ],
      ],

      'Disabled' => [
        'table' => 'b',
        'column' => true,
        'join' => [ 'type' => 'LEFT' ],
      ],

      'Name' => [
        'table' => 'm',
        'column' => true,
        'join' => [ 'type' => 'LEFT' ],
        'sort' => 'ASC',
      ],

      'FirstName' => [
        'table' => 'm',
        'column' => 'Vorname',
        'join' => [ 'type' => 'LEFT' ],
        'sort' => 'ASC',
      ],

      'Email' => [
        'table' => 'm',
        'column' => true,
        'join' => [ 'type' => 'LEFT' ],
      ],

      'MobilePhone' => [
        'table' => 'm',
        'column' => true,
        'join' => [ 'type' => 'LEFT' ],
      ],

      'FixedLinePhone' => [
        'table' => 'm',
        'column' => true,
        'join' => [ 'type' => 'LEFT' ],
      ],

      'Street' => [
        'table' => 'm',
        'column' => 'Strasse',
        'join' => [ 'type' => 'LEFT' ],
      ],

      'ZIPCode' => [
        'table' => 'm',
        'column' => 'Postleitzahl',
        'join' => [ 'type' => 'LEFT' ],
      ],

      'City' => [
        'table' => 'm',
        'column' => 'Stadt',
        'join' => [ 'type' => 'LEFT' ],
      ],

      'Coutry' => [
        'table' => 'm',
        'column' => 'Land',
        'join' => [ 'type' => 'LEFT' ],
      ],

      'Fees' => [
        'table' => 'b',
        'column' => 'Unkostenbeitrag',
        'join' => [ 'type' => 'LEFT' ],
      ],

      'PrePayment' => [
        'table' => 'b',
        'column' => 'Anzahlung',
        'join' => [ 'type' => 'LEFT' ],
       ],

      'AmountPaid' => [
        'table' => 'ProjectPayments',
        'tablename' => 'f',
        'column' => 'IFNULL(SUM(IF(b.Id = b2.Id, f.Amount, 0)), 0)
/
IF(i2.Id IS NULL, 1, COUNT(DISTINCT i2.Id))',
        'verbatim' => true,
        'join' => [
          'type' =>'LEFT',
          'condition' => 'f.InstrumentationId = b.Id'
        ],
      ],

      'PaidCurrentYear' => [
        'tablename' => 'f',
        'column' => 'IFNULL(SUM(IF(b.Id = b2.Id AND YEAR(NOW()) = YEAR(f.DateOfReceipt), f.Amount, 0)), 0)
/
IF(i2.Id IS NULL, 1, COUNT(DISTINCT i2.Id))',
        'verbatim' => true,
        'join' => [ 'type' => 'LEFT' ],
      ],

      'DebitNote' => [
        'table' => 'b',
        'column' => 'LastSchrift',
        'join' => [ 'type' => 'LEFT' ],
      ],

      'ProjectRemarks' => [
        'table' => 'b',
        'column' => 'Bemerkungen',
        'join' => [ 'type' => 'LEFT' ],
      ],

      'Language' => [
        'table' => 'm',
        'column' => 'Sprachpräferenz',
        'join' => [ 'type' => 'LEFT' ]
      ],

      'Birthday' => [
        'table' => 'm',
        'column' => 'Geburtstag',
        'join' => [ 'type' => 'LEFT' ],
      ],

      'MemberStatus' => [
        'table' => 'm',
        'column' => true,
        'join' => [ 'type' => 'LEFT' ],
      ],

      'Remarks' => [
        'table' => 'm',
        'column' => true,
        'join' => [ 'type' => 'LEFT' ],
      ],

      'Portrait' => [
        'table' => 'MusicianPhoto',
        'tablename' => 'mp',
        'column' => "CONCAT('data:',img.mime_type,';base64,',TO_BASE64(id.data))",
        'verbatim' => true,
        'join' => [
          'type' => 'LEFT',
          'condition' => 'm.Id = mp.owner_id
  LEFT JOIN Images img
  ON mp.image_id = img.id
  LEFT JOIN ImageData id
  ON img.image_data_id = id.id'
        ],
      ],

      'UUID' => [
        'table' => 'm',
        'column' => true,
        'join' => [ 'type' => 'LEFT' ],
      ],

      'Updated' => [
        'table' => 'm',
        'column' => true,
        'join' => [ 'type' => 'LEFT' ],
      ],

    ];

    $tableAlias = [];
    foreach ($viewStructure as $column => $data) {
      // here table and tablename neeed to be defined correctly, if
      // both are given.
      if (isset($data['table']) && isset($data['tablename'])) {
        $tableAlias[$data['tablename']] = $data['table'];
      }
    }
    foreach ($viewStructure as $column => &$data) {
      if (!isset($data['key'])) {
        $data['key'] = false;
      }
      isset($data['table']) || $data['table'] = $data['tablename'];
      $table = $data['table'];
      if (isset($tableAlias[$table])) {
        // switch, this is the alias
        $data['table'] = $tableAlias[$table];
        $data['tablename'] = $table;
      }
      isset($data['tablename']) || $data['tablename'] = $data['table'];
    }

    return $viewStructure;
  }

  public static function musicianPhotoJoinStructure()
  {
    $viewStructure = [
      // Principal key is still the key from the Besetzungen ==
      // Instrumentation table.
      'id' => [
        'table' => 'MusicianPhoto',
        'tablename' => 'mp',
        'column' => true,
        'key' => true,
        'join' => [ 'type' => 'LEFT' ],
        'sort' => 'ASC',
      ],

      'musician_id' => [
        'table' => 'mp',
        'column' => 'owner_id',
        'join' => [ 'type' => 'LEFT' ],
      ],

      'image_id' => [
        'table' => 'Images',
        'tablename' => 'img',
        'column' => 'id',
        'join' => [
          'type' => 'LEFT',
          'condition' => 'mp.image_id = img.id',
        ],
      ],

      'mime_type' => [
        'table' => 'img',
        'column' => true,
        'join' => [ 'type' => 'LEFT' ],
      ],

      'width' => [
        'table' => 'img',
        'column' => true,
        'join' => [ 'type' => 'LEFT' ],
      ],

      'height' => [
        'table' => 'img',
        'column' => true,
        'join' => [ 'type' => 'LEFT' ],
      ],

      'md5' => [
        'table' => 'img',
        'column' => true,
        'join' => [ 'type' => 'LEFT' ],
      ],

      'data' => [
        'table' => 'ImageData',
        'tablename' => 'id',
        'column' => 'TO_BASE64(id.data)',
        'verbatim' => true,
        'join' => [
          'type' => 'LEFT',
          'condition' => 'id.id = img.image_data_id',
        ],
      ],

    ];

    return $viewStructure;
  }

  public function generateJoinSql($slug)
  {
    $method = lcfirst($slug);
    $method .= 'JoinStructure';
    $joinStructure = self::$method();
    return DataBaseUtil::generateJoinSelect($joinStructure);
  }

  private function createJoinTableView($slug)
  {
    $joinSelect = $this->generateJoinSql($slug);
    $viewQuery = 'CREATE OR REPLACE VIEW `'.lcfirst($slug).'View` AS
'.$joinSelect;
    $this->logInfo('Try to create view with query '.$viewQuery);
    try {
      $this->connection->executeQuery($viewQuery);
    } catch (\Throwable $t) {
      throw new \Exception($this->l->t('Unable to create view for slug `%s`', [ $slug ]), $t->getCode(), $t);
    }
    return true;
  }

  /**
   * Generate some convenience views. This is rather a setup function.
   *
   * @todo Check whether this is really needed ...
   */
  public function createJoinTableViews()
  {
    $this->getDatabaseRepository(Entities\MusicianPhoto::class)->joinTable();

    $views = [
      'instrumentation',
      'musicianPhoto',
    ];
    foreach ($views as $viewSlug) {
      $this->createJoinTableView($viewSlug);
    }
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
  public function tableTabs($extraFields = false, $useFinanceTab = false)
  {
    $dfltTabs = $this->defaultTableTabs($useFinanceTab);

    if (!is_array($extraFields)) {
      return $dfltTabs;
    }

    $extraTabs = array();
    foreach ($extraFields as $field) {
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
          'tooltip' => $this->toolTipsService['extra-fields-extra-tab'],
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
