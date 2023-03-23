<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2023 Claus-Justus Heine
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

namespace OCA\CAFEVDB\PageRenderer\FieldTraits;

use Exception;
use RuntimeException;

use \OCA\CAFEVDB\Wrapped\Carbon\Carbon as DateTime;

use OCP\Files as CloudFiles;
use OCP\AppFramework\Http\TemplateResponse;

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
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\PageRenderer\PMETableViewBase;

use OCA\CAFEVDB\Controller\DownloadsController;
use OCA\CAFEVDB\Storage\DatabaseStorageUtil;

use OCA\CAFEVDB\Constants;

/** Participant-fields. */
trait ParticipantFieldsTrait
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Storage\Database\DatabaseStorageNodeNameTrait;
  use ParticipantFileFieldsTrait;
  use ParticipantFieldsCgiNameTrait;
  use SubstituteSQLFragmentTrait;

  /** @var UserStorage */
  protected $userStorage = null;

  /** @var ProjectParticipantFieldsService */
  protected $participantFieldsService;

  /** @var ProjectService */
  protected $projectService;

  /** @var ToolTipsService */
  protected $toolTipsService;

  /** @var string */
  protected static $toolTipsPrefix = 'page-renderer:participant-fields:display';

  /** @var bool */
  protected $expertMode;

  /** @var PHPMyEdit */
  protected $pme;

  /**
   * @var array
   *
   * @see PMETableViewBase::$joinStructure
   */
  // protected $joinStructure = [];

  /**
   * @var array
   *
   * @see PMETableViewBase::$joinTables
   */
  // protected $joinTables = null;

  /**
   * For each extra field add one dedicated join table entry
   * which is pinned to the respective field-id.
   *
   * @param iterable $participantFields
   *
   * @param string|array $projectIdField
   *
   * @param string $financeTab
   *
   * @return array
   * ```
   * [
   *   JOIN_STRUCTURE_FRAGMENT,
   *   callable(array &$fdd, array &subTotals = []),
   * ]
   * ```
   *
   * After merging JOIN_STRUCTURE_FRAGMENT to the rest of using class's
   * join-structure the generator callback has to be called and augments the
   * given field-description-data with columns for all participant fields. The
   * $subTotals array is filled with SQL-fragments which collect the
   * service-fee amounts of the respective monetary fields with data-type
   * field-type FieldType::RECEIVABLES,
   * FieldType::LIABILITIES, it is indexed by field-name. Steps to perform:
   *
   * - merge the returned join-structure into $this->joinStructure
   * - call PMETableViewBase::defineJoinStructure()
   * - call the returned generator function generator($fdd, $subTotals) which
   *   will then augment the given field-descriptor array and fill the
   *   sub-totals array.
   *
   * @note Joining many tables with multiple rows per join key is a
   * performance hit. Therefore there is only a single join and the respective
   * field is selected by an IF-clause inside the group functions. The problem
   * with the multiple joins is that they form a tensor-product of the tables
   * wich rather quickly leads to a memory blow-up.
   *
   * @see PMETableViewBase::defineJoinStructure()
   */
  public function renderParticipantFields(
    iterable $participantFields,
    mixed $projectIdField = 'project_id',
    string $financeTab = 'finance',
  ):array {
    $joinStructure = [];

    /** @var Entities\ProjectParticipantField $field */
    foreach ($participantFields as $field) {
      $fieldId = $field['id'];

      $tableName = self::participantFieldTableName($fieldId);
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

      $optionsTableName = self::participantFieldOptionsTableName($fieldId);
      $joinStructure[$optionsTableName] = [
        'entity' => Entities\ProjectParticipantFieldDataOption::class,
        'flags' => 0, //self::JOIN_READONLY,
        'identifier' => [
          'field_id' => [ 'value' => $fieldId, ],
          'key' => false,
        ],
        'reference' => self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE,
        'column' => 'key',
        'encode' => 'BIN2UUID(%s)',
      ];
    }

    $generator = function(array &$fieldDescData, array &$subTotals = []) use ($participantFields, $financeTab) {

      /** @var Entities\ProjectParticipantField $field */
      foreach ($participantFields as $field) {
        $fieldName = $field->getName();
        $fieldId   = $field->getId();
        $multiplicity = $field->getMultiplicity();
        $dataType = (string)$field->getDataType();
        $deleted = !empty($field->getDeleted());
        $subTotalsSign = $dataType == FieldType::LIABILITIES ? '-1 * ' : '';

        if (!$this->participantFieldsService->isSupportedType($multiplicity, $dataType)) {
          throw new Exception(
            $this->l->t(
              'Unsupported multiplicity / data-type combination: %s / %s',
              [ $multiplicity, $dataType ]));
        }

        // set tab unless overridden by field definition
        if (!empty($field['tab'])) {
          $tabId = $this->tableTabId($field['tab']);
          $tab = [ 'id' => $tabId ];
        } else {
          $tabId = ProjectParticipantFieldsService::defaultTabId($field['multiplicity'], $field['data_type']);
          if ($tabId == 'finance') {
            $tabId = $financeTab;
          }
          $tab = [ 'id' => $tabId ];
        }

        $tableName = self::participantFieldTableName($fieldId);
        $optionsTableName = self::participantFieldOptionsTableName($fieldId);

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

        list($fddBaseIndex, $keyFddName) = $this->makeJoinTableField(
          $fieldDescData, $tableName, 'option_key',
          Util::arrayMergeRecursive($extraFddBase, [ 'values' => ['encode' => 'BIN2UUID(%s)',], ])
        );
        $keyFdd = &$fieldDescData[$keyFddName];

        list($absValueFddIndex, $valueFddName) = $this->makeJoinTableField(
          $fieldDescData, $tableName, 'option_value',
          Util::arrayMergeRecursive($extraFddBase, [ 'input' => 'VSRH', ])
        );
        $valueFdd = &$fieldDescData[$valueFddName];
        $valueFddOffset = $absValueFddIndex - $fddBaseIndex;

        // @todo this would need more care.
        /* list($deletedFddIndex, $deletedFddName) = */$this->makeJoinTableField(
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
      COALESCE($join_col_fqn, "")
    )
  )
  ORDER BY $order_by))',
          ])
        );
        // $deletedFdd = &$fieldDescData[$deletedFddName];

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
              // case FieldType::DATE:
              // case FieldType::DATETIME:
            case FieldType::RECEIVABLES:
            case FieldType::LIABILITIES:
              $style = $this->defaultFDD[$dataType];
              if (empty($style)) {
                throw new Exception($this->l->t('Not default style for "%s" available.', $dataType));
              }
              unset($style['name']);
              unset($style['display|ACP']['postfix']);
              $fdd = array_merge($fdd, $style);
              $fdd['css']['postfix'] = array_merge($fdd['css']['postfix'], $css);
              break;
            case FieldType::DATE:
            case FieldType::DATETIME:
            case FieldType::CLOUD_FILE:
            case FieldType::CLOUD_FOLDER:
            case FieldType::DB_FILE:
              break;
          }
        }

        switch ($multiplicity) {
          case FieldMultiplicity::SIMPLE:
            /*-********************************************************************
             *
             * Simple input field.
             *
             */
            $valueFdd['input'] = $keyFdd['input'];
            $keyFdd['input'] = 'SRH';
            $valueFdd['css']['postfix'][] = 'simple-valued';
            $valueFdd['css']['postfix'][] = $dataType;
            $valueFdd['css']['postfix'][] = 'data-type-' . $dataType;

            $valueFdd['sql'] = 'GROUP_CONCAT(DISTINCT IF($join_table.field_id = '.$fieldId.$deletedSqlFilter.', $join_col_fqn, NULL))';

            $defaultValue = $field->getDefaultValue();
            $defaultButton = '<input type="button"
       value="'.$this->l->t('Revert to default').'"
       class="display-postfix revert-to-default [BUTTON_STYLE]"
       title="'.$this->toolTipsService[self::$toolTipsPrefix . ':revert-to-default'].'"
       data-field-id="'.$fieldId.'"
       data-field-property="[FIELD_PROPERTY]"
/>';

            switch ($dataType) {
              case FieldType::RECEIVABLES:
              case FieldType::LIABILITIES:
                unset($valueFdd['mask']);

                // yet another field for the supporting documents
                list($absInvoiceFddIndex, $invoiceFddName) = $this->makeJoinTableField(
                  $fieldDescData, $tableName, 'supporting_document_id', [
                    'input' => 'SRH',
                    'sql' => 'TRIM(BOTH \',\' FROM GROUP_CONCAT(DISTINCT
  IF($join_table.field_id = '.$fieldId.$deletedSqlFilter.', $join_col_fqn, NULL)
  ORDER BY $order_by))',
                    'values' => [
                      'orderby' => '$table.option_key ASC',
                    ],
                  ]);
                // $invoiceFdd = &$fieldDescData[$invoiceFddName];
                $invoiceFddOffset = $absInvoiceFddIndex - $fddBaseIndex;

                // yet another field to support summing up totals
                list($subTotalsIndex, $subTotalsName) = $this->makeJoinTableField(
                  $fieldDescData, $tableName, 'sub_totals_invoiced',
                  Util::arrayMergeRecursive(
                    $extraFddBase, [
                      'name' => $extraFddBase['name'] . ' (' . $this->l->t('sub-totals') . ')',
                      'input' => 'VSR' . (!$this->expertMode ? 'H' : ''),
                      'tab' => [ 'id' => 'tab-none' ], // move it away
                      'select' => 'N',
                      'sql' => $subTotalsSign . 'CAST(
  COALESCE(
    GROUP_CONCAT(
      DISTINCT
      IF($join_table.field_id = ' . $fieldId . ' AND $join_table.deleted IS NULL, $join_col_fqn, NULL)
    ),
    0
  )
  AS DECIMAL(7, 2)
)',
                      'php' => fn($value) => $this->expertMode ? $this->moneyValue($value) : null,
                      'align' => 'right',
                      'values' => [
                        'column' => 'option_value',
                      ],
                    ])
                );

                $viewCallback = function(
                  $optionValue,
                  $op,
                  $k,
                  $row,
                  $recordId,
                  $pme,
                  $valueFddOffset,
                ) use (
                  $field,
                  $invoiceFddOffset,
                ) {
                  $html = $this->moneyValue($optionValue);

                  $keyFddIndex = $k - $valueFddOffset;
                  $invoiceFddIndex = $keyFddIndex + $invoiceFddOffset;

                  $optionKey = $row['qf' . $keyFddIndex];
                  $invoice = $row['qf' . $invoiceFddIndex];
                  list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);

                  if (empty($optionKey)) {
                    $fieldOption = $field->getSelectableOptions()->first();
                    $optionKey = $fieldOption->getKey();
                  } else {
                    $fieldOption = $field->getDataOption($optionKey);
                  }

                  if (empty($invoice)) {
                    return $html;
                  }

                  $fieldDatum = $this->makeFieldDatum($field, $musician, $fieldOption, $optionValue, $invoice);
                  $fileInfo = $this->projectService->participantFileInfo($fieldDatum, includeDeleted: true);
                  $fileName = $fileInfo['fileName'];
                  $downloadLink = $this->urlGenerator()
                    ->linkToRoute($this->appName().'.downloads.get', [
                      'section' => 'database',
                      'object' => $invoice,
                    ])
                    . '?'
                    . http_build_query([
                      'fileName' => $fileName,
                      'requesttoken' => \OCP\Util::callRegister(),
                    ], '', '&');

                  $html = '<a class="download-link ajax-download flex-grow tooltip-auto"
   title="'.$this->toolTipsService[self::$toolTipsPrefix . ':attachment:download'].'"
   href="'.$downloadLink.'">' . $html . '</a>';

                  $filesAppAnchor = $this->getFilesAppAnchor($field, $musician);
                  $html = '<div class="pme-cell-wrapper flex-container flex-center flex-justify-end">' . $filesAppAnchor . $html . '</div>';

                  return $html;
                };

                $valueFdd['php|VDLF'] = function(
                  $optionValue,
                  $op,
                  $k,
                  $row,
                  $recordId,
                  $pme,
                ) use (
                  $field,
                  $invoiceFddOffset,
                  $valueFddOffset,
                  $viewCallback,
                ) {
                  return $viewCallback($optionValue, $op, $k, $row, $recordId, $pme, $valueFddOffset);
                };

                $valueFdd['display|ACP']['postfix'] = function(
                  $op,
                  $pos,
                  $k,
                  $row,
                  $pme,
                ) use (
                  $field,
                  $defaultValue,
                  $defaultButton,
                  $valueFddOffset,
                  $invoiceFddOffset,
                ) {
                  $html = '<span class="currency-symbol">'.$this->currencySymbol().'</span>';
                  if ($defaultValue !== '' && $defaultValue !== null) {
                    $html .=
                      str_replace([ '[BUTTON_STYLE]', '[FIELD_PROPERTY]' ], [ 'hidden-text', 'defaultValue' ], $defaultButton);
                  }
                  if ($op != PHPMyEdit::OPERATION_CHANGE) {
                    return $html;
                  }

                  $keyFddIndex = $k - $valueFddOffset;
                  $valueFddIndex = $k;
                  $invoiceFddIndex = $keyFddIndex + $invoiceFddOffset;

                  $optionValue = $row['qf' . $valueFddIndex];
                  $optionKey = $row['qf' . $keyFddIndex];
                  $invoice = $row['qf' . $invoiceFddIndex];
                  list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);

                  if (empty($optionKey)) {
                    $fieldOption = $field->getSelectableOptions()->first();
                    $optionKey = $fieldOption->getKey();
                  } else {
                    $fieldOption = $field->getDataOption($optionKey);
                  }

                  $pathChain = [
                    'participantFolder' => $this->projectService->ensureParticipantFolder($this->project, $musician, dry: true),
                    'documentsFolders' => $this->getDocumentsFolderName(),
                    'supportingDocumentsFolder' => $this->getSupportingDocumentsFolderName(),
                    'receivablesFolder' => $this->getReceivablesFolderName(),
                  ];
                  $participantFolder = $pathChain['participantFolder'];

                  $filesAppPath = implode(UserStorage::PATH_SEP, $pathChain);
                  while (!empty($pathChain)) {
                    $path = implode(UserStorage::PATH_SEP, $pathChain);
                    try {
                      $filesAppLink = $this->userStorage->getFilesAppLink($path, true);
                      break;
                    } catch (\OCP\Files\NotFoundException $e) {
                      $this->logDebug('No file found for ' . $filesAppPath);
                      array_pop($pathChain);
                    }
                  }

                  $fileName = null;
                  if (!empty($invoice)) {
                    $fieldDatum = $this->makeFieldDatum($field, $musician, $fieldOption, $optionValue, $invoice);
                    $fileInfo = $this->projectService->participantFileInfo($fieldDatum, includeDeleted: true);
                    $fileName = $fileInfo['fileName'];
                    $downloadLink = $this->urlGenerator()
                      ->linkToRoute($this->appName().'.downloads.get', [
                        'section' => 'database',
                        'object' => $invoice,
                      ])
                      . '?'
                      . http_build_query([
                        'fileName' => $fileName,
                        'requesttoken' => \OCP\Util::callRegister(),
                      ], '', '&');
                  }

                  $fileBase = $this->projectService->participantFilename(
                    $this->participantFieldsService->getFileSystemFieldName($field),
                    $musician,
                    ignoreExtension: true,
                  );

                  $html .=  '<span class="invoice-label">' . $this->l->t('Invoice') . ':</span>';
                  $html .= (new TemplateResponse(
                    $this->appName(),
                    'fragments/participant-fields/attachment-file-upload-menu', [
                      'containerTag' => 'span',
                      'containerAttributes' => [ 'class' => 'documents', ],
                      'fieldId' => $field->getId(),
                      'optionKey' => $optionKey,
                      'entityField' => 'supportingDocument',
                      'storage' => 'db',
                      'fileBase' => $fileBase,
                      'fileName' => $fileName,
                      'participantFolder' => $participantFolder,
                      'filesAppPath' => $filesAppPath,
                      'filesAppLink' => $filesAppLink ?? null,
                      'downloadLink' => $downloadLink ?? null,
                      'toolTips' => $this->toolTipsService,
                      'toolTipsPrefix' => self::$toolTipsPrefix,
                    ],
                    'blank',
                  ))->render();

                  return $html;
                };

                // We need one additional input field for the
                // service-fee-deposit. This is only needed for
                // FieldMultiplicity::SIMPLE and FieldType::RECEIVABLES or
                // FieldType::LIABILITIES and IFF the deposit-due-date field
                // in the option is non-zero.
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
                $depositFddOffset = count($fieldDescData) - $fddBaseIndex;

                $depositFdd['display|ACP']['postfix'] = '<span class="currency-symbol">'.$this->currencySymbol().'</span>';
                if ($defaultValue !== '' && $defaultValue !== null) {
                  $depositFdd['display|ACP']['postfix'] .=
                    str_replace([ '[BUTTON_STYLE]', '[FIELD_PROPERTY]' ], [ 'hidden-text', 'defaultDeposit' ], $defaultButton);
                }

                $depositFdd['php|VDLF'] = function(
                  $optionValue,
                  $op,
                  $k,
                  $row,
                  $recordId,
                  $pme,
                ) use (
                  $field,
                  $invoiceFddOffset,
                  $depositFddOffset,
                  $viewCallback,
                ) {
                  return $viewCallback($optionValue, $op, $k, $row, $recordId, $pme, $depositFddOffset);
                };

                break;

              case FieldType::DB_FILE:
                $this->joinStructure[$tableName]['flags'] |= self::JOIN_READONLY;
                $valueFdd['php|ACP'] = function($value, $op, $k, $row, $recordId, $pme) use ($field, $dataOptions) {
                  $fieldId = $field->getId();
                  $key = $dataOptions->first()->getKey();
                  $fileBase = $field->getName();
                  $subDir = null;
                  list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);
                  return '<div class="file-upload-wrapper" data-option-key="'.$key.'">
  <table class="file-upload">'
                    . $this->dbFileUploadRowHtml($value, $fieldId, $key, $subDir, $fileBase, $musician).'
  </table>
