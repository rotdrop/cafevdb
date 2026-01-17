<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine
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

use OCP\IRequest;


use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipationContext as ParticipationContext;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipationStatus as ParticipationStatus;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ContactsService;
use OCA\CAFEVDB\Service\Finance\InstrumentInsuranceService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\MailingListsService;
use OCA\CAFEVDB\Service\MusicianService;
use OCA\CAFEVDB\Service\PhoneNumberService;
use OCA\CAFEVDB\Service\ToolTipsService;

/**Table generator for Musicians table. */
class AddMusicians extends Musicians
{
  use FieldTraits\ProjectEntityTrait;
  use FieldTraits\ProjectModeNavigationItemTrait;

  const TEMPLATE = 'add-musicians';

  protected ?ParticipationContext $participationContext = null;

  /** {@inheritdoc} */
  public function __construct(
    ConfigService $configService,
    EntityManager $entityManager,
    IRequest $request,
    PHPMyEdit $phpMyEdit,
    PageNavigation $pageNavigation,
    ToolTipsService $toolTipsService,
    //
    ContactsService $contactsService,
    GeoCodingService $geoCodingService,
    InstrumentInsuranceService $insuranceService,
    MailingListsService $listsService,
    MusicianService $musicianService,
    PhoneNumberService $phoneNumberService,
  ) {
    parent::__construct(
      configService: $configService,
      entityManager: $entityManager,
      request: $request,
      pme: $phpMyEdit,
      pageNavigation: $pageNavigation,
      toolTipsService: $toolTipsService,
      //
      contactsService: $contactsService,
      geoCodingService: $geoCodingService,
      insuranceService: $insuranceService,
      listsService: $listsService,
      musicianService: $musicianService,
      phoneNumberService: $phoneNumberService,
    );

    $this->findProject(enforce: true);
    if ($this->request->getParam(PersistentCGIKeys::PARTICIPATION_CONTEXT) ?? null) {
      $this->participationContext = ParticipationContext::get($this->request->getParam(PersistentCGIKeys::PARTICIPATION_CONTEXT));
    }
  }

  /** {@inheritdoc} */
  public function shortTitle(): string
  {
    $shortTitle = parent::commonShortTitle();
    if ($shortTitle === null) {
      $shortTitle = $this->participationContext == ParticipationContext::ASSOCIATES
        ? $this->l->t('Add business partners and associates to "%s"', $this->projectName)
        : $this->l->t('Add musicians to "%s"', $this->projectName);
    }
    return $shortTitle;
  }

  /** {@inheritdoc} */
  public function render(bool $execute = true):void
  {
    $this->joinStructure = array_merge(
      $this->joinStructure,
      [
        self::PROJECT_PARTICIPANTS_TABLE => [
          'entity' => Entities\ProjectParticipant::class,
          'identifier' => [
            'project_id' => [
              'value' => $this->projectId,
            ],
            'musician_id' => 'id',
          ],
          'column' => 'musician_id',
          'flags' => self::JOIN_READONLY,
        ],
        self::PROJECT_INSTRUMENTS_TABLE => [
          'entity' => Entities\ProjectInstrument::class,
          'identifier' => [
            'project_id' => [
              'value' => $this->projectId,
            ],
            'musician_id' => 'id',
            'voice' => false,
            'instrument_id' => false,
          ],
          'column' => 'instrument_id',
          'flags' => self::JOIN_READONLY,
        ],
        self::INSTRUMENT_FAMILIES_JOIN_TABLE . self::VALUES_TABLE_SEP . 'project' => [
          'entity' => null,
          'identifier' => [
            'instrument_id' => [
              'table' => self::PROJECT_INSTRUMENTS_TABLE,
              'column' => 'instrument_id',
            ],
            'instrument_family_id' => false,
          ],
          'column' => 'instrument_id',
          'flags' => self::JOIN_READONLY,
        ],
        self::INSTRUMENT_FAMILIES_TABLE . self::VALUES_TABLE_SEP . 'project' => [
          'entity' => Entities\InstrumentFamily::class,
          'sql' => 'SELECT
  __t1.instrument_id AS instrument_id,
  GROUP_CONCAT(DISTINCT __t2.family) AS family
FROM ' . self::INSTRUMENT_FAMILIES_JOIN_TABLE . ' __t1
INNER JOIN ' . self::INSTRUMENT_FAMILIES_TABLE . ' __t2
ON __t1.instrument_family_id = __t2.id
  AND __t2.family = "' . Entities\ProjectInstrument::NOT_AN_INSTRUMENT_FAMILY . '"
GROUP BY __t1.instrument_id',
          'identifier' => [
            'instrument_id' => [
              'table' => self::PROJECT_INSTRUMENTS_TABLE,
              'column' => 'instrument_id',
            ],
          ],
          'column' => 'instrument_id',
          'flags' => self::JOIN_READONLY,
        ],
      ],
    );
    // $this->logInfo('JOIN STRUCTURE ' . print_r($this->joinStructure, true));
    ['opts' => $opts, 'joinTables' => $joinTables] = parent::generatePMEOptions();
    $opts['cgi']['persist'][PersistentCGIKeys::PROJECT_ID] = $this->projectId;
    $opts['cgi']['persist'][PersistentCGIKeys::PARTICIPATION_CONTEXT] = $this->participationContext->value;

    $this->logDebug('JOIN TABLES ' . print_r($joinTables, true));

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
      array_slice($opts['fdd'], 0, $surNamePos),
      [ 'add_musicians' => $addMusiciansFdd ],
      array_slice($opts['fdd'], $surNamePos - 1),
    );

