<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\PageRenderer\FieldTraits;

use OCP\Files as CloudFiles;

use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldType;
use OCA\CAFEVDB\Service\Finance\IRecurringReceivablesGenerator;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Common\Functions;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

use OCA\CAFEVDB\Controller\DownloadsController;
use OCA\CAFEVDB\Storage\DatabaseStorageUtil;

/** Participant-fields. */
trait ParticipantFieldsTrait
{
  /** @var UsreStorage */
  protected $userStorage = null;

  /**
   * For each extra field add one dedicated join table entry
   * which is pinned to the respective field-id.
   *
   * @todo Joining many tables with multiple rows per join key is a
   * performance hit. Maybe all those joins should be replaced by
   * only a single one by using IF-clauses inside the GROUP_CONCAT().
   *
   * @return array
   * ```
   * [ JOIN_STRUCTURE_FRAGMENT, GENERATOR(&$fdd) ]
   * ```
   */
  public function renderParticipantFields($participantFields, $projectIdField = 'project_id', $financeTab = 'finance')
  {
    $joinStructure = [];

    /** @var Entities\ProjectParticipantField $field */
    foreach ($participantFields as $field) {
      $fieldId = $field['id'];

      $tableName = self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE.self::VALUES_TABLE_SEP.$fieldId;
      $joinStructure[$tableName] = [
        'entity' => Entities\ProjectParticipantFieldDatum::class,
        'flags' => self::JOIN_REMOVE_EMPTY,
        'identifier' => [
          'project_id' => $projectIdField,
          'musician_id' => 'musician_id',
          'field_id' => [ 'value' => $fieldId, ],
          'option_key' => false,
        ],
        'reference' => self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE,
        'column' => 'option_key',
        'encode' => 'BIN2UUID(%s)',
      ];
    }

    // @todo needs also the join-structure of the "target". Perhaps
    // move to a common base-class or trait.
    $generator = function(&$fieldDescData) use ($participantFields, $financeTab) {

      /** @var Entities\ProjectParticipantField $field */
      foreach ($participantFields as $field) {
        $fieldName = $field->getName();
        $fieldId   = $field->getId();
        $multiplicity = $field->getMultiplicity();
        $dataType = (string)$field->getDataType();
        $deleted = !empty($field->getDeleted());

        if (!$this->participantFieldsService->isSupportedType($multiplicity, $dataType)) {
          throw new \Exception(
            $this->l->t('Unsupported multiplicity / data-type combination: %s / %s',
                        [ $multiplicity, $dataType ]));
        }

        // set tab unless overridden by field definition
        if ($field['data_type'] == FieldType::SERVICE_FEE) {
          $tab = [ 'id' => $financeTab ];
        } else {
          $tab = [ 'id' => 'project' ];
        }
        if (!empty($field['tab'])) {
          $tabId = $this->tableTabId($field['tab']);
          $tab = [ 'id' => $tabId ];
        }

        $tableName = self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE.self::VALUES_TABLE_SEP.$fieldId;

        $deletedSqlFilter = $this->showDisabled ? '' : ' AND $join_table.deleted IS NULL';
        $deletedValueFilter = $this->showDisabled ? '' : ' AND $table.deleted IS NULL';

        $css = [ 'participant-field', 'field-id-'.$fieldId, ];
        $extraFddBase = [
          'name' => $this->l->t($fieldName),
          'tab' => $tab,
          'css'      => [ 'postfix' => $css, ],
          'default|A'  => $field['default_value'],
          'filter' => [
            'having' => true,
          ],
          'sql' => 'TRIM(BOTH \',\' FROM GROUP_CONCAT(DISTINCT
  IF($join_table.field_id = '.$fieldId.$deletedSqlFilter.', $join_col_enc, NULL)
  ORDER BY $order_by))',
          'values' => [
            'grouped' => true,
            'filters' => ('$table.field_id = '.$fieldId
                          .' AND $table.project_id = '.$this->projectId
                          .' AND $table.musician_id = $record_id[musician_id]'
                          .$deletedValueFilter),
            'orderby' => '$table.option_key ASC',
          ],
          'tooltip' => $field['tooltip']?:null,
        ];
        if ($deleted && !$this->showDisabled) {
          $extraFddBase['input'] = 'VSRH';
        }

        list($keyFddIndex, $keyFddName) = $this->makeJoinTableField(
          $fieldDescData, $tableName, 'option_key',
          Util::arrayMergeRecursive($extraFddBase, [ 'values' => ['encode' => 'BIN2UUID(%s)',], ])
        );
        $keyFdd = &$fieldDescData[$keyFddName];

        list($valueFddIndex, $valueFddName) = $this->makeJoinTableField(
          $fieldDescData, $tableName, 'option_value',
          Util::arrayMergeRecursive($extraFddBase, [ 'input' => 'VSRH', ])
        );
        $valueFdd = &$fieldDescData[$valueFddName];

        // @todo this would need more care.
        list($deletedFddIndex, $deletedFddName) = $this->makeJoinTableField(
          $fieldDescData, $tableName, 'deleted',
          Util::arrayMergeRecursive($extraFddBase, [
            'name' => $this->l->t('Deleted'),
            'input' => 'SRH', // ($this->showDisabled ? 'SR' : 'SRH'),
            'sql' => 'TRIM(BOTH \',\' FROM GROUP_CONCAT(
  DISTINCT
  IF(
    NOT $join_table.field_id = '.$fieldId.' OR $join_col_fqn IS NULL,
    NULL,
    CONCAT_WS(
      \''.self::JOIN_KEY_SEP.'\',
      BIN2UUID($join_table.option_key),
      $join_col_fqn
    )
  )
  ORDER BY $order_by))',
          ])
        );
        $deletedFdd = &$fieldDescData[$deletedFddName];

        /** @var Doctrine\Common\Collections\Collection */
        $dataOptions = $field->getSelectableOptions($this->showDisabled);
        $values2     = [];
        $valueTitles = [];
        $valueData   = [];
        /** @var Entities\ProjectParticipantFieldDataOption $dataOption */
        foreach ($dataOptions as $dataOption) {
          $key = (string)$dataOption['key'];
          $values2[$key] = $dataOption['label'];
          $valueTitles[$key] = $dataOption['tooltip'];
          $valueData[$key] = $dataOption['data'];
        }

        foreach ([ &$keyFdd, &$valueFdd ] as &$fdd) {
          switch ($dataType) {
          case FieldType::TEXT:
            // default config
            break;
          case FieldType::HTML:
            $fdd['textarea'] = [
              'css' => 'wysiwyg-editor',
              'rows' => 5,
              'cols' => 50,
            ];
            $fdd['css']['postfix'][] = 'hide-subsequent-lines';
            $fdd['display|LF'] = [ 'popup' => 'data' ];
            $fdd['escape'] = false;
            break;
          case FieldType::BOOLEAN:
            // handled below
            $fdd['align'] = 'right';
            break;
          case FieldType::INTEGER:
            $fdd['select'] = 'N';
            $fdd['mask'] = '%d';
            $fdd['align'] = 'right';
            break;
          case FieldType::FLOAT:
            $fdd['select'] = 'N';
            $fdd['mask'] = '%g';
            $fdd['align'] = 'right';
            break;
          case FieldType::DATE:
          case FieldType::DATETIME:
          case FieldType::SERVICE_FEE:
            $style = $this->defaultFDD[$dataType];
            if (empty($style)) {
              throw new \Exception($this->l->t('Not default style for "%s" available.', $dataType));
            }
            unset($style['name']);
            $fdd = array_merge($fdd, $style);
            $fdd['css']['postfix'] = array_merge($fdd['css']['postfix'], $css);
            break;
          }
        }

        switch ($multiplicity) {
        case FieldMultiplicity::SIMPLE:
          /**********************************************************************
           *
           * Simple input field.
           *
           */
          $valueFdd['input'] = $keyFdd['input'];
          $keyFdd['input'] = 'SRH';
          $valueFdd['css']['postfix'][] = 'simple-valued';
          $valueFdd['css']['postfix'][] = $dataType;

          $valueFdd['sql'] = 'GROUP_CONCAT(DISTINCT IF($join_table.field_id = '.$fieldId.$deletedSqlFilter.', $join_col_fqn, NULL))';

          $defaultValue = $field->getDefaultValue();
          $defaultButton = '<input type="button"
       value="'.$this->l->t('Revert to default').'"
       class="display-postfix revert-to-default [BUTTON_STYLE]"
       title="'.$this->toolTipsService['participant-fields:revert-to-default'].'"
       data-field-id="'.$fieldId.'"
       data-field-property="[FIELD_PROPERTY]"
/>';

          switch ($dataType) {
          case FieldType::SERVICE_FEE:
            unset($valueFdd['mask']);
            $valueFdd['php|VDLF'] = function($value) {
              return $this->moneyValue($value);
            };
            $valueFdd['display|CAP']['postfix'] = '<span class="currency-symbol">'.$this->currencySymbol().'</span>';
            if (!empty($defaultValue)) {
              $valueFdd['display|CAP']['postfix'] .=
                str_replace([ '[BUTTON_STYLE]', '[FIELD_PROPERTY]' ] , [ 'hidden-text', 'defaultValue' ], $defaultButton);
            }

            // We need one additional input field for the
            // service-fee-deposit. This is only needed for
            // FieldMultiplicity::SIMPLE and
            // FieldType::SERVICE_FEE and IFF the deposit-due-date field in the option is non-zero.
            //
            // In all other cases the deposit is either not needed or fixed by the field options.
            $depositDueDate = $field->getDepositDueDate();
            if (empty($depositDueDate)) {
              break;
            }

            $depositFddName = self::joinTableFieldName(self::participantFieldTableName($fieldId), 'deposit');
            $fieldDescData[$depositFddName] = Util::arrayMergeRecursive($valueFdd, [
              'name' => $this->l->t('Deposit').' '.$this->l->t($fieldName),
              'values' => [
                'column' => 'deposit',
              ],
            ]);
            $depositFdd = &$fieldDescData[$depositFddName];

            $depositFdd['display|CAP']['postfix'] = '<span class="currency-symbol">'.$this->currencySymbol().'</span>';
            if (!empty($defaultValue)) {
              $depositFdd['display|CAP']['postfix'] .=
                str_replace([ '[BUTTON_STYLE]', '[FIELD_PROPERTY]' ] , [ 'hidden-text', 'defaultDeposit' ], $defaultButton);
            }

            break;

          case FieldType::DB_FILE:
            $this->joinStructure[$tableName]['flags'] |= self::JOIN_READONLY;
            $valueFdd['php|CAP'] = function($value, $op, $k, $row, $recordId, $pme) use ($field, $dataOptions) {
              $fieldId = $field->getId();
              $policy = $field->getDefaultValue()?:'rename';
              $key = $dataOptions->first()->getKey();
              $fileBase = $field['name'];
              $subDir = null;
              list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);
              return '<div class="file-upload-wrapper" data-option-key="'.$key.'">
  <table class="file-upload">'
              .$this->dbFileUploadRowHtml($value, $fieldId, $key, $fileBase, $musician).'
  </table>
</div>';
            };
            $valueFdd['php|LFDV'] = function($value, $op, $k, $row, $recordId, $pme) use ($field) {
              if (!empty($value)) {
                $downloadLink = $this->urlGenerator()
                                     ->linkToRoute($this->appName().'.downloads.get', [
                                       'section' => DownloadsController::SECTION_DATABASE,
                                       'object' => $value,
                                     ])
                              . '?requesttoken=' . urlencode(\OCP\Util::callRegister());
                list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);
                $fileBase = $field->getName();
                $fileName = $this->projectService->participantFilename($fileBase, $this->project, $musician);
                return '<a class="download-link tooltip-auto" title="'.$this->toolTipsService['participant-attachment-download'].'" href="'.$downloadLink.'">'.$fileName.'</a>';
              }
              return null;
            };
            break;
          case FieldType::CLOUD_FILE:
            $this->joinStructure[$tableName]['flags'] |= self::JOIN_READONLY;
            $valueFdd['php|CAP'] = function($value, $op, $k, $row, $recordId, $pme) use ($field, $dataOptions) {
              $fieldId = $field->getId();
              $optionKey = $dataOptions->first()->getKey();
              $policy = $dataOptions->first()->getData()?:'rename';
              $fileBase = $field['name'];
              $subDir = null;
              list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);
              return '<div class="file-upload-wrapper" data-option-key="'.$optionKey.'">
  <table class="file-upload">'
              .$this->cloudFileUploadRowHtml($value, $fieldId, $optionKey, $policy, $subDir, $fileBase, $musician).'
  </table>