</div>';
                };
                $valueFdd['php|LFDV'] = function($value, $op, $k, $row, $recordId, $pme) use ($field) {
                  if (empty($value)) {
                    return '';
                  }

                  /** @var Entities\DatabaseStorageFile $file */
                  $file = $this->getDatabaseRepository(Entities\DatabaseStorageFile::class)->find($value);
                  $fileName = $file->getName();
                  list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);
                  $downloadLink = $this->di(DatabaseStorageUtil::class)->getDownloadLink($value);
                  $filesAppAnchor = $this->getFilesAppAnchor($field, $musician);

                  return $filesAppAnchor . '<a class="download-link ajax-download tooltip-auto"
   title="'.$this->toolTipsService[self::$toolTipsPrefix . ':attachment:download'].'"
   href="'.$downloadLink.'">' . $fileName . '</a>';
                };
                break;
              case FieldType::CLOUD_FILE:
                $this->joinStructure[$tableName]['flags'] |= self::JOIN_READONLY;
                $valueFdd['php|ACP'] = function(
                  $value,
                  $op,
                  $k,
                  $row,
                  $recordId,
                  $pme,
                ) use (
                  $field,
                  $dataOptions,
                ) {
                  $fieldId = $field->getId();
                  $optionKey = $dataOptions->first()->getKey();
                  list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);

                  // this code path is not timing critical, so just sync with the file-system
                  $fieldData = [];
                  $dirty = $this->participantFieldsService->populateCloudFileField($field, $musician, fieldData: $fieldData, flush: true);
                  $this->reloadOuterForm = $dirty;

                  // after "populate" the field-data just reflects the folder contents
                  $value = !empty($fieldData) ? $fieldData[0]->getOptionValue() : '';

                  $fileBase = $this->participantFieldsService->getFileSystemFieldName($field);
                  $subDir = null;
                  return '<div class="file-upload-wrapper" data-option-key="'.$optionKey.'">
  <table class="file-upload">'
                    . $this->cloudFileUploadRowHtml($value, $fieldId, $optionKey, $subDir, $fileBase, $musician) . '
  </table>
