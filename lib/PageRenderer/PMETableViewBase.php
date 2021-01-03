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
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\ChangeLogService;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types as DBTypes;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

/** Base for phpMyEdit based table-views. */
abstract class PMETableViewBase extends Renderer implements IPageRenderer
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const JOIN_FIELD_NAME_SEPARATOR = ':';

  protected $requestParameters;

  protected $toolTipsService;

  protected $changeLogService;

  protected $l;

  protected $pme;

  protected $pmeBare;

  protected $pmeRecordId;

  protected $showDisabled;

  protected $expertMode;

  protected $pmeOptions;

  protected $musicianId;

  protected $projectId;

  protected $projectName;

  protected $template;

  protected $recordsPerPage;

  protected $defaultFDD;

  protected $pageNavigation;

  protected $joinStructure = [];

  protected function __construct(
    ConfigService $configService
    , RequestParameterService $requestParameters
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
    , ChangeLogService $changeLogService
    , ToolTipsService $toolTipsService
    , PageNavigation $pageNavigation
  ) {
    $this->configService = $configService;
    $this->requestParameters = $requestParameters;
    $this->entityManager = $entityManager;
    $this->pme = $phpMyEdit;
    $this->changeLogService = $changeLogService;
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

    $this->pmeOptions['tooltips'] = $this->toolTipsService;

    if ($this->getConfigValue('debugmode', 0) & ConfigService::DEBUG_QUERY) {
      $this->pmeOptions['debug'] = true;
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

  public function getProjectName() { return $this->projectName; }

  public function getProjectId() { return $this->projectId; }

  /** Short title for heading. */
  // public function shortTitle();

  /** Header text informations. */
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
    $newvals = array_intersect_key($newvals, array_fill($changed, 1));
    return count($newvals) > 0;
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
  public static function beforeAnythingTrimAnything($pme, $op, $step, &$oldvals, &$changed, &$newvals)
  {
    foreach ($newvals as $key => &$value) {
      if (!is_scalar($value)) { // don't trim arrays
        continue;
      }
      // Convert unicode space to ordinary space
      $value = str_replace("\xc2\xa0", "\x20", $value);
      $value = preg_replace('/\s+/', ' ', $value);

      // Then trim away ...
      $value = trim($value);
      $chgIdx = array_search($key, $changed);
      if ($chgIdx === false && $oldvals[$key] != $value) {
        $changed[] = $key;
      }
      if ($op == 'insert' && empty($value) && $chgIdx !== false) {
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
    $this->logInfo('OLDVALS '.print_r($oldvals, true));
    $this->logInfo('NEWVALS '.print_r($newvals, true));
    $this->logInfo('CHANGED '.print_r($changed, true));
    $changeSets = [];
    foreach ($changed as $field) {
      $fieldInfo = $this->joinTableField($field);
      $changeSets[$fieldInfo['table']][$fieldInfo['column']] = $field;
    }
    foreach ($this->joinStructure as $joinInfo) {
      if (!empty($joinInfo['read_only'])) {
        continue;
      }
      $table = $joinInfo['table'];
      if (empty($changeSets[$table])) {
        continue;
      }
      $changeSet = $changeSets[$table];
      //$this->logInfo('CHANGESETS '.$table.' '.print_r($changeSet, true));
      $entityClass = $joinInfo['entity'];
      $repository = $this->getDatabaseRepository($entityClass);
      $meta = $this->classMetadata($entityClass);
      //$this->logInfo('ASSOCIATIONMAPPINGS '.print_r($meta->associationMappings, true));

      $identifier = [];
      $identifierColumns = $meta->getIdentifierColumnNames();
      foreach ($identifierColumns as $key) {
        if (empty($joinInfo['identifier'][$key])) {
          // assume that the 'column' component contains the keys.
          $keyField = $this->joinTableFieldName($joinInfo, $joinInfo['column']);
          $identifier[$key] = [
            'old' => explode(',', $oldvals[$keyField]),
            'new' => explode(',', $newvals[$keyField]),
          ];

          $identifier[$key]['del'] = array_diff($identifier[$key]['old'], $identifier[$key]['new']);
          $identifier[$key]['add'] = array_diff($identifier[$key]['new'], $identifier[$key]['old']);
          $identifier[$key]['rem'] = array_intersect($identifier[$key]['new'], $identifier[$key]['old']);

          Util::unsetValue($changed, $changeSet[$joinInfo['column']]);
          unset($changeSet[$joinInfo['column']]);
          $multiple = $key;
        } else {
          $identifier[$key] = $oldvals[$joinInfo['identifier'][$key]];
        }
      }
      $this->logInfo('Keys Values: '.print_r($identifier, true));
      if (!empty($multiple)) {
        foreach ($identifier[$multiple]['old'] as $oldKey) {
          $oldIdentifier[$oldKey] = $identifier;
          $oldIdentifier[$oldKey][$multiple] = $oldKey;
        }
        foreach ($identifier[$multiple]['new'] as $newKey) {
          $newIdentifier[$newKey] = $identifier;
          $newIdentifier[$newKey][$multiple] = $newKey;
        }

        // Delete removed entities
        foreach ($identifier[$multiple]['del'] as $del) {
          $id = $oldIdentifier[$del];
          $entityId = $this->makeJoinTableId($meta, $id);
          $entity = $this->find($entityId);
          foreach ($this->entityManager->wrapped->unitOfWork->identityMap as $blah => $blub) {
            $this->logInfo(print_r(array_keys($blub), true));
          }
          $this->remove($entityId);
          $this->changeLogService->logDelete($table, $id, $id);
        }

        // Add new entities
        foreach ($identifier[$multiple]['new'] as $new) {
          $id = $newIdentifier[$new];
          $entityId = $this->makeJoinTableId($meta, $id);
          if (isset($identifier[$multiple]['add'][$new])) {
            $entity = $entityClass::create();
            foreach ($entityId as $key => $value) {
              $entity[$key] = $value;
            }
            $this->changeLogService->logInsert($table, $id, $id);
          } else if (!empty($changeSet)) {
            $entity = $this->find($entityId);
            // @TODO real update
            $this->changeLogService->logUpdate($table, $id, $id, $id);
          } else {
            continue;
          }

          // set further properties ...
          if (!empty($changeSet)) {
            throw new \Exception($this->l->t('Unimplemented'));
            foreach ($changeSet as $column => $field) {
              Util::unsetValue($changed, $field);
            }
          }

          // persist
          $this->persist($entity);
        }
      } else { // !multiple, simply update
        if (false) {
          // probably  easier
          $entityId = $this->makeJoinTableId($meta, $identifier);
          $entity = $this->find($entityId);
          $logOld = [];
          $logNew = [];
          foreach ($changeSet as $column => $field) {
            $entity[$column] = $newvals[$field];
            Util::unsetValue($changed, $field);
            $logOld[$column] = $oldvals[$field];
            $logNew[$column] = $newvals[$field];
          }
          $this->changeLogService->logUpdate($table, $entityId, $logOld, $logNew);
        } else {
          // probably faster, but life-cycle callbacks and events are
          // not handled.

          // hack 'updated' column, ugly, but should work
          if (isset($meta->fieldNames['updated'])) {
            $changeSet['updated'] = $this->joinTableFieldName($joinInfo, 'updated');
            $newvals[$changeSet['updated']] = new \DateTime();
          }
          $qb = $repository->createQueryBuilder('e')
                           ->update();
          $logOld = [];
          $logNew = [];
          foreach ($changeSet as $column => $field) {
            $qb->set('e.'.$this->property($column), ':'.$column)
               ->setParameter($column, $newvals[$field]);
            $this->logInfo("Unset $field in changed");
            Util::unsetValue($changed, $field);
            $logOld[$column] = $oldvals[$field];
            $logNew[$column] = $newvals[$field];
          }
          foreach ($identifier as $column => $value) {
            $qb->andWhere('e.'.$this->property($column).' = :'.$key)
               ->setParameter($key, $value);
          }
          $qb->getQuery()
             ->execute();
          $this->changeLogService->logUpdate($table, $identifier, $logOld, $logNew);
        }
      }
    }
    $this->flush(); // flush everything to the data-base
    if (!empty($changed)) {
      throw new \Exception('Change-set '.print_r($changed, true).' should be empty.');
    }
    return false; // nothing left to do
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
    foreach ($this->joinStructure as $joinInfo) {
      if (!empty($joinInfo['master'])) {
        continue;
      }
      $table = $joinInfo['table'];

      $joinIndex[$table] = count($opts['fdd']);
      $joinTable[$table] = 'PMEjoin'.$joinIndex[$table];
      $fqnColumn = $joinTable[$table].'.'.$joinInfo['column'];

      $group = false;
      $joinData = [];
      foreach ($joinInfo['identifier'] as $joinTableKey => $mainTableKey) {
        if (empty($mainTableKey)) {
          $group = true;
          continue;
        }
        $joinData[] = '$main_table.'.$mainTableKey.' = $join_table.'.$joinTableKey;
      }
      $fieldName = $this->joinTableMasterFieldName($table);
      $opts['fdd'][$fieldName] = [
        'tab' => 'all',
        'name' => $fieldName,
        'input' => 'HV',
        'sql' => ($group
                  ? 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $join_col_fqn ASC)'
                  : '$join_col_fqn'),
        'options' => '',
        'sort' => true,
        'values' => [
          'table' => $table,
          'column' => $joinInfo['column'],
          'join' => implode(' AND ', $joinData),
        ],
      ];
      if (!empty($joinInfo['group_by'])) {
        $opts['groupby_fields'][] = $fieldName;
        // use simple field grouping for list operation
        $sql = $opts['fdd'][$fieldName]['sql'];
        $opts['fdd'][$fieldName]['sql|L'] = '$join_col_fqn';
      }
      $this->logDebug('JOIN '.print_r($opts['fdd'][$fieldName], true));
    }
    if (!empty($opts['groupby_fields'])) {
      $opts['groupby_fields'] = array_merge(array_keys($opts['key']), $opts['groupby_fields']);
      $this->logDebug('GROUP_BY '.print_r($opts['groupby_fields'], true));
    }
    return $joinTable;
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
      $parts[0] = self::TABLE;
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
  protected function fieldIndex(array $fieldDescriptionData, array $tableInfo, string $column)
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
    $masterFieldName = $this->joinTableMasterFieldName($tableInfo);
    $joinIndex = array_search($masterFieldName, array_keys($fieldDescriptionData));
    if ($joinIndex === false) {
      $table = is_array($tableInfo) ? $tableInfo['table'] : $tableInfo;
      throw new \Exception($this->l->t("Master join-table field for %s not found.", $table));
    }
    $fieldName = $this->joinTableFieldName($tableInfo, $column);
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
    $fieldDescriptionData[$fieldName] = Util::arrayMergeRecursive($defaultFDD, $fdd);
  }

  /**
   * Generate ids for use with Doctrine/ORM from home-brewn table values.
   *
   * @param \Doctrine\ORM\Mapping\ClassMetadataInfo $meta Class-meta.
   *
   * @param array $idValues
   *
   * @return array
   */
  protected function makeJoinTableId($meta, $idValues)
  {
    $entityId = [];
    foreach ($meta->identifier as $metaId) {
      if (isset($idValues[$metaId])) {
        $columnName = $metaId;
      } else if (isset($meta->associationMappings[$metaId])) {
        if (count($meta->associationMappings[$metaId]['joinColumns']) != 1) {
          throw new \Exception($this->l->t('Foreign keys as principle keys cannot be composite'));
        }
        $columnName = $meta->associationMappings[$metaId]['joinColumns'][0]['name'];
      } else {
        throw new \Exception($this->l->t('Unexpected id: %s', $metaId));
      }
      $entityId[$metaId] = $idValues[$columnName];
    }
    return $entityId;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
