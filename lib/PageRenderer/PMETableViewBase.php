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

namespace OCA\CAFEVDB\PageRenderer;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types as DBTypes;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\UndoableRunQueue;
use OCA\CAFEVDB\Common\GenericUndoable;
use OCA\CAFEVDB\Common\IUndoable;
use OCA\CAFEVDB\Common\UndoableFolderRename;

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

/** Base for phpMyEdit based table-views. */
abstract class PMETableViewBase extends Renderer implements IPageRenderer
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  protected const JOIN_FLAGS_NONE = 0x00;
  protected const JOIN_MASTER = 0x01;
  protected const JOIN_READONLY = 0x02;
  protected const JOIN_GROUP_BY = 0x04;
  protected const JOIN_REMOVE_EMPTY = 0x08;

  const MUSICIANS_TABLE = 'Musicians';
  const PROJECTS_TABLE = 'Projects';
  const FIELD_TRANSLATIONS_TABLE = 'TableFieldTranslations';
  const SEPA_BANK_ACCOUNTS_TABLE = 'SepaBankAccounts';
  const SEPA_DEBIT_MANDATES_TABLE = 'SepaDebitMandates';
  const SEPA_BULK_TRANSACTIONS_TABLE = 'SepaBulkTransactions';
  const SEPA_BULK_TRANSACTION_DATA_TABLE = 'SepaBulkTransactionData';
  const PROJECT_PARTICIPANTS_TABLE = 'ProjectParticipants';
  const PROJECT_PARTICIPANT_FIELDS_TABLE = 'ProjectParticipantFields';
  const PROJECT_PARTICIPANT_FIELDS_DATA_TABLE = 'ProjectParticipantFieldsData';
  const PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE = 'ProjectParticipantFieldsDataOptions';
  const INSTRUMENTS_TABLE = 'Instruments';
  const INSTRUMENT_INSURANCES_TABLE = 'InstrumentInsurances';
  const PROJECT_PAYMENTS_TABLE = 'ProjectPayments';
  const COMPOSITE_PAYMENTS_TABLE = 'CompositePayments';
  const PROJECT_INSTRUMENTS_TABLE = 'ProjectInstruments';
  const PROJECT_INSTRUMENTATION_NUMBERS_TABLE = 'ProjectInstrumentationNumbers';
  const MUSICIAN_INSTRUMENTS_TABLE = 'MusicianInstruments';
  const MUSICIAN_PHOTO_JOIN_TABLE = 'MusicianPhoto';
  const FILES_TABLE = 'Files';

  const VALUES_SEP = ',';
  const JOIN_FIELD_NAME_SEPARATOR = ':';
  const JOIN_KEY_SEP = ':';
  const COMP_KEY_SEP = '-';
  const VALUES_TABLE_SEP = '@';
  const COL_QUOTE = '`';

  /** CSS tag for displaying participant fields */
  const CSS_TAG_PROJECT_PARTICIPANT_FIELDS = 'project-participant-fields';
  const CSS_TAG_SHOW_HIDE_DISABLED = 'show-hide-disabled';
  const CSS_TAG_DIRECT_CHANGE = 'direct-change';

  /** @var RequestParameterService */
  protected $requestParameters;

  /** @var ToolTipsService */
  protected $toolTipsService;

  /** @var IL10N */
  protected $l;

  /** @var PHPMyEdit */
  protected $pme;

  /** @var bool */
  protected $pmeBare;

  /** @var ?array|mixed */
  protected $pmeRecordId;

  /** @var bool */
  protected $showDisabled;

  /** @var bool */
  protected $expertMode;

  /** @var bool */
  protected $filterVisibility;

  /** @var array default PHPMyEdit options */
  protected $pmeOptions;

  /** @var ?int */
  protected $musicianId;

  /** @var ?int */
  protected $projectId;

  /** @var ?string */
  protected $projectName;

  /** @var int */
  protected $membersProjectId;

  /** @var string */
  protected $template;

  /** @var ?int */
  protected $recordsPerPage;

  /** @var array */
  protected $defaultFDD;

  /** @var PageNavigation */
  protected $pageNavigation;

  /**
   * @var int Number of affected entries, fields or
   * rows. Context dependent, for generating messages.
   */
  protected $changeSetSize = -1;

  /** @var bool Debug web requests */
  protected $debugRequests = false;

  /** @var UndoableRunQueue */
  protected $preCommitActions;

  /**
   * @var array
   * ```
   * [
   *   [
   *     'table' => SQL_TABLE_NAME,
   *     'entity' => DOCTRINE_ORM_ENTITY_CLASS_NAME,
   *     'flags'  => self::JOIN_READONLY|self::JOIN_MASTER|self::JOIN_REMOVE_EMPTY|self::JOIN_GROUP_BY
   *     'column' => column used in select statements
   *     'identifier' => [
   *       TO_JOIN_TABLE_COLUMN => ALREADY_THERE_COLUMN_NAME,
   *       ...
   *     ]
   *   ],
   *   ...
   * ]
   * ```
   */
  protected $joinStructure = [];

  /** @var \ArrayObject flat TABLE_NAME -> PMEjoinXY lookup-table for the
   *  join-tables generated by self::defineJoinStructure().
   */
  protected $joinTables = null;

  /** @var int Minimum numbers value to generate by self::preTrigger(). */
  protected $minimumNumbersValue = 128;

  protected function __construct(
    string $template
    , ConfigService $configService
    , RequestParameterService $requestParameters
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
    , ToolTipsService $toolTipsService
    , PageNavigation $pageNavigation
  ) {
    $this->configService = $configService;
    $this->requestParameters = $requestParameters;
    $this->entityManager = $entityManager;
    $this->pme = $phpMyEdit;
    $this->toolTipsService = $toolTipsService;
    $this->pageNavigation = $pageNavigation;
    $this->l = $this->l10n();

    $this->pmeBare = false;
    $this->pmeRecordId = $this->pme->getCGIRecordId();
    $this->showDisabled = $this->getUserValue('showdisabled', false) === 'on';
    $this->expertMode = $this->getUserValue('expertmode', false) === 'on';
    $this->filterVisibility = $this->getUserValue('filtervisibility', 'off') == 'on';

    $this->debugRequests = 0 != ($this->getConfigValue('debugmode', 0) & ConfigService::DEBUG_REQUEST);

    $this->preCommitActions = new UndoableRunQueue($this->logger(), $this->l10n());

    $this->membersProjectId = $this->getClubMembersProjectId();

    // this is done by the legacy code itself.
    $this->disableFilter('soft-deleteable');

    $this->defaultFDD = $this->createDefaultFDD();

    $cgiDefault = [
      'template' => 'blog',
      'musicianId' => -1,
      'projectId' => -1,
      'projectName' => false,
      'recordsPerPage' => $this->getUserValue('pagerows', 20),
    ];

    $this->pmeOptions = [
      'cgi' => [ 'persist' => [] ],
      'display' => [],
      'css' => [ 'postfix' => [], ],
    ];
    $this->pmeOptions['css']['postfix'][] = $this->showDisabled ? 'show-disabled' : 'hide-disabled';

    foreach ($cgiDefault as $key => $default) {
      $this->pmeOptions['cgi']['persist'][$key] =
        $this->{lcFirst($key)} =
          $this->requestParameters->getParam($key, $default);
    }

    $this->pmeOptions['cgi']['append'][$this->pme->cgiSysName('fl')] = $this->filterVisibility;

    $this->template = $template; // overwrite with child-class supplied value

    $this->pmeOptions[PHPMyEdit::OPT_TRIGGERS]['*']['pre'][] = [ $this, 'preTrigger' ];

    //$this->pmeOptions[PHPMyEdit::OPT_TRIGGERS]['update']['before'][] = [ __CLASS__, 'suspendLoggingTrigger' ];
    $this->pmeOptions[PHPMyEdit::OPT_TRIGGERS]['update']['after'][] =
      $this->pmeOptions[PHPMyEdit::OPT_TRIGGERS]['insert']['after'][] =
      $this->pmeOptions[PHPMyEdit::OPT_TRIGGERS]['copy']['after'][] =
      $this->pmeOptions[PHPMyEdit::OPT_TRIGGERS]['delete']['after'][] = [ __CLASS__, 'resumeLoggingTrigger' ];

    $this->pmeOptions[PHPMyEdit::OPT_TRIGGERS]['update']['before'][] =
      $this->pmeOptions[PHPMyEdit::OPT_TRIGGERS]['copy']['before'][] =
      $this->pmeOptions[PHPMyEdit::OPT_TRIGGERS]['insert']['before'][] =
        [ $this, 'beforeAnythingTrimAnything' ];

    $this->pmeOptions[PHPMyEdit::OPT_TRIGGERS]['update']['before'][] =
       $this->pmeOptions[PHPMyEdit::OPT_TRIGGERS]['insert']['before'][] =
       $this->pmeOptions[PHPMyEdit::OPT_TRIGGERS]['delete']['before'][] =
         function($pme, $op, $step, &$oldvals, &$changed, &$newvals) {
           $this->changeSetSize = count($changed);
           return true;
         };

    $this->pmeOptions[PHPMyEdit::OPT_TRIGGERS]['update']['after'][] = function($pme) {
      $pme->message = $this->l->n(
        '%n data field affected',
        '%n data fields affected',
        $this->changeSetSize);
      return true;
    };

    // @todo: the following should be done only on demand and is
    // somewhat chaotic.

    // List of instruments
    $this->instrumentInfo =
      $this->getDatabaseRepository(ORM\Entities\Instrument::class)->describeALL();
    $this->instruments = $this->instrumentInfo['byId'];
    $this->groupedInstruments = $this->instrumentInfo['nameGroups'];
    $this->instrumentFamilies =
      $this->getDatabaseRepository(ORM\Entities\InstrumentFamily::class)->values();

    // @todo think about how to generate translations
    $this->memberStatus = DBTypes\EnumMemberStatus::toArray();
    foreach ($this->memberStatus as $key => $tag) {
      if (!isset($this->memberStatusNames[$tag])) {
        $this->memberStatusNames[$tag] = $this->l->t('member status '.$tag);
      }
    }

    $this->projectType = DBTypes\EnumProjectTemporalType::toArray();
    foreach ($this->projectType as $key => $tag) {
      if (!isset($this->projectTypeNames[$tag])) {
        $this->projectTypeNames[$tag] = $this->l->t($tag);
      }
    }
  }

  protected function participantFieldMultiplicityNames()
  {
    $multiplicities = array_values(DBTypes\EnumParticipantFieldMultiplicity::toArray());
    foreach ($multiplicities as $tag) {
      $slug = 'extra field '.$tag;
      $result[$tag] = $this->l->t($slug);
    }
    return $result;
  }

  protected function participantFieldDataTypeNames()
  {
    $dataTypes = array_values(DBTypes\EnumParticipantFieldDataType::toArray());
    foreach ($dataTypes as $tag) {
      $slug = 'extra field type '.$tag;
      $result[$tag] = $this->l->t($slug);
    }
    return $result;
  }

  public function template()
  {
    return $this->template;
  }

  protected function setTemplate(string $template)
  {
    $this->template = $template;
  }

  /**Determine if we have the default ordering of rows. */
  public function defaultOrdering()
  {
    if (!isset($this->pme)) {
      return false;
    }
    return empty($this->pme->sfn);
  }

  /** Set table-navigation enable/disable. */
  public function navigation($enable)
  {
    $this->pmeBare = !$enable;
  }

  /**
   * Register a pre-commit action and optionally an associated
   * undo-action. The actions are run after all data-base operation
   * have completed just before the final commit step. If the action
   * succeeds, then its $undoAction will be registered for the case
   * that the final commit throws an exception. In this case all
   * undo-actions will be executed in reverse order.
   *
   * In case of an error $action must throw an \Exception, its return
   * value is ignored.
   *
   * The callables need to run "stand-alone" without parameters.
   *
   * @param mixed $action
   *
   * @param Callable $undo The associated undo-action.
   */
  public function registerPreCommitAction($action, ?Callable $undo = null)
  {
    if (is_callable($action)) {
      $this->preCommitActions->register(new GenericUndoable($action, $undo));
    } else if ($action instanceof IUndoable) {
      $this->preCommitActions->register($action);
    } else  {
      throw new \RuntimeException($this->l->t('$action must be callable or an instance of "%s".', IUndoable::class));
    }
  }

  /**
   * Common case of pre-commit action: rename a folder or file to
   * reflect changes in the data-base.
   */
  public function registerPreCommitRename(string $oldNode, string $newNode)
  {
    $this->registerPreCommitAction(new UndoableFolderRename($oldNode, $newNode));
  }

  /** Run underlying table-manager (phpMyEdit for now). */
  public function execute($opts = [])
  {
    // keep outside the transaction as seemingly this can cause a
    // strange "autocommit" with mysql.
    $this->generateNumbers($this->minimumNumbersValue);
    $this->pme->beginTransaction();
    try {
      $this->pme->execute($opts);
      $this->preCommitActions->executeActions();
      $this->pme->commit();
    } catch (\Throwable $t) {
      $this->logError('Rolling back SQL transaction ...');
      $this->preCommitActions->executeUndo();
      $this->pme->rollBack();
      throw new \Exception($this->l->t('SQL Transaction failed.'), $t->getCode(), $t);
    }
  }

  /**
   * Quick and dirty general export. On each cell a call-back function
   * is invoked with the html-output of that cell.
   *
   * This is just like list_table(), i.e. only the chosen range of
   * data is displayed and in html-display order.
   *
   * @param Callable $cellFilter $line[] = Callback($i, $j, $celldata)
   *
   * @param Callable $lineCallback($i, $line)
   *
   * @param string $css CSS-class to pass to cellDisplay().
   */
  public function export($cellFilter = false, $lineCallback = false, $css = 'noescape')
  {
    $this->pme->export($cellFilter, $lineCallback, $css);
  }

  public function getProjectName() { return $this->projectName; }

  public function getProjectId() { return $this->projectId; }

  public function cssClass():string
  {
    return $this->template;
  }

  /** Short title for heading. */
  public abstract function shortTitle();

  /**
   * Header text informations.
   *
   * @todo Display in popup in order not to bloat the small header space.
   */
  public function headerText()
  {
    return $this->shortTitle();
  }

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

  protected function mergeDefaultOptions(array $opts)
  {
    $opts = Util::arrayMergeRecursive($this->pmeOptions, $opts);
    if ($this->pmeBare) {
      // disable all navigation buttons, probably for html export
      $opts['navigation'] = 'N'; // no navigation
      $opts['options'] = '';
      // Don't display special page elements
      $opts['display'] =  array_merge(
        $opts['display'],
        [
          'form'  => false,
          'query' => false,
          'sort'  => false,
          'time'  => false,
          'tabs'  => false
        ]);
      // Disable sorting buttons
      foreach ($opts['fdd'] as $key => $value) {
        $opts['fdd'][$key]['sort'] = false;
      }
    }
    return $opts;
  }

  /**
   * The following maybe does not belong her, but gives some outdated
   * docs for the field definitions (fdd).
   *
   * Field definitions
   *
   *   Fields will be displayed left to right on the screen in the order in which they
   *   appear in generated list. Here are some most used field options documented.

   *   ['name'] is the title used for column headings, etc.;
   *   ['maxlen'] maximum length to display add/edit/search input boxes
   *   ['trimlen'] maximum length of string content to display in row listing
   *   ['width'] is an optional display width specification for the column
   *   e.g.  ['width'] = '100px';
   *   ['mask'] a string that is used by sprintf() to format field output
   *   ['sort'] true or false; means the users may sort the display on this column
   *   ['strip_tags'] true or false; whether to strip tags from content
   *   ['nowrap'] true or false; whether this field should get a NOWRAP
   *   ['select'] T - text, N - numeric, D - drop-down, M - multiple selection
   *   ['options'] optional parameter to control whether a field is displayed
   *   L - list, F - filter, A - add, C - change, P - copy, D - delete, V - view
   *   Another flags are:
   *   R - indicates that a field is read only
   *   W - indicates that a field is a password field
   *   H - indicates that a field is to be hidden and marked as hidden
   *   ['URL'] is used to make a field 'clickable' in the display
   *   e.g.: 'mailto:$value', 'http://$value' or '$page?stuff';
   *   ['URLtarget']  HTML target link specification (for example: _blank)
   *   ['textarea']['rows'] and/or ['textarea']['cols']
   *   specifies a textarea is to be used to give multi-line input
   *   e.g. ['textarea']['rows'] = 5; ['textarea']['cols'] = 10
   *   ['values'] restricts user input to the specified constants,
   *   e.g. ['values'] = array('A','B','C') or ['values'] = range(1,99)
   *   ['values']['table'] and ['values']['column'] restricts user input
   *   to the values found in the specified column of another table
   *   ['values']['description'] = 'desc_column'
   *   The optional ['values']['description'] field allows the value(s) displayed
   *   to the user to be different to those in the ['values']['column'] field.
   *   This is useful for giving more meaning to column values. Multiple
   *   descriptions fields are also possible. Check documentation for this.
   *
   * @return Some default settings for the PME options.
   */
  private function createDefaultFDD()
  {
    $fdd = [
      'email' => [
        'name' => $this->l->t('Em@il'),
        'css'      => [ 'postfix' => [ 'email', 'clip-long-text', 'short-width', ], ],
        'URL'      => 'mailto:$link?$key',
        'URLdisp'  => '$value',
        'display|LF' => ['popup' => 'data'],
        'select'   => 'T',
        'maxlen'   => 768,
        'sort'     => true,
        'nowrap'   => true,
        'escape'   => true,
      ],
      'money' => [
        'name' => $this->l->t('Fees').'<BR/>('.$this->l->t('expenses negative').')',
        'mask'  => '%02.02f'.' &euro;',
        'css'   => ['postfix' => [ 'money', ], ],
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
        'css'      => [ 'postfix' => [ 'datetime', ], ],
      ],
      'date' => [
        'select'   => 'T',
        'maxlen'   => 10,
        'sort'     => true,
        'css'      => [ 'postfix' => [ 'date', ], ],
        'datemask' => 'd.m.Y',
      ],
      'due_date' => [
        'select'   => 'T',
        'name' => $this->l->t('Due Date'),
        'maxlen'   => 10,
        'sort'     => true,
        'css'      => [ 'postfix' => [ 'date', 'due-date', ], ],
        'datemask' => 'd.m.Y',
      ],
      // Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity
      'deleted' => [
        'name' => $this->l->t('Revoked'),
        'input' => $this->expertMode ? '' : 'R',
        'maxlen' => 10,
        'sort' => true,
        'css' => [ 'postfix' => [ 'revocation-date', 'date',  'show-disabled-shown', 'hide-disabled-hidden', ], ],
        'datemask' => 'd.m.Y',
        'default' => null,
      ],
    ];
    $fdd['birthday'] = $fdd['date'];
    $fdd['birthday']['name'] = $this->l->t('birthday');
    $fdd['birthday']['css']['postfix'][] = 'birthday';
    $fdd['service-fee'] = $fdd['money'];
    $fdd['deposit'] = $fdd['money'];

    return $fdd;
  }

  /**
   * phpMyEdit calls the triggers (callbacks) with the following arguments:
   *
   * @param $pme The phpMyEdit instance
   *
   * @param $op The operation, 'insert', 'update' etc.
   *
   * @param $step 'before' or 'after'
   *
   * @param $oldvals Self-explanatory.
   *
   * @param &$changed Set of changed fields, may be modified by the callback.
   *
   * @param &$newvals Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated
   *
   * This trigger simply removes all unchanged fields.
   *
   * @return bool true if anything still needs to be done.
   *
   * @todo Check whether this is really needed, should not be the case.
   */
  public static function beforeUpdateRemoveUnchanged($pme, $op, $step, &$oldvals, &$changed, &$newvals)
  {
    $newvals = array_intersect_key($newvals, array_fill_keys($changed, 1));
    return count($newvals) > 0;
  }

  /**
   * Disable PME logging if it was enabled.
   */
  public static function suspendLoggingTrigger($pme, $op, $step, &$oldvals, &$changed, &$newvals)
  {
    $pme->setLogging(false);
    return true;
  }

  /**
   * Resume PME logging if it was enabled at all.
   */
  public static function resumeLoggingTrigger($pme, $op, $step, &$oldvals, &$changed, &$newvals)
  {
    $pme->setLogging(true);
    return true;
  }

  /**
   * phpMyEdit calls the triggers (callbacks) with the following arguments:
   *
   * @param $pme The phpMyEdit instance
   *
   * @param $op The operation, 'insert', 'update' etc.
   *
   * @param $step 'before' or 'after'
   *
   * @param $oldvals Self-explanatory.
   *
   * @param &$changed Set of changed fields, may be modified by the callback.
   *
   * @param &$newvals Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated
   *
   * This trigger trims any spaces from the new fields. In order to
   * sanitize old data records this trigger function adds to
   * $changed if trimming changes something. Otherwise
   * self::beforeUpdateRemoveUnchanged() would silently ignore the
   * sanitized values.
   */
  public function beforeAnythingTrimAnything($pme, $op, $step, &$oldvals, &$changed, &$newvals)
  {
    foreach ($newvals as $key => &$value) {
      if (!is_scalar($value)) { // don't trim arrays
        continue;
      }

      // Convert unicode space to ordinary space and trim
      $value = Util::normalizeSpaces($value);

      $fdn = $pme->fdn[$key];
      if ($pme->col_has_multiple($fdn)) {
        $value = preg_replace('/\s*,\s*/', ',', $value);
      }

      // @todo what is this
      if ($op !== 'insert' && ($pme->skipped($fdn) || $pme->readonly($fdn))) {
        continue;
      }

      $chgIdx = array_search($key, $changed);
      if ($chgIdx === false && $oldvals[$key] != $value) {
        $changed[] = $key;
      }
      if ($op == 'insert' && (string)$value === '' && $chgIdx !== false) {
        unset($changed[$chgIdx]);
        $changed = array_values($changed);
        unset($newvals[$key]);
      }
    }

    return true;
  }

  /**
   * The ranking of the mussician's instruments is implicitly stored
   * in the order of the instrument ids. Change the coressponding
   * field to include the ranking explicitly.
   */
  public function extractInstrumentRanking($pme, $op, $step, &$oldValues, &$changed, &$newValues)
  {
    $keyField = $this->joinTableFieldName(self::MUSICIAN_INSTRUMENTS_TABLE, 'instrument_id');
    $rankingField = $this->joinTableFieldName(self::MUSICIAN_INSTRUMENTS_TABLE, 'ranking');
    foreach (['old', 'new'] as $dataSet) {
      $keys = Util::explode(self::VALUES_SEP, Util::removeSpaces(${$dataSet.'Values'}[$keyField ]));
      $ranking = [];
      foreach ($keys as $key) {
        $ranking[] = $key.self::JOIN_KEY_SEP.(count($ranking)+1);
      }
      ${$dataSet.'Values'}[$rankingField] = implode(',', $ranking);
    }

    // as the ordering is implied by the ordering of keys the ranking
    // changes whenever the keys change.
    if (array_search($keyField, $changed) !== false) {
      $changed[] = $rankingField;
    }

    return true;
  }

  /**
   * Find the recursion end-point for a nested join description.
   *
   * @return Just the value of the pivot column.
   */
  private function findJoinColumnPivot(array $joinInfo, string $key)
  {
    $keyInfo = $joinInfo['identifier'][$key];
    if (!empty($keyInfo['table'])) {
      return $this->findJoinColumnPivot(
        $this->joinStructure[$keyInfo['table']], $keyInfo['column']);
    }
    // Remaining possibilities:
    // - main-table column
    // - value false
    // - [ 'value' => VALUE ]
    return $keyInfo;
  }

  /**
   * Before update-trigger which ideally should update all data such
   * that nothing remains to do for phpMyEdit.
   *
   * @param $pme The phpMyEdit instance
   *
   * @param $op The operation, 'insert', 'update' etc.
   *
   * @param $step 'before' or 'after'
   *
   * @param $oldvals Self-explanatory.
   *
   * @param &$changed Set of changed fields, may be modified by the callback.
   *
   * @param &$newvals Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated
   *
   * @bug This function is too hackish and too long.
   * @todo Cleanup. In particular, quite a bit of code is shared with
   * $this->beforeInsertDoInsertAll().
   */
  public function beforeUpdateDoUpdateAll(&$pme, $op, $step, &$oldvals, &$changed, &$newvals)
  {
    // leave time-stamps to the ORM "behaviors"
    Util::unsetValue($changed, 'updated');

    $this->debug('OLDVALS '.print_r($oldvals, true));
    $this->debug('NEWVALS '.print_r($newvals, true));
    $this->debug('CHANGED '.print_r($changed, true));
    $changeSets = [];
    foreach ($changed as $field) {
      $fieldInfo = $this->joinTableField($field);
      $changeSets[$fieldInfo['table']][$fieldInfo['column']] = $field;
    }
    $this->debug('CHANGESETS: '.print_r($changeSets, true));

    $masterTable = null;
    $masterEntity = null; // cache for a reference to the master entity
    foreach ($this->joinStructure as $table => $joinInfo) {
      $changeSet = $changeSets[$table];
      if (empty($changeSet)) {
        continue;
      }
      if ($joinInfo['flags'] & self::JOIN_READONLY) {
        foreach ($changeSet as $column => $field) {
          if (Util::unsetValue($changed, $field) > 0) {
            --$this->changeSetSize;
          }
        }
        continue;
      }
      if ($joinInfo['flags'] & self::JOIN_MASTER) {
        // fill the identifier keys as they are left out for
        // convenience in $this->joinStructure
        foreach ($this->pme->key as $key => $type) {
          $joinInfo['identifier'][$key] = $key;
        }
        $masterTable = $table;
      }
      $this->debug('CHANGESET '.$table.' '.print_r($changeSet, true));
      $entityClass = $joinInfo['entity'];
      $repository = $this->getDatabaseRepository($entityClass);
      $meta = $this->classMetadata($entityClass);

      $multiple = null;
      $identifier = [];
      $identifierColumns = $meta->getIdentifierColumnNames();
      foreach ($identifierColumns as $key) {
        $pivotColumn = $this->findJoinColumnPivot($joinInfo, $key);
        if ($pivotColumn === false) {
          if (!empty($multiple)) {
            throw new \RuntimeException($this->l->t('Table "%s": missing identifier for field "%s" and grouping field "%s" already set.', [ $table, $key, $multiple ]));
          }
          // assume that the 'column' component contains the keys.
          $keyField = $this->joinTableFieldName($joinInfo, $joinInfo['column']);
          $identifier[$key] = [
            'old' => Util::explode(',', Util::removeSpaces($oldvals[$keyField])),
            'new' => Util::explode(',', Util::removeSpaces($newvals[$keyField])),
          ];
          // handle "deleted" information if present. This is meant for disabled instruments and the like
          $deletedField = $this->joinTableFieldName($joinInfo, 'deleted');
          if (!empty($oldvals[$deletedField])) {
            $deletedKeys = Util::explode(',', $oldvals[$deletedField]);
            foreach (array_intersect($deletedKeys, $identifier[$key]['new']) as $deletedKey) {
              $identifier[$key]['old'][] = $deletedKey;
              $changeSet['deleted'] = $deletedField;
            }
            $identifier[$key]['old'] = array_values(array_unique($identifier[$key]['old']));
            $newvals[$deletedField] = implode(',', array_diff($deletedKeys, $identifier[$key]['new']));
          }

          $identifier[$key]['del'] = array_diff($identifier[$key]['old'], $identifier[$key]['new']);
          $identifier[$key]['add'] = array_diff($identifier[$key]['new'], $identifier[$key]['old']);
          $identifier[$key]['rem'] = array_intersect($identifier[$key]['new'], $identifier[$key]['old']);

          Util::unsetValue($changed, $changeSet[$joinInfo['column']]);
          unset($changeSet[$joinInfo['column']]);
          $multiple = $key;
        } else {
          if (!is_array($pivotColumn)) {
            $identifier[$key] = $oldvals[$pivotColumn];
          } else if (isset($pivotColumn['value'])){
            $identifier[$key] = $pivotColumn['value'];
          } else if (isset($pivotColumn['self'])) {
            $selfField = $this->joinTableFieldName($joinInfo, $key);
            $identifier[$key] = [ 'self' => $selfField, ];
          } else {
            throw new \RuntimeException($this->l->t('Field "%s.%s": nested multi-value join tables with unexpected pivot-column: %s.', [ $table, $key, print_r($pivotColumn, true), ]));
          }
        }
      }
      if (!empty($multiple)) {
        $this->debug('IDS '.print_r($identifier, true));
        $this->debug('CHG '.print_r($changeSet, true));

        $dataSets = [
          'del' => 'old',
          'add' => 'new',
          'rem' => 'old', // needs to be "old" in order to identify the existing entity
        ];
        foreach (array_keys($dataSets) as $operation) {
          ${$operation.'Identifier'} = [];
          foreach ($identifier[$multiple][$operation] as $idKey) {
            ${$operation.'Identifier'}[$idKey] = $identifier;
            ${$operation.'Identifier'}[$idKey][$multiple] = $idKey;
          }
          $this->debug('IDENTIFIER '.$operation.': '.print_r(${$operation.'Identifier'}, true));
        }

        // Example ProjectParticipants, voice field:
        // $selfKey == 'voice', $value is then the FQN, here
        // ProjectInstruments:voice
        //
        // NOTE: this will assemble the id-value for the self-field as
        // array. Seemingly ORM find() works with it ATM. If this
        // should change, we have to use just the "old" value in order
        // to perform an update later.
        foreach ($identifier as $selfKey => $value) {
          if (empty($value['self'])) {
            continue;
          }
          // $selfField == ProjectInstruments:voice
          $selfField = $value['self'];
          foreach ($dataSets as $operation => $dataSet) {
            foreach (${$operation.'Identifier'} as $key => &$idValues) {
              $idValues[$selfKey] = null;
            }
            $dataValues = ${$dataSet.'vals'};
            foreach (Util::explodeIndexed($dataValues[$selfField]) as $key => $value) {
              if (isset(${$operation.'Identifier'}[$key])) {
                ${$operation.'Identifier'}[$key][$selfKey] = $value;
              }
            }
            foreach (${$operation.'Identifier'} as $key => &$idValues) {
              if (empty($idValues[$selfKey])) {
                $idValues[$selfKey] = $pme->fdd[$selfField]['default'];
              }
              if ($idValues[$selfKey] === null) {
                throw new \RuntimeException($this->l->t('No value for identifier field "%s / %s".', [$selfKey, $selfField]));
              }
            }
          }
        }

        $this->debug('ADDIDS '.print_r($addIdentifier, true));
        $this->debug('REMIDS '.print_r($remIdentifier, true));
        $this->debug('DELIDS '.print_r($delIdentifier, true));

        if (!empty($joinInfo['association'])) {
          // Many-to-many or similar through join table. We modify the
          // join table indirectly by modifying the master entity's
          // association.

          if (empty($masterEntity)) {
            $masterEntity = $this
              ->getDatabaseRepository($this->joinStructure[$masterTable]['entity'])
              ->find($pme->rec);
            if (empty($masterEntity)) {
              throw new \RuntimeException($this->l->t('Unmable to find master entity for key "%s".', print_r($pme->rec, true)));
            }
          }

          $association = $masterEntity[$joinInfo['association']];
          $this->debug(get_class($association).': '.$association->count());

          // Delete entities by criteria matching Note: this needs
          // that the entity implements the \ArrayAccess interface
          foreach ($identifier[$multiple]['del'] as $del) {
            $id = $delIdentifier[$del];
            $entityId = $meta->extractKeyValues($id);
            foreach ($association->matching(self::criteriaWhere($entityId)) as $entity) {
              $association->removeElement($entity);
            }
          }

          // add entries by adding them to the association of the
          // master entity
          foreach ($identifier[$multiple]['add'] as $add) {
            $id = $addIdentifier[$add];
            $entityId = $meta->extractKeyValues($id);
            $association->add($this->getReference($entityClass, $entityId));
          }

          $masterEntity[$joinInfo['association']] = $association;

          continue; // skip to next field
        }

        // Delete removed entities
        foreach ($identifier[$multiple]['del'] as $del) {
          $id = $delIdentifier[$del];
          $entityId = $meta->extractKeyValues($id);
          $entity = $this->find($entityId);
          $usage  = method_exists($entity, 'usage') ? $entity->usage() : 0;
          $this->debug('Usage is '.$usage);
          $softDeleteable = method_exists($entity, 'isDeleted')
                         && method_exists($entity, 'setDeleted');

          $this->debug('SOFT-DELETEABLE '.(int)$softDeleteable.' HAS USAGE '.(int)method_exists($entity, 'usage'));

          if ($usage > 0) {
            /**
             * @todo needs more logic: disabled things would need to
             * be reenabled instead of adding new stuff. One
             * possibility would be to add disabled things as hidden
             * elements in order to keep them out of the way of the
             * select boxes of the user interface.
             */
            if ($softDeleteable && !$entity->isDeleted()) {
              $this->remove($entity);
            }
          } else {
            if ($softDeleteable && !$entity->isDeleted()) {
              $this->debug('SOFT DELETE');
              $this->remove($entity, true); // soft, need flush
            }
            $this->debug('HARD DELETE '.($softDeleteable && (int)$entity->isDeleted()));
            $this->remove($entity); // hard
          }
          $this->flush();
        }

        $multipleValues = [];
        $this->debug('CHANGESET: ' . print_r($changeSet, true));
        foreach ($changeSet as $column => $field) {
          $this->debug('GET MULTIPLE FOR ' . $column . ' / ' . $field);
          // convention for multiple change-sets:
          //
          // KEY0:VALUE0,KEY1:VALUE1,...
          //
          foreach (Util::explodeIndexed($newvals[$field], null) as $key => $value) {
            $multipleValues[$key][$column] = $value;
          }
          foreach ($identifier[$multiple]['new'] as $new) {
            if (!isset($multipleValues[$new][$column])) {
              $multipleValues[$new][$column] =
                isset($pme->fdd[$field]['default']) ? $pme->fdd[$field]['default'] : null;
            }
          }
        }

        $this->debug('MULTIPLE VALUES '.print_r($multipleValues, true));

        // Add new entities
        foreach ($identifier[$multiple]['new'] as $new) {
          if (isset($addIdentifier[$new])) {
            $this->debug('TRY ADD '.$new);
            $id = $addIdentifier[$new];
            $entityId = $meta->extractKeyValues($id);
            $entity = $entityClass::create();
            foreach ($entityId as $key => $value) {
              $entity[$key] = $value;
            }
          } else if (isset($remIdentifier[$new]) && !empty($changeSet)) {
            $this->debug('TRY MOD '.$new);
            $id = $remIdentifier[$new];
            $this->debug('REM IDS '.print_r($id, true));
            $entityId = $meta->extractKeyValues($id);
            $this->debug('ENTITIY ID '.print_r($entityId, true));
            $entity = $this->find($entityId);
            if (empty($entity)) {
              throw new \Exception($this->l->t('Unable to find entity in table %s given id %s',
                                               [ $table, print_r($entityId, true) ]));
            }
            if (method_exists($entity, 'setDeleted')) {
              $entity['deleted'] = null;
              $this->flush();
            }
          } else {
            continue;
          }

          // set further properties ...
          foreach (($multipleValues[$new] ?? [])as $column => $value) {
            $meta->setSimpleColumnValue($entity, $column, $value);
          }

          // persist
          $this->persist($entity);
        }
        foreach ($changeSet as $column => $field) {
          Util::unsetValue($changed, $field);
        }
      } else { // !multiple, simply update
        // Note: this implies an additional fetch from the database,
        // however, in the long run the goal would be to switch to
        // Doctrine/ORM for everything. So we live with it for the
        // moment.
        $this->logInfo('IDENTIFIER '.print_r($identifier, true));
        $entityId = $meta->extractKeyValues($identifier);
        $entity = $this->find($entityId);
        if (empty($entity)) {
          $entity = $entityClass::create();
          foreach ($entityId as $key => $value) {
            $entity[$key] = $value;
          }
        }
        foreach ($changeSet as $column => $field) {
          $meta->setSimpleColumnValue($entity, $column, $newvals[$field]);
          Util::unsetValue($changed, $field);
        }
        if (($joinInfo['flags'] & self::JOIN_REMOVE_EMPTY) && empty($entity[$joinInfo['column']])) {
          // just delete the entity in this case
          $this->remove($entity);
        } else {
          $this->persist($entity);
        }
        if ($joinInfo['flags'] & self::JOIN_MASTER) {
          $masterEntity = $entity;
        }
      }
    }
    $this->flush(); // flush everything to the data-base


    $this->debug('AFTER OLD '.print_r($oldvals, true));
    $this->debug('AFTER NEW '.print_r($newvals, true));
    $this->debug('AFTER CHG '.print_r($changed, true));

    if (!empty($changed)) {
      throw new \RuntimeException($this->l->t('Change-set %s should be empty.', print_r($changed, true)));
    }

    // all should be done
    $pme->setLogging(false);

    return true; // in order to update key-fields
  }

  /**
   * Before insert-trigger which ideally should insert all data such
   * that nothing remains to be done for phpMyEdit. The idea is to
   * eventually abandon phpMyEdit and for the time being do as much as
   * possible with the Doctrine ORM.
   *
   * @param $pme The phpMyEdit instance. This is
   * OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit.
   *
   * @param $op The operation, here must be 'insert'.
   *
   * @param $step 'before' or 'after'. Here it is 'before'.
   *
   * @param $oldvals This is empty here, as we are the 'insert' handler.
   *
   * @param &$changed Set of changed fields, may be modified by the
   * callback. This contains all fields to insert.
   *
   * @param &$newvals Set of new values, which may also be
   * modified. Ideally this should be empty on return.
   *
   * @return bool If returning @c false the operation will be
   * terminated. We have to return 'true' in order not hinder further
   * callbacks and in order to update the primary identifier record of
   * $pme.
   *
   * @bug This method is too large.
   */
  public function beforeInsertDoInsertAll(&$pme, $op, $step, &$oldvals, &$changed, &$newvals)
  {
    // leave time-stamps to the ORM "behaviors"
    Util::unsetValue($changed, 'created');
    Util::unsetValue($changed, 'updated');

    $missingKeys = [];
    foreach ($pme->key as $key => $type) {
      if (!isset($newvals[$key])) {
        $missingKeys[] = $key;
      }
    }
    $this->debug('MISSING '.print_r($missingKeys, true));
    foreach ($this->joinStructure as $joinInfo) {
      if ($joinInfo['flags'] & self::JOIN_MASTER) {
        continue;
      }
      foreach ($joinInfo['identifier'] as $joinColumn => $keyColumn) {
        if (array_search($keyColumn, $missingKeys) === false) {
          continue;
        }
        $joinFieldName = $this->joinTableFieldName($joinInfo, $joinColumn);
        if (isset($newvals[$joinFieldName])) {
          $newvals[$keyColumn] = $newvals[$joinFieldName];
          $changed[] = $keyColumn;
          unset($missingKeys[$keyColumn]);
        }
        if (empty($missingKeys)) {
          break 2;
        }
      }
    }

    $this->debug('NEWVALS '.print_r($newvals, true));
    $changeSets = [];
    foreach ($changed as $field) {
      $fieldInfo = $this->joinTableField($field);
      $changeSets[$fieldInfo['table']][$fieldInfo['column']] = $field;
    }
    $this->debug('CHANGESETS: '.print_r($changeSets, true));

    $masterEntity = null; // cache for a reference to the master entity
    foreach ($this->joinStructure as $table => $joinInfo) {
      $changeSet = $changeSets[$table];
      if ($joinInfo['flags'] & self::JOIN_READONLY) {
        foreach ($changeSet as $column => $joinColumn) {
          Util::unsetValue($changed, $joinColumn);
        }
        continue;
      }
      if (empty($changeSets[$table])) {
        continue;
      }
      if ($joinInfo['flags'] & self::JOIN_MASTER) {
        // fill the identifier keys as they are left out for
        // convenience in $this->joinStructure
        foreach ($this->pme->key as $key => $type) {
          $joinInfo['identifier'][$key] = $key;
        }
      }
      $this->debug('CHANGESET '.$table.' '.print_r($changeSet, true));
      $entityClass = $joinInfo['entity'];
      $repository = $this->getDatabaseRepository($entityClass);
      $meta = $this->classMetadata($entityClass);
      $multiple = null;
      $identifier = [];
      $identifierColumns = $meta->getIdentifierColumnNames();
      foreach ($identifierColumns as $key) {
        $pivotColumn = $this->findJoinColumnPivot($joinInfo, $key);
        if ($pivotColumn === false) {
          if (!empty($multiple)) {
            throw new \RuntimeException($this->l->t('Missing identifier for field "%s" and grouping field "%s" already set.', [ $key, $multiple ]));
          }
          // assume that the 'column' component contains the keys.
          $keyField = $this->joinTableFieldName($joinInfo, $joinInfo['column']);
          $identifier[$key] = Util::explode(',', $newvals[$keyField]);

          Util::unsetValue($changed, $changeSet[$joinInfo['column']]);
          unset($changeSet[$joinInfo['column']]);
          $multiple = $key;
        } else {
          if (!is_array($pivotColumn)) {
            $idKey = $pivotColumn;
            $identifier[$key] = $newvals[$idKey];
            unset($changeSet[$idKey]);
            Util::unsetValue($changed, $idKey);
          } else if (!empty($pivotColumn['value'])) {
            $identifier[$key] = $pivotColumn['value'];
          } else if (!empty($pivotColumn['self'])) {
            // Key value has to come from another field, possibly
            // defaulted if not yet known. This can only be used
            // together with the 'multiple' case and must not
            // introduce additional deletions and modifications.
            $selfField = $this->joinTableFieldName($joinInfo, $key);
            $identifier[$key] = [ 'self' => $selfField ];
          } else {
            throw new \RuntimeException($this->l->t('Field "%s.%s": nested multi-value join tables with unexpected pivot-column: %s.', [ $table, $key, print_r($pivotColumn, true), ]));
          }
        }
      }
      if (!empty($multiple)) {
        $this->debug('IDS '.print_r($identifier, true));
        $this->debug('CHG '.print_r($changeSet, true));

        $addIdentifier = [];
        foreach ($identifier[$multiple] as $addKey) {
          $addIdentifier[$addKey] = $identifier;
          $addIdentifier[$addKey][$multiple] = $addKey;
        }
        foreach ($identifier as $selfKey => $value) {
          if (empty($value['self'])) {
            continue;
          }
          $selfField = $value['self'];
          foreach ($addIdentifier as $key => &$idValues) {
            $idValues[$selfKey] = null;
          }
          foreach (Util::explodeIndexd($newvals[$selfField]) as $key => $value) {
            if (isset($addIdentifier[$key])) {
              $addIdentifier[$key][$selfKey] = $value;
            }
          }
          foreach ($addIdentifier as $key => &$idValues) {
            if (empty($idValues[$selfKey])) {
              $idValues[$selfKey] = $pme->fdd[$selfField]['default'];
            }
            if ($idValues[$selfKey] === null) {
              throw new \RuntimeException($this->l->t('No value for identifier field "%s / %s".', [$selfKey, $selfField]));
            }
          }
        }

        $this->debug('ADDIDS '.print_r($addIdentifier, true));

        if (!empty($joinInfo['association'])) {
          // Many-to-many or similar through join table. We modify the
          // join table indirectly by modifying the master entity's
          // association.

          if (empty($masterEntity)) {
            throw new \RuntimeException($this->l->t('Master entity is unset.'));
          }

          $association = $masterEntity[$joinInfo['association']];
          $this->debug(get_class($association).': '.$association->count());

          // add entries by adding them to the association of the
          // master entity
          foreach ($identifier[$multiple] as $add) {
            $id = $addIdentifier[$add];
            $entityId = $meta->extractKeyValues($id);
            $association->add($this->getReference($entityClass, $entityId));
          }

          $masterEntity[$joinInfo['association']] = $association;

          continue; // skip to next field
        }

        $multipleValues = [];
        foreach ($changeSet as $column => $field) {
          // convention for multiple change-sets:
          //
          // - the values start with the key
          // - boolean false values are omitted
          // - optional values are omitted
          // - values are separated by a colon from the key
          foreach (Util::explodeIndexed($newvals[$field], null) as $key => $value) {
            $multipleValues[$key][$column] = $value;
          }
          foreach ($identifier[$multiple] as $new) {
            if (!isset($multipleValues[$new][$column])) {
              $multipleValues[$new][$column] =
                isset($pme->fdd[$field]['default']) ? $pme->fdd[$field]['default'] : null;
            }
          }
        }

        $this->debug('VAL '.print_r($multipleValues, true));

        // Add new entities
        foreach ($identifier[$multiple] as $new) {
          $this->debug('TRY MOD '.$new);
          $id = $addIdentifier[$new];
          $entityId = $meta->extractKeyValues($id);
          $entity = $entityClass::create();
          foreach ($entityId as $key => $value) {
            $entity[$key] = $value;
          }

          // set further properties ...
          foreach ($multipleValues[$new] as $column => $value) {
            $meta->setSimpleColumnValue($entity, $column, $value);
          }

          // persist
          $this->persist($entity);
        }
        foreach ($changeSet as $column => $field) {
          Util::unsetValue($changed, $field);
        }
      } else {
        // !$multiple, simply insert. The "master-"table can only
        // "land" here
        $entityId = $meta->extractKeyValues($identifier);
        $entity = $entityClass::create();
        foreach ($entityId as $key => $value) {
          $entity[$key] = $value;
        }
        foreach ($changeSet as $column => $field) {
          $meta->setSimpleColumnValue($entity, $column, $newvals[$field]);
          Util::unsetValue($changed, $field);
        }

        if (!($joinInfo['flags'] & self::JOIN_REMOVE_EMPTY) || !empty($entity[$joinInfo['column']])) {
          $this->persist($entity);
        }

        // if this is the master table, then we need also to fetch the
        // id and to insert the id(s) into the change-sets for the
        // joined entities which are yet to be inserted.
        if ($joinInfo['flags'] & self::JOIN_MASTER) {
          $this->flush();
          $masterEntity = $entity;
          $identifier = $meta->getIdentifierColumnValues($masterEntity);
          foreach (array_keys($this->pme->key) as $key) {
            $newvals[$key] = $identifier[$key];
          }
        }
      }
    }
    $this->flush(); // flush everything to the data-base

    $this->debug('BEFORE INS: '.print_r($changed, true));

    if (!empty($changed)) {
      throw new \Exception(
        $this->l->t('Remaining change-set %s must be empty', print_r($changed, true)));
    }

    // all should be done
    $pme->setLogging(false);

    return true; // in order to update key-fields
  }

  /**
   * Define a basic join-structure for phpMyEdit by using the
   * information from self::$joinStructure.
   *
   * self::$joinStructure has the following structure:
   * ```
   * [
   *   SQL_TABLE_NAME = [
   *     'table' => SQL_TABLE_NAME, // optional, will be added if not present
   *     'entity' => ENTITY_CLASS_NAME,
   *     'flags'  => self::JOIN_READONLY|self::JOIN_MASTER|self::JOIN_REMOVE_EMPTY|self::JOIN_GROUP_BY
   *     'identifier' => [
   *        COLUMN_NAME => ID_COLUMN_DESCRIPTION, // see below
   *        ...
   *     ],
   *     'filter' => [
   *        COLUMN_NAME => ID_COLUMN_DESCRIPTION, // see below
   *        ...
   *     ],
   *     'column' => COLUMN_TO_FETCH,
   *   ],
   *   ...
   * ]
   * ```
   * ID_COLUMN_DESCRIPTION is one of the following
   * - string OTHER_COLUMN Just another column name of the main-table
   * - false  "incomplete" key for grouping
   * - array
   *    - ```[ 'value' => VALUE ]``` where VALUE may be again an array of
   *      values which are used to form an IN condition, false or null
   *      for "IS NULL", true for "IS NOT NULL". Any other value as string
   *      or number depending on its value. Strings are properly escaped.
   *    - ```[
   *        'table' => OTHER_TABLE,
   *        'column' => COLUMN_IN_OTHER_TABLE,
   *      ]```
   *    - ```[ 'self' => true ]``` like 'table' but with the same table
   *      and just this COLUMN_NAME as column.
   *
   * The difference between 'filter' and 'identifier' is that the
   * 'identifier' section is also used to update entities, while the
   * 'filter' section simply defines further join restrictions.
   *
   * @param array $opts phpMyEdit options.
   *
   * @return array ```[ TABLE_NAME => phpMyEdit_alias ]```
   */
  protected function defineJoinStructure(array &$opts)
  {
    $joinTables = [];
    if (!empty($opts['groupby_fields']) && !is_array($opts['groupby_fields'])) {
      $opts['groupby_fields'] = [ $opts['groupby_fields'], ];
    }
    $grouped = [];
    $orderBy = [];
    foreach ($this->joinStructure as $table => &$joinInfo) {
      $joinInfo['table'] = $table;
      $joinInfo['flags'] = $joinInfo['flags'] ?? self::JOIN_FLAGS_NONE;
      if ($joinInfo['flags'] & self::JOIN_MASTER) {
        if (is_array($opts['key'])) {
          foreach (array_keys($opts['key']) as $key) {
            $joinInfo['identifier'][$key] = $key;
          }
        } else {
          $joinInfo['identifier'][$opts['key']] = $opts['key'];
        }
        $joinTables[$table] = 'PMEtable0';
        continue;
      }
      $valuesTable = $joinInfo['sql'] ?? explode(self::VALUES_TABLE_SEP, $table)[0];

      $opts['fdd'] = $opts['fdd'] ?? [];
      $joinIndex[$table] = count($opts['fdd']);
      $joinTables[$table] = 'PMEjoin'.$joinIndex[$table];
      $fqnColumn = $joinTables[$table].'.'.$joinInfo['column'];

      $group = false;
      $groupOrderBy = [];
      $joinData = [];
      foreach (['identifier', 'filter'] as $columnRestriction) {
        foreach (($joinInfo[$columnRestriction] ?? []) as $joinTableKey => $joinTableValue) {
          if (empty($joinTableValue)) {
            $group = true;
            $groupOrderBy[] = $joinTables[$table].'.'.$joinInfo['column'].' ASC';
            continue;
          }
          $joinColumn =  '$join_table.' . self::COL_QUOTE . $joinTableKey . self::COL_QUOTE;
          $joinCondition = $joinColumn . ' ';
          if (is_array($joinTableValue)) {
            if (!empty($joinTableValue['table'])) {
              $mainTableColumn = $joinTableValue['column']?: 'id';
              $joinCondition .= '= '.$joinTables[$joinTableValue['table']].'.'.self::COL_QUOTE.$mainTableColumn.self::COL_QUOTE;
              $group = $grouped[$joinTableValue['table']];
              $groupOrderBy = array_merge($groupOrderBy, $orderBy[$joinTableValue['table']]);
            } else if (array_key_exists('value', $joinTableValue)
                       && ($joinTableValue['value'] === null || $joinTableValue['value'] === false)) {
              $joinCondition = $joinColumn . ' IS NULL';
            } else if (array_key_exists('value', $joinTableValue)
                       && $joinTableValue['value'] === true) {
              $joinCondition = $joinColumn . ' IS NOT NULL';
            } else if (!empty($joinTableValue['value'])) {
              $values = $joinTableValue['value'];
              $values = array_map(function($value) {
                return is_numeric($value) ? $value : "'".addslashes($value)."'";
              }, is_array($values) ? $values : [ $values ]);
              $joinCondition .= 'IN (' . implode(',', $values) . ')';
            } else if (!empty($joinTableValue['condition'])) {
              $joinCondition .= $joinTableValue['condition'];
            } else if (!empty($joinTableValue['self'])) {
              // use during update to determine key values, otherwise ignore
              continue;
            } else {
              throw new \RuntimeException($this->l->t('Unknown column description: "%s"', print_r($joinTableValue, true)));
            }
          } else {
            $joinCondition .= '= $main_table.'.$joinTableValue;
          }
          $joinData[] = $joinCondition;
        }
      }
      if (!empty($joinInfo['reference'])) {
        $reference = $joinTables[$joinInfo['reference']];
        $joinData = [ 'reference' => $reference, ];
        $valuesTable = null;
        $joinTables[$table] = $reference;
      } else {
        $joinData = implode(' AND ', $joinData);
      }
      $grouped[$table] = $group;
      $orderBy[$table] = $groupOrderBy;
      $fieldName = $this->joinTableMasterFieldName($table);
      $opts['fdd'][$fieldName] = [
        'tab' => 'all',
        'name' => $fieldName,
        'input' => 'HV',
        'options' => '',
        'sort' => true,
        'values' => [
          'table' => $valuesTable,
          'column' => $joinInfo['column'],
          'order_by' => implode(', ', $groupOrderBy),
          'grouped' => $group,
          'encode' => $joinInfo['encode'] ?? null,
          'join' => $joinData,
        ],
      ];
      if ($joinInfo['flags'] & self::JOIN_GROUP_BY) {
        $opts['groupby_fields'][] = $fieldName;

        // use simple field grouping for list and filter operation
        $opts['fdd'][$fieldName]['sql|FL'] = '$join_col_fqn';
      }
      //$this->debug('JOIN '.print_r($opts['fdd'][$fieldName], true));
    }
    if (!empty($opts['groupby_fields'])) {
      $keys = is_array($opts['key']) ? array_keys($opts['key']) : [ $opts['key'] ];
      $opts['groupby_fields'] = array_values(array_unique(array_merge($keys, $opts['groupby_fields'])));
      // $this->debug('GROUP_BY '.print_r($opts['groupby_fields'], true));
    }
    $this->joinTables = new \ArrayObject($joinTables);

    return $this->joinTables;
  }

  /**
   * The name of the master join table field which triggers PME to
   * actually do the join.
   *
   * @param string|array $tableInfo @see joinTableFieldName().
   *
   * @return string
   */
  protected function joinTableMasterFieldName($tableInfo)
  {
    if (is_array($tableInfo)) {
      $table = $tableInfo['table'];
    } else {
      $table = $tableInfo;
    }
    return $table.'_key';
  }

  /**
   * The name of the field-descriptor for a join-table field
   * referencing a master-join-table.
   *
   * @param string|array $joinInfo Table-description-data
   * ```
   * [
   *   'table' => SQL_TABLE_NAME,
   *   'entity' => DOCTRINE_ORM_ENTITY_CLASS_NAME,
   *   'flags'  => self::JOIN_READONLY|self::JOIN_MASTER|self::JOIN_REMOVE_EMPTY|self::JOIN_GROUP_BY
   *   'column' => column used in select statements
   *   'identifier' => [
   *     TO_JOIN_TABLE_COLUMN => ALREADY_THERE_COLUMN_NAME,
   *     ...
   *   ]
   * ]
   * ```
   *
   * @param string $column column to generate a query field for. This
   * is another column different from $joinInfo['column'] in order to
   * generate further query fields from an existing join-table.
   *
   * @return string Cooked field-name composed of $joinInfo and $column.
   *
   */
  static protected function joinTableFieldName($joinInfo, string $column)
  {
    if (is_array($joinInfo)) {
      if ($joinInfo['flags'] & self::JOIN_MASTER) {
        return $column;
      }
      $table = $joinInfo['table'];
    } else {
      $table = $joinInfo;
    }
    return $table.self::JOIN_FIELD_NAME_SEPARATOR.$column;
  }

  /**
   * Inverse of self::joinTableFieldName().
   */
  protected function joinTableField(string $fieldName)
  {
    $parts = explode(self::JOIN_FIELD_NAME_SEPARATOR, $fieldName);
    if (count($parts) == 1) {
      $parts[1] = $parts[0];
      $parts[0] = $this->pme->tb;
      if (empty($this->pme->tb)) {
        throw new \Exception($this->l->t('Table-name not specified'));
      }
    }
    return [
      'table' => $parts[0],
      'column' => $parts[1],
    ];
  }

  /**
   * Compute the index into the phpMyEdit field-description data of
   * the given $tableInfo and $column.
   *
   * @param array $fieldDescriptionData See phpMyEdit source code and
   * look-out for "fdd"
   *
   * @param string|array $tableInfo @see joinTableFieldName().
   *
   * @param string $column @see joinTableFieldName().
   */
  protected function fieldIndex(array $fieldDescriptionData, $tableInfo, string $column)
  {
    $fieldName = $this->joinTableFieldName($tableInfo, $column);
    return array_search($fieldName, array_keys($fieldDescriptionData));
  }

  /**
   * Generate a join-table field, given join-table and field name.
   *
   * @param array $fieldDescriptionData See phpMyEdit source code and
   * look-out for "fdd"
   *
   * @param string|array $tableInfo @see joinTableFieldName().
   *
   * @param string $column SQL column.
   *
   * @param array $fdd Override FDD, see phpMyEdit.
   */
  protected function makeJoinTableField(array &$fieldDescriptionData, $tableInfo, string $column, array $fdd)
  {
    if (is_string($tableInfo) && isset($fdd['values']['join'])) {
      $defaultFDD = [
        'select' => 'T',
        'maxlen' => 128,
        'sort' => true,
        'input' => 'S',
        'values' => [
          'table' => $tableInfo,
          'column' => $column,
        ],
      ];
    } else {
      $masterFieldName = $this->joinTableMasterFieldName($tableInfo);
      $joinIndex = array_search($masterFieldName, array_keys($fieldDescriptionData));
      if ($joinIndex === false) {
        $table = is_array($tableInfo) ? $tableInfo['table'] : $tableInfo;
        throw new \Exception($this->l->t("Master join-table field for %s not found.", $table));
      }
      if (isset($fieldDescriptionData[$masterFieldName]['values']['join']['reference'])) {
        $joinIndex = $fieldDescriptionData[$masterFieldName]['values']['join']['reference'];
      }
      $defaultFDD = [
        'select' => 'T',
        'maxlen' => 128,
        'sort' => true,
        'input' => 'S',
        'values' => [
          'column' => $column,
          'join' => [ 'reference' => $joinIndex, ],
        ],
      ];
    }
    $fieldName = $this->joinTableFieldName($tableInfo, $column);
    $index = count($fieldDescriptionData);
    $fieldDescriptionData[$fieldName] = Util::arrayMergeRecursive($defaultFDD, $fdd);
    return [ $index, $fieldName ];
  }

  /**
   * Fill the sequence table 'numbers' with values from 1 up to $min.
   *
   * @param int $min Mininum max value of sequence table.
   */
  protected function generateNumbers(int $min)
  {
    $query = 'CALL generateNumbers('.$min.')';
    $result = $this->pme->myquery($query);
    $this->pme->sql_free_result($result);
  }

  /**
   * phpMyEdit calls the trigger (callback) with the following arguments:
   *
   * @param $pme The phpMyEdit instance
   *
   * @param $op The operation, 'insert', 'update' etc.
   *
   * @param $step 'before' or 'after' or 'pre'
   *
   * @return boolean If returning @c false the operation will be terminated
   */
  public function preTrigger(&$pme, $op, $step)
  {
    return true;
  }

  /**
   * Add a "slug" as tooltips-index and css-class.
   *
   * @param string $slug The tag to add.
   *
   * @param array &$fdd The field-description-data. It is modified
   * in-place.
   */
  protected function addSlug(string $slug, array &$fdd)
  {
    $cssSlug = $this->cssClass().'-'.$slug;
    $tooltipSlug = $this->cssClass().ToolTipsService::SUB_KEY_SEP.$slug;
    if (!isset($fdd['css']['postfix'])) {
      $fdd['css'] = [ 'postfix' => [] ];
    }
    $fdd['css']['postfix'][] = $cssSlug;
    $fdd['tooltip'] = $this->toolTipsService[$toolTipSlug];
  }

  protected function queryFieldIndex(string $key, array $fdd)
  {
    return array_search($key, array_keys($fdd));
  }

  protected function queryField(string $key, array $fdd)
  {
    return 'qf'.$this->queryFieldIndex($key, $fdd);
  }

  protected function joinQueryField($tableInfo, $column, array $fdd)
  {
    return $this->queryField($this->joinTableFieldName($tableInfo, $column), $fdd);
  }

  protected function makeFieldTranslationsJoin(array $joinInfo, $fields)
  {
    if (!is_array($fields)) {
      $fields = [ $fields ];
    }
    $l10nFields = [];
    foreach ($fields as $field) {
      $l10nFields[] = 'COALESCE(jt_'.$field.'.content, t.'.$field.') AS l10n_'.$field;
    }
    $entity = addslashes($joinInfo['entity']);
    if (count($joinInfo['identifier']) > 1) {
      throw new \RuntimeException($this->l->t('Composite keys are not yet supported for translated database table fields.'));
    }
    $id = array_keys($joinInfo['identifier'])[0];
    $lang = $this->l10n()->getLanguageCode();
    $l10nJoins = [];
    foreach ($fields as $field) {
      $jt = 'jt_'.$field;
      $l10nJoins[] = "  LEFT JOIN ".self::FIELD_TRANSLATIONS_TABLE." $jt
  ON $jt.locale = '$lang'
    AND $jt.object_class = '$entity'
    AND $jt.field = '$field'
    AND $jt.foreign_key = t.$id
";
    }
    $query = 'SELECT t.*, '.implode(', ', $l10nFields).'
  FROM '.$joinInfo['table'].' t
'.implode('', $l10nJoins);
    return $query;
  }

  protected function makeFieldTranslationFddValues(array $joinInfo, $field)
  {
    if (empty($joinInfo['identifier'])) {
      $id = 'id';
    } else {
      $id = array_keys($joinInfo['identifier'])[0];
    }
    $lang = $this->l10n()->getLanguageCode();
    return [
      'sql' => 'COALESCE($join_col_fqn, $main_table.$field_name)',
      'values' => [
        'table' => self::FIELD_TRANSLATIONS_TABLE,
        'column' => 'content',
        'join' => '$join_table.field = "'.$field.'"
  AND $join_table.foreign_key = $main_table.'.$id.'
  AND $join_table.object_class = "'.addslashes($joinInfo['entity']).'"
  AND $join_table.locale = "'.$lang.'"',
        'filters' => '$table.field = "'.$field.'"
  AND $table.locale = "'.$lang.'
  AND $table.object_class = "'.addslashes($joinInfo['entity']).'"',
      ],
    ];
  }

  /**
   * Unset a value in the input vars of legacy PME triggers.
   *
   * @param null|array $oldValues From database.
   *
   * @param array $changed Computed change-set form legacy PME.
   *
   * @param array $newvals New values from input form.
   */
  protected static function unsetRequestValue($tag, ?array &$oldValues, array &$changed, array &$newValues)
  {
    Util::unsetValue($changed, $tag);
    if (!empty($oldValues)) {
      unset($oldValues[$tag]);
    }
    unset($newValues[$tag]);
  }

  /**
   * Be noisy if debugging is enabled. Debug can be switched on and
   * off in the user interface if export-mode is enabled.
   */
  protected function debug(string $message, array $context = [], $shift = 2) {
    if ($this->debugRequests) {
      $this->logInfo($message, $context, $shift + 1);
    } else {
      $this->logDebug($message, $context, $shift + 1);
    }
  }

  /**
   * Possibly regenerate the user-id slug.
   *
   * @copydoc beforeTriggerSetTimestamp
   *
   * @todo This would rather belong to some service class.
   */
  public function ensureUserIdSlug($pme, $op, $step, &$oldValues, &$changed, &$newValues)
  {
    $tag = 'user_id_slug';
    if (!empty($pme->fdn[$this->joinTableMasterFieldName(self::MUSICIANS_TABLE)])) {
      $tag = $this->joinTableFieldName(self::MUSICIANS_TABLE, $tag);
    }
    if (empty($newValues[$tag])) {
      // force regeneration by setting the slug to a "magic" value.
      $newValues[$tag] = \Gedmo\Sluggable\SluggableListener::PLACEHOLDER_SLUG;
      $changed[] = $tag;
      $changed = array_values(array_unique($changed));
    }

    return true;
  }

  /**
   * Rename the file-system folders if the user-id slug has changed.
   *
   * @todo This would rather belong to some service class.
   */
  public function renameProjectParticipantFolders($pme, $op, $step, &$oldValues, &$changed, &$newValues)
  {
    if ($op != 'update') {
      return true; // safeguard
    }

    $tag = 'user_id_slug';
    if (!empty($pme->fdn[$this->joinTableMasterFieldName(self::MUSICIANS_TABLE)])) {
      $tag = $this->joinTableFieldName(self::MUSICIANS_TABLE, $tag);
    }

    $newUserIdSlug = $newValues[$tag];
    $oldUserIdSlug = $oldValues[$tag];

    if (empty($newUserIdSlug) && empty($oldUserIdSlug)) {
      return true; // safeguard
    }

    if (empty($newUserIdSlug)) {
      throw new \RuntimeException($this->l->t('New suggested user-id is but must not be empty, old id was "%s".',
                                              $oldUserIdSlug));
    }

    if ($newUserIdSlug == $oldUserIdSlug) {
      return true; // nothing to do
    }

    // register a pre-commit callback to rename the user folder

    /** @var Entities\Musician $musician */
    $musician = $this->getDatabaseRepository(Entities\Musician::class)->find($pme->rec);
    if (empty($musician)) {
      throw new \RuntimeException(
        $this->l->t('Unable to retrieve musician for id "%s" from database.', $pme->rec));
    }

    /** @var Entities\ProjectParticipant $projectParticipant */
    foreach ($musician->getProjectParticipation() as $projectParticipant) {
      $project = $projectParticipant->getProject();

      /** @var ProjectService $projectService */
      $projectService = $this->di(ProjectService::class);

      $participantsFolder = $projectService->getProjectFolder($project, ConfigService::PROJECT_PARTICIPANTS_FOLDER);

      $oldName = $oldUserIdSlug ? $participantsFolder.UserStorage::PATH_SEP.$oldUserIdSlug : null;
      $newName = $participantsFolder.UserStorage::PATH_SEP.$newUserIdSlug;

      $this->registerPreCommitRename($oldName, $newName);
    }

    return true;
  }

  /**
   * Fill an instance of Entities\Musician with the data from a legacy
   * PHPMyEdit query.
   *
   * @param array $row The data generated by PHPMyEdit
   *
   * @param null|PHPMyEdit Instance of PME.
   *
   * @return array
   * ```
   * [ 'musician' => MUSICIAN_ENTITY, 'categories' => ADDRESSBOOK_CATEGORIES ]
   * ```
   */
  protected function musicianFromRow($row, ?PHPMyEdit $pme)
  {
    $pme = $pme?:$this->pme;
    $joinTable = !empty($pme->fdn[$this->joinTableMasterFieldName(self::MUSICIANS_TABLE)]);
    $data = [];
    foreach($pme->fds as $idx => $label) {
      $data[$label] = $row['qf'.$idx];
    }
    $categories = [];
    $musician = new Entities\Musician();
    if ($joinTable) {
      // make sure to fetch the id-record
      foreach ($this->joinStructure as $joinInfo) {
        if ($joinInfo['table'] == self::MUSICIANS_TABLE) {
          $idColumn = $joinInfo['identifier']['id'];
          $id = $row['qf'.($pme->fdn[$idColumn])];
          $musician->setId($id);
          break;
        }
      }
    }
    foreach ($data as $key => $value) {
      // In order to support "categories" the same way as the
      // AddressBook-integration we need to feed the
      // Musician-entity with more data:
      switch ($key) {
      case 'all_projects':
      case 'projects':
        $categories = array_merge($categories, explode(',', Util::removeSpaces($value)));
        break;
      case 'MusicianInstrument:instrument_id':
        foreach (explode(',', Util::removeSpaces($value)) as $instrumentId) {
          $categories[] = $this->instrumentInfo['byId'][$instrumentId];
        }
        break;
      default:
        if ($joinTable) {
          $fieldInfo = $this->joinTableField($key);
          if ($fieldInfo['table'] != self::MUSICIANS_TABLE) {
            continue 2;
          }
          $column = $fieldInfo['column'];
        } else {
          $column = $key;
        }
        try {
          $musician[$column] = $value;
        } catch (\Throwable $t) {
          // Don't care, we know virtual stuff is not there
        }
        break;
      }
    }
    return [ 'musician' => $musician, 'categories' => $categories ];
  }

  /**
   * Generate an SQL fragment which composes a display name from the
   * available name-parts sur_name, first_name, nick_name,
   * display_name.
   *
   * @param string $tableAlias Table to refer to, refers to
   * placeholder '$table'.
   */
  static public function musicianPublicNameSql($tableAlias = '$table', $firstNameFirst = false)
  {
    if ($firstNameFirst) {
      return "CONCAT_WS(
  ' ',
  IF($tableAlias.nick_name IS NULL OR $tableAlias.nick_name = '',
    $tableAlias.first_name,
    $tableAlias.nick_name
  ),
  $tableAlias.sur_name)";
    } else {
      return "IF($tableAlias.display_name IS NULL OR $tableAlias.display_name = '',
      CONCAT(
        $tableAlias.sur_name,
        ', ',
        IF($tableAlias.nick_name IS NULL OR $tableAlias.nick_name = '',
          $tableAlias.first_name,
          $tableAlias.nick_name
        )
      ),
      $tableAlias.display_name
    )";
    }
  }

  /**
   * Return an SQL filter fragment to ensure that a musician-id is
   * only in the request project.
   */
  static public function musicianInProjectSql($projectIdSql, $musicianId = 'id', $tableAlias = '$table')
  {
    return "$tableAlias.$musicianId IN (SELECT pp.musician_id
  FROM ".self::PROJECT_PARTICIPANTS_TABLE." pp
  WHERE pp.project_id = $projectIdSql)";
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