</div>';
                };
                $phpViewFunction = function(
                  $value,
                  $op,
                  $k,
                  $row,
                  $recordId,
                  $pme,
                ) use (
                  $field,
                ) {
                  if ($op == 'view') {
                    // not timing critical, sync with the FS
                    list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);

                    // this code path is not timing critical, so just sync with the file-system
                    $fieldData = [];
                    $dirty = $this->participantFieldsService->populateCloudFileField($field, $musician, fieldData: $fieldData, flush: true);
                    $this->reloadOuterForm = $dirty;

                    // after "populate" the field-data just reflects the folder contents
                    $value = !empty($fieldData) ? $fieldData[0]->getOptionValue() : '';
                  }

                  if (!empty($value)) {
                    if (empty($musician)) {
                      list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);
                    }
                    $participantFolder = $this->projectService->ensureParticipantFolder($this->project, $musician);
                    $fileBase = $this->participantFieldsService->getFileSystemFieldName($field);
                    $fileName = $this->projectService->participantFilename($fileBase, $musician);
                    $extension = pathinfo($value, PATHINFO_EXTENSION);
                    $fileName .= '.' . $extension;
                    $filePath = $participantFolder . UserStorage::PATH_SEP . $fileName;
                    $filesAppAnchor = $this->getFilesAppAnchor($field, $musician);
                    try {
                      $downloadLink = $this->userStorage->getDownloadLink($filePath);
                      $html = '<a class="download-link ajax-download tooltip-auto"
   title="'.$this->toolTipsService[self::$toolTipsPrefix . ':attachment:download'].'"
   href="'.$downloadLink.'">' . $fileBase . '.' . $extension . '</a>';
                    } catch (\OCP\Files\NotFoundException $e) {
                      $this->logException($e);
                      $html = '<span class="error tooltip-auto" title="' . $filePath . '">' . $this->l->t('The file "%s" could not be found on the server.', $fileBase) . '</span>';
                    }
                    return $filesAppAnchor.$html;
                  }
                  return null;
                };
                $valueFdd['php|DV'] = function($value, $op, $k, $row, $recordId, $pme) use ($phpViewFunction) {
                  return $phpViewFunction($value, 'view', $k, $row, $recordId, $pme);
                };
                $valueFdd['php|LF'] = function($value, $op, $k, $row, $recordId, $pme) use ($phpViewFunction) {
                  return $phpViewFunction($value, 'list', $k, $row, $recordId, $pme);
                };
                break;
              case FieldType::CLOUD_FOLDER:
                $this->joinStructure[$tableName]['flags'] |= self::JOIN_SINGLE_VALUED;
                if (empty($this->userStorage)) {
                  $this->userStorage = $this->di(UserStorage::class);
                }
                $valueFdd['php|ACP'] = function($value, $op, $k, $row, $recordId, $pme) use ($field, $dataOptions) {
                  $fieldId = $field->getId();
                  $optionKey = $dataOptions->first()->getKey();
                  list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);

                  $folderPath = $this->participantFieldsService->doGetFieldFolderPath($field, $musician);
                  $subDir = basename($folderPath);

                  // this path is not timing critical, so we make sure
                  // here that the cloud-folder exists:
                  $this->userStorage->ensureFolder($folderPath);

                  // synchronize the folder contents s.t. entries can also safely be deleted.
                  $dirty = $this->participantFieldsService->populateCloudFolderField($field, $musician, fieldDatum: $fieldDatum, flush: true);
                  $this->reloadOuterForm = $dirty;

                  // after "populate" the field-data just reflects the folder contents
                  $value = $value = !empty($fieldDatum) ? $fieldDatum->getOptionValue() : '';
                  $folderContents = json_decode($value, true);
                  if (!is_array($folderContents)) {
                    $folderContents = [];
                  }

                  $html = '<div class="file-upload-wrapper" data-option-key="'.$optionKey.'">
  <table class="file-upload">';

                  foreach ($folderContents as $nodeName) {
                    $html .= $this->cloudFileUploadRowHtml($nodeName, $fieldId, $optionKey, $subDir, '', $musician);
                  }

                  $html .= $this->cloudFileUploadRowHtml(null, $fieldId, $optionKey, $subDir, '', $musician);

                  $html .= '
  </table>