</div>';
            };
            $valueFdd['php|LFDV'] = function($value, $op, $k, $row, $recordId, $pme) use ($field) {
              if (!empty($value)) {
                list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);
                $participantFolder = $this->projectService->ensureParticipantFolder($this->project, $musician);
                $filePath = $participantFolder.UserStorage::PATH_SEP.$value;
                $fileBase = $field['name'];
                try {
                  $downloadLink = $this->userStorage->getDownloadLink($filePath);
                  return '<a class="download-link tooltip-auto" title="'.$this->toolTipsService['participant-attachment-download'].'" href="'.$downloadLink.'">'.$fileBase.'</a>';
                } catch (\OCP\Files\NotFoundException $e) {
                  $this->logException($e);
                  return '<span class="error tooltip-auto" title="' . $filePath . '">' . $this->l->t('The file "%s" could not be found on the server.', $fileBase) . '</span>';
                }
              }
              return null;
            };
            break;
          case FieldType::CLOUD_FOLDER:
            $this->joinStructure[$tableName]['flags'] |= self::JOIN_SINGLE_VALUED;
            if (empty($this->userStorage)) {
              $this->userStorage = $this->di(UserStorage::class);
            }
            $valueFdd['php|CAP'] = function($value, $op, $k, $row, $recordId, $pme) use ($field, $dataOptions) {
              $fieldId = $field->getId();
              $optionKey = $dataOptions->first()->getKey();
              $policy = $dataOptions->first()->getData()?:'rename';
              $fileBase = '';
              $subDir = $field->getName();
              list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);

              $participantFolder = $this->projectService->ensureParticipantFolder($this->project, $musician);
              $folderPath = $participantFolder . UserStorage::PATH_SEP . $subDir;
              // read the configured directory in

              /** @var OCP\Files\Folder $folder */
              $folder = $this->userStorage->getFolder($folderPath);
              $folderContents = empty($folder) ? [] : $folder->getDirectoryListing();

              $html = '<div class="file-upload-wrapper" data-option-key="'.$optionKey.'">
  <table class="file-upload">';

              /** @var CloudFiles\Node $node */
              foreach ($folderContents as $node) {
                $fileName = $node->getName() . ($node->getType() == CloudFiles\FileInfo::TYPE_FOLDER ? UserStorage::PATH_SEP : '');
                $html .= $this->cloudFileUploadRowHtml($fileName, $fieldId, $optionKey, $policy, $subDir, '', $musician);
              }

              $html .= $this->cloudFileUploadRowHtml(null, $fieldId, $optionKey, $policy, $subDir, '', $musician);

              $html .= '
  </table>
