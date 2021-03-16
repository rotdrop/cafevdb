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
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

/** Base for phpMyEdit based table-views. */
abstract class PMETableViewBase extends Renderer implements IPageRenderer
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const JOIN_FIELD_NAME_SEPARATOR = ':';
  const VALUES_TABLE_SEP = '@';

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

  /** @var array default PHPMyEdit options */
  protected $pmeOptions;

  /** @var ?int */
  protected $musicianId;

  /** @var ?int */
  protected $projectId;

  /** @var ?string */
  protected $projectName;

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

  /**
   * @var array
   * ```
   * [
   *   [
   *     'table' => SQL_TABLE_NAME,
   *     'entity' => DOCTRINE_ORM_ENTITY_CLASS_NAME,
   *     'master' => bool optional master-table
   *     'read_only' => bool optional not considered for update
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
    ];
    foreach ($cgiDefault as $key => $default) {
      $this->pmeOptions['cgi']['persist'][$key] =
        $this->{lcFirst($key)} =
          $this->requestParameters->getParam($key, $default);
    }

    $this->template = $template; // overwrite with child-class supplied value

    $this->pmeOptions['triggers']['*']['pre'][] = [ $this, 'preTrigger' ];

    //$this->pmeOptions['triggers']['update']['before'][] = [ __CLASS__, 'suspendLoggingTrigger' ];
    $this->pmeOptions['triggers']['update']['after'][] =
      $this->pmeOptions['triggers']['insert']['after'][] =
      $this->pmeOptions['triggers']['copy']['after'][] =
      $this->pmeOptions['triggers']['delete']['after'][] = [ __CLASS__, 'resumeLoggingTrigger' ];

    $this->pmeOptions['triggers']['update']['before'][] =
      $this->pmeOptions['triggers']['copy']['before'][] =
      $this->pmeOptions['triggers']['insert']['before'][] =
        [ $this, 'beforeAnythingTrimAnything' ];

    $this->pmeOptions['triggers']['update']['before'][] =
       $this->pmeOptions['triggers']['insert']['before'][] =
       $this->pmeOptions['triggers']['delete']['before'][] =
         function($pme, $op, $step, &$oldvals, &$changed, &$newvals) {
           $this->changeSetSize = count($changed);
           return true;
         };

    $this->pmeOptions['triggers']['update']['after'][] = function($pme) {
      $pme->message = $this->l->t(
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

    $this->extraFieldMultiplicities = DBTypes\EnumExtraFieldMultiplicity::toArray();
    foreach ($this->extraFieldMultiplicities as $key => $tag) {
      $slug = 'extra field '.$tag;
      $this->extraFieldMultiplicityNames[$tag] = $this->l->t($slug);
    }

    $this->extraFieldDataTypes = DBTypes\EnumExtraFieldDataType::toArray();
    foreach ($this->extraFieldDataTypes as $key => $tag) {
      $slug = 'extra field type '.$tag;
      $this->extraFieldDataTypeNames[$tag] = $this->l->t($slug);
    }
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
      $this->logError("Rolling back SQL transaction ...");
      $this->pme->rollBack();
      throw new \Exception($this->l->t("SQL Transaction failed."), $t->getCode(), $t);
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

  public function cssClass()
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
        'css'      => [ 'postfix' => ' email clip-long-text short-width' ],
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
        'css'      => [ 'postfix' => ' datetime' ],
      ],
      'date' => [
        'select'   => 'T',
        'maxlen'   => 10,
        'sort'     => true,
        'css'      => [ 'postfix' => ' date' ],
        'datemask' => 'd.m.Y',
      ],
      // Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity
      'deleted_at' => [
        'name' => $this->l->t('Date Revoked'),
        'input' => 'R',
        'maxlen' => 10,
        'sort' => true,
        'css' => [ 'postfix' => ' revocation-date date' ],
        'datemask' => 'd.m.Y',
      ],
    ];
    $fdd['birthday'] = $fdd['date'];
    $fdd['birthday']['name'] = $this->l->t('birthday');
    $fdd['birthday']['css']['postfix'] .= ' birthday';
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

      if ($pme->skipped($fdn) || $pme->readonly($fdn)) {
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
   */
  public function beforeUpdateDoUpdateAll(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
  {
    // leave time-stamps to the ORM "behaviors"
    Util::unsetValue($changed, 'updated');

    $logMethod = 'logDebug';
    // $logMethod = 'logInfo';

    $this->$logMethod('OLDVALS '.print_r($oldvals, true));
    $this->$logMethod('NEWVALS '.print_r($newvals, true));
    $this->$logMethod('CHANGED '.print_r($changed, true));
    $changeSets = [];
    foreach ($changed as $field) {
      $fieldInfo = $this->joinTableField($field);
      $changeSets[$fieldInfo['table']][$fieldInfo['column']] = $field;
    }
    $this->$logMethod('CHANGESETS: '.print_r($changeSets, true));

    foreach ($this->joinStructure as $joinInfo) {
      if (!empty($joinInfo['read_only'])) {
        continue;
      }
      $table = $joinInfo['table'];
      if (empty($changeSets[$table])) {
        continue;
      }
      if ($joinInfo['master']) {
        foreach ($this->pme->key as $key => $type) {
          $joinInfo['identifier'][$key] = $key;
        }
        // leave this to phpMyEdit, otherwise key-updates would need
        // further care.
        // continue;
      }
      $changeSet = $changeSets[$table];
      $this->$logMethod('CHANGESET '.$table.' '.print_r($changeSet, true));
      $entityClass = $joinInfo['entity'];
      $repository = $this->getDatabaseRepository($entityClass);
      $meta = $this->classMetadata($entityClass);
      //$this->$logMethod('ASSOCIATIONMAPPINGS '.print_r($meta->associationMappings, true));

      $identifier = [];
      $identifierColumns = $meta->getIdentifierColumnNames();
      foreach ($identifierColumns as $key) {
        if (empty($joinInfo['identifier'][$key])) {
          // assume that the 'column' component contains the keys.
          $keyField = $this->joinTableFieldName($joinInfo, $joinInfo['column']);
          $identifier[$key] = [
            'old' => Util::explode(',', Util::removeSpaces($oldvals[$keyField])),
            'new' => Util::explode(',', Util::removeSpaces($newvals[$keyField])),
          ];
          // handle "disabled" information if present
          $disabledField = $this->joinTableFieldName($joinInfo, 'disabled');
          if (!empty($oldvals[$disabledField])) {
            $disabledKeys = Util::explode(',', $oldvals[$disabledField]);
            foreach (array_intersect($disabledKeys, $identifier[$key]['new']) as $disabledKey) {
              $identifier[$key]['old'][] = $disabledKey;
              $changeSet['disabled'] = $disabledField;
            }
            $identifier[$key]['old'] = array_values(array_unique($identifier[$key]['old']));
            $newvals[$disabledField] = implode(',', array_diff($disabledKeys, $identifier[$key]['new']));
          }

          $identifier[$key]['del'] = array_diff($identifier[$key]['old'], $identifier[$key]['new']);
          $identifier[$key]['add'] = array_diff($identifier[$key]['new'], $identifier[$key]['old']);
          $identifier[$key]['rem'] = array_intersect($identifier[$key]['new'], $identifier[$key]['old']);

          Util::unsetValue($changed, $changeSet[$joinInfo['column']]);
          unset($changeSet[$joinInfo['column']]);
          $multiple = $key;
        } else if (is_array($joinInfo['identifier'][$key])) {
          if (!empty($joinInfo['identifier'][$key]['value'])) {
            $identifier[$key] = $joinInfo['identifier'][$key]['value'];
          } else {
            throw new \Exception($this->l->t('Nested multi-value join tables are not yet supported.'));
          }
        } else {
          $identifier[$key] = $oldvals[$joinInfo['identifier'][$key]];
        }
      }
      if (!empty($multiple)) {
        foreach ($identifier[$multiple]['old'] as $oldKey) {
          $oldIdentifier[$oldKey] = $identifier;
          $oldIdentifier[$oldKey][$multiple] = $oldKey;
        }
        foreach ($identifier[$multiple]['add'] as $addKey) {
          $addIdentifier[$addKey] = $identifier;
          $addIdentifier[$addKey][$multiple] = $addKey;
        }
        foreach ($identifier[$multiple]['rem'] as $remKey) {
          $remIdentifier[$remKey] = $identifier;
          $remIdentifier[$remKey][$multiple] = $remKey;
        }

        $this->$logMethod('IDS '.print_r($identifier, true));
        $this->$logMethod('CHG '.print_r($changeSet, true));

        // Delete removed entities
        foreach ($identifier[$multiple]['del'] as $del) {
          $id = $oldIdentifier[$del];
          $entityId = $this->extractKeyValues($meta, $id);
          $entity = $this->find($entityId);
          $usage  = method_exists($entity, 'usage') ? $entity->usage() : 0;
          if ($usage > 0) {
            /** @todo needs more logic: disabled things would need to
             *  be reenabled instead of adding new stuff. One
             *  possibility would be to add disabled things as hidden
             *  elements in order to keep them out of the way of the
             *  select boxes of the user interface.
             */
            $entity->setDisabled(true); // should be persisted on flush
            $this->flush($entity);
          } else {
            $this->remove($entityId);
          }
        }

        foreach ($changeSet as $column => $field) {
          // convention for multiple change-sets:
          //
          // - the values start with the key
          // - boolean false values are omitted
          // - optional values are omitted
          // - values are separated by a colon from the key
          foreach (explode(',', $newvals[$field]) as $value) {
            $keyVal = array_merge(explode(':', $value), [ true, true ]);
            $multipleValues[$keyVal[0]][$column] = $keyVal[1];
          }
          foreach ($identifier[$multiple]['new'] as $new) {
            if (!isset($multipleValues[$new][$column])) {
              $multipleValues[$new][$column] =
                isset($pme->fdd[$field]['default']) ? $pme->fdd[$field]['default'] : null;
            }
          }
        }

        $this->$logMethod('VAL '.print_r($multipleValues, true));

        // Add new entities
        foreach ($identifier[$multiple]['new'] as $new) {
          $this->$logMethod('TRY MOD '.$new);
          if (isset($addIdentifier[$new])) {
            $id = $addIdentifier[$new];
            $entityId = $this->extractKeyValues($meta, $id);
            $entity = $entityClass::create();
            foreach ($entityId as $key => $value) {
              $entity[$key] = $value;
            }
          } else if (isset($remIdentifier[$new]) && !empty($changeSet)) {
            $id = $remIdentifier[$new];
            $entityId = $this->extractKeyValues($meta, $id);
            $entity = $this->find($entityId);
            if (empty($entity)) {
              throw new \Exception($this->l->t('Unable to find entity in table %s given id %s',
                                               [ $table, print_r($entityId, true) ]));
            }
            if (method_exists($entity, 'setDisabled')) {
              $entity['disabled'] = false; // reenable
            }
            $useMerge = true;
          } else {
            continue;
          }

          // set further properties ...
          foreach ($multipleValues[$new] as $column => $value) {
            $entity[$column] = $value;
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
        $entityId = $this->extractKeyValues($meta, $identifier);
        $entity = $this->find($entityId);
        if (empty($entity)) {
          $entity = $entityClass::create();
          foreach ($entityId as $key => $value) {
            $entity[$key] = $value;
          }
        }
        foreach ($changeSet as $column => $field) {
          $entity[$column] = $newvals[$field];
          Util::unsetValue($changed, $field);
        }
        $this->persist($entity);
      }
    }
    $this->flush(); // flush everything to the data-base

    // debug
    foreach ($changed as $field) {
      $fieldInfo = $this->joinTableField($field);
      throw new \Exception($this->l->t('Change-set %s should be empty.', print_r($changed, true)));
    }
    $this->$logMethod('BEFORE UPD: '.print_r($changed, true));

    // all should be done
    $pme->setLogging(false);

    return true; //!empty($changed);
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
   */
  public function beforeInsertDoInsertAll(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
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
    $this->logDebug('MISSING '.print_r($missingKeys, true));
    foreach ($this->joinStructure as $joinInfo) {
      if ($joinInfo['master']) {
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

    $this->logDebug('NEWVALS '.print_r($newvals, true));
    $changeSets = [];
    foreach ($changed as $field) {
      $fieldInfo = $this->joinTableField($field);
      $changeSets[$fieldInfo['table']][$fieldInfo['column']] = $field;
    }
    $this->logDebug('CHANGESETS: '.print_r($changeSets, true));

    foreach ($this->joinStructure as $joinInfo) {
      $table = $joinInfo['table'];
      $changeSet = $changeSets[$table];
      if (!empty($joinInfo['read_only'])) {
        foreach ($changeSet as $column => $joinColumn) {
          Util::unsetValue($changed, $joinColumn);
        }
        continue;
      }
      if (empty($changeSets[$table])) {
        continue;
      }
      if ($joinInfo['master']) {
        foreach ($this->pme->key as $key => $type) {
          $joinInfo['identifier'][$key] = $key;
        }
        // leave this to phpMyEdit, otherwise key-updates would need
        // further care.
        // continue;
      }
      $this->logDebug('CHANGESET '.$table.' '.print_r($changeSet, true));
      $entityClass = $joinInfo['entity'];
      $repository = $this->getDatabaseRepository($entityClass);
      $meta = $this->classMetadata($entityClass);
      //$this->logDebug('ASSOCIATIONMAPPINGS '.print_r($meta->associationMappings, true));

      $identifier = [];
      $identifierColumns = $meta->getIdentifierColumnNames();
      foreach ($identifierColumns as $key) {
        if (empty($joinInfo['identifier'][$key])) {
          // assume that the 'column' component contains the keys.
          $keyField = $this->joinTableFieldName($joinInfo, $joinInfo['column']);
          $identifier[$key] = Util::explode(',', $newvals[$keyField]);

          Util::unsetValue($changed, $changeSet[$joinInfo['column']]);
          unset($changeSet[$joinInfo['column']]);
          $multiple = $key;
        } else if (is_array($joinInfo['identifier'][$key])) {
          if (!empty($joinInfo['identifier'][$key]['value'])) {
            $identifier[$key] = $joinInfo['identifier'][$key]['value'];
          } else {
            throw new \Exception($this->l->t('Nested multi-value join tables are not yet supported.'));
          }
        } else {
          $idKey = $joinInfo['identifier'][$key];
          $identifier[$key] = $newvals[$idKey];
          unset($changeSet[$idKey]);
          Util::unsetValue($changed, $idKey);
        }
      }
      if (!empty($multiple)) {
        foreach ($identifier[$multiple] as $addKey) {
          $addIdentifier[$addKey] = $identifier;
          $addIdentifier[$addKey][$multiple] = $addKey;
        }

        $this->logDebug('IDS '.print_r($identifier, true));
        $this->logDebug('CHG '.print_r($changeSet, true));

        foreach ($changeSet as $column => $field) {
          // convention for multiple change-sets:
          //
          // - the values start with the key
          // - boolean false values are omitted
          // - optional values are omitted
          // - values are separated by a colon from the key
          foreach (explode(',', $newvals[$field]) as $value) {
            $keyVal = array_merge(explode(':', $value), [ true, true ]);
            $multipleValues[$keyVal[0]][$column] = $keyVal[1];
          }
          foreach ($identifier[$multiple] as $new) {
            if (!isset($multipleValues[$new][$column])) {
              $multipleValues[$new][$column] =
                isset($pme->fdd[$field]['default']) ? $pme->fdd[$field]['default'] : null;
            }
          }
        }

        $this->logDebug('VAL '.print_r($multipleValues, true));

        // Add new entities
        foreach ($identifier[$multiple] as $new) {
          $this->logDebug('TRY MOD '.$new);
          $id = $addIdentifier[$new];
          $entityId = $this->extractKeyValues($meta, $id);
          $entity = $entityClass::create();
          foreach ($entityId as $key => $value) {
            $entity[$key] = $value;
          }

          // set further properties ...
          foreach ($multipleValues[$new] as $column => $value) {
            $entity[$column] = $value;
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
        $entityId = $this->extractKeyValues($meta, $identifier);
        $entity = $entityClass::create();
        foreach ($entityId as $key => $value) {
          $entity[$key] = $value;
        }
        foreach ($changeSet as $column => $field) {
          $entity[$column] = $newvals[$field];
          Util::unsetValue($changed, $field);
        }
        $this->persist($entity);

        // if this is the master table, then we need also to fetch the
        // id and to insert the id(s) into the change-sets for the
        // joined entities which are yet to be inserted.
        if ($joinInfo['master']) {
          $this->flush($entity);
          $identifier = $this->getIdentifierColumnValues($entity, $meta);
          foreach (array_keys($this->pme->key) as $key) {
            $newvals[$key] = $identifier[$key];
          }
        }
      }
    }
    $this->flush(); // flush everything to the data-base

    $this->logDebug('BEFORE INS: '.print_r($changed, true));
    if (!empty($changed)) {
      throw new \Exception(
        $this->l->t('Remaining change-set %s must be empty', print_r($changed, true)));
    }

    // all should be done
    $pme->setLogging(false);

    return true; //!empty($changed);
  }

  /**
   * Define a basic join-structure for phpMyEdit by using the
   * information from self::$joinStructure.
   *
   * @param array $opts phpMyEdit options.
   *
   * @return array ```[ TABLE_NAME => phpMyEdit_alias ]```
   */
  protected function defineJoinStructure(array &$opts)
  {
    if (!empty($opts['groupby_fields']) && !is_array($opts['groupby_fields'])) {
      $opts['groupby_fields'] = [ $opts['groupby_fields'], ];
    }
    $grouped = [];
    $orderBy = [];
    foreach ($this->joinStructure as &$joinInfo) {
      if (!empty($joinInfo['master'])) {
        if (is_array($opts['key'])) {
          foreach (array_keys($opts['key']) as $key) {
            $joinInfo['identifier'][$key] = $key;
          }
        } else {
          $joinInfo['identifier'][$opts['key']] = $opts['key'];
        }
        $joinTables[$joinInfo['table']] = 'PMEtable0';
        continue;
      }
      $table = $joinInfo['table'];
      $valuesTable = explode(self::VALUES_TABLE_SEP, $table)[0];

      $joinIndex[$table] = count($opts['fdd']);
      $joinTables[$table] = 'PMEjoin'.$joinIndex[$table];
      $fqnColumn = $joinTables[$table].'.'.$joinInfo['column'];

      $group = false;
      $groupOrderBy = [];
      $joinData = [];
      foreach ($joinInfo['identifier'] as $joinTableKey => $joinTableValue) {
        if (empty($joinTableValue)) {
          $group = true;
          $groupOrderBy[] = $joinTables[$table].'.'.$joinInfo['column'].' ASC';
          continue;
        }
        $joinCondition = '$join_table.'.$joinTableKey.' ';
        if (is_array($joinTableValue)) {
          if (!empty($joinTableValue['table'])) {
            $mainTableColumn = $joinTableValue['column']?: 'id';
            $joinCondition .= '= '.$joinTables[$joinTableValue['table']].'.'.$mainTableColumn;
            $group = $grouped[$joinTableValue['table']];
            $groupOrderBy = array_merge($groupOrderBy, $orderBy[$joinTableValue['table']]);
          } else if (array_key_exists('value', $joinTableValue)
                     && $joinTableValue['value'] === null) {
            $joinCondition = '$join_table.'.$joinTableKey.' IS NULL';
          } else if (!empty($joinTableValue['value'])) {
            $joinCondition .= '= '.$joinTableValue['value'];
          } else if (!empty($joinTableValue['condition'])) {
            $joinCondition .= $joinTableValue['condition'];
          }
        } else {
          $joinCondition .= '= $main_table.'.$joinTableValue;
        }
        $joinData[] = $joinCondition;
      }
      $grouped[$table] = $group;
      $orderBy[$table] = $groupOrderBy;
      $fieldName = $this->joinTableMasterFieldName($table);
      $opts['fdd'][$fieldName] = [
        'tab' => 'all',
        'name' => $fieldName,
        'input' => 'HV',
        'sql' => ($group
                  ? 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY '.implode(', ', $groupOrderBy).')'
                  : '$join_col_fqn'),
        'options' => '',
        'sort' => true,
        'values' => [
          'table' => $valuesTable,
          'column' => $joinInfo['column'],
          'join' => implode(' AND ', $joinData),
        ],
      ];
      if (!empty($joinInfo['group_by'])) {
        $opts['groupby_fields'][] = $fieldName;

        // use simple field grouping for list and filter operation
        $opts['fdd'][$fieldName]['sql|FL'] = '$join_col_fqn';
      }
      $this->logDebug('JOIN '.print_r($opts['fdd'][$fieldName], true));
    }
    if (!empty($opts['groupby_fields'])) {
      $keys = is_array($opts['key']) ? array_keys($opts['key']) : [ $opts['key'] ];
      $opts['groupby_fields'] = array_unique(array_merge($keys, $opts['groupby_fields']));
      $this->logDebug('GROUP_BY '.print_r($opts['groupby_fields'], true));
    }
    return $joinTables;
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
   * @param string|array $table Table-description-data
   * ```
   * [
   *   'table' => SQL_TABLE_NAME,
   *   'entity' => DOCTRINE_ORM_ENTITY_CLASS_NAME,
   *   'master' => bool optional master-table
   *   'read_only' => bool optional not considered for update
   *   'column' => column used in select statements
   *   'identifier' => [
   *     TO_JOIN_TABLE_COLUMN => ALREADY_THERE_COLUMN_NAME,
   *     ...
   *   ]
   * ]
   * ```
   *
   * @param string $column column to generate a query field for. This
   * is another column different from $tableInfo['column'] in order to
   * generate further query fields from an existing join-table.
   *
   * @return string Cooked field-name composed of $tableInfo and $column.
   *
   */
  protected function joinTableFieldName($tableInfo, string $column)
  {
    if (is_array($tableInfo)) {
      if (!empty($tableInfo['master'])) {
        return $column;
      }
      $table = $tableInfo['table'];
    } else {
      $table = $tableInfo;
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
    $this->pme->sql_free_result($this->pme->myquery($query));
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
    $this->generateNumbers($this->minimumNumbersValue);
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
    $slug = $this->cssClass().'-'.$slug;
    if (!isset($fdd['css']['postfix'])) {
      $fdd['css'] = [ 'postfix' => '' ];
    }
    $fdd['css']['postfix'] .= ' '.$slug;
    $fdd['tooltip'] = $this->toolTipsService[$slug];
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

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