</div>';
                  return $html;
                };
                $phpViewFunction = function($value, $op, $k, $row, $recordId, $pme) use ($field) {
                  list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);
                  $folderPath = $this->participantFieldsService->doGetFieldFolderPath($field, $musician);
                  $subDir = basename($folderPath);

                  if ($op == 'view') {
                    // this path is not timing critical, so we make sure
                    // here that the cloud-folder exists:
                    $this->userStorage->ensureFolder($folderPath);

                    // synchronize the folder contents s.t. entries can also safely be deleted.
                    $dirty = $this->participantFieldsService->populateCloudFolderField($field, $musician, fieldDatum: $fieldDatum, flush: true);
                    $this->reloadOuterForm = $dirty;
                    $value = !empty($fieldDatum) ? $fieldDatum->getOptionValue() : '';
                  }

                  $listing = json_decode($value, true);
                  if (!is_array($listing)) {
                    $listing = [];
                  }
                  if (!empty($listing)) {
                    $toolTip = $this->toolTipsService['participant-attachment-open-parent'].'<br>'.implode(', ', $listing);
                    $subDir = basename($folderPath);
                    $linkText = $subDir . '.'  . 'zip';
                    try {
                      $downloadLink = $this->userStorage->getDownloadLink($folderPath);
                      $html = '<a href="'.$downloadLink.'"
                             title="'.$toolTip.'"
                             class="download-link tooltip-auto">
  ' . $linkText . '
</a>';
                    } catch (\OCP\Files\NotFoundException $e) {
                      $this->logException($e);
                      $html = '<span class="error tooltip-auto" title="' . $folderPath . '">' . $this->l->t('The folder "%s" could not be found on the server.', $subDir) . '</span>';
                    }
                  } else {
                    $html = '<span class="empty tooltip-auto" title="' . $folderPath . '">' . $this->l->t('The folder is empty.') . '</span>';
                  }

                  $filesAppAnchor = $this->getFilesAppAnchor($field, $musician, toolTip: implode(', ', $listing));

                  return $filesAppAnchor . $html;
                };
                $valueFdd['php|DV'] = function($value, $op, $k, $row, $recordId, $pme) use ($phpViewFunction) {
                  return $phpViewFunction($value, 'view', $k, $row, $recordId, $pme);
                };
                $valueFdd['php|LF'] = function($value, $op, $k, $row, $recordId, $pme) use ($phpViewFunction) {
                  return $phpViewFunction($value, 'list', $k, $row, $recordId, $pme);
                };
                break;
              case FieldType::DATE:
              case FieldType::DATETIME:
                $style = $this->defaultFDD[$dataType];
                if (empty($style)) {
                  throw new Exception($this->l->t('Not default style for "%s" available.', $dataType));
                }
                unset($style['name']);
                $fdd = array_merge($fdd, $style);
                $fdd['css']['postfix'] = array_merge($fdd['css']['postfix'], $css);
                // fall through
              default:
                if (!empty($defaultValue)) {
                  $valueFdd['display|ACP'] = $valueFdd['display|ACP'] ?? [];
                  $valueFdd['display|ACP']['postfix'] =
                    ($valueFdd['display|ACP']['postfix'] ?? '')
                    . str_replace([ '[BUTTON_STYLE]', '[FIELD_PROPERTY]', ], [ 'image-left-of-text', 'defaultValue', ], $defaultButton);
                }
                break;
            }
            break;
          case FieldMultiplicity::SINGLE:
            /*-********************************************************************
             *
             * Single choice field, yes/no
             *
             */
            reset($values2);
            $key = key($values2);
            $keyFdd['values2|ACP'] = [ $key => '' ]; // empty label for simple checkbox
            $keyFdd['values2|LVDF'] = [
              0 => $this->l->t('false'),
              $key => $this->l->t('true'),
            ];
            $keyFdd['select'] = 'C';
            // make sure we get 0 not null
            $keyFdd['sql|LVDF'] = 'COALESCE(' . $keyFdd['sql'] . ', 0)';
            $keyFdd['sql|ACP'] = $keyFdd['sql'];
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
              case FieldType::RECEIVABLES:
              case FieldType::LIABILITIES:
                $money = $this->moneyValue($dataValue);
                $noMoney = $this->moneyValue(0);
                // just use the amount to pay as label
                $keyFdd['values2|LVDF'] = [
                  '' => '-,--',
                  0 => $noMoney, //'-,--',
                  $key => $money,
                ];
                $keyFdd['values2|ACP'] = [ $key => $money, ];
                unset($keyFdd['mask']);
                $keyFdd['php|VDLF'] = function($value) {
                  return $this->moneyValue($value);
                };

                // yet another field to support summing up totals
                list($subTotalsIndex, $subTotalsName) = $this->makeJoinTableField(
                  $fieldDescData, $tableName, 'sub_totals_invoiced',
                  Util::arrayMergeRecursive(
                    $extraFddBase, [
                      'name' => $extraFddBase['name'] . ' (' . $this->l->t('sub-totals') . ')',
                      'input' => 'VSR' . (!$this->expertMode ? 'H' : ''),
                      'tab' => [ 'id' => 'tab-none' ], // move it away
                      'select' => 'T',
                      'sql' => $subTotalsSign . 'CAST(
  COALESCE(
    IF(
      GROUP_CONCAT(
        DISTINCT
        IF($join_table.field_id = '. $fieldId . ' AND $join_table.deleted IS NULL, $join_col_fqn, NULL)
        SEPARATOR ""
      ) IS NULL,
      0,
      ' . $dataValue . '
    ),
    0
  ) AS DECIMAL(7,2))',
                      'php' => fn($value) => $this->expertMode ? $this->moneyValue($value) : null,
                      'align' => 'right',
                      'values' => [
                        'column' => 'option_key',
                      ],
                    ])
                  );

                break;
              case FieldType::DATE:
              case FieldType::DATETIME:
                if (!empty($dataValue)) {
                  try {
                    $date = DateTime::parse($dataValue, $this->getDateTimeZone());
                    $dataValue = ($dataType == FieldType::DATE)
                      ? $this->dateTimeFormatter()->formatDate($date, 'medium')
                      : $this->dateTimeFormatter()->formatDateTime($date, 'medium', 'short');
                  } catch (\Throwable $t) {
                    $this->logException($t);
                    // don't care
                  }
                }
                // fall through
              default:
                if (!empty($dataValue)) {
                  $keyFdd['values2|LVDF'] = [
                    '' => '',
                    0 => '',
                    $key => $dataValue,
                  ];
                }
                $keyFdd['values2|ACP'] = [ $key => $dataValue ];
                break;
            } // data-type switch
            break;
          case FieldMultiplicity::PARALLEL:
          case FieldMultiplicity::MULTIPLE:
            /*-********************************************************************
             *
             * Multiple or single choices from a set of predefined choices.
             *
             */
            switch ($dataType) {
              case FieldType::CLOUD_FILE:
                $this->joinStructure[$tableName]['flags'] |= self::JOIN_READONLY;
                $keyFdd['php|ACP'] = function(
                  $value,
                  $op,
                  $k,
                  $row,
                  $recordId,
                  $pme,
                ) use (
                  $field,
                ) {

                  list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);
                  // this code path is not timing critical, so just sync with the file-system
                  $fieldData = [];
                  $dirty = $this->participantFieldsService->populateCloudFileField($field, $musician, fieldData: $fieldData, flush: true);
                  $this->reloadOuterForm = $dirty;

                  if (true || $dirty) {
                    $values = [];
                    /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
                    foreach ($fieldData as $fieldDatum) {
                      $values[(string)$fieldDatum->getOptionKey()] = $fieldDatum->getOptionValue();
                    }
                  } else {
                    $optionKeys = Util::explode(self::VALUES_SEP, $row['qf'.($k+0)], Util::TRIM);
                    $optionValues = Util::explode(self::VALUES_SEP, $row['qf'.($k+1)], Util::TRIM);
                    $values = array_combine($optionKeys, $optionValues);
                  }

                  $fieldId = $field->getId();

                  $subDir = $this->participantFieldsService->getFileSystemFieldName($field);

                  /** @var Entities\ProjectParticipantFieldDataOption $option */
                  $html = '<div class="file-upload-wrapper">
  <table class="file-upload">';
                  foreach ($field->getSelectableOptions() as $option) {
                    $optionKey = (string)$option->getKey();
                    $fileBase = $this->participantFieldsService->getFileSystemOptionLabel($option);
                    $html .= $this->cloudFileUploadRowHtml($values[$optionKey] ?? null, $fieldId, $optionKey, $subDir, $fileBase, $musician);
                  }
                  $html .= '
  </table>
</div>';
                  return $html;
                };
                $phpViewFunction = function(
                  $value,
                  $op,
                  $k,
                  $row,
                  $recordId,
                  $pme,
                ) use (
                  $field,
                ) {
                  if ($op === 'view') {
                    // this code path is not timing critical, so just sync with the file-system
                    list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);
                    $fieldData = [];
                    $dirty = $this->participantFieldsService->populateCloudFileField($field, $musician, fieldData: $fieldData, flush: true);
                    $this->reloadOuterForm = $dirty;

                    /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
                    foreach ($fieldData as $fieldDatum) {
                      $values[(string)$fieldDatum->getOptionKey()] = $fieldDatum->getOptionValue();
                    }
                  } elseif (!empty($value)) {
                    $optionKeys = Util::explode(self::VALUES_SEP, $row['qf'.($k+0)], Util::TRIM);
                    $optionValues = Util::explode(self::VALUES_SEP, $row['qf'.($k+1)], Util::TRIM);
                    $values = array_combine($optionKeys, $optionValues);
                  }

                  if (!empty($values)) {
                    if (empty($musician)) {
                      list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);
                    }
                    $folderPath = $this->participantFieldsService->doGetFieldFolderPath($field, $musician);
                    $subDir = basename($folderPath);

                    // restore the extensions ... $value is a concatenation of the option names
                    $listing = [];
                    foreach ($values as $optionKey => $fileName) {
                      $option = $field->getDataOption($optionKey);
                      $fileBase = $this->participantFieldsService->getFileSystemOptionLabel($option);
                      $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                      $listing[] = $fileBase . '.' . $extension;
                    }
                    $toolTip = $this->toolTipsService['participant-attachment-open-parent'].'<br>'.implode(', ', $listing);
                    $linkText = $subDir . '.'  . 'zip';
                    try {
                      $downloadLink = $this->userStorage->getDownloadLink($folderPath);
                      $html = '<a href="'.$downloadLink.'"
                             title="'.$toolTip.'"
                             class="download-link tooltip-auto">
  ' . $linkText . '
</a>';
                    } catch (\OCP\Files\NotFoundException $e) {
                      $this->logException($e);
                      $html = '<span class="error tooltip-auto" title="' . $folderPath . '">' . $this->l->t('The folder "%s" could not be found on the server.', $subDir) . '</span>';
                    }

                    $filesAppAnchor = $this->getFilesAppAnchor($field, $musician);

                    return $filesAppAnchor . $html;
                  }
                  return null;
                };
                $keyFdd['php|DV'] = function($value, $op, $k, $row, $recordId, $pme) use ($phpViewFunction) {
                  return $phpViewFunction($value, 'view', $k, $row, $recordId, $pme);
                };
                $keyFdd['php|LF'] = function($value, $op, $k, $row, $recordId, $pme) use ($phpViewFunction) {
                  return $phpViewFunction($value, 'list', $k, $row, $recordId, $pme);
                };
                $keyFdd['values2'] = $values2;
                $keyFdd['valueTitles'] = $valueTitles;
                $keyFdd['valueData'] = $valueData;
                $keyFdd['select'] = 'M';
                $keyFdd['css']['postfix'][] = $dataType;
                $keyFdd['css']['postfix'][] = 'data-type-' . $dataType;
                break;
              case FieldType::DB_FILE:
                $this->joinStructure[$tableName]['flags'] |= self::JOIN_READONLY;
                $keyFdd['php|ACP'] = function($value, $op, $k, $row, $recordId, $pme) use ($field) {
                  $optionKeys = Util::explode(self::VALUES_SEP, $row['qf'.($k+0)], Util::TRIM);
                  $optionValues = Util::explode(self::VALUES_SEP, $row['qf'.($k+1)], Util::TRIM);
                  $values = array_combine($optionKeys, $optionValues);
                  $fieldId = $field->getId();
                  $subDir = $field->getName();
                  list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);
                  /** @var Entities\ProjectParticipantFieldDataOption $option */
                  $html = '<div class="file-upload-wrapper">
  <table class="file-upload">';
                  /** @var Entities\ProjectParticipantFieldDataOption $option */
                  foreach ($field->getSelectableOptions() as $option) {
                    $optionKey = (string)$option->getKey();
                    $fileBase = $option->getLabel();
                    $html .= $this->dbFileUploadRowHtml($values[$optionKey] ?? null, $fieldId, $optionKey, $subDir, $fileBase, $musician);
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
                    if (!empty($values)) {
                      list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);
                      $fileBase = $field->getName();
                      $extension = 'zip';
                      $fileName = $this->projectService->participantFilename($fileBase, $musician) . '.' . $extension;

                      $downloadLink = $this->di(DatabaseStorageUtil::class)->getDownloadLink(
                        array_values($values), $fileName);
                      $filesAppAnchor = $this->getFilesAppAnchor($field, $musician);

                      return $filesAppAnchor . '<a class="download-link ajax-download tooltip-auto"
   title="'.$this->toolTipsService[self::$toolTipsPrefix . ':attachment:download'].'"
   href="'.$downloadLink.'">' . $fileBase . '.' . $extension . '</a>';
                    }
                  }
                  return null;
                };
                $keyFdd['values2'] = $values2;
                $keyFdd['valueTitles'] = $valueTitles;
                $keyFdd['valueData'] = $valueData;
                $keyFdd['select'] = 'M';
                $keyFdd['css']['postfix'][] = $dataType;
                $keyFdd['css']['postfix'][] = 'data-type-' . $dataType;
                break;
              case FieldType::DATE:
              case FieldType::DATETIME:
              case FieldType::RECEIVABLES:
              case FieldType::LIABILITIES:
                foreach ($dataOptions as $dataOption) {
                  $key = (string)$dataOption['key'];
                  $label = $dataOption['label'];
                  $data  = $dataOption['data'];
                  $values2[$key] = $this->allowedOptionLabel($label, $data, $dataType);
                }
                unset($keyFdd['mask']);
                $keyFdd['escape'] = false;

                if ($dataType == FieldType::RECEIVABLES || $dataType == FieldType::LIABILITIES) {

                  // yet another field to support summing up totals
                  if ($multiplicity == FieldMultiplicity::MULTIPLE) {
                    $optionValueSql = 'IF(
  $join_table.field_id = ' . $fieldId . '
  AND $join_table.deleted IS NULL
  AND ' . $this->joinTables[$optionsTableName] . '.key = $join_col_fqn,
  ' . $this->joinTables[$optionsTableName] . '.data,
  NULL
)';
                    $sql = 'CAST(COALESCE(GROUP_CONCAT(DISTINCT ' . $optionValueSql . '), 0) AS DECIMAL(7, 2))';
                  } else {
                    // comparatively difficult because of the many multi-valued joins.

                    $optionValueSql = 'IF(
  $join_table.field_id = ' . $fieldId . '
  AND $join_table.deleted IS NULL
  AND ' . $this->joinTables[$optionsTableName] . '.key = $join_col_fqn,
  CAST(' . $this->joinTables[$optionsTableName] . '.data AS DECIMAL(7, 2)),
  NULL
)';
                    $optionKeySql = 'IF(
  $join_table.field_id = ' . $fieldId . '
  AND $join_table.deleted IS NULL
  AND ' . $this->joinTables[$optionsTableName] . '.key = $join_col_fqn,
  $join_col_fqn,
  NULL
)';
                    $sql = 'CAST(COALESCE(SUM(' . $optionValueSql . ') * COUNT(DISTINCT '. $optionKeySql . ') / COUNT(' . $optionKeySql . '), 0) AS DECIMAL(7, 2))';
                  }

                  list($subTotalsIndex, $subTotalsName) = $this->makeJoinTableField(
                    $fieldDescData, $tableName, 'sub_totals_invoiced',
                    Util::arrayMergeRecursive(
                      $extraFddBase, [
                        'name' => $extraFddBase['name'] . ' (' . $this->l->t('sub-totals') . ')',
                        'input' => 'VSR' . (!$this->expertMode ? 'H' : ''),
                        'tab' => [ 'id' => 'tab-none' ], // move it away
                        'select' => 'T',
                        'align' => 'right',
                        'php' => fn($value) => $this->expertMode ? $this->moneyValue($value) : null,
                        'sql' => $subTotalsSign . $sql,
                        'values' => [
                          'column' => 'option_key',
                          'encode' => 'BIN2UUID(%s)',
                        ],
                      ])
                  );
                }

                // fall through
              default:
                $keyFdd['values2'] = $values2;
                $keyFdd['valueTitles'] = $valueTitles;
                $keyFdd['valueData'] = $valueData;
                $keyFdd['values2glue'] = '</span><br/><span>';
                $keyFdd['display|LF'] = [
                  'popup' => 'data',
                  'prefix' => '<div class="allowed-option-wrapper"><span>',
                  'postfix' => '</spans></div>',
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

            /*-********************************************************************
             *
             * Recurring auto-generated fields
             *
             */

            $generatorOption = $field->getManagementOption();
            $generatorClass = $generatorOption->getData();
            $generatorSlug = $generatorClass::slug();

            foreach ([&$keyFdd, &$valueFdd] as &$fdd) {
              $fdd['css']['postfix'][] = $multiplicity;
              $fdd['css']['postfix'][] = 'generated';
              $fdd['css']['postfix'][] = $dataType;
              $fdd['css']['postfix'][] = 'data-type-' . $dataType;
              $fdd['css']['postfix'][] = 'multiplicity-' . $multiplicity;
              $fdd['css']['postfix'][] = 'recurring-generator-' . $generatorSlug;
              $fdd['css']['postfix'][] = 'restrict-height';
              unset($fdd['mask']);
              $fdd['select'] = 'M';
              $fdd['values'] = array_merge(
                $fdd['values'], [
                  'column' => 'option_key',
                  'description' => [
                    'columns' => [
                      'IF($table.field_id = '.$fieldId.$deletedValueFilter.', BIN2UUID($table.option_key), NULL)',
                      '$table.option_value',
                    ],
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

            if ($dataType == FieldType::RECEIVABLES || $dataType == FieldType::LIABILITIES) {

              // yet another field to support summing up totals
              $optionValueSql = 'IF(
  $join_table.field_id = ' . $fieldId . '
  AND $join_table.deleted IS NULL
  AND ' . $this->joinTables[$optionsTableName] . '.key = $join_col_fqn
  AND $join_table.option_value IS NOT NULL
  AND $join_table.option_value <> "",
  CAST($join_table.option_value AS DECIMAL(7, 2)),
  NULL
)';
              // in contrast to Multiplicity::PARALLEL and ::MULTIPLE the
              // value varies from participant to participant and ist stored
              // in the data table.
              $optionKeySql = 'IF(
  $join_table.field_id = ' . $fieldId . '
  AND $join_table.deleted IS NULL
  AND ' . $this->joinTables[$optionsTableName] . '.key = $join_col_fqn
  AND $join_table.option_value IS NOT NULL
  AND $join_table.option_value <> "",
  $join_col_fqn,
  NULL
)';

              list($subTotalsIndex, $subTotalsName) = $this->makeJoinTableField(
                $fieldDescData, $tableName, 'sub_totals_invoiced',
                Util::arrayMergeRecursive(
                  $extraFddBase, [
                    'name' => $extraFddBase['name'] . ' (' . $this->l->t('sub-totals') . ')',
                    'input' => 'VSR' . (!$this->expertMode ? 'H' : ''),
                    'tab' => [ 'id' => 'tab-none' ], // move it away
                    'select' => 'T',
                    'align' => 'right',
                    'php' => fn($value) => $this->expertMode ? $this->moneyValue($value) : null,
                    'sql' => $subTotalsSign . 'CAST(COALESCE(SUM(' . $optionValueSql . ') * COUNT(DISTINCT '. $optionKeySql . ') / COUNT(' . $optionKeySql . '), 0) AS DECIMAL(7, 2))',
                    'values' => [
                      'column' => 'option_key',
                      'encode' => 'BIN2UUID(%s)',
                    ],
                  ])
              );
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
            if ($dataType != FieldType::DB_FILE) {
              $keyFdd['display|LF'] = [ 'popup' => 'data' ];
            }

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
      COALESCE($join_table.option_value, "")
    ),
    NULL
  )
  ORDER BY $order_by)
)';

            // Allow update of the label for recurring receivables.
            /* list($labelFddIndex, $labelFddName) = */$this->makeJoinTableField(
              $fieldDescData, $optionsTableName, 'label', [
                'name' => $this->l->t('Label for %s', $fieldName),
                'tab'  => $tab,
                'css'  => [ 'postfix' => [ 'participant-field-label', 'field-id-'.$fieldId, ] ],
                'select' => 'M',
                'input' => ' HR',
                'php' => fn() => '',
                'filter' => [
                  'having' => true,
                ],
                'sql' => 'TRIM(BOTH \',\' FROM GROUP_CONCAT(
  DISTINCT
  IF(
    $join_table.field_id = '.$fieldId.$deletedSqlFilter.',
    CONCAT_WS(
      \''.self::JOIN_KEY_SEP.'\',
      BIN2UUID($join_table.key),
      COALESCE($join_table.l10n_label, "")
    ),
    NULL
  )
  ORDER BY $order_by)
)',
                'values' => [
                  'grouped' => true,
                  'filters' => ('$table.field_id = '.$fieldId
                                .$deletedValueFilter),
                  'orderby' => '$table.key ASC',
                ],
              ],
            );
            // $labelFdd = &$fieldDescData[$labelFddName];

            // yet another field for the supporting documents
            list($absInvoiceFddIndex, $invoiceFddName) = $this->makeJoinTableField(
              $fieldDescData, $tableName, 'supporting_document_id', [
                'input' => 'SRH',
                'sql' => 'TRIM(BOTH \',\' FROM GROUP_CONCAT(
  DISTINCT
  IF(
    $join_table.field_id = '.$fieldId.$deletedSqlFilter.',
    CONCAT_WS(
      \''.self::JOIN_KEY_SEP.'\',
      BIN2UUID($join_table.option_key),
      COALESCE($join_col_fqn, "")
    ),
    NULL
  )
  ORDER BY $order_by)
)',
                'values' => [
                  'orderby' => '$table.created DESC, $table.option_key ASC',
                ],
              ]);
            $invoiceFddOffset = $absInvoiceFddIndex - $fddBaseIndex;

            $viewClosure = function(
              $value,
              $op,
              $k,
              $row,
              $recordId,
              $pme,
            ) use (
              $field,
              $dataType,
              $invoiceFddOffset,
            ) {
              $keyFddIndex = $k;
              $invoiceFddIndex = $keyFddIndex + $invoiceFddOffset;

              // LF are actually both the same. $value will always just
              // come from the filter's $value2 array. The actual values
              // we need are in the description fields which are passed
              // through the 'qf'.$k field in $row.
              $values = array_filter(Util::explodeIndexed($row['qf' . $k]));
              $options = self::fetchValueOptions($field, $values);

              $invoices = Util::explodeIndexed($row['qf' . $invoiceFddIndex]);
              list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);

              switch ($dataType) {
                case FieldType::DB_FILE:
                  // just model this like the FieldMultiplicity::PARALLEL stuff
                  if (!empty($values)) {
                    $fileBase = $this->participantFieldsService->getFileSystemFieldName($field);
                    $extension = 'zip';
                    $fileName = $this->projectService->participantFilename($fileBase, $musician, ignoreExtension: true) . '.' . $extension;
                    $downloadLink = $this->di(DatabaseStorageUtil::class)->getDownloadLink(
                      array_values($values), $fileName);
                    $filesAppAnchor = $this->getFilesAppAnchor($field, $musician);
                    return $filesAppAnchor
                      . '<a class="download-link ajax-download tooltip-auto"
   title="'.$this->toolTipsService[self::$toolTipsPrefix . ':attachment:download'].'"
   href="'.$downloadLink.'">' . $fileBase . '.' . $extension . '</a>';
                  }
                  return '';
                default:
                  $hasSupportingDocuments = false;
                  $html = [];
                  foreach ($options as $optionKey => $fieldOption) {
                    if (empty($fieldOption)) { // ??? how could this happen? Seems to be a legacy relict
                      $this->logError('Missing option entity for key ' . $optionKey);
                      continue;
                    }
                    $optionValue = $values[$optionKey];
                    $label = $fieldOption->getLabel()??'';
                    $rowHtml = $this->allowedOptionLabel($label, $optionValue, $dataType);

                    if (!empty($invoices[$optionKey])) {
                      $hasSupportingDocuments = true;
                      $fieldDatum = $this->makeFieldDatum($field, $musician, $fieldOption, $optionValue, $invoices[$optionKey]);
                      $fileInfo = $this->projectService->participantFileInfo($fieldDatum, includeDeleted: true);
                      $fileName = $fileInfo['fileName'];
                      $downloadLink = $this->urlGenerator()
                        ->linkToRoute($this->appName().'.downloads.get', [
                          'section' => 'database',
                          'object' => $invoices[$optionKey],
                        ])
                        . '?'
                        . http_build_query([
                          'fileName' => $fileName,
                          'requesttoken' => \OCP\Util::callRegister(),
                        ], '', '&');
                      $rowHtml = '<a class="download-link ajax-download tooltip-auto"
   title="'.$this->toolTipsService[self::$toolTipsPrefix . ':attachment:download'].'"
   href="'.$downloadLink.'">' . $rowHtml . '</a>';
                    }
                    $html[] = $rowHtml;
                  }

                  $html = '<div class="allowed-option-wrapper flex-grow">'.implode('<br/>', $html).'</div>';

                  if ($hasSupportingDocuments) {
                    $filesAppAnchor = $this->getFilesAppAnchor($field, $musician);
                    $html = '<div class="pme-cell-wrapper flex-container flex-center flex-justify-end">' . $filesAppAnchor . $html . '</div>';
                  }

                  return $html;
              }
            };


            $keyFdd['php|LF'] = fn($value, $op, $k, $row, $recordId, $pme) => $viewClosure($value, PHPMyEdit::OPERATION_LIST, $k, $row, $recordId, $pme);
            $keyFdd['php|VD'] = fn($value, $op, $k, $row, $recordId, $pme) => $viewClosure($value, PHPMyEdit::OPERATION_VIEW, $k, $row, $recordId, $pme);

            // $keyFdd has probably to be voided here as otherwise hidden
            // input fields are emitted which conflict with the $valueFdd
            $keyFdd['php|ACP'] = function($value, $op, $k, $row, $recordId, $pme) use ($field, $dataType) {
              return '';
            };
            $keyFdd['input|ACP'] = 'HR';
            $valueFdd['php|ACP'] = function(
              $value,
              $op,
              $k,
              $row,
              $recordId,
              $pme,
            ) use (
              $field,
              $dataType,
              $valueFddOffset,
              $invoiceFddOffset,
            ) {
              $keyFddIndex = $k - $valueFddOffset;
              $invoiceFddIndex = $keyFddIndex + $invoiceFddOffset;

              // $this->logInfo('VALUE '.$k.': '.$value);
              // $this->logInfo('ROW '.$k.': '.$row['qf'.$k]);
              // $this->logInfo('ROW IDX '.$k.': '.$row['qf'.$k.'_idx']);

              $fieldId = $field->getId();
              $multiplicity = FieldMultiplicity::RECURRING;

              $value = $row['qf'.$k];
              $values = Util::explodeIndexed($value);

              $options = [];
              $labelled = false;
              $options = self::fetchValueOptions($field, $values, $labelled);
              $invoices = Util::explodeIndexed($row['qf' . $invoiceFddIndex]);

              $generatorOption = $field->getManagementOption();
              $generatorClass = $generatorOption->getData();
              $generatorSlug = $generatorClass::slug();
              $noRecomputeButton = $generatorClass::operationLabels(IRecurringReceivablesGenerator::OPERATION_OPTION_REGENERATE) === false;
              $uiFlags = $generatorClass::uiFlags();

              // generate the per-option input rows


              list('musician' => $musician, ) = $this->musicianFromRow($row, $pme);
              $subDir = $this->participantFieldsService->getFileSystemFieldName($field);
              $rowHtml = '';
              $hasEmptyLabelledValues = false;
              $idx = 0;
              /** @var Entities\ProjectParticipantFieldDataOption $option */
              foreach ($options as $optionKey => $option) {
                $hasEmptyLabelledValues = $hasEmptyLabelledValues || (!empty($option->getLabel()) && empty($values[$optionKey]));
                switch ($dataType) {
                  case FieldType::DB_FILE:
                    $rowHtml .= $this->dbFileUploadRowHtml($values[$optionKey], $fieldId, $optionKey, $subDir, fileBase: null, musician: $musician);
                    break;
                  default:
                    $rowHtml .= $this->recurringChangeRowHtml($values[$optionKey], $field, $option, $invoices, $idx++, musician: $musician, uiFlags: $uiFlags);
                    break;
                }
              }

              // generate the table header

              $valueLabel = $this->l->t('Value');
              $invoiceLabel = $this->l->t('Documents');
              switch ($dataType) {
                case FieldType::RECEIVABLES:
                case FieldType::LIABILITIES:
                  $valueLabel = $this->l->t('Value [%s]', $this->currencySymbol());
                  $invoiceLabel = $this->l->t('Invoice');
                  break;
                case FieldType::DATE:
                  $valueLabel = $this->l->t('Date');
                  break;
                case FieldType::DATETIME:
                  $valueLabel = $this->l->t('Date/Time');
                  break;
                case FieldType::DB_FILE:
                  // @todo: insert file-controls below instead of manual data input
                  $valueLabel = $this->l->t('File');
                  break;
              }

              $cssClass = [
                'row-count-' .count($values),
                'multiplicity-' . $multiplicity,
                'data-type-' . $dataType,
                'recurring-generator-' . $generatorSlug,
                'recurring-option-recompute-button-' . ($noRecomputeButton ? 'dis' : 'en') . 'able',
              ];
              if ($uiFlags & IRecurringReceivablesGenerator::UI_EDITABLE_LABEL) {
                $cssClass[] = 'show-empty-labels';
              }
              $cssClass[] = 'hide-empty-values';
              if ($hasEmptyLabelledValues) {
                $cssClass[] = 'has-empty-labelled-values';
              }

              $html = '<table class="'.implode(' ', $cssClass).'">
  <thead>
    <tr>
      <th class="operations"><span class="column-heading">' . $this->l->t('Actions') . '</span></th>
      <th class="label"><span class="column-heading">'.$this->l->t('Subject').'</span></th>
      <th class="input"><span class="column-heading">'.$valueLabel.'</span></th>
      <th class="documents"><span class="column-heading">'.$invoiceLabel.'</</th>
    </tr>
  </thead>
  <tbody>';

              // add the rows
              $html .= $rowHtml;

              // generate the footer
              $recomputeLabel = $generatorClass::operationLabels(IRecurringReceivablesGenerator::OPERATION_OPTION_REGENERATE_ALL);
              if (is_callable($recomputeLabel)) {
                $recomputeLabel = $recomputeLabel($dataType);
              }
              $recomputeLabel = $this->l->t($recomputeLabel);

              $html .= (new TemplateResponse(
                $this->appName(),
                'fragments/participant-fields/recurring-receivable-generator-row', [
                  'field' => $field,
                  'uiFlags' => $uiFlags,
                  'generatorSlug' => $generatorSlug,
                  'recomputeLabel' => $recomputeLabel,
                  'updateStrategyChoices' => $generatorClass::updateStrategyChoices(),
                  'toolTips' => $this->toolTipsService,
                  'toolTipsPrefix' => self::$toolTipsPrefix,
                ],
                'blank'
              ))->render();

              $html .= '
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
            /*-********************************************************************
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
            list($groupMemberFddIndex, $groupMemberFddName) = $this->makeJoinTableField(
              $fieldDescData, $tableName, 'musician_id', $keyFdd);
            $groupMemberFddOffset = $groupMemberFddIndex - $fddBaseIndex;

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
            $dataOptionsData = json_encode(array_column($dataOptionsData, 'data', 'key'));

            // new field, member selection
            $groupMemberFdd = &$fieldDescData[$groupMemberFddName];
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

            if ($dataType == FieldType::RECEIVABLES || $dataType == FieldType::LIABILITIES) {
              $groupMemberFdd['css']['postfix'][] = 'money';
              $groupMemberFdd['css']['postfix'][] = $dataType;
              $fieldData = $generatorOption['data'];
              $money = $this->moneyValue($fieldData);
              $groupMemberFdd['display|LFVD'] = array_merge(
                $groupMemberFdd['display'],
                [
                  'prefix' => '<span class="allowed-option money group service-fee"><span class="allowed-option-name money clip-long-text group">',
                  'postfix' => function($op, $pos, $k, $row, $pme) use ($money, $groupMemberFddOffset) {
                    $keyFddIndex = $k - $groupMemberFddOffset;
                    $selectedKey = $row['qf'.$keyFddIndex];
                    $active = empty($selectedKey) ? '' : ' selected';
                    return '</span><span class="allowed-option-separator money">&nbsp;</span>'
                      .'<span class="allowed-option-value money">'.$money.'</span></span>';
                  },
                ]);
              $groupMemberFdd['display|ACP'] = array_merge(
                $groupMemberFdd['display'],
                [
                  'prefix' => '<label class="'.implode(' ', $css).'">',
                  'postfix' => function($op, $pos, $k, $row, $pme) use ($fieldData, $dataType, $groupMemberFddOffset) {
                    $keyFddIndex = $k - $groupMemberFddOffset;
                    $selectedKey = $row['qf'.$keyFddIndex];
                    $active = ($op == 'display' && empty($selectedKey)) ? '' : 'selected';
                    return $this->allowedOptionLabel('', $fieldData, $dataType, $active)
                      .'</label>';
                  },
                ]);

              // yet another field to support summing up totals
              list($subTotalsIndex, $subTotalsName) = $this->makeJoinTableField(
                $fieldDescData, $tableName, 'sub_totals_invoiced',
                Util::arrayMergeRecursive(
                  $extraFddBase, [
                    'name' => $extraFddBase['name'] . ' (' . $this->l->t('sub-totals') . ')',
                    'tab' => [ 'id' => 'tab-none' ], // move it away
                    'input' => 'VSR' . (!$this->expertMode ? 'H' : ''),
                    'select' => 'T',
                    'sql' => $subTotalsSign . 'CAST(
  COALESCE(
    IF(
      GROUP_CONCAT(
        DISTINCT
        IF($join_table.field_id = '. $fieldId . ' AND $join_table.deleted IS NULL, $join_col_fqn, NULL)
        SEPARATOR ""
      ) IS NULL,
      0,
      ' . $fieldData . '
    ),
    0
  ) AS DECIMAL(7,2))',
                    'php' => fn($value) => $this->expertMode ? $this->moneyValue($value) : null,
                    'align' => 'right',
                    'values' => [
                      'column' => 'option_key',
                    ],
                  ])
              );
            }

            // in filter mode mask out all non-group-members
            $groupMemberFdd['values|LF'] = array_merge(
              $groupMemberFdd['values'],
              [ 'filters' => '$table.group_id IS NOT NULL' ]);

            break;
          case FieldMultiplicity::GROUPSOFPEOPLE:
            /*-********************************************************************
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
            foreach ($dataOptions as $dataOption) {
              $valueGroups[--$idx] = $dataOption['label'];
              $data = $dataOption['data'];
              if ($dataType == FieldType::RECEIVABLES || $dataType == FieldType::LIABILITIES) {
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
            if ($dataType == FieldType::RECEIVABLES || $dataType == FieldType::LIABILITIES) {
              $css[] = ' money '.$dataType;
              foreach ($groupValues2 as $key => $value) {
                $groupValues2[$key] = $this->allowedOptionLabel(
                  $value, $groupValueData[$key], $dataType, 'group');
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
            list($groupMemberFddIndex, $groupMemberFddName) = $this->makeJoinTableField(
              $fieldDescData, $tableName, 'musician_id', $fddBase);
            $groupMemberFddOffset = $groupMemberFddIndex - $fddBaseIndex;

            // compute group limits per group
            $dataOptionsData = $dataOptions->map(function($value) {
              return [
                'key' => (string)$value['key'],
                'data' =>  [ 'limit' => $value['limit'], ],
              ];
            })->getValues();
            $dataOptionsData = json_encode(array_column($dataOptionsData, 'data', 'key'));

            // new field, member selection
            $groupMemberFdd = &$fieldDescData[$groupMemberFddName];
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
                  'prefix' => function($op, $pos, $k, $row, $pme) use ($css) {
                    return '<label class="'.implode(' ', $css).'">';
                  },
                  'postfix' => function($op, $pos, $k, $row, $pme) use ($dataOptions, $dataType, $groupMemberFddOffset) {
                    $keyFddIndex = $k - $groupMemberFddOffset;
                    $selectedKey = $row['qf'.$keyFddIndex];
                    $html = '';
                    foreach ($dataOptions as $dataOption) {
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

            if ($dataType == FieldType::RECEIVABLES || $dataType == FieldType::LIABILITIES) {
              // yet another field to support summing up totals
              $optionValueSql = 'IF(
  $join_table.field_id = ' . $fieldId . '
  AND $join_table.deleted IS NULL
  AND ' . $this->joinTables[$optionsTableName] . '.key = $join_col_fqn,
  ' . $this->joinTables[$optionsTableName] . '.data,
  NULL
)';
              list($subTotalsIndex, $subTotalsName) = $this->makeJoinTableField(
                $fieldDescData, $tableName, 'sub_totals_invoiced',
                Util::arrayMergeRecursive(
                  $extraFddBase, [
                    'name' => $extraFddBase['name'] . ' (' . $this->l->t('sub-totals') . ')',
                    'input' => 'VSR' . (!$this->expertMode ? 'H' : ''),
                    'tab' => [ 'id' => 'tab-none' ], // move it away
                    'select' => 'T',
                    'align' => 'right',
                    'php' => fn($value) => $this->expertMode ? $this->moneyValue($value) : null,
                    'sql' => $subTotalsSign . 'CAST(COALESCE(GROUP_CONCAT(DISTINCT ' . $optionValueSql . '), 0) AS DECIMAL(7, 2))',
                    'values' => [
                      'column' => 'option_key',
                      'encode' => 'BIN2UUID(%s)',
                    ],
                  ])
              );
            }

            break;
        }

        if ($dataType == FieldType::RECEIVABLES || $dataType == FieldType::LIABILITIES) {
          $sql = $fieldDescData[$subTotalsName]['sql'];
          $sql = $this->substituteSQLFragment($fieldDescData, $subTotalsName, $sql, $subTotalsIndex);
          $subTotals[$subTotalsName] = $sql;
          // $this->logInfo('SUB TOTALS SQL ' . print_r($subTotals[$subTotalsName], true));
        }

      } // foreach ($participantFields ...)
    };

    return [ $joinStructure, $generator ];
  }

  /**
   * Create a label for a multi-value option (multiple, parallel, recurring).
   *
   * @param string $label Label of the option.
   *
   * @param mixed $value Value of the per-participant data.
   *
   * @param string $dataType String-value of the FieldType.
   *
   * @param null|string $css Optional css-class.
   *
   * @param array $data Optional data for data-attributes of the container-span/div.
   *
   * @return string The html-string for rendering the option value.
   */
  protected function allowedOptionLabel(string $label, mixed $value, string $dataType, ?string $css = null, array $data = []):string
  {
    $label = Util::htmlEscape($label);
    $css = $dataType . (empty($css) ? '' : ' ' . $css);
    $innerCss = $dataType;
    $htmlData = [];
    foreach ($data as $key => $dataValue) {
      $htmlData[] = "data-".$key."='".$dataValue."'";
    }
    $htmlData = implode(' ', $htmlData);
    if (!empty($htmlData)) {
      $htmlData = ' '.$htmlData;
    }
    switch ($dataType) {
      case FieldType::RECEIVABLES:
      case FieldType::LIABILITIES:
        $value = $this->moneyValue($value);
        $innerCss .= ' money';
        $css .= ' money';
        break;
      case FieldType::DATE:
      case FieldType::DATETIME:
        if (!empty($value)) {
          try {
            $date = DateTime::parse($value, $this->getDateTimeZone());
            $value = ($dataType == FieldType::DATE)
              ? $this->dateTimeFormatter()->formatDate($date, 'medium')
              : $this->dateTimeFormatter()->formatDateTime($date, 'medium', 'short');
          } catch (\Throwable $t) {
            // don't care
          }
        }
        // fallthrough
      default:
        $value = Util::htmlEscape($value);
        break;
    }
    $label = '<span class="allowed-option-name '.$innerCss.'">'.$label.'</span>';
    $sep   = '<span class="allowed-option-separator '.$innerCss.'">&nbsp;</span>';
    $value = '<span class="allowed-option-value '.$innerCss.'">'.$value.'</span>';
    return '<span class="allowed-option '.$css.'"'.$htmlData.'>'.$label.$sep.$value.'</span>';
  }

  /**
   * Generate one row for Multiplicity::RECURRING in Change/Add/Paste mode.
   *
   * @param string $optionValue Value of the option.
   *
   * @param Entities\ProjectParticipantField $field The respective field.
   *
   * @param Entities\ProjectParticipantFieldDataOption $fieldOption The associated field-option.
   *
   * @param array $invoices Potential supporting documents.
   *
   * @param int $optionIdx Consecutive index of the active options.
   *
   * @param Entities\Musician $musician
   *
   * @param int $uiFlags
   *
   * @return string HTML fragment.
   */
  protected function recurringChangeRowHtml(
    ?string $optionValue,
    Entities\ProjectParticipantField $field,
    Entities\ProjectParticipantFieldDataOption $fieldOption,
    array $invoices,
    int $optionIdx,
    Entities\Musician $musician,
    int $uiFlags,
  ):string {
    $fieldId = $field->getId();
    $dataType = $field->getDataType();
    $optionKey = (string)$fieldOption->getKey();

    $lockCssClass = [
      'pme-input',
      'pme-input-lock',
      'lock-unlock',
      'left-of-input',
    ];
    $lockCssClass = implode(' ', $lockCssClass);

    $lockRightCssClass = $lockCssClass . ' position-right';
    if ($dataType != FieldType::RECEIVABLES && $dataType != FieldType::LIABILITIES) {
      $lockCssClass = $lockRightCssClass;
    }

    if (!empty($optionValue)) {
      switch ($dataType) {
        case FieldType::DATE:
        case FieldType::DATETIME:
          try {
            $date = DateTime::parse($optionValue, $this->getDateTimeZone());
            $optionValue = ($dataType == FieldType::DATE)
              ? $this->dateTimeFormatter()->formatDate($date, 'medium')
              : $this->dateTimeFormatter()->formatDateTime($date, 'medium', 'short');
          } catch (\Throwable $t) {
            // ignore for now
          }
          break;
        case FieldType::DB_FILE:
          break;
      }
    }

    $fileName = null;
    if (!empty($invoices[$optionKey])) {
      $fieldDatum = $this->makeFieldDatum($field, $musician, $fieldOption, $optionValue, $invoices[$optionKey]);
      $fileInfo = $this->projectService->participantFileInfo($fieldDatum, includeDeleted: true);
      $fileName = $fileInfo['fileName'];
      $downloadLink = $this->urlGenerator()
        ->linkToRoute($this->appName().'.downloads.get', [
          'section' => 'database',
          'object' => $invoices[$optionKey],
        ])
        . '?'
        . http_build_query([
          'fileName' => $fileName,
          'requesttoken' => \OCP\Util::callRegister(),
        ], '', '&');
    }

    $optionLabelName = $this->pme->cgiDataName(self::joinTableFieldName(self::participantFieldOptionsTableName($fieldId), 'label'));
    $optionValueName = $this->pme->cgiDataName(self::participantFieldValueFieldName($fieldId));
    $optionKeyName = $this->pme->cgiDataName(self::participantFieldKeyFieldName($fieldId));

    $pathChain = [
      'participantFolder' => $this->projectService->ensureParticipantFolder($this->project, $musician, dry: true),
      'documentsFolders' => $this->getDocumentsFolderName(),
      'supportingDocumentsFolder' => $this->getSupportingDocumentsFolderName(),
      'receivablesFolder' => $this->getReceivablesFolderName(),
      'fieldFolder' => $this->participantFieldsService->getFileSystemFieldName($field),
    ];
    $participantFolder = $pathChain['participantFolder'];

    $filesAppPath = implode(UserStorage::PATH_SEP, $pathChain);
    while (!empty($pathChain)) {
      $path = implode(UserStorage::PATH_SEP, $pathChain);
      try {
        $filesAppLink = $this->userStorage->getFilesAppLink($path, true);
        break;
      } catch (\OCP\Files\NotFoundException $e) {
        $this->logDebug('No file found for ' . $filesAppPath);
        array_pop($pathChain);
      }
    }

    return (new TemplateResponse(
      $this->appName(),
      'fragments/participant-fields/recurring-receivable-change-row', [
        'uiFlags' => $uiFlags,
        'field' => $field,
        'fieldOption' => $fieldOption,
        'optionValue' => $optionValue,
        'optionLabelName' => $optionLabelName,
        'optionKeyName' => $optionKeyName,
        'optionValueName' => $optionValueName,
        'optionIdx' => $optionIdx,
        'fileName' => null,
        'fileBase' => null,
        'filesAppPath' => $filesAppPath,
        'filesAppLink' => $filesAppLink ?? null,
        'downloadLink' => $downloadLink ?? null,
        'participantFolder' => $participantFolder,
        'toolTips' => $this->toolTipsService,
        'toolTipsPrefix' => self::$toolTipsPrefix,
      ],
      'blank'
    ))->render();
  }

  /**
   * Tweak the submitted data for the somewhat complicate "participant
   * fields" -- i.e. the personal data collected for the project
   * participants -- into a form understood by
   * beforeUpdataDoUpdateAll() and beforeInsertDoInsertAll().
   *
   * @param PHPMyEdit $pme The phpMyEdit instance.
   *
   * @param string $op The operation, 'insert', 'update' etc.
   *
   * @param string $step 'before' or 'after'.
   *
   * @param array $oldValues Self-explanatory.
   *
   * @param array $changed Set of changed fields, may be modified by the callback.
   *
   * @param null|array $newValues Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated
   */
  public function beforeUpdateSanitizeParticipantFields(PHPMyEdit &$pme, string $op, string $step, array &$oldValues, array &$changed, array &$newValues):bool
  {
    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'before');

    /** @var Entities\ProjectParticipantField $participantField */
    foreach ($this->project['participantFields'] as $participantField) {
      $fieldId = $participantField['id'];
      $multiplicity = $participantField['multiplicity'];
      $dataType = $participantField['dataType'];

      $tableName = self::participantFieldTableName($fieldId);

      $keyName = $this->joinTableFieldName($tableName, 'option_key');
      $valueName = $this->joinTableFieldName($tableName, 'option_value');
      $depositName = $this->joinTableFieldName($tableName, 'deposit');
      $groupFieldName = $this->joinTableFieldName($tableName, 'musician_id');

      $optionTableName = self::participantFieldOptionsTableName($fieldId);
      $optionLabelName = $this->joinTableFieldName($optionTableName, 'label');
      $optionLabelKeyName = self::joinTableFieldName(self::participantFieldOptionsTableName($fieldId), 'key');

      $supportingDocumentName = $this->joinTableFieldName($tableName, 'supporting_document_id');

      // label may only be tweaked for recurring multiplicity
      if ($multiplicity != FieldMultiplicity::RECURRING) {
        foreach ([$optionLabelName, $optionLabelKeyName] as $key) {
          unset($newValues[$key]);
          unset($oldValues[$key]);
          Util::unsetValue($changed, $key);
        }
      }

      // supporting documents are always handled immediately after upload
      $key = $supportingDocumentName;
      unset($newValues[$key]);
      unset($oldValues[$key]);
      Util::unsetValue($changed, $key);

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
            throw new RuntimeException(
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
            case FieldType::DATE:
              if (!empty($newValues[$valueName])) {
                $date = DateTime::parseFromLocale($newValues[$valueName], $this->getLocale(), 'UTC');
                $newValues[$valueName] = $date->format('Y-m-d');
              }
              break;
            case FieldType::DATETIME:
              if (!empty($newValues[$valueName])) {
                $date = DateTime::parseFromLocale($newValues[$valueName], $this->getLocale(), $this->getDateTimeZone());
                $newValues[$valueName] = $date->setTimezone('UTC')->toIso8601String();
              }
              break;
            default:
              break;
          }
          // tweak the option_key value
          $newValues[$keyName] = $key;
          $changed[] = $keyName;

          // Tweak the option value to have the desired form. Make sure to
          // escape commas as these count as multi-value separators.
          $newValues[$valueName] = $key . self::JOIN_KEY_SEP .  Util::escapeDelimiter($newValues[$valueName], PMETableViewBase::VALUES_SEP);
          if (isset($newValues[$depositName])) {
            $newValues[$depositName] = $key . self::JOIN_KEY_SEP . Util::escapeDelimiter($newValues[$depositName], PMETableViewBase::VALUES_SEP);
          }
          break;
        case FieldMultiplicity::RECURRING:
          if (array_search($valueName, $changed) === false
              && array_search($keyName, $changed) === false) {
            continue 2;
          }

          if ($dataType == FieldType::DB_FILE) {
            // these are handled immediately during upload and have to be
            // removed from the change-sets.
            foreach ([$keyName, $valueName, $optionLabelName, $optionLabelKeyName] as $key) {
              unset($newValues[$key]);
              unset($oldValues[$key]);
              Util::unsetValue($changed, $key);
            }
            break;
          }

          // just convert to KEY:VALUE notation for the following trigger functions
          // $oldValues ATM already has this format
          foreach ([&$newValues] as &$dataSet) {
            $keys = Util::explode(',', $dataSet[$keyName]);
            $values = Util::explode(',', $dataSet[$valueName], flags: Util::ESCAPED);
            $keyedValues = [];
            if (count($keys) != count($values)) {
              $this->logError('MISMATCH ' . $keyName . ': ' . print_r($keys, true) . ' vs ' . print_r($values, true));
            }

            foreach (array_combine($keys, $values) as $key => $value) {
              switch ($dataType) {
                case FieldType::DATE:
                  $date = DateTime::parseFromLocale($value, $this->getLocale(), 'UTC');
                  $value = $date->format('Y-m-d');
                  break;
                case FieldType::DATETIME:
                  $date = DateTime::parseFromLocale($value, $this->getLocale(), $this->getDateTimeZone());
                  $value = $date->setTimezone('UTC')->toIso8601String();
                  break;
              }
              $keyedValues[] = $key . self::JOIN_KEY_SEP . $value;
            }
            $dataSet[$valueName] = implode(',', $keyedValues);

            $labels = Util::explode(',', $dataSet[$optionLabelName], flags: Util::ESCAPED);
            $keyedLabels = [];
            foreach (array_combine($keys, $labels) as $key => $label) {
              $keyedLabels[] = $key . self::JOIN_KEY_SEP . $label;
            }
            $dataSet[$optionLabelName] = implode(',', $keyedLabels);
          }

          // mark both as changed
          foreach ([$keyName, $valueName, $optionLabelName] as $fieldName) {
            Util::unsetValue($changed, $fieldName);
            if ($oldValues[$fieldName] != $newValues[$fieldName]) {
              $changed[] = $fieldName;
            }
          }
          if (array_search($optionLabelName, $changed)) {
            // make sure the key value is also there
            $oldValues[$optionLabelKeyName] = $newValues[$optionLabelKeyName] = $newValues[$keyName];
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

          // $oldGroupId = $oldValues[$keyName];
          $newGroupId = $newValues[$keyName];

          // $this->logInfo('NEW GROUP ID ' . $newGroupId);

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
            // $this->logInfo('OPTION: ' . $newGroupId . ' ' . Functions\dump($newDataOption));
          }

          // $oldMembers = Util::explode(',', $oldValues[$groupFieldName]);
          $newMembers = Util::explode(',', $newValues[$groupFieldName]);

          if (count($newMembers) > $max) {
            throw new Exception(
              $this->l->t(
                'Number %d of requested participants for group %s is larger than the number %d of allowed participants.',
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
          break;
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
   *
   * @param PHPMyEdit $pme The phpMyEdit instance.
   *
   * @param string $op The operation, 'insert', 'update' etc.
   *
   * @param string $step 'before' or 'after'.
   *
   * @param array $oldValues Self-explanatory.
   *
   * @param array $changed Set of changed fields, may be modified by the callback.
   *
   * @param null|array $newValues Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated
   */
  public function cleanupParticipantFields(PHPMyEdit &$pme, string $op, string $step, array &$oldValues, array &$changed, array &$newValues):bool
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

  /**
   * Given a value array indexed by option key fetch the corresponding options
   * sorted in a consistent way, first by option label, option data,
   * data-value.
   *
   * @param Entities\ProjectParticipantField $field
   *
   * @param array $values
   * ```[ OPTOIN_KEY => VALUE, ... ]```
   *
   * @param null|bool $labelled
   *
   * @return array<int, Entities\ProjectParticipantFieldDataOption>
   */
  private static function fetchValueOptions(Entities\ProjectParticipantField $field, array $values, ?bool &$labelled = null)
  {
    $options = [];
    $labelled = false;
    foreach (array_keys($values) as $key) {
      $options[$key] = $field->getDataOption($key);
      if (empty($options[$key])) {
        \OCP\Util::writeLog('cafevdb', 'KEY ' . (string)$key . ' / ' . $values[$key] . ' / ' . $field->getId() . ' / ' . print_r($values, true), \OCP\Util::INFO);
      }
      if (!empty($options[$key]->getLabel())) {
        $labelled = true;
      }
    }

    // sort by option label, then option data, then value
    uasort($options, function($a, $b) use ($values) {
      /** @var Entities\ProjectParticipantFieldDataOption $a */
      /** @var Entities\ProjectParticipantFieldDataOption $b */
      $cmp = Functions\strCmpEmptyLast($a->getLabel(), $b->getLabel());
      if ($cmp === 0) {
        $cmp = Functions\strCmpEmptyLast($a->getData(), $b->getData());
      }
      if ($cmp === 0) {
        $cmp = Functions\strCmpEmptyLast($values[(string)$a->getKey()], $values[(string)$b->getKey()]);
      }
      return $cmp;
    });
    return $options;
  }

  /**
   * Make a "fake" field-datum from the legacy provided data:
   *
   * @param Entities\ProjectParticipantField $field
   *
   * @param Entities\Musician $musician
   *
   * @param mixed $fieldOption
   *
   * @param null|string $optionValue
   *
   * @param null|int|Entities\EncryptedFile $supportingDocument
   *
   * @return Entities\ProjectParticipantFieldDatum
   */
  private function makeFieldDatum(
    Entities\ProjectParticipantField $field,
    Entities\Musician $musician,
    mixed $fieldOption,
    ?string $optionValue,
    mixed $supportingDocument = null,
  ):Entities\ProjectParticipantFieldDatum {
    /** @var Entities\ProjectParticipantFieldDataOption $fieldOption */
    if (!($fieldOption instanceof Entities\ProjectParticipantFieldDataOption)) {
      $optionKey = $fieldOption;
      $fieldOption = $field->getDataOption($optionKey);
    } else {
      $optionKey = $fieldOption->getKey();
    }
    if (!empty($supportingDocument) && !($supportingDocument instanceof Entities\DatabaseStorageFile)) {
      try {
        $supportingDocument = $this->findEntity(Entities\DatabaseStorageFile::class, $supportingDocument);
      } catch (\Throwable $t) {
        $supportingDocument = null;
      }
    }
    $fieldDatum = (new Entities\ProjectParticipantFieldDatum)
      ->setField($field)
      ->setMusician($musician)
      ->setDataOption($fieldOption)
      ->setOptionKey($optionKey)
      ->setOptionValue($optionValue)
      ->setSupportingDocument($supportingDocument);

    return $fieldDatum;
  }
}