</div>';
              return $html;
            };
            $valueFdd['php|LFDV'] = function($value, $op, $k, $row, $recordId, $pme) use ($field) {
              if (!empty($value)) {
                list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);
                $subDir = $field->getName();
                $participantFolder = $this->projectService->ensureParticipantFolder($this->project, $musician);
                $folderPath = $participantFolder . UserStorage::PATH_SEP . $subDir;
                $folder = $this->userStorage->getFolder($folderPath);
                if (!empty($folder)) {

                  $listing = json_decode($value, true);
                  if (!is_array($listing)) {
                    $listing = [];
                  }
                  $toolTip = $this->toolTipsService['participant-attachment-open-parent'].'<br>'.implode(', ', $listing);
                  $linkText = '&mldr;' . UserStorage::PATH_SEP
                    . implode(
                      UserStorage::PATH_SEP,
                      array_slice(
                        explode(UserStorage::PATH_SEP, $folderPath),
                        -3)
                    );
                  $filesAppLink = $this->userStorage->getFilesAppLink($folderPath, true);
                  $filesAppTarget = md5($filesAppLink);
                  return '<a href="'.$filesAppLink.'"
                             target="'.$filesAppTarget.'"
                             title="'.$toolTip.'"
                             class="open-parent tooltip-auto">
  ' . $linkText . '
</a>';
                }
              }
              return null;
            };
            break;
          default:
            if (!empty($defaultValue)) {
              $valueFdd['display|CAP'] = $valueFdd['display|CAP'] ?? [];
              $valueFdd['display|CAP']['postfix'] =
                ($valueFdd['display|CAP']['postfix'] ?? '')
                . str_replace([ '[BUTTON_STYLE]', '[FIELD_PROPERTY]', ], [ 'image-left-of-text', 'defaultValue', ], $defaultButton);
            }
            break;
          }
          break;
        case FieldMultiplicity::SINGLE:
          /**********************************************************************
           *
           * Single choice field, yes/no
           *
           */
          reset($values2); $key = key($values2);
          $keyFdd['values2|CAP'] = [ $key => '' ]; // empty label for simple checkbox
          $keyFdd['values2|LVDF'] = [
            0 => $this->l->t('false'),
            $key => $this->l->t('true'),
          ];
          $keyFdd['select'] = 'C';
          // make sure we get 0 not null
          $keyFdd['sql|LVDF'] = 'COALESCE(' . $keyFdd['sql'] . ', 0)';
          $keyFdd['sql|CAP'] = $keyFdd['sql'];
          $keyFdd['default'] = $field->getDefaultValue() === null ? false : $key;
          $keyFdd['css']['postfix'][] = 'boolean';
          $keyFdd['css']['postfix'][] = 'single-valued';
          $keyFdd['css']['postfix'][] = $dataType;
          $keyFdd['values|FL'] = array_merge(
            $keyFdd['values'], [
              'filters' => ('$table.field_id = '.$fieldId
                            .' AND $table.project_id = '.$this->projectId),
            ]);
          $dataValue = reset($valueData);
          switch ($dataType) {
          case FieldType::BOOLEAN:
            break;
          case 'money':
          case FieldType::SERVICE_FEE:
            $money = $this->moneyValue($dataValue);
            $noMoney = $this->moneyValue(0);
            // just use the amount to pay as label
            $keyFdd['values2|LVDF'] = [
              '' => '-,--',
              0 => $noMoney, //'-,--',
              $key => $money,
            ];
            $keyFdd['values2|CAP'] = [ $key => $money, ];
            unset($keyFdd['mask']);
            $keyFdd['php|VDLF'] = function($value) {
              return $this->moneyValue($value);
            };
            break;
          default:
            if (!empty($dataValue)) {
              $keyFdd['values2|LVDF'] = [
                '' => '',
                0 => '',
                $key => $dataValue,
              ];
            }
            $keyFdd['values2|CAP'] = [ $key => $dataValue ];
            break;
          } // data-type switch
          break;
        case FieldMultiplicity::PARALLEL:
        case FieldMultiplicity::MULTIPLE:
          /**********************************************************************
           *
           * Multiple or single choices from a set of predefined choices.
           *
           */
          switch ($dataType) {
          case FieldType::CLOUD_FILE:
            $this->joinStructure[$tableName]['flags'] |= self::JOIN_READONLY;
            $keyFdd['php|CAP'] = function($value, $op, $k, $row, $recordId, $pme) use ($field) {
              $optionKeys = Util::explode(self::VALUES_SEP, $row['qf'.($k+0)], Util::TRIM);
              $optionValues = Util::explode(self::VALUES_SEP, $row['qf'.($k+1)], Util::TRIM);
              $values = array_combine($optionKeys, $optionValues);
              $this->debug('VALUES '.print_r($values, true));
              $fieldId = $field->getId();
              $subDir = $field->getName();
              list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);
              /** @var Entities\ProjectParticipantFieldDataOption $option */
              $html = '<div class="file-upload-wrapper" data-option-key="'.$key.'">
  <table class="file-upload">';
              foreach ($field->getSelectableOptions() as $option) {
                $optionKey = (string)$option->getKey();
                $fileBase = $option->getLabel();
                $policy = $option->getData()?:'rename';
                $html .= $this->cloudFileUploadRowHtml($values[$optionKey], $fieldId, $optionKey, $policy, $subDir, $fileBase, $musician);
              }
              $html .= '
  </table>
</div>';
              return $html;
            };
            $keyFdd['php|LFVD'] = function($value, $op, $k, $row, $recordId, $pme) use ($field) {
              if (!empty($value)) {
                $optionKeys = Util::explode(self::VALUES_SEP, $row['qf'.($k+0)], Util::TRIM);
                $optionValues = Util::explode(self::VALUES_SEP, $row['qf'.($k+1)], Util::TRIM);
                $values = array_combine($optionKeys, $optionValues);
                list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);
                $participantFolder = $this->projectService->ensureParticipantFolder($this->project, $musician);
                $subDir = $field->getName();
                $folderPath = $participantFolder.UserStorage::PATH_SEP.$subDir;
                $filesAppLink = $this->userStorage->getFilesAppLink($folderPath, true);
                $filesAppTarget = md5($filesAppLink);
                return '<a href="'.$filesAppLink.'" target="'.$filesAppTarget.'" title="'.$this->toolTipsService['participant-attachment-open-parent'].'" class="open-parent tooltip-auto">'.$value.'</a>';
              }
              return null;
            };
            $keyFdd['values2'] = $values2;
            $keyFdd['valueTitles'] = $valueTitles;
            $keyFdd['valueData'] = $valueData;
            $keyFdd['select'] = 'M';
            $keyFdd['css']['postfix'][] = $dataType;
            break;
          case FieldType::DB_FILE:
            $this->joinStructure[$tableName]['flags'] |= self::JOIN_READONLY;
            $keyFdd['php|CAP'] = function($value, $op, $k, $row, $recordId, $pme) use ($field) {
              $optionKeys = Util::explode(self::VALUES_SEP, $row['qf'.($k+0)], Util::TRIM);
              $optionValues = Util::explode(self::VALUES_SEP, $row['qf'.($k+1)], Util::TRIM);
              $values = array_combine($optionKeys, $optionValues);
              $fieldId = $field->getId();
              $subDir = $field->getName();
              list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);
              /** @var Entities\ProjectParticipantFieldDataOption $option */
              $html = '<div class="file-upload-wrapper" data-option-key="'.$key.'">
  <table class="file-upload">';
              foreach ($field->getSelectableOptions() as $option) {
                $optionKey = (string)$option->getKey();
                $fileBase = $option->getLabel();

                $html .= $this->dbFileUploadRowHtml($values[$optionKey], $fieldId, $optionKey, $fileBase, $musician);
              }
              $html .= '
  </table>
