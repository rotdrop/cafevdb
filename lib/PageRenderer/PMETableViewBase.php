<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCA\CAFEVDB\Service\ProjectService;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types as DBTypes;

use OCA\CAFEVDB\Exceptions;

use OCA\CAFEVDB\Common\Util;

use OCA\CAFEVDB\Storage\UserStorage;

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

/** Base for phpMyEdit based table-views. */
abstract class PMETableViewBase extends Renderer implements IPageRenderer
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /**
   * Hard-coded sequence table name. This relies on
   * @link https://mariadb.com/kb/en/sequence-storage-engine/
   */
  protected const SEQUENCE_TABLE = 'seq_0_to_100000_step_1';

  protected const JOIN_FLAGS_NONE = 0x00;

  /**
   * This entry of self::$joinStructure refers to the "master" table.
   */
  protected const JOIN_MASTER = 0x01;

  /**
   * Do not add/change/delete any joined entities.
   */
  protected const JOIN_READONLY = 0x02;

  /**
   * Adds an SQL `GROUP BY` using the configured join-key and the keys
   * of the master table.
   */
  protected const JOIN_GROUP_BY = 0x04;

  /**
   * Remove entities when the value of the join-column is changed to
   * something for which empty() returns true.
   */
  protected const JOIN_REMOVE_EMPTY = 0x08;

  /**
   * Assume the join is (at most) single valued. Otherwise the values
   * are split by PMETableViewBase::VALUES_SEP (comma). If this flag
   * is set, then the expected format of the data field is
   *
   * ```
   * JOIN_KEY:VALUES
   * ```
   *
   * and VALUES may contain self::VALUES_SEP (comma) and
   * self::JOIN_KEY_SEP (colon). If this flag is not set then the expected format is
   *
   * ```
   * KEY1:VALUE1,KEY2:VALUE2,...
   * ```
   *
   * Consequently, the VALUEX must not contain commas and colons in
   * the absence of this flag.
   */
  protected const JOIN_SINGLE_VALUED = 0x10;

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
  const PROJECT_BALANCE_SUPPORTING_DOCUMENTS_TABLE = 'ProjectBalanceSupportingDocuments';
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

  /**
   * MySQL/MariaDB column quote.
   */
  const COL_QUOTE = '`';

  /** CSS tag for displaying participant fields */
  const CSS_TAG_PROJECT_PARTICIPANT_FIELDS_DISPLAY = 'project-participant-fields-display';
  const CSS_TAG_SHOW_HIDE_DISABLED = 'show-hide-disabled';
  const CSS_TAG_DIRECT_CHANGE = 'direct-change';

  protected const PME_NAVIGATION_NO_MULTI = 'GUD';
  protected const PME_NAVIGATION_MULTI = 'GUDM';

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

  /** @var bool Request reload of ambient form/table */
  protected $reloadOuterForm = false;

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

    $this->membersProjectId = $this->getClubMembersProjectId();

    // this is done by the legacy code itself.
    $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    $this->defaultFDD = $this->createDefaultFDD();

    $cgiDefault = [
      'template' => 'blog',
      'musicianId' => null,
      'projectId' => null,
      'projectName' => '',
      'recordsPerPage' => $this->getUserValue('pagerows', 20),
    ];

    $this->pmeOptions = [
      'cgi' => [ 'persist' => [] ],
      'display' => [],
      'css' => [ 'postfix' => [], ],
      'navigation' => self::PME_NAVIGATION_NO_MULTI,
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

    $this->pmeOptions['display']['postfix'] = function($pme) {
      $html = '';
      $html .= $this->pme->htmlHiddenSys('reloadOuterForm', $this->reloadOuterForm);
      if (!$this->expertMode) {
        return $html;
      }
      $html .= '<span class="query-log"><select class="chosen chosen-dropup" name="query-log" data-placeholder="' . $this->l->t('Query Log'). '">';
      //$html .= '<option value="" hidden>' . $this->l->t('Query Log') . '</option>';
      $html .= '<option value="" hidden></option>';
      $cnt = 0;
      $queryLog = $this->pme->queryLog();
      usort($queryLog, function($a, $b) {
        $aVal = (double)$a['duration'];
        $bVal = (double)$b['duration'];
        return (($aVal == $bVal) ? 0 : ($aVal < $bVal ? +1 : -1));
      });
      foreach ($queryLog as $logEntry) {
        $label = htmlspecialchars(substr($logEntry['query'], 0, 24));
        if (strlen($logEntry['query']) > 24) {
          $label .= ' &#8230;';
        }
        $data = htmlspecialchars(json_encode($logEntry), ENT_QUOTES, 'UTF-8');
        $html .= '<option data-query=\'' . $data . '\' value="' . $cnt . '">' . $label . '</option>';
        $cnt++;
      }
      $html .= '</select></span>';
      return $html;
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

  /** Run underlying table-manager (phpMyEdit for now). */
  public function execute($opts = [])
  {
    $this->pme->beginTransaction();
    try {
      $this->pme->execute($opts);
      $this->pme->commit();
    } catch (\Throwable $t) {
      $this->logException($t, 'Rolling back SQL transaction ...');
      $this->pme->rollBack();
      throw new \Exception($this->l->t('SQL Transaction failed: %s', $t->getMessage()), $t->getCode(), $t);
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

  public function operation()
  {
    return $this->pme->operation??'';
  }

  /** Are we in add mode? */
  public function addOperation()
  {
    return $this->pme->add_operation();
  }

  /** Are we in change mode? */
  public function changeOperation()
  {
    return $this->pme->change_operation();
  }

  /** Are we in copy mode? */
  public function copyOperation()
  {
    return $this->pme->copy_operation();
  }

  /** Are we in view mode? */
  public function viewOperation()
  {
    return $this->pme->view_operation();
  }

  /** Are we in delete mode?*/
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
        'display|ACP' => [
          'attributes' => [
            'step' => '0.01',
          ],
          'postfix' => '&nbsp;' . $this->currencySymbol(),
        ],
        'maxlen' => '8', // NB: +NNNN.NN = 8
        'escape' => false,
        'sort' => true,
      ],
      'datetime' => [
        'select'   => 'T',
        'maxlen'   => 19,
        'sort'     => true,
        'dateformat' => 'medium',
        'timeformat' => 'short',
        'css'      => [ 'postfix' => [ 'datetime', ], ],
      ],
      'date' => [
        'select'   => 'T',
        'maxlen'   => 10,
        'sort'     => true,
        'css'      => [ 'postfix' => [ 'date', ], ],
        'dateformat' => 'medium',
        'timeformat' => false, // or leave out
      ],
      'due_date' => [
        'select'   => 'T',
        'name' => $this->l->t('Due Date'),
        'maxlen'   => 10,
        'sort'     => true,
        'css'      => [ 'postfix' => [ 'date', 'due-date', ], ],
        'dateformat' => 'medium',
      ],
      // Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity
      'deleted' => [
        'name' => $this->l->t('Revoked'),
        'input' => $this->expertMode ? '' : 'R',
        'maxlen' => 10,
        'sort' => true,
        'css' => [ 'postfix' => [ 'revocation-date', 'date',  'show-disabled-shown', 'hide-disabled-hidden', ], ],
        'dateformat' => 'medium',
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
        $value = preg_replace('/\s*,\s*/', self::VALUES_SEP, $value);
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

  protected function debugPrintValues($oldValues, $changed, $newValues, $onlyKeys = null, $prefix = null)
  {
    $prefix = $prefix ? strtoupper($prefix) . ' ' : '';
    $this->debug($prefix .'OLDVALS ' . print_r(Util::arraySliceKeys($oldValues, $onlyKeys), true), [], 1);
    $this->debug($prefix . 'NEWVALS ' . print_r(Util::arraySliceKeys($newValues, $onlyKeys), true), [], 1);
    $this->debug($prefix . 'CHANGED ' . print_r($changed, true), [], 1);
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

    $this->debug('FIELDS: ' . $keyField . ' / ' . $rankingField);
    $this->debugPrintValues($oldValues, $changed, $newValues, [ $keyField, $rankingField ]);

    foreach (['old', 'new'] as $dataSet) {
      $keys = Util::explode(self::VALUES_SEP, Util::removeSpaces(${$dataSet.'Values'}[$keyField ] ?? ''));
      $ranking = [];
      foreach ($keys as $key) {
        $ranking[] = $key.self::JOIN_KEY_SEP.(count($ranking)+1);
      }
      ${$dataSet.'Values'}[$rankingField] = implode(self::VALUES_SEP, $ranking);
    }

    // as the ordering is implied by the ordering of keys the ranking
    // changes whenever the keys change.
    if (array_search($keyField, $changed) !== false) {
      $changed[] = $rankingField;
    }

    $this->debugPrintValues($oldValues, $changed, $newValues, [ $keyField, $rankingField ], 'after');

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

    $this->debugPrintValues($oldvals, $changed, $newvals, null, 'before');

    $changeSets = [];
    foreach ($changed as $field) {
      $fieldInfo = $this->joinTableField($field);
      $changeSets[$fieldInfo['table']][$fieldInfo['column']] = $field;
    }
    $this->debug('CHANGESETS: '.print_r($changeSets, true));

    $masterTable = null;
    $masterEntity = null; // cache for a reference to the master entity
    foreach ($this->joinStructure as $table => $joinInfo) {
      $this->debug('TABLE ' . $table);
      $changeSet = $changeSets[$table]??[];
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
            'old' => Util::explode(self::VALUES_SEP, Util::removeSpaces($oldvals[$keyField])),
            'new' => Util::explode(self::VALUES_SEP, Util::removeSpaces($newvals[$keyField])),
          ];
          // handle "deleted" information if present. This is meant for disabled instruments and the like
          $deletedField = $this->joinTableFieldName($joinInfo, 'deleted');
          if (!empty($oldvals[$deletedField]) && !preg_match('/^\\d+:/', $oldvals[$deletedField])) {
            $deletedKeys = Util::explode(self::VALUES_SEP, $oldvals[$deletedField]);
            foreach (array_intersect($deletedKeys, $identifier[$key]['new']) as $deletedKey) {
              $identifier[$key]['old'][] = $deletedKey;
              $changeSet['deleted'] = $deletedField;
            }
            $identifier[$key]['old'] = array_values(array_unique($identifier[$key]['old']));
            $newvals[$deletedField] = implode(self::VALUES_SEP, array_diff($deletedKeys, $identifier[$key]['new']));
          }

          $identifier[$key]['del'] = array_diff($identifier[$key]['old'], $identifier[$key]['new']);
          $identifier[$key]['add'] = array_diff($identifier[$key]['new'], $identifier[$key]['old']);
          $identifier[$key]['rem'] = array_intersect($identifier[$key]['new'], $identifier[$key]['old']);

          if (isset($changeSet[$joinInfo['column']])) {
            Util::unsetValue($changed, $changeSet[$joinInfo['column']]);
            unset($changeSet[$joinInfo['column']]);
          }
          $multiple = $key;
          if ($joinInfo['flags'] & self::JOIN_SINGLE_VALUED) {
            if (count($identifier[$key]['old']) > 1 || count($identifier[$key]['old']) > 1) {
              throw new \RuntimeException(
                $this->l->t(
                  'Identifier column "%s" for single-valued join-table "%s" contains more than one key: "%s" / "%s".', [
                    $key,
                    $table,
                    implode(',', $identifier[$key]['old']),
                    implode(',', $identifier[$key]['new']),
                  ]));
            }
          }
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

        // needed later to slice entity ids
        $multipleKeys = [ $multiple ];

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
            // wrap into just another array in order to handle 'self' key-value provider
            ${$operation.'Identifier'}[$idKey] = [ ${$operation.'Identifier'}[$idKey] ];
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
          $multipleKeys[] = $selfKey;

          // e.g. $selfField == ProjectInstruments:voice
          $selfField = $value['self'];

          // initialize self-key values
          foreach ($dataSets as $operation => $dataSet) {
            foreach (${$operation.'Identifier'} as $key => &$idValues) {
              foreach ($idValues as &$idValuesTuple) {
                $idValuesTuple[$selfKey] = null;
              }
              unset($idValuesTuple); // break reference
            }
            unset($idValues); // break reference
          }

          // explode key values
          foreach (['old', 'new'] as $dataSet) {
            $dataValues = ${$dataSet.'vals'};
            $selfValues[$dataSet] = Util::explode(self::VALUES_SEP, $dataValues[$selfField]);
          }
          $selfValues['del'] = array_diff($selfValues['old'], $selfValues['new']);
          $selfValues['add'] = array_diff($selfValues['new'], $selfValues['old']);
          $selfValues['rem'] = array_intersect($selfValues['new'], $selfValues['old']);
          foreach(['old', 'new', 'del', 'add', 'rem'] as $tag) {
            $selfValues[$tag] = Util::explodeIndexedMulti(implode(self::VALUES_SEP,  $selfValues[$tag]));
          }

          $this->debug('SELFVALUES ' . print_r($selfValues, true));

          // $selfValues['del'] adds to the 'del' key values, but does
          // not change add and rem
          $operation = 'del';
          foreach ($selfValues[$operation] as $key => $selfValuesTuple) {
            // make sure we have a $delIdentifier
            if (!isset(${$operation.'Identifier'}[$key])) {
              if (!isset($remIdentifier[$key])) {
                throw new \RuntimeException($this->l->t('Inconsistent removal request for "%1$s" (%3$s), major key "%2$s" (%4$s).', [$selfField, $multiple, implode(self::VALUES_SEP, $selfValuesTuple), $key]));
              }
              ${$operation.'Identifier'}[$key] = $remIdentifier[$key];
              if (array_search($key, $identifier[$multiple]['del']) === false) {
                $identifier[$multiple]['del'][] = $key;
              }
            }

            // blow up the delete identifiers
            $idValues = ${$operation.'Identifier'}[$key];
            ${$operation.'Identifier'}[$key] = [];
            foreach ($idValues as $idValuesTuple) {
              foreach ($selfValuesTuple as $selfValue) {
                $idValuesTuple[$selfKey] = $selfValue;
                ${$operation.'Identifier'}[$key][] = $idValuesTuple;
              }
            }
          }

          // $selfValues['add'] adds to the 'add' key values and
          // removes from the 'rem' key values if no other self-values
          // are in the "remaining" set.
          $operation = 'add';
          foreach ($selfValues[$operation] as $key => $selfValuesTuple) {
            // make sure we have an $addIdentifier
            if (!isset(${$operation.'Identifier'}[$key])) {
              if (!isset($remIdentifier[$key])) {
                throw new \RuntimeException($this->l->t('Inconsistent add request for "%1$s" (%3$%s), major key "%2$s" (%4$s).', [$selfField, $multiple, implode(self::VALUES_SEP, $selfValuesTuple), $key]));
              }
              ${$operation.'Identifier'}[$key] = $remIdentifier[$key];
              if (empty($selfValues['rem'][$key])) {
                unset($remIdentifier[$key]);
              }
              if (array_search($key, $identifier[$multiple]['new']) === false) {
                $identifier[$multiple]['new'][] = $key;
              }
            }

            // blow up the add identifiers
            $idValues = ${$operation.'Identifier'}[$key];
            ${$operation.'Identifier'}[$key] = [];
            foreach ($idValues as $idValuesTuple) {
              foreach ($selfValuesTuple as $selfValue) {
                $idValuesTuple[$selfKey] = $selfValue;
                ${$operation.'Identifier'}[$key][] = $idValuesTuple;
              }
            }
          }

          // $selfValues['rem'] just blows up the 'rem' key values
          $operation = 'rem';
          foreach ($selfValues[$operation] as $key => $selfValuesTuple) {
            // make sure we have an $addIdentifier
            if (!isset(${$operation.'Identifier'}[$key])) {
              throw new \RuntimeException($this->l->t('Inconsistent remaining data for "%1$s" (%3$s), major key "%2$s" (%4$s).', [$selfField, $muliple, implode(self::VALUES_SEP, $selfValuesTuple), $key]));
            }

            $this->debug('SELF VALUES TUPLE ' . $key . ' => ' . print_r($selfValuesTuple, true));

            // blow up the rem identifiers
            $idValues = ${$operation.'Identifier'}[$key];
            ${$operation.'Identifier'}[$key] = [];
            foreach ($idValues as $idValuesTuple) {
              $this->debug('ID VALUES TUPLE ' . $key . ' => ' . print_r($idValuesTuple, true));
              foreach ($selfValuesTuple as $selfValue) {
                $idValuesTuple[$selfKey] = $selfValue;
                ${$operation.'Identifier'}[$key][] = $idValuesTuple;
              }
            }
          }

          // just make sure that the self-value has a value in any case
          foreach ($dataSets as $operation => $dataSet) {
            foreach (${$operation.'Identifier'} as $key => &$idValues) {
              foreach ($idValues as &$idValuesTuple) {
                if (empty($idValuesTuple[$selfKey])) {
                  $idValuesTuple[$selfKey] = $pme->fdd[$selfField]['default'];
                }
                if ($idValuesTuple[$selfKey] === null) {
                  throw new \RuntimeException($this->l->t('No value for identifier field "%s / %s".', [$selfKey, $selfField]));
                }
              }
            }
          }

          // remove the here handled key-column from the changeset
          unset($changeSet[$selfKey]);
          Util::unsetValue($changed, $selfField);
        }

        $this->debug('IDENTIFIER ' . print_r($identifier, true));
        $this->debug('MULTIPLE KEYS ' . print_r($multipleKeys, true));
        $this->debug('ADDIDS ' . print_r($addIdentifier, true));
        $this->debug('REMIDS ' . print_r($remIdentifier, true));
        $this->debug('DELIDS ' . print_r($delIdentifier, true));

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
            $ids = $delIdentifier[$del];
            foreach ($ids as $id) {
              $entityId = $meta->extractKeyValues($id);
              foreach ($association->matching(self::criteriaWhere($entityId)) as $entity) {
                $association->removeElement($entity);
              }
            }
          }

          // add entries by adding them to the association of the
          // master entity
          foreach ($identifier[$multiple]['add'] as $add) {
            $ids = $addIdentifier[$add];
            foreach ($ids as $id) {
              $entityId = $meta->extractKeyValues($id);
              $association->add($this->getReference($entityClass, $entityId));
            }
          }

          $masterEntity[$joinInfo['association']] = $association;

          continue; // skip to next field
        }

        // Delete removed entities
        foreach ($identifier[$multiple]['del'] as $del) {
          $ids = $delIdentifier[$del];
          foreach ($ids as $id) {
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
              $this->debug('HARD DELETE '.(int)($softDeleteable && (int)$entity->isDeleted()));
              $this->remove($entity); // hard
            }
            $this->flush();
          }
        }

        $multipleValues = [];
        $this->debug('CHANGESET: ' . print_r($changeSet, true));
        foreach ($changeSet as $column => $field) {
          $this->debug('GET MULTIPLE FOR ' . $column . ' / ' . $field);
          $this->debug('ROW MULTIPLE DATA ' . $newvals[$field]);
          // convention for multiple change-sets:
          //
          // KEY00-KEY01:VALUE0,KEY10-KEY11:VALUE1,...
          //
          // VALUE_N must not contain commas and colons
          $multipleValues[$column] = [
            'data' => [],
            'default' => $pme->fdd[$field]['default']??null,
          ];
          if ($joinInfo['flags'] & self::JOIN_SINGLE_VALUED) {
            if (!empty($newvals[$field])) {
              // assume everything after the first self::JOIN_KEY_SEP is
              // the one and only value
              list($key, $value) = explode(self::JOIN_KEY_SEP, $newvals[$field], 2);
              $multipleValues[$column]['data'][$key] = $value;
            }
          } else {
            foreach (Util::explodeIndexed($newvals[$field], null, self::VALUES_SEP, self::JOIN_KEY_SEP) as $key => $value) {
              $multipleValues[$column]['data'][$key] = $value;
            }
          }
        }

        $this->debug('MULTIPLE VALUES '.print_r($multipleValues, true));

        // Add new entities
        foreach ($identifier[$multiple]['new'] as $new) {
          if (isset($addIdentifier[$new])) {
            $this->debug('TRY ADD ' . $new);
            $ids = $addIdentifier[$new];
            foreach ($ids as $id) {
              $this->debug('ADD ID ' . print_r($id, true));
              $entityId = $meta->extractKeyValues($id);
              $this->debug('ENTITIY ID '.print_r($entityId, true));
              $entity = $entityClass::create();
              foreach ($entityId as $key => $value) {
                if (is_numeric($value) && $value <= 0) {
                  // treat this as autoincrement or otherwise auto-generated ids
                  continue;
                }
                $entity[$key] = $value;
              }

              // set further properties ...
              $multipleIndex = $this->compositeKeySlice($multipleKeys, $id);
              $this->debug('MULTIPLE KEYS ' . $multipleIndex);
              foreach ($multipleValues as $column => $dataItem) {
                $value = $dataItem['data'][$multipleIndex]??$dataItem['default'];
                $meta->setColumnValue($entity, $column, $value);
              }

              // persist
              $this->persist($entity);

              // flush in order to trigger auto-increment
              $this->flush();

              // distribute potential new key-values to the $newvals array

              $identifierColumnValues = $meta->getIdentifierColumnValues($entity);
              foreach ($identifierColumns as $key) {
                // Always set the field with explicitly matching name
                $selfField = $this->joinTableFieldName($joinInfo, $key);
                if (isset($newvals[$selfField])) {
                  $newvals[$selfField] = $identifierColumnValues[$key];
                }
                // set further values for more complicated joins
                $pivotColumn = $this->findJoinColumnPivot($joinInfo, $key);
                if ($pivotColumn === false) {
                  // assume that the 'column' component contains the keys.
                  $keyField = $this->joinTableFieldName($joinInfo, $joinInfo['column']);
                  $masterField = $this->joinTableMasterFieldName($joinInfo);
                  $newvals[$masterField] = $newvals[$keyField] = $identifierColumnValues[$key];
                } else if (!is_array($pivotColumn)) {
                  $newvals[$pivotColumn] = $identifierColumnValues[$key];
                } else if (isset($pivotColumn['value'])){
                  if ($pivotColumn['value'] != $identifierColumnValues[$key]) {
                    throw new Exceptions\DatabaseInconsistentValueException($this->l->t(
                      'Adding a new entity "%1$s" resulted in an inconsistent value for the column "%2$s", prescribed value was "%3$s", generated value is "%4$s".',
                      [ $entityClass, $key, $pivotColumn['value'], $identifierColumnValues[$key] ]
                    ));
                  }
                } else if (isset($pivotColumn['self'])) {
                  // always set
                } else {
                  throw new \RuntimeException($this->l->t('Field "%s.%s": nested multi-value join tables with unexpected pivot-column: %s.', [ $table, $key, print_r($pivotColumn, true), ]));
                }
              }
            }
          }
          if (isset($remIdentifier[$new]) && !empty($changeSet)) {
            $this->debug('TRY MOD '.$new);
            $ids = $remIdentifier[$new];
            foreach ($ids as $id) {
              $this->debug('REM ID '.print_r($id, true));
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

              // set further properties ...
              $multipleIndex = $this->compositeKeySlice($multipleKeys, $id);
              $this->debug('MULTIPLE KEYS ' . $multipleIndex);
              foreach ($multipleValues as $column => $dataItem) {
                $value = $dataItem['data'][$multipleIndex]??$dataItem['default'];
                $this->debug('SET MULTIPLE VALUE ' .  $column . ' => ' . $value);
                $meta->setColumnValue($entity, $column, $value);
              }

              // persist
              $this->persist($entity);
            }
          }
        }
        foreach ($changeSet as $column => $field) {
          Util::unsetValue($changed, $field);
        }
      } else {
        // !$multiple, simply update. The "master-"table can only
        // "land" here

        // Note: this implies an additional fetch from the database,
        // however, in the long run the goal would be to switch to
        // Doctrine/ORM for everything. So we live with it for the
        // moment.
        $this->debug('IDENTIFIER '.print_r($identifier, true));
        $entityId = $meta->extractKeyValues($identifier);
        $entity = $this->find($entityId);
        if (empty($entity)) {
          $this->debug('Entity not found, creating');
          $entity = $entityClass::create();
          foreach ($entityId as $key => $value) {
            $entity[$key] = $value;
          }
        }
        foreach ($changeSet as $column => $field) {
          $this->debug('Set ' . $column . ' / ' . $field . ' to ' . $newvals[$field]);
          $meta->setColumnValue($entity, $column, $newvals[$field]);
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

    // As this is not timing critical we should perhaps reload the master entity
    // from the database in order to sanitize all associations.
    // $this->refreshEntity($masterEntity);

    $this->debugPrintValues($oldvals, $changed, $newvals, null, 'after');

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

    $this->debugPrintValues($oldvals, $changed, $newvals, null, 'before');

    $missingKeys = [];
    foreach ($pme->key as $key => $type) {
      if (!isset($newvals[$key])) {
        $missingKeys[] = $key;
      }
    }
    $this->debug('MISSING '.print_r($missingKeys, true));

    // try to fill missing keys of the master table by set keys of join columns
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
    $this->debug('CHANGESETS: ' . print_r($changeSets, true));

    $masterEntity = null; // cache for a reference to the master entity
    foreach ($this->joinStructure as $table => $joinInfo) {
      $changeSet = $changeSets[$table] ?? [];
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
      $this->debug('CHANGESET ' . $table . ' ' . print_r($changeSet, true));
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
          $identifier[$key] = Util::explode(self::VALUES_SEP, $newvals[$keyField]);

          if (isset($changeSet[$joinInfo['column']])) {
            Util::unsetValue($changed, $changeSet[$joinInfo['column']]);
            unset($changeSet[$joinInfo['column']]);
          }
          $multiple = $key;
          if ($joinInfo['flags'] & self::JOIN_SINGLE_VALUED) {
            if (count($identifier[$key]['old']) > 1 || count($identifier[$key]['old']) > 1) {
              throw new \RuntimeException(
                $this->l->t(
                  'Identifier column "%s" for single-valued join-table "%s" contains more than one key: "%s" / "%s".', [
                    $key,
                    $table,
                    implode(',', $identifier[$key]['old']),
                    implode(',', $identifier[$key]['new']),
                  ]));
            }
          }
        } else {
          $this->debug('PIVOT-COLUMN ' . $key . ' -> ' . print_r($pivotColumn, true));
          if (!is_array($pivotColumn)) {
            $identifier[$key] = $newvals[$pivotColumn]??null;
            unset($changeSet[$key]);
            Util::unsetValue($changed, $key);
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

        // needed later to slice entity ids
        $multipleKeys = [ $multiple ];

        $this->debug('IDS '.print_r($identifier, true));
        $this->debug('CHG '.print_r($changeSet, true));

        $addIdentifier = [];
        foreach ($identifier[$multiple] as $addKey) {
          $addIdentifier[$addKey] = $identifier;
          $addIdentifier[$addKey][$multiple] = $addKey;
          // wrap into just another array in order to handle 'self' key-value provider
          $addIdentifier[$addKey] = [ $addIdentifier[$addKey] ];
        }
        $this->debug('ADDIDS INITIAL: '.print_r($addIdentifier, true));

        foreach ($identifier as $selfKey => $value) {
          if (empty($value['self'])) {
            continue;
          }
          $multipleKeys[] = $selfKey;

          // e.g. $selfField == ProjectInstruments:voice
          $selfField = $value['self'];
          $this->debug('FETCH VALUE FOR SELF-FIELD ' . $selfField);

          foreach ($addIdentifier as $key => &$idValues) {
            foreach ($idValues as &$idValuesTuple) {
              $idValuesTuple[$selfKey] = null;
            }
          }
          unset($idValues); // break reference
          unset($idValuesTuple); // break reference
          $this->debug('ADDIDS INITIALIZED: '.print_r($addIdentifier, true));

          // explode key values
          $selfValues = Util::explodeIndexedMulti($newvals[$selfField]);

          // $selfValues potentially adds to the 'add' key values
          foreach ($selfValues as $key => $selfValuesTuple) {

            $this->debug('SELF VALUES FOR ' . $selfKey . '@' . $key . ': ' . print_r($selfValuesTuple, true));

            // make sure we have an $addIdentifier
            if (!isset($addIdentifier[$key])) {
              throw new \RuntimeException($this->l->t('Inconsistent add request for "%1$s", major key "%2$s".', [$selfField, $selfKey]));
            }

            // blow up the add identifiers
            $idValues = $addIdentifier[$key];
            $addIdentifier[$key] = [];
            $this->debug('ID-VALUES FOR ' . $key . ': ' . print_r($idValues, true));
            foreach ($idValues as $idValuesTuple) {
              foreach ($selfValuesTuple as $selfValue) {
                $idValuesTuple[$selfKey] = $selfValue;
                $addIdentifier[$key][] = $idValuesTuple;
              }
            }
            $this->debug('ADDIDS SO FAR: ' . print_r($addIdentifier, true));
          }

          // just make sure that the self-value has a value in any case
          foreach ($addIdentifier as $key => &$idValues) {
            foreach ($idValues as &$idValuesTuple) {
              if (empty($idValuesTuple[$selfKey])) {
                $this->debug('SET SELF KEY TO DEFAULT ' . $selfField . ' / ' . $selfKey . ' -> ' . $pme->fdd[$selfField]['default']);
                $idValuesTuple[$selfKey] = $pme->fdd[$selfField]['default'];
              }
              if ($idValuesTuple[$selfKey] === null) {
                throw new \RuntimeException($this->l->t('No value for identifier field "%s / %s".', [$selfKey, $selfField]));
              }
            }
          }

          // remove the here handled key-column from the changeset
          unset($changeSet[$selfKey]);
          Util::unsetValue($changed, $selfField);
        }

        $this->debug('ADDIDS FINALLY: '.print_r($addIdentifier, true));
        $this->debug('MULTIPLE KEYS ' . print_r($multipleKeys, true));

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
            $ids = $addIdentifier[$add];
            foreach ($ids as $id) {
              $entityId = $meta->extractKeyValues($id);
              $association->add($this->getReference($entityClass, $entityId));
            }
          }

          $masterEntity[$joinInfo['association']] = $association;

          continue; // skip to next field
        }

        $multipleValues = [];
        foreach ($changeSet as $column => $field) {
          $this->debug('GET MULTIPLE FOR ' . $column . ' / ' . $field);
          // convention for multiple change-sets:
          //
          // KEY00-KEY01:VALUE0,KEY10-KEY11:VALUE1,...
          //
          // VALUE_N must not contain commas and colons
          $multipleValues[$column] = [
            'data' => [],
            'default' => $pme->fdd[$field]['default']??null,
          ];
          if (!str_starts_with($field, $table . self::JOIN_KEY_SEP)) {
            // This happens if the master table injects auto-increment ids. In
            // this case $field is one of the identifier fields of the master
            // table. We slightly mis-use the default field in order to inject
            // the correct value.
            $multipleValues[$column]['default'] = $newvals[$field];
          } else if ($joinInfo['flags'] & self::JOIN_SINGLE_VALUED) {
            if (!empty($newvals[$field])) {
              // assume everything after the first self::JOIN_KEY_SEP is
              // the one and only value
              list($key, $value) = explode(self::JOIN_KEY_SEP, $newvals[$field], 2);
              $multipleValues[$column]['data'][$key] = $value;
            }
          } else {
            foreach (Util::explodeIndexed($newvals[$field], null, self::VALUES_SEP, self::JOIN_KEY_SEP) as $key => $value) {
              $multipleValues[$column]['data'][$key] = $value;
            }
          }
        }

        $this->debug('MULTIPLE VALUS '.print_r($multipleValues, true));

        // Add new entities
        foreach ($identifier[$multiple] as $new) {
          $this->debug('TRY ADD ' . $new . ' ' . print_r($addIdentifier[$new], true));
          $ids = $addIdentifier[$new];
          foreach ($ids as $id) {
            $entityId = $meta->extractKeyValues($id);
            $entity = $entityClass::create();
            foreach ($entityId as $key => $value) {
              $entity[$key] = $value;
            }

            // set further properties ...
            $this->debug('MULTIPLE KEYS ' . print_r($multipleKeys, true));
            $this->debug('ENTITY ID ' . print_r($entityId, true));
            $multipleIndex = $this->compositeKeySlice($multipleKeys, $id);
            $this->debug('MULTIPLE INDEX ' . $multipleIndex);
            foreach ($multipleValues as $column => $dataItem) {
              $value = $dataItem['data'][$multipleIndex]??$dataItem['default'];
              $this->debug('Set ' . $entityClass . '::' . $column . ' -> ' . $value);
              $meta->setColumnValue($entity, $column, $value);
            }

            // persist
            $this->persist($entity);

            // flush in order to trigger auto-increment
            $this->flush();

            // distribute potential new key-values to the $newvals array
            $identifierColumnValues = $meta->getIdentifierColumnValues($entity);
            foreach ($identifierColumns as $key) {
              // Always set the field with explicitly matching name
              $selfField = $this->joinTableFieldName($joinInfo, $key);
              if (isset($newvals[$selfField])) {
                $newvals[$selfField] = $identifierColumnValues[$key];
              }
              // set further values for more complicated joins
              $pivotColumn = $this->findJoinColumnPivot($joinInfo, $key);
              if ($pivotColumn === false) {
                // assume that the 'column' component contains the keys.
                $keyField = $this->joinTableFieldName($joinInfo, $joinInfo['column']);
                $masterField = $this->joinTableMasterFieldName($joinInfo);
                $newvals[$masterField] = $newvals[$keyField] = $identifierColumnValues[$key];
              } else if (!is_array($pivotColumn)) {
                $newvals[$pivotColumn] = $identifierColumnValues[$key];
              } else if (isset($pivotColumn['value'])){
                if ($pivotColumn['value'] != $identifierColumnValues[$key]) {
                  throw new Exceptions\DatabaseInconsistentValueException($this->l->t(
                    'Adding a new entity "%1$s" resulted in an inconsistent value for the column "%2$s", prescribed value was "%3$s", generated value is "%4$s".',
                    [ $entityClass, $key, $pivotColumn['value'], $identifierColumnValues[$key] ]
                  ));
                }
              } else if (isset($pivotColumn['self'])) {
                // always set
              } else {
                throw new \RuntimeException($this->l->t('Field "%s.%s": nested multi-value join tables with unexpected pivot-column: %s.', [ $table, $key, print_r($pivotColumn, true), ]));
              }
            }
          }
        }
        foreach ($changeSet as $column => $field) {
          Util::unsetValue($changed, $field);
        }
      } else {
        // !$multiple, simply insert. The "master-"table can only
        // "land" here
        $this->debug('IDENTIFIER ' . print_r($identifier, true));
        $entityId = $meta->extractKeyValues($identifier);
        $entity = $entityClass::create();
        foreach ($entityId as $key => $value) {
          $this->debug('TRY SET ID ' . $key . ' => ' . $value);
          $entity[$key] = $value;
        }
        foreach ($changeSet as $column => $field) {
          $this->debug('TRY SET ' . $column . ' => ' . $newvals[$field]);
          $meta->setColumnValue($entity, $column, $newvals[$field]);
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
            $this->debug('INJECT MASTER KEY ' . $key . ' -> ' . $identifier[$key]);
            $newvals[$key] = $identifier[$key];
          }

          // fill in missing join keys if required
          foreach ($changeSets as $table => &$childChangeSet) {
            $childJoinInfo = $this->joinStructure[$table];
            if ($childJoinInfo['flags'] & self::JOIN_MASTER) {
              continue;
            }
            $this->debug('ORIG CHILD CHANGESET ' . $table . ' ' . print_r($childChangeSet, true));
            foreach (array_keys($this->pme->key) as $key) {
              foreach (['identifier', 'filter'] as $columnRestriction) {
                foreach (($childJoinInfo[$columnRestriction]??[]) as $column => $target) {
                  if ($target === $key) {
                    // $newvals[$this->joinTableFieldName($table, $column)] = $newvals[$key];
                    $childChangeSet[$column] = $key;
                  }
                }
              }
            }
            $this->debug('MANIP CHILD CHANGESET ' . $table . ' ' . print_r($childChangeSet, true));
          }
          $this->debug('MANIP newvals ' . print_r($newvals, true));
          unset($childChangeSet); // break reference
        }
      }
    }
    $this->flush(); // flush everything to the data-base

    // As this is not time critical we should perhaps reload the master entity
    // from the database in order to sanitize all associations.
    // $this->refreshEntity($masterEntity);

    $this->debugPrintValues($oldvals, $changed, $newvals, null, 'after');

    if (!empty($changed)) {
      throw new \Exception(
        $this->l->t('Remaining change-set %s must be empty', print_r($changed, true)));
    }

    // all should be done
    $pme->setLogging(false);

    return true; // in order to update key-fields
  }

  /**
   * Convert the given legacy PHPMyEdit record id as given by the
   * member variable PHPMyEdit::rec to something understood by
   * Doctrine\ORM.
   *
   * @param array $pmeRecordId
   *
   * @return array
   */
  protected function legacyRecordToEntityId(array $pmeRecordId, string $table = null):array
  {
    if (empty($table)) {
      $entityName = null;
      foreach ($this->joinStructure as $table => $joinInfo) {
        if (($joinInfo['flags']??self::JOIN_FLAGS_NONE) & self::JOIN_MASTER) {
          $entityName = $joinInfo['entity'];
          break;
        }
      }
    } else {
      $entityName = $this->joinStructure[$table]['entity'];
    }
    $repository = $this->getDatabaseRepository($entityName);
    $meta = $this->classMetadata($entityName);
    $entityId = $meta->extractKeyValues($pmeRecordId);

    $this->logInfo('ENTITY ' . $entityName . ' ' . print_r($entityId, true));

    return $entityId;
  }

  /**
   * Convert the given legacy PHPMyEdit record id as given by the
   * member variable PHPMyEdit::rec to something understood by
   * Doctrine\ORM.
   *
   * @param array $pmeRecordId
   *
   * @return Object
   */
  protected function legacyRecordToEntity(array $pmeRecordId)
  {
    $entityId = $this->legacyRecordToEntityId($pmeRecordId);

    return $this->find($entityId);
  }

  /**
   * This trigger simply deletes the given entity and prevents the
   * legacy PHPMyEdit class to do the deletion in order to benefit
   * from ORM. No fancy things are done, simply the deletion.
   *
   * phpMyEdit calls the trigger (callback) with
   * the following arguments:
   *
   * @param $pme The phpMyEdit instance
   *
   * @param $op The operation, 'insert', 'update' etc.
   *
   * @param $step 'before' or 'after'
   *
   * @param $oldValues Self-explanatory.
   *
   * @param &$changed Set of changed fields, may be modified by the callback.
   *
   * @param &$newValues Set of new values, which may also be modified.
   *
   * @return boolean If returning @c false the operation will be terminated
   */
  public function beforeDeleteSimplyDoDelete(&$pme, $op, $step, $oldValues, &$changed, &$newValues)
  {
    $entityId = $this->legacyRecordToEntityId($pme->rec);
    $this->remove($entityId, true);

    $changed = []; // disable PME delete query

    return true; // but run further triggers if appropriate
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
   *     'sql' => OPTIONAL_SUBQUERY_OR_CALLABLE_RETURNING_SUBQUERY,
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
      if (!empty($joinInfo['sql'])) {
        if (is_callable($joinInfo['sql'])) {
          $valueTable = call_user_func($joinInfo['sql'], $joinInfo);
        } else {
          $valuesTable = $joinInfo['sql'];
        }
      } else {
        $valuesTable = explode(self::VALUES_TABLE_SEP, $table)[0];
      }

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
              $joinCondition .= 'IN (' . implode(self::VALUES_SEP, $values) . ')';
            } else if (!empty($joinTableValue['condition'])) {
              $joinCondition .= $joinTableValue['condition'];
            } else if (!empty($joinTableValue['self'])) {
              // used during update to determine key values, otherwise ignored
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
      } else if ($group) {
        $opts['fdd'][$fieldName]['filter'] = [
          'having' => true,
        ];
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
   * Slice the given entity id by the given keys and return a
   * composite key joined with self::COMP_KEY_SEP
   */
  protected function compositeKeySlice(array $keys, array $entityId):string
  {
    $slice = [];
    foreach ($keys as $idKey) {
      $slice[] = $entityId[$idKey];
    }
    $slice = implode(self::COMP_KEY_SEP, $slice);
    return $slice;
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
   *
   * @return array
   * ```
   * [ INDEX, FIELDNAME ]
   * ```
   */
  protected function makeJoinTableField(array &$fieldDescriptionData, $tableInfo, string $column, array $fdd)
  {
    if (is_string($tableInfo) && isset($fdd['values']['join'])) {
      $defaultFDD = [
        'name' => $column,
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
        'name' => $column,
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
    if (isset($fdd['decoration'])) {
      if (!empty($fdd['decoration']['slug'])) {
        $this->addSlug($fdd['decoration']['slug'], $fieldDescriptionData[$fieldName]);
      }
      unset($fieldDescriptionData[$fieldName]['decoration']);
    }
    return [ $index, $fieldName ];
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
    $cssSlug = $this->cssClass().'--'.$slug;
    $tooltipSlug = $this->tooltipSlug($slug);
    if (!isset($fdd['css']['postfix'])) {
      $fdd['css'] = [ 'postfix' => [] ];
    }
    if (0 == count(preg_grep('/^tooltip-/', $fdd['css']['postfix']))) {
      $fdd['css']['postfix'][] = 'tooltip-auto';
    }
    $fdd['css']['postfix'][] = $cssSlug;
    $fdd['css']['postfix'][] = $this->appName() . '-' . 'slugged';
    $fdd['tooltip'] = $this->toolTipsService[$tooltipSlug];
    $fdd['colattrs'] = $fdd['colattrs']??[];
    $fdd['colattrs']['data-slug'] = $tooltipSlug;
  }

  /**
   * Generate a "unified" tooltip-slug in order to obtain tool-tips
   * from the ToolTipsService.
   */
  protected function tooltipSlug(string $slug)
  {
    return $this->cssClass().ToolTipsService::SUB_KEY_SEP.$slug;
  }

  protected function queryFieldIndex(string $key, array $fdd)
  {
    return array_search($key, array_keys($fdd));
  }

  protected function queryField(string $key, array $fdd)
  {
    return 'qf'.$this->queryFieldIndex($key, $fdd);
  }

  protected function queryIndexField(string $key, array $fdd)
  {
    return $this->queryField($key, $fdd) . '_idx';
  }

  protected function joinQueryField($tableInfo, $column, array $fdd)
  {
    return $this->queryField($this->joinTableFieldName($tableInfo, $column), $fdd);
  }

  protected function joinQueryIndexField($tableInfo, $column, array $fdd)
  {
    return $this->queryIndexField($this->joinTableFieldName($tableInfo, $column), $fdd);
  }

  /**
   * Join with an in-database translation table. The following fields are provided:
   *
   * - l10n_FIELD -- translated field will fallback translation to original value
   * - translated_FIELD -- translated field without fallback to original value
   * - original_FIELD -- untranslated field with fallback to translated value
   * - untranslated_FIELD -- untranslated field without fallback to translated value
   *
   * @param array $joinInfo Join desciption, see PMETableViewBase::defineJoinStructure().
   *
   * @param string|array $fields Single field-name as string or array of field-names.
   *
   * @param bool $onlyTranslated Only fetch line with translation values.
   *
   */
  protected function makeFieldTranslationsJoin(array $joinInfo, $fields, $onlyTranslated = false)
  {
    if (!is_array($fields)) {
      $fields = [ $fields ];
    }
    $l10nFields = [];
    foreach ($fields as $field) {
      $l10nFields[] = 'COALESCE(jt_'.$field.'.content, t.'.$field.') AS l10n_'.$field;
      $l10nFields[] = 'jt_'.$field.'.content AS translated_'.$field;
      $l10nFields[] = 'COALESCE(t.'.$field.', jt_'.$field.'.content) AS original_'.$field;
      $l10nFields[] = 't.'.$field.' AS untranslated_'.$field;
    }
    $entity = addslashes($joinInfo['entity']);
    // if (count($joinInfo['identifier']) > 1) {
    //   throw new \RuntimeException($this->l->t('Composite keys are not yet supported for translated database table fields.'));
    // }
    // $id = array_keys($joinInfo['identifier'])[0];
    $lang = $this->l10n()->getLanguageCode();
    $l10nJoins = [];
    foreach ($fields as $field) {
      $jt = 'jt_'.$field;
      $l10nJoin = "  LEFT JOIN ".self::FIELD_TRANSLATIONS_TABLE." $jt
  ON $jt.locale = '$lang'
    AND $jt.object_class = '$entity'
    AND $jt.field = '$field'
    AND $jt.foreign_key = ";
      if (count($joinInfo['identifier']) > 1) {
        $l10nJoin .= " CONCAT_WS(' ', ";
      }
      $l10nJoin .= implode(' ,', array_map(function($id) use ($joinInfo) {
        $column = 't.' . $id;
        if (isset($joinInfo['column']) && $id === $joinInfo['column'] && !empty($joinInfo['encode'])) {
          $column = sprintf($joinInfo['encode'], $column);
        }
        return $column;
      }, array_keys($joinInfo['identifier'])));
      if (count($joinInfo['identifier']) > 1) {
        $l10nJoin .= ')';
      }
      $l10nJoins[] = $l10nJoin;
    }
    $table = explode(self::VALUES_TABLE_SEP, $joinInfo['table'])[0];
    $query = 'SELECT t.*'
      . ', '
      . implode(', ', $l10nFields).'
  FROM '.$table.' t
'.implode('', $l10nJoins);
    if ($onlyTranslated) {
      array_map(function($field) { return 'l10n_'.$field.' IS NOT NULL'; }, $fields);
      $query .= ' WHERE '
        .implode(
          ' AND ',
          array_map(function($field) { return 'jt_'.$field.'.content IS NOT NULL'; }, $fields));
    }
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
  protected function debug(string $message, array $context = [], $shift = 0) {
    ++$shift;
    if ($this->debugRequests) {
      $this->logInfo($message, $context, $shift);
    } else {
      $this->logDebug($message, $context, $shift);
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
   *
   * @bug This should be moved to a trait for reuse only in classes needing it.
   */
  protected function musicianFromRow($row, ?PHPMyEdit $pme)
  {
    $pme = $pme?:$this->pme;
    $joinTable = !empty($pme->fdn[$this->joinTableMasterFieldName(self::MUSICIANS_TABLE)]);
    $data = [];
    foreach($pme->fds as $idx => $label) {
      if (isset($row['qf' . $idx . '_idx'])) {
        $data[$label] = $row['qf' . $idx . '_idx'];
      } elseif (isset($row['qf'.$idx])) {
        $data[$label] = $row['qf' . $idx];
      }
    }
    $categories = [];
    $musician = new Entities\Musician();
    if ($joinTable) {
      // make sure to fetch the id-record
      foreach ($this->joinStructure as $joinInfo) {
        if ($joinInfo['table'] == self::MUSICIANS_TABLE) {
          $idColumn = $joinInfo['identifier']['id'];
          $id = $row['qf' . ($pme->fdn[$idColumn])];
          $musician->setId($id);
          break;
        }
      }
    }
    $userIdSlugSeen = false;
    foreach ($data as $key => $value) {
      // In order to support "categories" the same way as the
      // AddressBook-integration we need to feed the
      // Musician-entity with more data:
      switch ($key) {
      case 'all_projects':
      case 'projects':
        $categories = array_merge($categories, explode(self::VALUES_SEP, Util::removeSpaces($value)));
        break;
      case 'MusicianInstrument:instrument_id':
        foreach (explode(self::VALUES_SEP, Util::removeSpaces($value)) as $instrumentId) {
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
          if ($column == 'user_id_slug') {
            $userIdSlugSeen = true;
          }
        } catch (\Throwable $t) {
          // Don't care, we know virtual stuff is not there
        }
        break;
      }
    }
    if (!$userIdSlugSeen) {
      $musicianId = $musician->getId();
      $musician = $this->findEntity(Entities\Musician::class, $musicianId);
      if (empty($musician)) {
        $this->logError('NO MUSICIAN FOR ROW ' . $musicianId . ' ' . print_r($row, true));
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

  /**
   * Create a trivial description definition with no casts and no NULL
   * coalescing.
   */
  static protected function trivialDescription($singleField = PHPMyEdit::TRIVIAL_DESCRIPION)
  {
    return [
      'columns' => [ $singleField ],
      'ifnull' => [ false ],
      'cast' => [ false ],
    ];
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
