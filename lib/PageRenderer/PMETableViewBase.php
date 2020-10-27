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

namespace OCA\CAFEVDB\PageRenderer;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types as DBTypes;

/** Base for phpMyEdit based table-views. */
abstract class PMEPageRendererBase extends Renderer implements IPageRenderer
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  protected $requestParameters;

  protected $toolTipsService;

  protected $l;

  protected $pme;

  protected $pmeBare;

  protected $pmeRecordId;

  protected $showDisabled;

  protected $pmeOptions;

  protected $musicianId;

  protected $projectId;

  protected $projectName;

  protected $template;

  protected $recordsPerPage;

  protected $defaultFDD;

  protected function __construct(
    ConfigService $configService,
    RequestParameterService $requestParameters,
    EntityManager $entityManager,
    PHPMyEdit $phpMyEdit,
    ToolTipsService $toolTipsService
  ) {
    $this->configService = $configService;
    $this->requestParameters = $requestParameters;
    $this->entityManager = $entityManager;
    $this->pme = $phpMyEdit;
    $this->toolTipsService = $toolTipsService;
    $this->l = $this->l10n();

    $this->pmeBare = false;
    $this->pmeRecordId = $this->pme->getCGIRecordId();
    $this->showDisabled = $this->getUserValue('showdisabled', false) === 'on';

    $this->defaultFDD = $this->createDefaultFDD();

    $cgiDefault = [
      'Template' => 'blog',
      'MusicianId' => -1,
      'ProjectId' => -1,
      'ProjectName' => false,
      'RecordsPerPage' => $this->getUserValue('pagerows', 20),
    ];

    $this->pmeOptions = [ 'cgi' => [ 'persist' => [] ] ];
    foreach ($cgiDefault as $key => $default) {
      $this->pmeOptions['cgi']['persist'][$key] =
        $this->{lcFirst($key)} =
        $this->requestParameters->getParam($key, $default);
    }

    // @TODO: the following should be done only on demand and is
    // somewhat chaotic.

    // List of instruments
    $this->instrumentInfo =
      $this->getDatabaseRepository(ORM\Entities\Instrument::class)->describeALL();
    $this->instruments = $this->instrumentInfo['byId'];
    $this->groupedInstruments = $this->instrumentInfo['nameGroups'];
    $this->instrumentFamilies =
      $this->getDatabaseRepository(ORM\Entities\InstrumentFamily::class)->values();
    $this->memberStatus = (new DBTypes\EnumMemberStatus)->getValues();
    $this->memberStatusNames = [
      'regular' => strval($this->l->t('regular musician')),
      'passive' => strval($this->l->t('passive member')),
      'soloist' => strval($this->l->t('soloist')),
      'conductor' => strval($this->l->t('conductor')),
      'temporary' => strval($this->l->t('temporary musician'))
      ];
    foreach ($this->memberStatus as $tag) {
      if (!isset($this->memberStatusNames[$tag])) {
        $this->memberStatusNames[$tag] = strval($this->l->t(tag));
      }
    }
    if (false) {
      // Dummies to keep the translation right.
      $this->l->t('regular');
      $this->l->t('passive');
      $this->l->t('soloist');
      $this->l->t('conductor');
      $this->l->t('temporary');
    }
  }

  /** Set table-navigation enable/disable. */
  public function navigation($enable)
  {
    $this->pmeBare = !$enable;
  }

  /** Run underlying table-manager (phpMyEdit for now). */
  public function execute($opts = [])
  {
    $this->pme->execute($opts);
  }

  /** Short title for heading. */
  // public function shortTitle();

  /** Header text informations. */
  // public function headerText();

  /** Show the underlying table. */
  // public function render();

  /**Are we in add mode? */
  public function addOperation()
  {
    return $this->pme->add_operation();
  }

  /**Are we in change mode? */
  public function changeOperation()
  {
    return $this->pme->change_operation();
  }

  /**Are we in copy mode? */
  public function copyOperation()
  {
    return $this->pme->copy_operation();
  }

  /**Are we in view mode? */
  public function viewOperation()
  {
    return $this->pme->view_operation();
  }

  /**Are we in delete mode?*/
  public function deleteOperation()
  {
    return $this->pme->delete_operation();
  }

  public function listOperation()
  {
    return $this->pme->list_operation();
  }

  private function createDefaultFDD()
  {
    $fdd = [
      'email' => [
        'name' => $this->l->t('Em@il'),
        'css'      => [ 'postfix' => ' email' ],
        'URL'      => 'mailto:$link?recordId=$key',
        'URLdisp'  => '$value',
        'select'   => 'T',
        'maxlen'   => 768,
        'sort'     => true,
        'nowrap'   => true,
        'escape'   => true,
      ],
      'money' => [
        'name' => $this->l->t('Fees').'<BR/>('.$this->l->t('expenses negative').')',
        'mask'  => '%02.02f'.' &euro;',
        'css'   => ['postfix' => ' money'],
        //'align' => 'right',
        'select' => 'N',
        'maxlen' => '8', // NB: +NNNN.NN = 8
        'escape' => false,
        'sort' => true,
      ],
      'datetime' => [
        'select'   => 'T',
        'maxlen'   => 19,
        'sort'     => true,
        'datemask' => 'd.m.Y H:i:s',
        'css'      => ['postfix' => ' datetime'],
      ],
      'date' => [
        'name' => strval($this->l->t('birthday')),
        'select'   => 'T',
        'maxlen'   => 10,
        'sort'     => true,
        'css'      => ['postfix' => ' birthday date'],
        'datemask' => 'd.m.Y',
      ]
    ];
    $fdd['birthday'] = $fdd['date'];

    return $fdd;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