</div>';
              return $html;
            };
            $keyFdd['php|LFVD'] = function($value, $op, $k, $row, $recordId, $pme) use ($field) {
              if (!empty($value)) {
                $optionKeys = Util::explode(self::VALUES_SEP, $row['qf'.($k+0)], Util::TRIM);
                $optionValues = Util::explode(self::VALUES_SEP, $row['qf'.($k+1)], Util::TRIM);
                $values = array_combine($optionKeys, $optionValues);
                list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);
                if (!empty($values)) {
                  list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);
                  $fileBase = $field->getName();
                  $fileName = $this->projectService->participantFilename($fileBase, $this->project, $musician) . '.zip';

                  $downloadLink = $this->di(DatabaseStorageUtil::class)->getDownloadLink(
                    array_values($values), $fileName);

                  return '<a class="download-link tooltip-auto" title="'.$this->toolTipsService['participant-attachment-download'].'" href="'.$downloadLink.'">'.$fileName.'</a>';
                }
              }
              return null;
            };
            $keyFdd['values2'] = $values2;
            $keyFdd['valueTitles'] = $valueTitles;
            $keyFdd['valueData'] = $valueData;
            $keyFdd['select'] = 'M';
            $keyFdd['css']['postfix'][] = $dataType;
            break;
          case FieldType::SERVICE_FEE:
            foreach ($dataOptions as $dataOption) {
              $key = (string)$dataOption['key'];
              $label = $dataOption['label'];
              $data  = $dataOption['data'];
              $values2[$key] = $this->allowedOptionLabel($label, $data, $dataType, 'money');
            }
            unset($keyFdd['mask']);
            $keyFdd['values2glue'] = '<br/>';
            $keyFdd['escape'] = false;
            // fall through
          default:
            $keyFdd['values2'] = $values2;
            $keyFdd['valueTitles'] = $valueTitles;
            $keyFdd['valueData'] = $valueData;
            $keyFdd['display|LF'] = [
              'popup' => 'data',
              'prefix' => '<div class="allowed-option-wrapper">',
              'postfix' => '</div>',
            ];
            if ($multiplicity == FieldMultiplicity::PARALLEL) {
              $keyFdd['css']['postfix'][] = 'set hide-subsequent-lines';
              $keyFdd['select'] = 'M';
            } else {
              $keyFdd['css']['postfix'][] = 'enum allow-empty';
              $keyFdd['select'] = 'D';
            }
            $keyFdd['css']['postfix'][] = $dataType;
            break;
          }
          break;
        case FieldMultiplicity::RECURRING:

          /**********************************************************************
           *
           * Recurring auto-generated fields
           *
           */

          foreach ([&$keyFdd, &$valueFdd] as &$fdd) {
            $fdd['css']['postfix'][] = 'recurring generated';
            $fdd['css']['postfix'][] = $dataType;
            unset($fdd['mask']);
            $fdd['select'] = 'M';
            $fdd['values'] = array_merge(
              $fdd['values'], [
                'column' => 'option_key',
                'description' => [
                  'columns' => [ 'BIN2UUID($table.option_key)', '$table.option_value', ],
                  'divs' => self::JOIN_KEY_SEP,
                  'ifnull' => [ false, "''" ],
                  'cast' => [ false, false ],
                ],
                // ordering by UUID is meaningless but provides a
                // consistent ordering if any two fields should have
                // been created at the same time.
                'orderby' => '$table.created DESC, $table.option_key ASC',
                'encode' => 'BIN2UUID(%s)',
              ]);
          }

          foreach ($dataOptions as $dataOption) {
            $values2[(string)$dataOption['key']] = $dataOption['label'];
          }
          $keyFdd['values2|LFVD'] = $values2;

          $keyFdd['values|FL'] = array_merge(
            $keyFdd['values'], [
              'filters' => ('$table.field_id = '.$fieldId
                            .' AND $table.project_id = '.$this->projectId),
            ]);
          $keyFdd['display|LF'] = [ 'popup' => 'data' ];
          $keyFdd['php|LFVD'] = function($value, $op, $k, $row, $recordId, $pme) use ($field, $dataType) {
            // LF are actually both the same. $value will always just
            // come from the filter's $value2 array. The actual values
            // we need are in the description fields which are passed
            // through the 'qf'.$k field in $row.
            $values = Util::explodeIndexed($row['qf'.$k]);
            $html = [];
            foreach ($values as $key => $value) {
              $option =  $field->getDataOption($key);
              if (empty($option)) {
                continue;
              }
              $label = $option ? $option->getLabel() : '';
              $html[] = $this->allowedOptionLabel($label, $value, $dataType);
            }
            return '<div class="allowed-option-wrapper">'.implode('<br/>', $html).'</div>';
          };

          // For a useful add/change/copy view we should use the value fdd.
          $valueFdd['input|ACP'] = $keyFdd['input'];
          $keyFdd['input|ACP'] = 'SRH';

          // The following KEY:VALUE always result into a "changed"
          // entry in the legacy PME code. This is adjusted later in
          // $this->beforeUpdateSanitizeParticipantFields()

          // @todo Why is the TRIM necessary?
          $valueFdd['sql'] = 'TRIM(BOTH \',\' FROM GROUP_CONCAT(
  DISTINCT
  IF(
    $join_table.field_id = '.$fieldId.$deletedSqlFilter.',
    CONCAT_WS(
      \''.self::JOIN_KEY_SEP.'\',
      BIN2UUID($join_table.option_key),
      $join_table.option_value
    ),
    NULL
  )
  ORDER BY $order_by)
)';

          // yet another field for the supporting documents
          list($invoiceFddIndex, $invoiceFddName) = $this->makeJoinTableField(
            $fieldDescData, $tableName, 'supporting_document_id', [
              'input' => 'SRH',
              'sql' => 'TRIM(BOTH \',\' FROM GROUP_CONCAT(
  DISTINCT
  IF(
    $join_table.field_id = '.$fieldId.$deletedSqlFilter.',
    CONCAT_WS(
      \''.self::JOIN_KEY_SEP.'\',
      BIN2UUID($join_table.option_key),
      $join_col_fqn
    ),
    NULL
  )
  ORDER BY $order_by)
)',
              'values' => [
                'orderby' => '$table.created DESC, $table.option_key ASC',
              ],
            ]);
          $invoiceFdd = &$fieldDescData[$invoiceFddName];

          $valueFdd['php|ACP'] = function($value, $op, $k, $row, $recordId, $pme) use ($field, $dataType, $keyFddName, $valueFddName) {
            // $this->logInfo('VALUE '.$k.': '.$value);
            // $this->logInfo('ROW '.$k.': '.$row['qf'.$k]);
            // $this->logInfo('ROW IDX '.$k.': '.$row['qf'.$k.'_idx']);

            $value = $row['qf'.$k];
            $values = Util::explodeIndexed($value);
            $valueName = $this->pme->cgiDataName($valueFddName);
            $keyName = $this->pme->cgiDataName($keyFddName);
            $invoices = Util::explodeIndexed($row['qf'.($k+1)]);
            $html = '<table class="row-count-'.count($values).'">
  <thead>
    <tr>
      <th>'.$this->l->t('Actions').'</th>
      <th>'.$this->l->t('Subject').'</th>
      <th>'.$this->l->t('Value [%s]', $this->currencySymbol()).'</th>
      <th>'.$this->l->t('Invoice').'</th>
    </tr>
  </thead>
  <tbody>';
            $options = [];
            foreach (array_keys($values) as $key) {
              $options[$key] = $field->getDataOption($key);
            }
            // sort by label and data ascending order like in ProjectParticipantFields
            uasort($options, function($a, $b) {
              /** @var Entities\ProjectParticipantFieldDataOption $a */
              /** @var Entities\ProjectParticipantFieldDataOption $b */
              $cmp = strcmp($a->getLabel(), $b->getLabel());
              if ($cmp === 0) {
                $aData = $a->getData();
                $bData = $b->getData();
                $cmp = strcmp($aData, $bData);
              }
              return $cmp;
            });

            $idx = 0;
            foreach ($options as $key => $option) {
              $value = $values[$key];
              $label = $option ? $option->getLabel() : '';
              if (!empty($invoices[$key])) {
                $downloadLink = $this->urlGenerator()
                                     ->linkToRoute($this->appName().'.downloads.get', [
                                       'section' => 'database',
                                       'object' => $invoices[$key],
                                     ])
                              . '?requesttoken=' . urlencode(\OCP\Util::callRegister());
              }
              $html .= '
<tr class="field-datum" data-option-key="'.$key.'" data-field-id="'.$field['id'].'">
  <td class="operations">
    <input
      class="operation delete-undelete"
      title="'.$this->toolTipsService['participant-fields-recurring-data:delete-undelete'].'"
      type="button"/>
    <input
      class="operation regenerate"
      title="'.$this->toolTipsService['participant-fields-recurring-data:regenerate'].'"
      type="button"/>
  </td>
  <td class="label">
    '.$label.'
  </td>
  <td class="input">
    <input id="receivable-input-'.$key.'" type=checkbox checked="checked" class="pme-input pme-input-lock lock-unlock left-of-input"/>
    <label class="pme-input pme-input-lock lock-unlock left-of-input" title="'.$this->toolTipsService['pme:input:lock-unlock'].'" for="receivable-input-'.$key.'"></label>
    <input class="pme-input '.$dataType.'" type="number" readonly="readonly" name="'.$valueName.'['.$idx.']" value="'.$value.'"/>
    <input class="pme-input '.$dataType.'" type="hidden" name="'.$keyName.'['.$idx.']" value="'.$key.'"/>
  </td>
  <td>
     <a class="download-link tooltip-auto'.(empty($downloadLink) ? ' hidden' : '').'"
        href="'.($downloadLink??'').'">
       '.$this->l->t('download').'
     </a>
 </td>
</tr>';
              $idx++;
            }
            foreach (IRecurringReceivablesGenerator::UPDATE_STRATEGIES as $tag) {
              $option = [
                'value' => $tag,
                'name' => $this->l->t($tag),
                'flags' => ($tag === IRecurringReceivablesGenerator::UPDATE_STRATEGY_EXCEPTION ? PageNavigation::SELECTED : 0),
                'title' => $this->toolTipsService['participant-fields-recurring-data:update-strategy:'.$tag],
              ];
              $updateStrategies[] = $option;
            }
            $updateStrategies = PageNavigation::selectOptions($updateStrategies);
            $html .= '
    <tr class="generator" data-field-id="'.$field['id'].'">
      <td class="operations" colspan="4">
        <input
          class="operation regenerate-all"
          title="'.$this->toolTipsService['participant-fields-recurring-data:regenerate-all'].'"
          type="button"
          value="'.$this->l->t('Recompute all Receivables').'"
          title="'.$this->toolTipsService['participant-fields-recurring-data:regenerate-all'].'"
        />
        <label for="recurring-receivables-update-strategy" class="recurring-receivables-update-strategy">
          '.$this->l->t('In case of Conflict').'
        </label>
        <select
          id="recurring-receivables-update-strategy"
          class="recurring-multiplicity-required recurring-receivables-update-strategy"
          name="recurringReceivablesUpdateStrategy"
          title="'.Util::htmlEscape($this->toolTipsService['participant-fields-recurring-data:update-strategies']).'"
        >
' . $updateStrategies
. '
        </select>
      </td>
    </tr>
  </tbody>
</table>';
            return $html;
          };
          break;
          /*
           * end of FieldMultiplicity::RECURRING
           *
           *********************************************************************/
        case FieldMultiplicity::GROUPOFPEOPLE:
          /**********************************************************************
           *
           * Grouping with variable number of groups, e.g. "room-mates".
           *
           */

          // special option with Uuid::NIL holds the management information
          $generatorOption = $field->getManagementOption();
          $valueGroups = [ -1 => $this->l->t('without group'), ];

          // old field, group selection
          $keyFdd = array_merge($keyFdd, [ 'mask' => null, ]);

          // generate a new group-definition field as yet another column
          list(, $fddGroupMemberName) = $this->makeJoinTableField(
            $fieldDescData, $tableName, 'musician_id', $keyFdd);

          // hide value field and tweak for view displays.
          $css[] = FieldMultiplicity::GROUPOFPEOPLE;
          $css[] = 'single-valued';
          $keyFdd = Util::arrayMergeRecursive(
            $keyFdd, [
              'css' => [ 'postfix' => array_merge($css, [ 'groupofpeople-id', ]), ],
              'input' => 'SRH',
            ]);


          // tweak the join-structure entry for the group field
          $joinInfo = &$this->joinStructure[$tableName];
          $joinInfo = array_merge(
            $joinInfo,
            [
              'identifier' => [
                'project_id' => 'project_id',
                'musician_id' => false,
                'field_id' => [ 'value' => $fieldId, ],
                'option_key' => [ 'self' => true, ],
              ],
              'column' => 'musician_id',
            ]);

          // store the necessary group data compatible to the predefined groups stuff
          $max = $generatorOption['limit'];
          $dataOptionsData = $dataOptions->map(function($value) use ($max) {
            return [
              'key' => (string)$value['key'],
              'data' => [ 'limit' => $max, ],
            ];
          })->getValues();
          array_unshift(
            $dataOptionsData,
            [ 'key' => (string)$generatorOption['key'], 'data' => [ 'limit' => $max, ], ]
          );
          $dataOptionsData = json_encode($dataOptionsData);

          // new field, member selection
          $groupMemberFdd = &$fieldDescData[$fddGroupMemberName];
          $groupMemberFdd = array_merge(
            $groupMemberFdd, [
              'select' => 'M',
              'sql' => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $order_by)',
              'display' => [ 'popup' => 'data' ],
              'colattrs' => [ 'data-groups' => $dataOptionsData, ],
              'filter' => [
                'having' => true,
              ],
              'values' => [
                'table' => "SELECT
   m1.id AS musician_id,
   CONCAT(
     ".$this->musicianPublicNameSql('m1').",
     IF(fd.deleted IS NOT NULL, ' (".$this->l->t('deleted').")', '')
   ) AS name,
   m1.sur_name AS sur_name,
   m1.first_name AS first_name,
   m1.nick_name AS nick_name,
   m1.display_name AS display_name,
   fd.option_key AS group_id,
   fdg.group_number AS group_number
FROM ".self::PROJECT_PARTICIPANTS_TABLE." pp
LEFT JOIN ".self::MUSICIANS_TABLE." m1
  ON m1.id = pp.musician_id
LEFT JOIN ".self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE." fd
  ON fd.musician_id = pp.musician_id
     AND fd.project_id = $this->projectId
     AND fd.field_id = $fieldId
     ".($this->showDisabled ? '' : ' AND fd.deleted IS NULL')."
LEFT JOIN (SELECT
    fd2.option_key AS group_id,
    ROW_NUMBER() OVER (ORDER BY fd2.field_id) AS group_number
    FROM ".self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE." fd2
    WHERE fd2.project_id = $this->projectId AND fd2.field_id = $fieldId
    GROUP BY fd2.option_key
  ) fdg
  ON fdg.group_id = fd.option_key
WHERE pp.project_id = $this->projectId",
                'column' => 'musician_id',
                'description' => [
                  'columns' => [ 'name' ],
                  'cast' => [ false ],
                  'ifnull' => [ false ],
                ],
                'groups' => "IF(
  \$table.group_number IS NULL,
  '".$this->l->t('without group')."',
  CONCAT_WS(' ', '".$fieldName."', \$table.group_number))",
                'data' => 'JSON_OBJECT(
  "groupId", IFNULL(BIN2UUID($table.group_id), -1),
  "limit", '.$max.'
)',
                'orderby' => '$table.group_id ASC, $table.display_name ASC, $table.sur_name ASC, $table.nick_name ASC, $table.first_name ASC',
                'join' => '$join_table.group_id = '.$this->joinTables[$tableName].'.option_key',
              ],
              'valueGroups|ACP' => $valueGroups,
            ],
          );

          $groupMemberFdd['css']['postfix'] = array_merge($groupMemberFdd['css']['postfix'], $css);

          if ($dataType == FieldType::SERVICE_FEE) {
            $groupMemberFdd['css']['postfix'][] = 'money';
            $groupMemberFdd['css']['postfix'][] = $dataType;
            $fieldData = $generatorOption['data'];
            $money = $this->moneyValue($fieldData);
            $groupMemberFdd['display|LFVD'] = array_merge(
              $groupMemberFdd['display'],
              [
                'prefix' => '<span class="allowed-option money group service-fee"><span class="allowed-option-name money clip-long-text group">',
                'postfix' => ('</span><span class="allowed-option-separator money">&nbsp;</span>'
                              .'<span class="allowed-option-value money">'.$money.'</span></span>'),
              ]);
            $groupMemberFdd['display|ACP'] = array_merge(
              $groupMemberFdd['display'],
              [
                'prefix' => '<label class="'.implode(' ', $css).'">',
                'postfix' => ($this->allowedOptionLabel('', $fieldData, $dataType, 'money selected')
                              .'</label>'),
              ]);
          }

          // in filter mode mask out all non-group-members
          $groupMemberFdd['values|LF'] = array_merge(
            $groupMemberFdd['values'],
            [ 'filters' => '$table.group_id IS NOT NULL' ]);

          break;
        case FieldMultiplicity::GROUPSOFPEOPLE:
          /**********************************************************************
           *
           * Grouping with predefined group names, e.g. for car-sharing
           * or excursions.
           *
           */
          // tweak the join-structure entry for the group field
          $joinInfo = &$this->joinStructure[$tableName];
          $joinInfo = array_merge(
            $joinInfo, [
              'identifier' => [
                'project_id' => 'project_id',
                'musician_id' => false,
                'field_id' => [ 'value' => $fieldId, ],
                'option_key' => [ 'self' => true, ],
              ],
              'column' => 'musician_id',
            ]);

          // define the group stuff
          $groupValues2   = $values2;
          $groupValueData = $valueData;
          $values2 = [];
          $valueGroups = [ -1 => $this->l->t('without group'), ];
          $idx = -1;
          foreach($dataOptions as $dataOption) {
            $valueGroups[--$idx] = $dataOption['label'];
            $data = $dataOption['data'];
            if ($dataType == FieldType::SERVICE_FEE) {
              $data = $this->moneyValue($data);
            }
            if (!empty($data)) {
              $valueGroups[$idx] .= ':&nbsp;' . $data;
            }
            $values2[$idx] = $this->l->t('add to this group');
            $valueData[$idx] = json_encode([ 'groupId' => $dataOption['key'], ]);
          }

          // make the field a select box for the predefined groups, like
          // for the "multiple" stuff.

          $css[] = FieldMultiplicity::GROUPOFPEOPLE;
          $css[] = 'predefined';
          if ($dataType === FieldType::SERVICE_FEE) {
            $css[] = ' money '.$dataType;
            foreach ($groupValues2 as $key => $value) {
              $groupValues2[$key] = $this->allowedOptionLabel(
                $value, $groupValueData[$key], $dataType, 'money group');
            }
          }

          // old field, group selection
          $keyFdd = array_merge(
            $keyFdd, [
              //'name' => $this->l->t('%s Group', $fieldName),
              'css'         => [ 'postfix' => $css, ],
              'select'      => 'D',
              'values2'     => $groupValues2,
              'display'     => [ 'popup' => 'data' ],
              'sort'        => true,
              'escape'      => false,
              'mask' => null,
            ]);

          $fddBase = Util::arrayMergeRecursive([], $keyFdd);

          // hide value field
          $keyFdd = Util::arrayMergeRecursive(
            $keyFdd, [
              'css' => [ 'postfix' => array_merge($css, [ 'groupofpeople-id', ]), ],
              'input' => 'SRH',
            ]);

          // generate a new group-definition field as yet another column
          list(, $fddGroupMemberName) = $this->makeJoinTableField(
            $fieldDescData, $tableName, 'musician_id', $fddBase);

          // compute group limits per group
          $dataOptionsData = $dataOptions->map(function($value) {
            return [
              'key' => (string)$value['key'],
              'data' =>  [ 'limit' => $value['limit'], ],
            ];
          })->getValues();
          $dataOptionsData = json_encode(array_column($dataOptionsData, 'data', 'key'));

          // new field, member selection
          $groupMemberFdd = &$fieldDescData[$fddGroupMemberName];
          $groupMemberFdd = Util::arrayMergeRecursive(
            $groupMemberFdd, [
              'select' => 'M',
              'sql|ACP' => 'GROUP_CONCAT(DISTINCT $join_table.musician_id ORDER BY $order_by)',
              //'sql' => 'GROUP_CONCAT(DISTINCT $join_table.musician_id)',
              //'display' => [ 'popup' => 'data' ],
              'colattrs' => [ 'data-groups' => $dataOptionsData, ],
              'values|ACP' => [
                'table' => "SELECT
  m3.id AS musician_id,
  CONCAT(
    ".$this->musicianPublicNameSql('m3').",
    IF(fd.deleted IS NOT NULL, ' (".$this->l->t('deleted').")', '')
  ) AS name,
  m3.sur_name AS sur_name,
  m3.first_name AS first_name,
  m3.nick_name AS nick_name,
  m3.display_name AS display_name,
  fd.option_key AS group_id,
  do.label AS group_label,
  do.data AS group_data,
  do.limit AS group_limit
FROM ".self::PROJECT_PARTICIPANTS_TABLE." pp
LEFT JOIN ".self::MUSICIANS_TABLE." m3
  ON m3.id = pp.musician_id
LEFT JOIN ".self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE." fd
  ON fd.musician_id = pp.musician_id
     AND fd.project_id = $this->projectId
     AND fd.field_id = $fieldId
     ".($this->showDisabled ? '' : ' AND fd.deleted IS NULL')."
LEFT JOIN ".self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE." do
  ON do.field_id = fd.field_id AND do.key = fd.option_key
WHERE pp.project_id = $this->projectId",
                'column' => 'musician_id',
                'description' => [
                  'columns' => [ 'name' ],
                  'ifnull' => [ false ],
                  'cast' => [ false ],
                ],
                'groups' => "CONCAT(\$table.group_label, ': ', \$table.group_data)",
                'data' => 'JSON_OBJECT(
  "groupId", IFNULL(BIN2UUID($table.group_id), -1),
  "limit", $table.group_limit
)',
                'orderby' => '$table.group_id ASC, $table.display_name ASC, $table.sur_name ASC, $table.nick_name ASC, $table.first_name ASC',
                'join' => '$join_table.group_id = '.$this->joinTables[$tableName].'.option_key',
                //'titles' => '$table.name',
              ],
              'valueGroups|ACP' => $valueGroups,
              'valueData|ACP' => $valueData,
              'values2|ACP' => $values2,
              'mask' => null,
              'display|LDV' => [
                'popup' => 'data:next',
              ],
              'display|ACP' => [
                'prefix' => function($op, $pos, $row, $k, $pme) use ($css) {
                  return '<label class="'.implode(' ', $css).'">';
                },
                'postfix' => function($op, $pos, $row, $k, $pme) use ($dataOptions, $dataType, $keyFddIndex) {
                  $selectedKey = $row['qf'.$keyFddIndex];
                  $html = '';
                  foreach ($dataOptions  as $dataOption) {
                    $key = $dataOption['key'];
                    $active = $selectedKey == $key ? 'selected' : null;
                    $html .= $this->allowedOptionLabel(
                      $dataOption['label'], $dataOption['data'], $dataType, $active, [ 'key' => $dataOption['key'], ]);
                  }
                  $html .= '</label>';
                  return $html;
                },
              ],
            ]);

          $groupMemberFdd['css']['postfix'][] = 'clip-long-text';
          $groupMemberFdd['css|LFVD']['postfix'] = $groupMemberFdd['css']['postfix'];
          $groupMemberFdd['css|LFVD']['postfix'][] = 'view';

          // generate yet another field to define popup-data
          list(, $fddMemberNameName) = $this->makeJoinTableField(
            $fieldDescData, $tableName, 'musician_name', $fddBase);

          // new field, data-popup
          $popupFdd = &$fieldDescData[$fddMemberNameName];

          // data-popup field, this can be virtual as it is only used for displaying data
          $popupFdd = Util::arrayMergeRecursive(
            $popupFdd, [
              'input' => 'VSRH',
              'css'   => [ 'postfix' => array_merge($css, [ 'groupofpeople-popup', ]), ],
              'sql|LVFD' => "GROUP_CONCAT(DISTINCT \$join_col_fqn ORDER BY \$order_by SEPARATOR ', ')",
              'values|LFDV' => [
                'table' => "SELECT
  m2.id AS musician_id,
  CONCAT(
    ".$this->musicianPublicNameSql('m2').",
    IF(fd.deleted IS NOT NULL, ' (".$this->l->t('deleted').")', '')
  ) AS name,
  m2.sur_name AS sur_name,
  m2.first_name AS first_name,
  m2.nick_name AS nick_name,
  m2.display_name AS display_name,
  fd.option_key AS group_id
FROM ".self::PROJECT_PARTICIPANTS_TABLE." pp
LEFT JOIN ".self::MUSICIANS_TABLE." m2
  ON m2.id = pp.musician_id
LEFT JOIN ".self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE." fd
  ON fd.musician_id = pp.musician_id
     AND fd.project_id = pp.project_id
     AND fd.field_id = $fieldId
     ".($this->showDisabled ? '' : ' AND fd.deleted IS NULL')."
WHERE pp.project_id = $this->projectId AND fd.field_id = $fieldId",
                'column' => 'name',
                'orderby' => '$table.group_id ASC, $table.display_name ASC, $table.sur_name ASC, $table.nick_name ASC, $table.first_name ASC',
                'join' => '$join_table.group_id = '.$this->joinTables[$tableName].'.option_key',
              ],
            ]);

          break;
        }
      } // foreach ($participantFields ...)
    };

    return [ $joinStructure, $generator ];
  }

  protected function allowedOptionLabel($label, $value, $dataType, $css = null, $data = null)
  {
    $label = Util::htmlEscape($label);
    $css = empty($css) ? $dataType : $css.' '.$dataType;
    $innerCss = $dataType;
    $htmlData = [];
    if (is_array($data)) {
      foreach ($data as $key => $_value) {
        $htmlData[] = "data-".$key."='".$_value."'";
      }
    }
    $htmlData = implode(' ', $htmlData);
    if (!empty($htmlData)) {
      $htmlData = ' '.$htmlData;
    }
    switch ($dataType) {
    case 'money':
    case FieldType::SERVICE_FEE:
      $value = $this->moneyValue($value);
      $innerCss .= ' money';
      break;
    default:
      $value = Util::htmlEscape($value);
      break;
    }
    $label = '<span class="allowed-option-name '.$innerCss.'">'.$label.'</span>';
    $sep   = '<span class="allowed-option-separator '.$innerCss.'">&nbsp;</span>';
    $value = '<span class="allowed-option-value '.$innerCss.'">'.$value.'</span>';
    return '<span class="allowed-option '.$css.'"'.$htmlData.'>'.$label.$sep.$value.'</span>';
  }

  static protected function participantFieldTableName($fieldId)
  {
    return self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE.self::VALUES_TABLE_SEP.$fieldId;
  }

  static protected function participantFieldValueFieldName($fieldId)
  {
    return self::joinTableFieldName(self::participantFieldTableName($fieldId), 'option_value');
  }

  static protected function participantFieldKeyFieldName($fieldId)
  {
    return self::joinTableFieldName(self::participantFieldTableName($fieldId), 'option_key');
  }

  protected function cloudFileUploadRowHtml($value, $fieldId, $optionKey, $policy, $subDir, $fileBase, $musician)
  {
    $participantFolder = $this->projectService->ensureParticipantFolder($this->project, $musician);
    // make sure $subDir exists
    if (!empty($subDir)) {
      $subDirPrefix = UserStorage::PATH_SEP . $subDir;
      $this->userStorage->ensureFolder($participantFolder . $subDirPrefix);
    } else {
      $subDirPrefix = '';
    }
    if (!empty($value)) {
      $filePath = $participantFolder . $subDirPrefix . UserStorage::PATH_SEP.$value;
      try {
        $downloadLink = $this->userStorage->getDownloadLink($filePath);
        $filesAppLink = $this->userStorage->getFilesAppLink($filePath);
      } catch (\OCP\Files\NotFoundException $e) {
        $downloadLink = '#';
        $filesAppLink = '#';
        $value = '<span class="error tooltip-auto" title="' . $filePath . '">' . $this->l->t('The file "%s" could not be found on the server.', $value) . '</span>';
      }
    } else {
      $downloadLink = '';
      $filesAppLink = $this->userStorage->getFilesAppLink($participantFolder . $subDirPrefix);
    }
    $filesAppTarget = md5($filesAppLink);
    if (!empty($fileBase)) {
      $fileName = $this->projectService->participantFilename($fileBase, $this->project, $musician);
      $placeHolder = $this->l->t('Load %s', $fileName);
    } else {
      $fileName = $value;
      $placeHolder = $this->l->t('Drop files here or click to upload fileds.');
    }
    $emptyDisabled = empty($value) ? ' disabled' : '';
    $optionValueName = $this->pme->cgiDataName(self::participantFieldValueFieldName($fieldId))
                     . ($subDir ? '[]' : '');
    switch ($policy) {
    case 'rename':
      $policyTooltip = $this->toolTipsService['participant-attachment-upload-rename'];
      break;
    case 'replace':
      $policyTooltip = $this->toolTipsService['participant-attachment-upload-replace'];
      break;
    }
    $html = '
  <tr class="file-upload-row" data-field-id="'.$fieldId.'" data-option-key="'.$optionKey.'" data-sub-dir="'.$subDir.'" data-file-base="'.$fileBase.'" data-file-name="'.$fileName.'" data-upload-policy="'.$policy.'" data-storage="cloud">
    <td class="operations">
      <input type="button"'.$emptyDisabled.' title="'.$this->toolTipsService['participant-attachment-delete'].'" class="operation delete-undelete"/>
      <input type="button" title="'.$policyTooltip.'" class="operation upload-replace"/>
      <a href="'.$filesAppLink.'" target="'.$filesAppTarget.'" title="'.$this->toolTipsService['participant-attachment-open-parent'].'" class="button operation open-parent tooltip-auto"></a>
    </td>
    <td class="cloud-file">
      <a class="download-link tooltip-auto" title="'.$this->toolTipsService['participant-attachment-download'].'" href="'.$downloadLink.'">'.$value.'</a>
      <input class="upload-placeholder"
             title="'.$this->toolTipsService['participant-attachment-upload'].'"
             placeholder="'.$placeHolder.'"
             type="text"
             name="'.$optionValueName.'"
             value="'.htmlspecialchars($value).'"
      />
    </td>
  </tr>';
    return $html;
  }

  protected function dbFileUploadRowHtml($value, $fieldId, $key, $fileBase, $musician)
  {
    if (!empty($value)) {
      $downloadLink = $this->urlGenerator()->linkToRoute($this->appName().'.downloads.get', [
        'section' => 'database',
        'object' => $value,
      ])
      . '?requesttoken=' . urlencode(\OCP\Util::callRegister());
    }
    $fileName = $this->projectService->participantFilename($fileBase, $this->project, $musician);
    $placeHolder = $this->l->t('Load %s', $fileName);
    $emptyDisabled = empty($value) ? ' disabled' : '';
    $optionValueName = $this->pme->cgiDataName(self::participantFieldValueFieldName($fieldId));
    $html = '
  <tr class="file-upload-row"
      data-field-id="'.$fieldId.'"
      data-option-key="'.$key.'"
      data-file-base="'.$fileBase.'"
      data-upload-policy="replace"
      data-storage="db"
      data-sub-dir=""
    >
    <td class="operations">
      <input type="button"'.$emptyDisabled.' title="'.$this->toolTipsService['participant-attachment-delete'].'" class="operation delete-undelete"/>
      <input type="button" title="'.$this->toolTipsService['participant-attachment-upload-replace'].'" class="operation upload-replace"/>
    </td>
    <td class="db-file">
      <a class="download-link tooltip-auto" title="'.$this->toolTipsService['participant-attachment-download'].'" href="'.$downloadLink.'">'.$fileName.'</a>
      <input class="upload-placeholder"
             title="'.$this->toolTipsService['participant-attachment-upload'].'"
             placeholder="'.$placeHolder.'"
             type="text"
             name="'.$optionValueName.'"
             value="'.htmlspecialchars($value).'"
      />
    </td>
  </tr>';
    return $html;
  }

  /**
   * Tweak the submitted data for the somewhat complicate "participant
   * fields" -- i.e. the personal data collected for the project
   * participants -- into a form understood by
   * beforeUpdataDoUpdateAll() and beforeInsertDoInsertAll().
   */
  public function beforeUpdateSanitizeParticipantFields(&$pme, $op, $step, &$oldValues, &$changed, &$newValues)
  {
    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'before');

    /** @var Entities\ProjectParticipantField $participantField */
    foreach ($this->project['participantFields'] as $participantField) {
      $fieldId = $participantField['id'];
      $multiplicity = $participantField['multiplicity'];
      $dataType = $participantField['dataType'];

      $tableName = self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE.self::VALUES_TABLE_SEP.$fieldId;

      $keyName = $this->joinTableFieldName($tableName, 'option_key');
      $valueName = $this->joinTableFieldName($tableName, 'option_value');
      $depositName = $this->joinTableFieldName($tableName, 'deposit');
      $groupFieldName = $this->joinTableFieldName($tableName, 'musician_id');

      $this->debug('FIELDNAMES '.$keyName." / ".$groupFieldName);
      $this->debug("MULTIPLICITY / DATATYPE ".$multiplicity.' / '.$dataType);
      switch ($multiplicity) {
      case FieldMultiplicity::SIMPLE:
        // We fake a multi-selection field and set the user input as
        // additional field value.
        if (array_search($valueName, $changed) === false
            && array_search($depositName, $changed) === false) {
          continue 2;
        }
        $dataOption = $participantField->getSelectableOptions()->first(); // the only one
        $key = (string)$dataOption['key'];
        $oldKey = (string)$oldValues[$keyName]?:$key;
        if ($oldKey !== $key) {
          throw new \RuntimeException(
            $this->l->t('Inconsistent field keys for "%s", field-id %d, should: "%s", old: "%s", new: "%s"', [
              $participantField->getName(),
              $participantField->getId(),
              $key,
              $oldKey,
              $newValues[$keyName],
            ]));
        }
        switch ($dataType) {
        case FieldType::CLOUD_FOLDER:
          // collect the data into a JSON array, input is a comma
          // separated list of directory entry names
          $newValues[$valueName] = json_encode(Util::explode(self::VALUES_SEP, $newValues[$valueName]));
          if ($newValues[$valueName] === $oldValues[$valueName]) {
            Util::unsetValue($changed, $valueName);
            continue 3;
          }
          break;
        default:
          break;
        }
        // tweak the option_key value
        $newValues[$keyName] = $key;
        $changed[] = $keyName;
        // tweak the option value to have the desired form
        $newValues[$valueName] = $key.self::JOIN_KEY_SEP.$newValues[$valueName];
        $newValues[$depositName] = $key.self::JOIN_KEY_SEP.$newValues[$depositName];
        break;
      case FieldMultiplicity::RECURRING:
        if (array_search($valueName, $changed) === false
            && array_search($keyName, $changed) === false) {
          continue 2;
        }

        // just convert to KEY:VALUE notation for the following trigger functions
        // $oldValues ATM already has this format
        foreach ([&$newValues] as &$dataSet) {
          $keys = Util::explode(',', $dataSet[$keyName]);
          $amounts = Util::explode(',', $dataSet[$valueName]);
          $values = [];
          foreach (array_combine($keys, $amounts) as $key => $amount) {
            $values[] = $key.self::JOIN_KEY_SEP.$amount;
          }
          $dataSet[$valueName] = implode(',', $values);
        }

        // mark both as changed
        foreach ([$keyName, $valueName] as $fieldName) {
          Util::unsetValue($changed, $fieldName);
          if ($oldValues[$fieldName] != $newValues[$fieldName]) {
            $changed[] = $fieldName;
          }
        }
        break;
      case FieldMultiplicity::GROUPOFPEOPLE:
      case FieldMultiplicity::GROUPSOFPEOPLE:
        // Multiple predefined groups with a variable number of
        // members. Think of distributing members to cars or rooms

        if (array_search($groupFieldName, $changed) === false
            && array_search($keyName, $changed) === false) {
          continue 2;
        }

        $oldGroupId = $oldValues[$keyName];
        $newGroupId = $newValues[$keyName];

        $max = PHP_INT_MAX;
        $label = $this->l->t('unknown');
        if ($multiplicity == FieldMultiplicity::GROUPOFPEOPLE) {
          /** @var Entities\ProjectParticipantFieldDataOption $generatorOption */
          $generatorOption = $participantField->getManagementOption();
          $max = $generatorOption['limit'];
          $label = $participantField['name'];
        } else {
          $newDataOption = $participantField->getDataOption($newGroupId);
          $max = $newDataOption['limit'];
          $label = $newDataOption['label'];
          //$this->logInfo('OPTION: '.$newGroupId.' '.Functions\dump($newDataOption));
        }

        $oldMembers = Util::explode(',', $oldValues[$groupFieldName]);
        $newMembers = Util::explode(',', $newValues[$groupFieldName]);

        if (count($newMembers) > $max) {
          throw new \Exception(
            $this->l->t('Number %d of requested participants for group %s is larger than the number %d of allowed participants.',
                        [ count($newMembers), $label, $max ]));
        }

        if ($multiplicity == FieldMultiplicity::GROUPOFPEOPLE && !empty($newMembers)) {
          // make sure that a group-option exists, clean up afterwards
          if (empty($newGroupId) || $newGroupId == Uuid::NIL) {
            $newGroupId = Uuid::create();
            $dataOption = (new Entities\ProjectParticipantFieldDataOption)
                        ->setField($participantField)
                        ->setKey($newGroupId);
            $this->persist($dataOption);
          }
        }

        // In order to compute the changeset in
        // PMETableViewBase::beforeUpdateDoUpdateAll() we need to
        // include all musicians referencing the new group into the
        // set of old members as well as all newMembers who already
        // reference a group. This will result in deletion of all old
        // members as well as deletion of references to other groups
        // (group membership is single select).
        // The current musician must always remain

        $oldMemberships = []; // musician_id => option_key
        foreach ($participantField->getFieldData() as $fieldDatum) {
          $musicianId = $fieldDatum->getMusician()->getId();
          $optionKey = $fieldDatum->getOptionKey();
          if (array_search($musicianId, $newMembers) !== false
              || $optionKey == $newGroupId
              || $musicianId == $pme->rec['musician_id']
          ) {
            $oldMemberships[$musicianId] = $musicianId.self::JOIN_KEY_SEP.$fieldDatum->getOptionKey();
          }
        }

        // recompute the old set of relevant musicians
        $oldValues[$groupFieldName] = implode(',', array_keys($oldMemberships));
        $oldValues[$keyName] = implode(',', array_values($oldMemberships));

        // recompute the new set of relevant musicians
        foreach ($newMembers as &$member) {
          $member .= self::JOIN_KEY_SEP.$newGroupId;
        }
        $newValues[$keyName] = implode(',', $newMembers);

        $changed[] = $groupFieldName;
        $changed[] = $keyName;
      default:
        break;
      }
    }
    $changed = array_values(array_unique($changed));
    $this->changeSetSize = count($changed);

    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'after');

    return true;
  }

  /**
   * In particular remove no longer needed groupofpeople options
   */
  public function cleanupParticipantFields(&$pme, $op, $step, &$oldValues, &$changed, &$newValues)
  {
    /** @var Entities\ProjectParticipantField $participantField */
    foreach ($this->project['participantFields'] as $participantField) {
      if ($participantField->getMultiplicity() != FieldMultiplicity::GROUPOFPEOPLE()) {
        continue;
      }
      /** @var Entities\ProjEctParticipantFieldDataOption $dataOption */
      foreach ($participantField->getDataOptions() as $dataOption) {
        if ((string)$dataOption->getKey() != Uuid::NIL
            && count($dataOption->getFieldData()) == 0) {
          $this->debug('Remove data-option ' . $dataOption->getKey());
          $participantField->getDataOptions()->removeElement($dataOption);
          $this->remove($dataOption);
          $this->flush();
        }
      }
    }
    return true;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
