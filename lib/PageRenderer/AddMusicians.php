<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\PageRenderer;

use InvalidArgumentException;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ContactsService;
use OCA\CAFEVDB\Service\Finance\InstrumentInsuranceService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\MailingListsService;
use OCA\CAFEVDB\Service\MusicianService;
use OCA\CAFEVDB\Service\PhoneNumberService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;

/**Table generator for Musicians table. */
class AddMusicians extends Musicians
{
  const TEMPLATE = parent::ADD_TEMPLATE;

  /** @var Entities\Project */
  private Entities\Project $project;

  /** {@inheritdoc} */
  public function __construct(
    ConfigService $configService,
    RequestParameterService $requestParameters,
    EntityManager $entityManager,
    PHPMyEdit $phpMyEdit,
    ToolTipsService $toolTipsService,
    PageNavigation $pageNavigation,
    GeoCodingService $geoCodingService,
    ContactsService $contactsService,
    PhoneNumberService $phoneNumberService,
    InstrumentInsuranceService $insuranceService,
    MusicianService $musicianService,
    MailingListsService $listsService,
  ) {
    parent::__construct(
      self::TEMPLATE,
      $configService,
      $requestParameters,
      $entityManager,
      $phpMyEdit,
      $toolTipsService,
      $pageNavigation,
      $geoCodingService,
      $contactsService,
      $phoneNumberService,
      $insuranceService,
      $musicianService,
      $listsService,
    );

    if (!($this->projectId > 0)) {
      throw InvalidArgumentException($this->l->t('Required request parameter "projectId" is not set.'));
    }
    $this->project = $this->getDatabaseRepository(Entities\Project::class)->find($this->projectId);
    if (empty($this->projectName)) {
      $this->projectName = $this->project->getName();
    }
  }

  /** {@inheritdoc} */
  public function shortTitle()
  {
    return parent::commonShortTitle() ?? $this->l->t("Add musicians to the project `%s'", [ $this->projectName ]);
  }

  /*** {@inheritdoc} */
  public static function navigationItem(?int $projectId = null, ?string $projectName = null):array
  {
    return array_merge(
      parent::navigationItem($projectId, $projectName), [
        'templateParameters' => [ 'projectId' => $projectId, 'projectName' =>  $projectName ],
      ]);
  }

  /** {@inheritdoc} */
  public function navigationItems():array
  {
    return [
      Projects::navigationItem(),
      ProjectParticipants::navigationItem($this->projectId),
      ProjectInstrumentationNumbers::navigationItem($this->projectId),
      Instruments::navigationItem(),
    ];
  }

  /** {@inheritdoc} */
  public function render(bool $execute = true):void
  {
    ['opts' => $opts, 'joinTables' => $joinTables] = parent::generatePMEOptions();

    $bval = strval($this->l->t('Add to %s', [ $this->projectName ]));
    $tip  = strval($this->toolTipsService['page-renderer:musicians:register']);
    $addMusiciansFdd = [
      'tab' => [ 'id' => 'tab-all' ],
      'name' => $this->l->t('Add Musicians'),
      'css' => [ 'postfix' => [ 'register-musician', ], ],
        'select' => 'T',
      'options' => 'VCLR',
      'input' => 'V',
      'sql' => '$main_table.id',
      'php' => function($musicianId, $action, $k, $row, $recordId, $pme) use ($bval, $tip) {
        return '<div class="register-musician">'
          .'  <input type="button"'
          .'         value="'.$bval.'"'
          .'         data-musician-id="'.$musicianId.'"'
          .'         title="'.$tip.'"'
          .'         name="registerMusician"'
          .'         class="register-musician" />'
          .'</div>';
      },
      'escape' => false,
      'nowrap' => true,
      'sort' =>false,
    ];

    $surNamePos = array_search('sur_name', array_keys($opts['fdd']));
    $opts['fdd'] = array_merge(
      array_slice($opts['fdd'], 0, $surNamePos - 1),
      $addMusiciansFdd,
      array_slice($opts['fdd'], $surNamePos - 1),
    );

    ++$opts['cgi']['persist']['memberStatusFddIndex'];
    ++$opts['cgi']['persist']['instrummentsFddIndex'];

    //$key = PHPMyEdit::QUERY_FIELD . $projectsIdx;
    $projectsJoin = $joinTables[self::PROJECT_PARTICIPANTS_TABLE];
    $projectIds = "GROUP_CONCAT(DISTINCT {$projectsJoin}.project_id)";
    $opts[PHPMyEdit::OPT_HAVING]['AND'] = "($projectIds IS NULL OR NOT FIND_IN_SET('{$this->projectId}', $projectIds))";
    $opts['misc']['css']['minor'] = [ 'bulkcommit', 'tooltip-right' ];
    $opts['labels']['Misc'] = strval($this->l->t('Add all to %s', $this->projectName));

    $opts = $this->mergeDefaultOptions($opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }
}