    if ($this->participationContext == ParticipationContext::PARTICIPANTS && !$this->pmeBare) {
      $opts['fdd']['organization']['input|LF'] = 'RH';
      $opts['fdd']['job_title']['input|LF'] = 'RH';
      $opts['fdd']['po_box']['input|LF'] = 'RH';
    }

    $this->logDebug('FDD ARRAY ' . print_r(array_keys($opts['fdd']), true));

    ++$opts['cgi']['persist'][PersistentCGIKeys::PARTICIPATION_STATUS_FDD_INDEX];
    ++$opts['cgi']['persist'][PersistentCGIKeys::INSTRUMENTS_FDD_INDEX];

    // Filter out already registered musicians
    $opts[PHPMyEdit::OPT_HAVING]['AND'] = [];

    $projectsJoin = $joinTables[self::PROJECT_PARTICIPANTS_TABLE];
    $instrumentFamiliesJoin = $joinTables[self::INSTRUMENT_FAMILIES_TABLE . self::VALUES_TABLE_SEP . 'project'];
    $instrumentFamily = "COALESCE($instrumentFamiliesJoin.family, '')";
    $participationStatus = "{$projectsJoin}.participation_status";
    $associated = ParticipationStatus::ASSOCIATED->value;
    $notAnInstrumentFamily = Entities\ProjectINstrument::NOT_AN_INSTRUMENT_FAMILY;
    if ($this->participationContext == ParticipationContext::ASSOCIATES) {
      // INCLUDE IF
      // - not registered
      // -   OR NOT (associated OR not-an-instrument)
      //
      // Note that due to "LEFT JOIN" participation_status and instrumentFamily
      // will be NULL for all not-registered musicians.
      $opts[PHPMyEdit::OPT_HAVING]['AND'] = '('
        . "({$projectsJoin}.project_id IS NULL)"
        . " OR "
        . "({$projectsJoin}.deleted IS NOT NULL OR NOT {$projectsJoin}.project_id = {$this->projectId})"
        . " OR NOT "
        . "($participationStatus = '$associated' OR $instrumentFamily = '$notAnInstrumentFamily')"
        . ")";
    } else {
      // INCLUDE IF
      // - not registered
      // - OR associated
      $opts[PHPMyEdit::OPT_HAVING]['AND'] = '('
        . "({$projectsJoin}.project_id IS NULL)"
        . " OR "
        . "({$projectsJoin}.deleted IS NOT NULL OR NOT {$projectsJoin}.project_id = {$this->projectId})"
        . " OR "
        . "($participationStatus = '$associated')"
        // . " OR "
        // . "($instrumentFamily = '$notAnInstrumentFamily')"
        . ")";
    }

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
