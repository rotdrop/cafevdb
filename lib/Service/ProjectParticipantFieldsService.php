<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020-2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service;

use Exception;
use InvalidArgumentException;
use RuntimeException;

use OCP\Files as CloudFiles;

use OCA\CAFEVDB\Common;
use OCA\CAFEVDB\Common\DecimalRationalMonetary as MonetaryNumberType;
use OCA\CAFEVDB\Common\RationalNumber;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumAccessPermission as AccessPermission;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldDataType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipationContext as ParticipationContext;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service\Finance\ReceivablesGeneratorFactory;
use OCA\CAFEVDB\Storage\Database\Factory as DatabaseStorageFactory;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections;

/**
 * General support service, kind of inconsequent glue between
 * Doctrine\ORM and CAFEVDB\PageRenderer.
 */
class ProjectParticipantFieldsService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** Matrix of unsupported data-types. */
  private const UNSUPPORTED = [
    FieldMultiplicity::SIMPLE->value => [
      FieldDataType::BOOLEAN,
    ],
    FieldMultiplicity::SINGLE->value => [
      FieldDataType::CLOUD_FILE,
      FieldDataType::DB_FILE,
      FieldDataType::CLOUD_FOLDER,
    ],
    FieldMultiplicity::MULTIPLE->value => [
      FieldDataType::BOOLEAN,
      FieldDataType::CLOUD_FILE,
      FieldDataType::DB_FILE,
      FieldDataType::CLOUD_FOLDER,
    ],
    FieldMultiplicity::PARALLEL->value => [
      FieldDataType::BOOLEAN,
      FieldDataType::CLOUD_FOLDER,
    ],
    FieldMultiplicity::RECURRING->value => [
      FieldDataType::BOOLEAN,
      // FieldDataType::TEXT,
      FieldDataType::HTML,
      FieldDataType::INTEGER,
      FieldDataType::FLOAT,
      // FieldDataType::DATE,
      // FieldDataType::DATETIME,
      FieldDataType::CLOUD_FILE,
      FieldDataType::CLOUD_FOLDER,
    ],
    FieldMultiplicity::GROUPOFPEOPLE->value => [
      FieldDataType::BOOLEAN,
      FieldDataType::CLOUD_FILE,
      FieldDataType::DB_FILE,
      FieldDataType::CLOUD_FOLDER,
    ],
    FieldMultiplicity::GROUPSOFPEOPLE->value => [
      FieldDataType::BOOLEAN,
      FieldDataType::CLOUD_FILE,
      FieldDataType::DB_FILE,
      FieldDataType::CLOUD_FOLDER,
    ],
  ];

  private const ALLOWED_TRANSITIONS = [
    FieldMultiplicity::SIMPLE->value => [],
    FieldMultiplicity::SINGLE->value => [
      FieldMultiplicity::SIMPLE,
      FieldMultiplicity::MULTIPLE,
      FieldMultiplicity::PARALLEL,
    ],
    FieldMultiplicity::MULTIPLE->value => [
      FieldMultiplicity::SIMPLE,
      FieldMultiplicity::PARALLEL,
    ],
    FieldMultiplicity::PARALLEL->value => [
      FieldMultiplicity::SIMPLE,
    ],
    FieldMultiplicity::RECURRING->value => [
      // we could allow to change to PARALLEL, too.
      FieldMultiplicity::SIMPLE,
    ],
    FieldMultiplicity::GROUPOFPEOPLE->value => [],
    FieldMultiplicity::GROUPSOFPEOPLE->value => [],
  ];

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected ConfigService $configService,
    protected EntityManager $entityManager,
  ) {
    $this->l = $configService->getL10n();
  }
  // phpcs:enable

  /**
   * Just return a flat array with the class names of the implemented
   * recurring receivables generators implementing
   * OCA\CAFEVDB\Service\Finance\IRecurringReceivablesGenerator
   *
   * @return array
   */
  public function recurringReceivablesGenerators()
  {
    $generatorsFactory = $this->di(ReceivablesGeneratorFactory::class);
    return $generatorsFactory->findGenerators();
  }

  /**
   * @param string $value Receivables generator class name or short tag.
   *
   * @return string The full class-name of the generator.
   */
  public function resolveReceivableGenerator(string $value):string
  {
    $generators = $this->recurringReceivablesGenerators();
    if (!empty($generators[$value])) {
      $value = $generators[$value];
    } else {
      foreach ($generators as $key => $generator) {
        if (strtolower($value) == strtolower((string)$this->l->t($key))) {
          $value = $generator;
          break;
        }
      }
    }
    if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*(\\\\[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)*$/', $value)) {
      throw new RuntimeException($this->l->t('Generator "%s" does not appear to be valid PHP class name.', $value));
    }
    $this->di($value); // try to actually find it

    return $value; // return resolved class-name if we made it until here
  }

  /**
   * Fetch all monetary fields for the given project.
   *
   * @param Entities\Project $project
   *
   * @param string|Types\EnumParticipationContext $participationContext
   *
   * @return iterable
   */
  public function monetaryFields(
    Entities\Project $project,
    string|ParticipationContext $participationContext = ParticipationContext::UNRESTRICTED,
  ):iterable {
    // "matching" cannot work as our quasi-enums are objects which are not
    // singletons. "matching" uses === which only yields true for objects if
    // their refer to the same instance.
    return $project->getParticipantFields($participationContext)->filter(function($field) {
      switch ($field->getDataType()) {
        case FieldDataType::RECEIVABLES:
        case FieldDataType::LIABILITIES:
          return true;
        default:
          return false;
      }
    });
  }

  /**
   * Fetch all generator fields for the given project.
   *
   * @param Entities\Project $project
   *
   * @return iterable
   */
  public function generatedFields(Entities\Project $project):iterable
  {
    // return $project->getParticipantFields()->matching(DBUtil::criteriaWhere([
    //   'multiplicity' => FieldMultiplicity::RECURRING
    // ]));
    return $project->getParticipantFields()->filter(function($field) {
      return $field->getMultiplicity() == FieldMultiplicity::RECURRING;
    });
  }

  /**
   * Generate a drop-down select for service-fees to be used when
   * generating debit notes. Only recurring fees are split into single
   * items (we think hear of recurring fees on a yearly basis), which
   * all other sub-options can only be charged together.
   *
   * @param Entities\Project $project
   *
   * @param bool $distinct Split multi-select options into grouped
   * parts. Note that options with FieldMultiplicity::RECURRING are always
   * split.
   *
   * @return array
   */
  public function monetarySelectOptions(Entities\Project $project, bool $distinct = false):array
  {
    $nonRecurringGroup = $this->l->t('One-time Receivables');
    $selectOptions = [];
    /** @var Entities\ProjectParticipantField $field */
    foreach ($this->monetaryFields($project) as $field) {
      switch ($field->getMultiplicity()) {
        case FieldMultiplicity::SIMPLE:
        case FieldMultiplicity::SINGLE:
          // always only a single option
          $selectOptions[] = [
            'group' => $nonRecurringGroup,
            'name' => $field->getName(),
            'value' => $field->getId(), // list of keys?
            'data' => [], // relevant data from field definition
          ];
          break;
        case FieldMultiplicity::MULTIPLE:
        case FieldMultiplicity::GROUPOFPEOPLE:
        case FieldMultiplicity::GROUPSOFPEOPLE:
        case FieldMultiplicity::PARALLEL:
          if (!$distinct) {
            // only a single option
            $selectOptions[] = [
              'group' => $nonRecurringGroup,
              'name' => $field->getName(),
              'value' => $field->getId(), // list of keys?
              'data' => [], // relevant data from field definition
            ];
          } else {
            // option groups with multiple options
            $group = sprintf('%s "%s"', $this->l->t($field->getMultiplicity()->getValue()), $field->getName());
            /** @var Entities\ProjectParticipantFieldDataOption $option */
            foreach ($field->getSelectableOptions() as $option) {
              $selectOptions[] = [
                'group' => $group,
                'name' => $option->getLabel(),
                'value' => $option->getKey(),
                'data' => [], // ? needed ?
                'groupData' => [], // relevant data from field definition
              ];
            }
          }
          break;
        case FieldMultiplicity::RECURRING:
          // option groups with multiple options
          $group = $this->l->t('Recurring "%s"', $field->getName());
          /** @var Entities\ProjectParticipantFieldDataOption $option */
          foreach ($field->getSelectableOptions() as $option) {
            $selectOptions[] = [
              'group' => $group,
              'name' => $option->getLabel(),
              'value' => $option->getKey(),
              'data' => [], // ? needed ?
              'groupData' => [], // relevant data from field definition
            ];
          }
          break;
      }
    }
    return array_values(array_filter($selectOptions, fn($option) => !empty($option['name'])));
  }

  /**
   * Given a musician and a project, compute the total fees to invoice
   * or to pay, and the amounts already paid. The sign convention is
   * such that a positive sign means that the orchestra earns money, a
   * negative sign means that the participant earns money.
   *
   * If no project is given compute the totals for all projects.
   *
   * @param Entities\Musician $musician
   *
   * @param null|Entities\Project $project
   *
   * @return array
   * ```
   * [
   *   'sum' => TOTAL_SUM_OF_OBLIGATIONS,
   *   'received' => TOTAL_AMOUNT_RECEIVED,
   * ]
   * ```
   */
  public static function participantMonetaryObligations(
    Entities\Musician $musician,
    ?Entities\Project $project = null,
  ): array {
    $obligations = [
      'sum' => MonetaryNumberType::zero(), // total sum
      'received' => MonetaryNumberType::zero(), // sum of payments
    ];

    if (empty($project)) {
      /** @var Entities\ProjectParticipant $projectParticipant */
      foreach ($musician->getProjectParticipation() as $projectParticipant) {
        list('sum' => $sum, 'received' => $received) = self::participantMonetaryObligations($musician, $projectParticipant->getProject());
        $obligations['sum']->addEq($sum);
        $obligations['received']->addEq($received);
      }
      return $obligations;
    }

    $projectParticipant = $musician->getProjectParticipantOf($project);
    /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
    foreach ($projectParticipant->getParticipantFieldsData() as $fieldDatum) {
      $fieldFieldDataType = $fieldDatum->getField()->getDataType();
      switch ($fieldFieldDataType) {
        case FieldDataType::RECEIVABLES:
        case FieldDataType::LIABILITIES:
          $obligations['sum']->addEq($fieldDatum->amountPayable());
          $obligations['received']->addEq($fieldDatum->amountPaid());
          break;
      }
    }

    return $obligations;
  }

  /**
   * Internal function: given a surcharge choice compute the associated amount
   * of money and return that as MonetaryNumberType. This is used in the legacy
   * display code in ParticipantTotalFeesTrait.
   *
   * @param string|null $key Key from the participant-fields data table. $key may
   * be a comma-separated list of keys.
   *
   * @param string|null $value Value from the participant-fields data
   * table. This is a decimal number if non-null.
   *
   * @param Entities\ProjectParticipantField $participantField Field definition.
   *
   * @return MonetaryNumberType
   */
  public function participantFieldSurcharge(
    ?string $key,
    ?string $value,
    Entities\ProjectParticipantField $participantField,
  ): MonetaryNumberType {
    switch ($participantField->getMultiplicity()) {
      case FieldMultiplicity::SIMPLE:
        return MonetaryNumberType::create($value);
      case FieldMultiplicity::GROUPOFPEOPLE:
        if (empty($key)) {
          break;
        }
        return MonetaryNumberType::create($participantField->getManagementOption()->getData());
      case FieldMultiplicity::SINGLE:
        if (empty($key)) {
          break;
        }
        /** @var Entities\ProjectParticipantFieldDataOption */
        $dataOption = $participantField->getDataOptions()->first();
        // Non empty value means "yes".
        if ((string)$dataOption['key'] != $key) {
          $this->logWarn('Stored value "' . $key . '" unequal to stored key "' . $dataOption['key'] . '"');
        }
        return MonetaryNumberType::create($dataOption['data']);
      case FieldMultiplicity::GROUPSOFPEOPLE:
      case FieldMultiplicity::MULTIPLE:
        if (empty($key)) {
          break;
        }
        foreach ($participantField->getDataOptions() as $dataOption) {
          if ((string)$dataOption['key'] == $key) {
            return MonetaryNumberType::create($dataOption['data']);
          }
        }
        $this->logError('No data item for multiple choice key "'.$key.'"');
        return MonetaryNumberType::zero();
      case FieldMultiplicity::PARALLEL:
        if (empty($key)) {
          break;
        }
        $keys = Util::explode(',', $key);
        $found = false;
        /** @var MonetaryNumberType $amount */
        $amount = MonetaryNumberType::zero();
        foreach ($participantField->getDataOptions() as $dataOption) {
          if (array_search((string)$dataOption['key'], $keys) !== false) {
            $amount->addEq($dataOption['data']);
            $found = true;
          }
        }
        if (!$found) {
          $this->logError('No data item for parallel choice key "'.$key.'"');
        }
        return $amount;
      case FieldMultiplicity::RECURRING:
        if (empty($key) || empty($value)) {
          break;
        }
        // $keys = Util::explode(',', $key);
        $values = Util::explodeIndexed($value);

        $amount = MonetaryNumberType::zero();
        foreach ($values as $key => $value) {
          if (!empty($value)) {
            $amount->addEq($value);
          }
        }
        return $amount;
    }
    return MonetaryNumberType::zero();
  }

  /**
   * Determine whether a multiplicity-type combination is supported.
   *
   * @param FieldMultiplicity $multiplicity
   *
   * @param FieldDataType $type
   *
   * @return bool
   */
  public static function isSupportedType(FieldMultiplicity $multiplicity, FieldDataType $type): bool
  {
    $unsupported = self::UNSUPPORTED[$multiplicity->value] ?? [];
    return !in_array($type, $unsupported);
  }

  /**
   * Return an array MULTIPLICITY => [ DATATYPES, ... ] of unsupported
   * combinations.
   *
   * @return array
   */
  public static function multiplicityTypeMask()
  {
    return self::UNSUPPORTED;
  }

  /**
   * @param FieldMultiplicity $multiplicity
   *
   * @param FieldDataType $dataType
   *
   * @return string
   */
  public static function defaultTabId(FieldMultiplicity $multiplicity, FieldDataType $dataType):string
  {
    switch ($dataType) {
      case FieldDataType::RECEIVABLES:
      case FieldDataType::LIABILITIES:
        return  'finance';
      case FieldDataType::CLOUD_FILE:
      case FieldDataType::CLOUD_FOLDER:
      case FieldDataType::DB_FILE:
        return 'file-attachments';
      default:
        return 'project';
    }
  }

  /**
   * @param null|string $name Name to sanitize.
   *
   * @return null|string Sanitized file-name, no dots, no slashes, no
   * spaces. null if the argument was null.
   */
  private static function sanitizeFileName(?string $name):?string
  {
    if (empty($name)) {
      return null;
    }
    $name = Util::normalizeSpaces($name);
    $name = preg_replace([ '|\s*/\s*|', '/[.]/', '/\s*/' ], [ '-', '_', '' ], $name);

    return $name;
  }

  /**
   * Sanitize the field name s.t. it is suitable as a file-system entry.
   *
   * @param Entities\ProjectParticipantField $field
   *
   * @return null|string
   */
  public function getFileSystemFieldName(Entities\ProjectParticipantField $field):?string
  {
    assert($field->isFileSystemContext());

    return self::sanitizeFileName($field->getName());
  }

  /**
   * Sanitize the option label s.t. it is suitable as a file-system entry.
   *
   * @param Entities\ProjectParticipantFieldDataOption $option
   *
   * @return null|string
   */
  public function getFileSystemOptionLabel(Entities\ProjectParticipantFieldDataOption $option):?string
  {
    assert($option->isFileSystemContext());

    return self::sanitizeFileName($option->getLabel());
  }

  /**
   * Return the cloud-folder name for the given $field which must be of
   * type FieldDataType::CLOUD_FOLDER or FieldDataType::CLOUD_FILE.
   *
   * @param Entities\ProjectParticipantFieldDatum $datum
   *
   * @param bool $dry If \false actually also created the folder.
   *
   * @return null|string
   */
  public function getFieldFolderPath(Entities\ProjectParticipantFieldDatum $datum, bool $dry = true):?string
  {
    return $this->doGetFieldFolderPath($datum->getField(), $datum->getMusician(), $dry);
  }

  /**
   * Return the cloud-folder name for the given $field which must be of
   * type FieldDataType::CLOUD_FOLDER or FieldDataType::CLOUD_FILE.
   *
   * @param Entities\ProjectParticipantField $field
   *
   * @param Entities\Musician $musician
   *
   * @param bool $dry Default \true. If \false actually create the folder if
   * it does not exist.
   *
   * @return null|string The path name of the folder or null if the field-type
   * does not refer to a cloud file-system node.
   */
  public function doGetFieldFolderPath(Entities\ProjectParticipantField $field, Entities\Musician $musician, bool $dry = true):?string
  {
    $fieldType = $field->getDataType();
    if ($fieldType != FieldDataType::CLOUD_FILE && $fieldType != FieldDataType::CLOUD_FOLDER) {
      return null;
    }

    $fieldName = $this->getFileSystemFieldName($field);

    /** @var ProjectService $projectService */
    $projectService = $this->di(ProjectService::class);

    $participantFolder = $projectService->ensureParticipantFolder($field->getProject(), $musician, dry: $dry);

    switch ($field->getDataType()) {
      case FieldDataType::CLOUD_FOLDER:
        return $participantFolder . UserStorage::PATH_SEP . $fieldName;
      case FieldDataType::CLOUD_FILE:
        $subDirPrefix = ($field->getMultiplicity() == FieldMultiplicity::SIMPLE)
          ? ''
          : UserStorage::PATH_SEP . $fieldName;

        return $participantFolder . $subDirPrefix;
    }
    return null;
  }

  /**
   * Return the effective value of the given datum. In particular
   * referenced files are returned as cloud file-node or DB
   * file-entity. Dates are converted to \DateTimeImmutable. Float
   * values to MonetaryNumberType, int to int, boolean to boolean.
   *
   * @param Entities\ProjectParticipantFieldDatum $datum
   *
   * @return mixed
   */
  public function getEffectiveFieldDatum(Entities\ProjectParticipantFieldDatum $datum)
  {
    $value = $datum->getEffectiveValue();

    /** @var Entities\ProjectParticipantField $field */
    $field = $datum->getField();

    switch ($field->getDataType()) {
      case FieldDataType::BOOLEAN:
        return boolval($value);
      case FieldDataType::DATE:
      case FieldDataType::DATETIME:
        return Util::convertToDateTime($value);
      case FieldDataType::FLOAT:
      case FieldDataType::RECEIVABLES:
        return MonetaryNumberType::create($value);
      case FieldDataType::LIABILITIES:
        return MonetaryNumberType::create($value)->neg();
      case FieldDataType::INTEGER:
        return intval($value);
      case FieldDataType::CLOUD_FILE:
        if (empty($value)) {
          return null;
        }
        $folderPath = $this->getFieldFolderPath($datum, dry: false);
        $filePath = $folderPath . UserStorage::PATH_SEP . $value;
        $file = $this->di(UserStorage::class)->getFile($filePath);
        if (empty($file)) {
          $this->logError('NO FILE FOUND FOR PATH "' . $folderPath . '" FIELD ' . $field->getName() . ' ' . $field->getId());
        }
        return $file;
      case FieldDataType::CLOUD_FOLDER:
        $folderPath = $this->getFieldFolderPath($datum, dry: false);
        return $this->di(UserStorage::class)->getFolder($folderPath);
      case FieldDataType::DB_FILE:
        if (empty($value)) {
          return null;
        }
        return $this->getDatabaseRepository(Entities\DatabaseStorageFile::class)->find($value);
      case FieldDataType::TEXT:
      case FieldDataType::HTML:
      default:
        return $value;
    }
  }

  /**
   * Gernarate a formatted string for the given value.
   *
   * @param null|Entities\ProjectParticipantFieldDatum $datum
   *
   * @param string $dateFormat
   *
   * @param int $floatPrecision
   *
   * @return string
   */
  public function printEffectiveFieldDatum(
    ?Entities\ProjectParticipantFieldDatum $datum,
    string $dateFormat = 'long',
    int $floatPrecision = 2,
  ):string {
    if (empty($datum)) {
      return '';
    }

    $fieldValue = $this->getEffectiveFieldDatum($datum);
    if (empty($fieldValue)) {
      return '';
    }

    /** @var Entities\ProjectParticipantField $field */
    $field = $datum->getField();

    switch ($field->getDataType()) {
      case FieldDataType::BOOLEAN:
        return $fieldValue ? $this->l->t('true') : $this->l->t('false');
      case FieldDataType::DATE:
        return $this->formatDate($fieldValue, $dateFormat);
      case FieldDataType::DATETIME:
        return $this->formatDateTime($fieldValue, $dateFormat);
      case FieldDataType::FLOAT:
        return $this->floatValue($fieldValue, $floatPrecision);
      case FieldDataType::RECEIVABLES:
      case FieldDataType::LIABILITIES:
        return $this->moneyValue($fieldValue);
      case FieldDataType::INTEGER:
        return (string)(int)$fieldValue;
      case FieldDataType::CLOUD_FILE:
        return $fieldValue->getName();
      case FieldDataType::CLOUD_FOLDER:
        return $fieldValue->getName() . UserStorage::PATH_SEP;
      case FieldDataType::DB_FILE:
        return $fieldValue->getFileName();
      case FieldDataType::TEXT:
      case FieldDataType::HTML: // should use tidy
      default:
        return $fieldValue;
    }
  }

  /**
   * Return the row with the key matching the argument $key. This seems not be
   * generally used, just by
   * \OCA\CAFEVDB\PageRenderer\ProjectParticipantFields.
   *
   * @param string $key Allowed values key to search for.
   *
   * @param array $values Exploded allowed values data.
   *
   * @return null|array The matching row if found or null.
   */
  public static function findDataOption(string $key, array $values):?array
  {
    return $values[$key]?:null;
  }

  /**
   * Prototype for allowed values, i.e. multiple-value options.
   *
   * @return array
   */
  public static function dataOptionPrototype():array
  {
    return [
      'key' => false,
      'label' => false,
      'data' => false,
      'deposit' => false,
      'limit' => false,
      'balancingAccount' => false,
      'tooltip' => false,
      'deleted' => false,
    ];
  }

  /**
   * Explode the given json encoded string into a PHP array.
   *
   * @param mixed $values
   *
   * @param bool $addProto
   *
   * @param bool $trimInactive
   *
   * @return array
   */
  public function explodeDataOptions(mixed $values, bool $addProto = true, bool $trimInactive = false):array
  {
    $options = empty($values) ? [] : (is_array($values) ? $values : json_decode($values, true));
    if (is_string($options)) {
      // perhaps double encoded, try ...
      $options = json_decode($options, true);
    }
    if (is_string($values) && $values != '[]' && !empty($values) && empty($options)) {
      $options = [
        array_merge(
          $this->dataOptionPrototype(),
          [ 'key' => Uuid::create(), 'label' => $values, ]),
      ];
    }
    if (isset($options[-1])) {
      throw new Exception(
        $this->l->t(
          'Option index -1 should not be present here, options: %s',
          print_r($options, true)));
    }
    $options = array_values($options);
    $protoType = $this->dataOptionPrototype();
    $protoKeys = array_keys($protoType);
    $result = [];
    foreach ($options as $option) {
      $keys = array_keys($option);
      if ($keys !== $protoKeys) {
        throw new InvalidArgumentException(
          $this->l->t(
            'Prototype keys "%s" and options keys "%s" differ',
            [ implode(',', $protoKeys), implode(',', $keys) ])
        );
      }
      if ($trimInactive && !empty($option['deleted'])) {
        continue;
      }
      $result[(string)$option['key']] = $option;
    }
    if ($addProto) {
      $result[$protoType['key']] = $protoType;
    }
    return $result;
  }

  /**
   * Serialize a list of allowed values in the form
   * ```
   * [
   *   [ 'key' => KEY1, ... ],
   *   [ 'key' => KEY2, ... ],
   * ]
   * ```
   * as JSON for storing in the database. As a side-effect missing
   * keys are generated and empty missing fields are inserted.
   *
   * @param array $options As explained above.
   *
   * @return string JSON encoded data.
   */
  public function implodeDataOptions(array $options):string
  {
    if (isset($options[-1])) {
      throw new Exception($this->l->t('Option index -1 should not be present here, options: %s', print_r($options, true)));
    }
    $proto = $this->dataOptionPrototype();
    foreach ($options as &$option) {
      $option = array_merge($proto, $option);
      if (empty($option['key'])) {
        $option['key'] = Uuid::create();
      }
    }
    return json_encode(array_values($options));
  }

  /**
   * For the given FieldMultiplicity::GROUPOFPEOPLE or
   * FieldMultiplicity::GROUPSOFPEOPLE option find the group members.
   *
   * @param Entities\ProjectParticipantFieldDataOption|Entities\ProjectParticipantFieldDatum $groupOptionProvider
   *
   * @return array|Collection Array or collection of all members in
   * the form or the Entities\ProjectParticipantFieldDatum entities
   * for the group.
   */
  public function findGroupMembersOf($groupOptionProvider)
  {
    if ($groupOptionProvider instanceof Entities\ProjectParticipantFieldDatum) {
      $groupKey = $groupOptionProvider->getOptionKey();
    } elseif ($groupOptionProvider instanceof Entities\ProjectParticipantFieldDataOption) {
      $groupKey = $groupOptionProvider->getKey();
    }

    $groupField = $groupOptionProvider->getField();
    $multipliciy = $groupField->getMultiplicity();
    if ($multipliciy != FieldMultiplicity::GROUPOFPEOPLE
        && $multipliciy != FieldMultiplicity::GROUPSOFPEOPLE) {
      throw new RuntimeException(
        $this->l->t('Field "%s" is not a group of participants field.', $groupField->getName()));
    }

    $groupMembers = $this->getDatabaseRepository(Entities\ProjectParticipantFieldDatum::class)
      ->findBy([ 'optionKey' => $groupKey ]);

    return $groupMembers;
  }

  /**
   * Find the members of all groups for the given option, indexed by the option-key.
   *
   * @param Entities\ProjectParticipantField $groupField
   *
   * @return array
   */
  public function findGroupMembers(Entities\ProjectParticipantField $groupField):array
  {
    $multipliciy = $groupField->getMultiplicity();
    if ($multipliciy != FieldMultiplicity::GROUPOFPEOPLE
        && $multipliciy != FieldMultiplicity::GROUPSOFPEOPLE) {
      throw new RuntimeException(
        $this->l->t('Field "%s" is not a group of participants field.', $groupField->getName()));
    }

    $groupMembers = [];
    /** @var Entities\ProjectParticipantFieldDataOption $groupOption */
    foreach ($groupField->getSelectableOptions() as $groupOption) {
      $groupKey = $groupOption->getKey();
      $groupMembers[$groupKey->getBytes()] = $this->findGroupMembersOf($groupOption);
    }
    return $groupMembers;
  }

  /**
   * Select a field with matching names from a collection of fields.
   *
   * @param Collections\Collection $things
   *
   * @param string $fieldName
   *
   * @param bool $singleResult
   *
   * @return null|object|Collections\Collection
   */
  public function filterByFieldName(
    Collections\Collection $things,
    string $fieldName,
    bool $singleResult = true,
  ):mixed {
    if ($things->isEmpty()) {
      return $singleResult ? null :  new Collections\ArrayCollection;
    }
    $fieldNames = $this->translationVariants($fieldName);
    /** @var Collections\ArrayCollection $remaining */
    $remaining = new Collections\ArrayCollection;
    if ($things->first() instanceof Entities\ProjectParticipantField) {
      /** @var Entities\ProjectParticipantField $field */
      $remaining = $things->filter(function($field) use ($fieldNames) {
        $fieldName = strtolower($field->getName());
        foreach ($fieldNames as $searchItem) {
          if ($fieldName == $searchItem) {
            return true;
          }
        }
        return false;
      });
    } elseif ($things->first() instanceof Entities\ProjectParticipantFieldDataOption) {
      /** @var Entities\ProjectParticipantFieldDataOption $option */
      $remaining = $things->filter(function($option) use ($fieldNames) {
        $field = $option->getField();
        $fieldName = strtolower($field->getName());
        foreach ($fieldNames as $searchItem) {
          if ($fieldName == $searchItem) {
            return true;
          }
        }
        return false;
      });
    } elseif ($things->first() instanceof Entities\ProjectParticipantFieldDatum) {

      /** @var Entities\ProjectParticipantFieldDatum $datum */
      $remaining = $things->filter(function($datum) use ($fieldNames) {
        $field = $datum->getField();
        $fieldName = strtolower($field->getName());
        foreach ($fieldNames as $searchItem) {
          if ($fieldName == $searchItem) {
            return true;
          }
        }
        return false;
      });
    } else {
      throw new RuntimeException($this->l->t('Only "field" collections can be filtered, not instances of "%s".', get_class($things->first())));
    }
    if ($singleResult) {
      if ($remaining->count() == 1) {
        return $remaining->first();
      } elseif ($remaining->isEmpty()) {
        return null;
      }
    }
    return $remaining;
  }

  /**
   * Generate an "absence" field for the given project event if such a field
   * does not yet exist. Otherwise make sure its title is updated, and
   * soft-delete it if the given project event is also soft-deleted.
   *
   * @param Entities\ProjectEvent $projectEvent
   *
   * @param bool $flush Whether to flusht the changes to the database.
   *
   * @return Entities\ProjectParticipantField
   */
  public function ensureAbsenceField(
    Entities\ProjectEvent $projectEvent,
    bool $flush = false,
  ):?Entities\ProjectParticipantField {
    $softDeleteableState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    /** @var Entities\Project $project */
    $project = $projectEvent->getProject();

    /** @var EventsService $eventsService */
    $eventsService = $this->appContainer()->get(EventsService::class);
    $eventData = $eventsService->getEventData($projectEvent);

    /** @var Entities\ProjectParticipantField $absenceField */
    $absenceField = $projectEvent->getAbsenceField();
    $l = $this->appL10n();
    if (empty($absenceField)) {
      $protoType = $project->getParticipantFields()->matching(DBUtil::criteriaWhere([
        '&(|name' => $this->l->t('Prototype'),
        'name' => 'Prototype',
        ')(|tab' => $l->t('Absence'),
        'tab' => 'Absence',
      ]));
      if ($protoType->count()) {
        /** @var Entities\ProjectParticipantField $protoType */
        $protoType = $protoType->first();
        $absenceField = clone($protoType);
      } else {
        $protoType = null;
        $absenceField = (new Entities\ProjectParticipantField)
          ->setProject($project)
          ->setName($eventsService->briefEventDate($eventData))
          ;
        if (false) {
          $absenceField
            ->setMultiplicity(FieldMultiplicity::MULTIPLE)
            ->setDataType(FieldDataType::TEXT);
        } else {
          $absenceField
            ->setMultiplicity(FieldMultiplicity::SIMPLE)
            ->setDataType(FieldDataType::HTML);
        }
        switch ($absenceField->getMultiplicity()) {
          case FieldMultiplicity::MULTIPLE:
            $options = [
              (string)$l->t('absent') => $l->t('This person will not participate in this event.'),
              (string)$l->t('contacted') => $l->t('This person has been asked to confirm the participation but did not yet answer.'),
              (string)$l->t('tentative') => $l->t('This person does not yet know whether a participation is possible.'),
            ];
            foreach ($options as $label => $tooltip) {
              /** @var Entities\ProjectParticipantFieldDataOption $option */
              $option = (new Entities\ProjectParticipantFieldDataOption)
                ->setLabel($label)
                ->setTooltip($tooltip)
                ->setField($absenceField)
                ->setKey(Uuid::create());
              $absenceField->getDataOptions()->set((string)$option->getKey(), $option);
              $this->persist($option);
            }
            break;
          case FieldMultiplicity::SIMPLE:
            // simple text field still needs one dummy option
            $option = (new Entities\ProjectParticipantFieldDataOption)
              ->setLabel($absenceField->getName())
              ->setKey(Uuid::create())
              ->setField($absenceField);
            $this->persist($option);
            $absenceField->getDataOptions()->set((string)$option->getKey(), $option);
            break;
        }
        /** @var Entities\ProjectParticipantField $protoType */
        $protoType = (clone $absenceField)
          ->setName($this->l->t('Prototype'))
          ->setTab($l->t('Absence'))
          ->setDeleted('now');
        $this->persist($protoType);
      }
      $this->persist($absenceField);
    }

    $brief  = htmlspecialchars(stripslashes($eventData['summary']));
    $location = htmlspecialchars(stripslashes($eventData['location']));
    $description = htmlspecialchars(nl2br(stripslashes($eventData['description'])));
    $longDate = $eventsService->longEventDate($eventData);
    $description = $longDate
      . (!empty($brief) ? '<br/>' . $brief  : '')
      . (!empty($location) ? '<br/>' . $location  : '')
      . (!empty($description) ? '<br/>' . $description : '');
    $dateString = $eventsService->briefEventDate($eventData);

    $absenceField->setName($dateString)
      ->setTooltip($description)
      ->setDisplayOrder(-$eventData['start']->getTimestamp())
      ->setParticipantAccess(AccessPermission::READ);

    if ($absenceField->getMultiplicity() == FieldMultiplicity::SIMPLE) {
      $defaultOption = $absenceField->getDataOption();
      if (empty($defaultOption->getTooltip())) {
        $defaultOption->setTooltip($description);
      }
    }

    if (empty($absenceField->getTab())) {
      // TRANSLATORS: Column heading in table (capital first character)
      $absenceField->setTab($l->t('Absence'));
    }

    $projectEvent->setAbsenceField($absenceField);

    if ($projectEvent->isDeleted()) {
      $this->remove($absenceField, soft: true);
    } else {
      $absenceField->setDeleted(null);
    }

    if ($flush) {
      $this->flush();
    }

    $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER, $softDeleteableState);

    return $absenceField;
  }

  /**
   * Create a field with given name and type. The field returned is not yet persisted.
   *
   * @param string $name
   *
   * @param FieldMultiplicity $multiplicity
   *
   * @param FieldDataType $dataType
   *
   * @param null|string $tooltip
   *
   * @return null|Entities\ProjectParticipantField
   */
  public function createField(
    string $name,
    FieldMultiplicity $multiplicity,
    FieldDataType $dataType,
    ?string $tooltip = null,
  ): ?Entities\ProjectParticipantField {
    if ($multiplicity != FieldMultiplicity::SIMPLE) {
      return null;
    }

    /** @var Entities\ProjectParticipantField $field */
    $field = (new Entities\ProjectParticipantField)
           ->setName($name)
           ->setMultiplicity($multiplicity)
           ->setDataType($dataType)
           ->setTooltip($tooltip);
    /** @var Entities\ProjectParticipantFieldDataOption $option */
    $option = (new Entities\ProjectParticipantFieldDataOption)
            ->setLabel($name)
            ->setTooltip($tooltip)
            ->setField($field);
    $field->getDataOptions()->set($option->getKey(), $option);
    $field->setDefaultValue($option);

    return $field;
  }

  /**
   * Delete the given field, taking default values and soft-deletion into account.
   *
   * @param int|Entities\ProjectParticipantField $fieldOrId
   *
   * @return bool true if the field was really deleted, false if it
   * was kept.
   *
   * @todo We might want to remove "side-effects", i.e. data-base files and
   * cloud files and cloud folders.
   */
  public function deleteField(int|Entities\ProjectParticipantField $fieldOrId)
  {
    if (!($fieldOrId instanceof Entities\ProjectParticipantField)) {
      $field = $this->getDatabaseRepository(Entities\ProjectParticipantField::class)->find($fieldOrId);
      if (empty($field)) {
        throw new RuntimeException($this->l->t('Unable to find participant field for id "%s"', $fieldOrId));
      }
    } else {
      $field = $fieldOrId;
    }

    $used = false;

    /** @var Entities\ProjectParticipantFieldDataOption $option */
    foreach ($field->getDataOptions() as $option) {
      if ($option === $field->getDefaultValue()) {
        $field->setDefaultValue(null);
        //$this->flush();
      }

      /** @var Entities\ProjectParticipantFieldDatum $datum */
      foreach ($option->getFieldData() as $datum) {
        if ($datum->unused()) {
          $this->remove($datum, true);
        }
        $this->remove($datum, true);
      }

      if ($option->unused()) {
        $this->remove($option, true);
      } else {
        $used = true;
      }
      $this->remove($option, true);
    }

    $this->remove($field, true); // this should be soft-delete
    if (!$used && $field->unused()) {
      $this->remove($field, true); // this should be hard-delete
    }

    return !$used;
  }

  /**
   * Generate all user-folders with an optional README.md if the field has type
   * CLOUD_FOLDER. Install also default values if any are given.
   *
   * @param Entities\ProjectParticipantField $field
   *
   * @return void
   */
  public function handlePersistField(Entities\ProjectParticipantField $field):void
  {
    $this->entityManager->registerPreCommitAction(
      new Common\GenericUndoable(
        function() use ($field) {
          $defaultValue = $field->getDefaultValue();
          if ($defaultValue === null) {
            return;
          }
          $needFlush = false;
          foreach ($field->getProject()->getParticipants() as $participant) {
            $datum = Entities\ProjectParticipantFieldDatum::fromDefaultValue($field, $participant);
            if ($datum === null) {
              continue;
            }
            $this->entityManager->persist($datum);
            $needFlush = true;
          }
          if ($needFlush) {
            $this->entityManager->flush();
          } else {
            $this->logInfo('NEED FLUSH FALSE');
          }
        },
        // no undo, the rollback will fix it and the entity manager is closed
        // anyways after an error.
      ),
    );

    // check if we have to do something
    if ($field->getDataType() != FieldDataType::CLOUD_FOLDER) {
      return;
    }

    $readMe = Util::htmlToMarkDown($field->getTooltip());

    $needsFlush = false;

    /** @var Entities\ProjectParticipant $participant */
    foreach ($field->getProject()->getParticipants() as $participant) {
      $musician = $participant->getMusician();

      $this->entityManager
        ->registerPreCommitAction(
          new Common\UndoableFolderCreate(
            fn() => $this->doGetFieldFolderPath($field, $musician),
            gracefully: true,
          )
        )->register(
          new Common\UndoableTextFileUpdate(
            fn() => $this->doGetFieldFolderPath($field, $musician) . Constants::PATH_SEP . Constants::README_NAME,
            gracefully: true,
            content: $readMe,
          )
        )
        ->register(new Common\GenericUndoable(function() use ($field, $musician, &$needsFlush) {
          $needsFlush = $this->populateCloudFolderField($field, $musician, flush: false) || $needsFlush;
        }));
    }
    $this->entityManager->registerPreCommitAction(
      new Common\GenericUndoable(function() use (&$needsFlush) {
        $needsFlush && $this->flush();
      })
    );
  }

  /**
   * Update the README.md file with a changed tooltip if the field has type CLOUD_FOLDER
   *
   * @param Entities\ProjectParticipantField $field
   *
   * @param null|string $oldTooltip
   *
   * @param null|string $newTooltip
   *
   * @return void
   */
  public function handleChangeFieldTooltip(
    Entities\ProjectParticipantField $field,
    ?string $oldTooltip,
    ?string $newTooltip,
  ):void {
    // check if we have to do something
    $isHandledField = $field->getDataType() == FieldDataType::CLOUD_FOLDER
      || ($field->getDataType() == FieldDataType::CLOUD_FILE && $field->getMultiplicity() != FieldMultiplicity::SIMPLE);

    if (!$isHandledField) {
      return;
    }

    $mkdir = $field->getDataType() != FieldDataType::CLOUD_FILE;

    $oldReadMe = Util::htmlToMarkDown($oldTooltip);
    $newReadMe = Util::htmlToMarkDown($newTooltip);

    /** @var Entities\ProjectParticipant $participant */
    foreach ($field->getProject()->getParticipants() as $participant) {
      $musician = $participant->getMusician();

      $this->entityManager
        ->registerPreCommitAction(
          new Common\UndoableTextFileUpdate(
            name: fn() => $this->doGetFieldFolderPath($field, $musician) . Constants::PATH_SEP . Constants::README_NAME,
            content: $newReadMe,
            replacableContent: $oldReadMe,
            gracefully: true,
            mkdir: $mkdir,
          )
        );
    }
  }

  /**
   * Update the README.md file with a changed tooltip if the field has type CLOUD_FOLDER.
   *
   * @param Entities\ProjectParticipantField $field
   *
   * @param null|FieldDataType $oldType
   *
   * @param null|FieldDataType $newType
   *
   * @return void
   */
  public function handleChangeFieldType(
    Entities\ProjectParticipantField $field,
    ?FieldDataType $oldType,
    ?FieldDataType $newType,
  ):void {

    if ($newType == $oldType) {
      return;
    }

    $needsFlush = false;

    switch ($newType) {
      case FieldDataType::CLOUD_FOLDER:
      case FieldDataType::CLOUD_FOLDER:
      case FieldDataType::CLOUD_FILE:
        $readMe = Util::htmlToMarkDown($field->getTooltip());

        switch ($newType) {
          case FieldDataType::CLOUD_FOLDER:
            // try to create any missing folder
            /** @var Entities\ProjectParticipant $participant */
            foreach ($field->getProject()->getParticipants() as $participant) {
              $musician = $participant->getMusician();

              $this->entityManager->registerPreCommitAction(
                new Common\UndoableFolderCreate(
                  fn() => $this->doGetFieldFolderPath($field, $musician),
                  gracefully: true,
                )
              )->register(
                new Common\UndoableTextFileUpdate(
                  fn() => $this->doGetFieldFolderPath($field, $musician) . Constants::PATH_SEP . Constants::README_NAME,
                  gracefully: true,
                  content: $readMe,
                )
              )->register(
                new Common\GenericUndoable(function() use ($field, $musician, &$needsFlush) {
                  $needsFlush = $this->populateCloudFolderField($field, $musician, flush: false) || $needsFlush;
                }));
            }
            break;
          case FieldDataType::CLOUD_FILE:
            foreach ($field->getProject()->getParticipants() as $participant) {
              $musician = $participant->getMusician();
              $this->entityManager->registerPreCommitAction(
                new Common\GenericUndoable(function() use ($field, $musician, &$needsFlush) {
                  $needsFlush = $this->populateCloudFileField($field, $musician, flush: false) || $needsFlush;
                })
              );
            }
            break;
        }
        switch ($oldType) {
          case FieldDataType::CLOUD_FOLDER:
            // try to remove essentially empty folders
            /** @var Entities\ProjectParticipant $participant */
            foreach ($field->getProject()->getParticipants() as $participant) {
              $musician = $participant->getMusician();

              // currently we only remove empty (READMEs are ignored) folders, also in
              // order to mitigate user-errors: if deleting the field in error it can
              // be added again and the files are still there.

              // this has to be precomputed, as this belongs to the old field type.
              $field->setDataType($oldType);
              $fieldFolderPath = $this->doGetFieldFolderPath($field, $musician);
              $field->setDataType($newType);

              $this->entityManager->registerPreCommitAction(
                new Common\UndoableFolderRemove($fieldFolderPath, gracefully: true, recursively: false)
              );
            }
            break;
        }
        break; // FS stuff
      case FieldDataType::RECEIVABLES:
      case FieldDataType::LIABILITIES:
        // change the sign of all values in the entire hierarchy.

        /** @var Entities\ProjectParticipantFieldDataOption $option */
        /** @var Entities\ProjectParticipantFieldDatum $datum */

        $needsFlush = $field->getDataOptions()->count() > 0
          || $field->getFieldData()->count() > 0;

        switch ($field->getMultiplicity()) {
          case FieldMultiplicity::SINGLE:
          case FieldMultiplicity::MULTIPLE:
          case FieldMultiplicity::PARALLEL:
          case FieldMultiplicity::GROUPSOFPEOPLE:
            // value is stored in the option, negate it
            foreach ($field->getDataOptions() as $option) {
              $option->setData(-$option->getData());
              $deposit = $option->getDeposit();
              if ($deposit) {
                $option->setDeposit($deposit->neg());
              }
            }
            break;
          case FieldMultiplicity::GROUPOFPEOPLE:
            // value in management option of $field
            $option = $field->getManagementOption();
            $option->setData(-$option->getData());
            $option->setDeposit($option->getDeposit()->neg());
            break;
          case FieldMultiplicity::SIMPLE:
            /** @var Entities\ProjectParticipantFieldDatum $datum */
            foreach ($field->getFieldData() as $datum) {
              $datum->setOptionValue(-$datum->getOptionValue());
              $deposit = $datum->getDeposit();
              if ($deposit) {
                $datum->setDeposit($deposit->neg());
              }
            }
            $option = $field->getDefaultValue();
            if (!empty($option)) {
              $option->setData(-$option->getData());
              $deposit = $option->getDeposit();
              if ($deposit) {
                $this->logInfo('CHANGE DEPOSIT ' . print_r($deposit, true) . ' -> ' . print_r($deposit->neg(), true));
                $option->setDeposit($deposit->neg());
              }
            }
            break;
          case FieldMultiplicity::RECURRING:
            // value in data-entities, no deposit in this case
            foreach ($field->getFieldData() as $datum) {
              $datum->setOptionValue(-$datum->getOptionValue());
            }
            break;
        }
        break;
    }
    $this->entityManager->registerPreCommitAction(
      new Common\GenericUndoable(function() use (&$needsFlush) {
        $needsFlush && $this->flush();
      })
    );
  }

  /**
   * Check if the transistion from $old to $new is implemented.
   *
   * @param FieldMultiplicity $oldFieldMultiplicity
   *
   * @param FieldMultiplicity $newFieldMultiplicity
   *
   * @return bool
   */
  private function isSupportedFieldMultiplicityTransition(FieldMultiplicity $oldFieldMultiplicity, FieldMultiplicity $newFieldMultiplicity):bool
  {
    $allowed = self::ALLOWED_TRANSITIONS[(string)$oldFieldMultiplicity];
    return in_array((string)$newFieldMultiplicity, $allowed);
  }

  /**
   * Try to gracefully change the field-type.
   *
   * @param Entities\ProjectParticipantField $field
   *
   * @param null|FieldMultiplicity $oldFieldMultiplicity
   *
   * @param null|FieldMultiplicity $newFieldMultiplicity
   *
   * @return void
   */
  public function handleChangeFieldFieldMultiplicity(
    Entities\ProjectParticipantField $field,
    ?FieldMultiplicity $oldFieldMultiplicity,
    ?FieldMultiplicity $newFieldMultiplicity,
  ):void {

    if ($oldFieldMultiplicity === null || $newFieldMultiplicity == $oldFieldMultiplicity) {
      return;
    }

    $softDeleteableState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    $dataType = $field->getDataType();

    if (!$this->isSupportedType($newFieldMultiplicity, $dataType)) {
      $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER, $softDeleteableState);
      throw new Exceptions\EnduserNotificationException(
        $this->l->t(
          'Changing the multiplicity from "%1$s" to "%2$s" is not possible as the new multiplicity does not support the field data-type "%3$s".', [
            $this->l->t($oldFieldMultiplicity), $this->l->t($newFieldMultiplicity), $this->l->t($dataType),
          ],
        )
      );
    }

    if ($field->usage() <= 0 || (!empty($field->getProjectEvent()) && $field->usage() < 1)) {
      return;
    }

    if (!$this->isSupportedFieldMultiplicityTransition($oldFieldMultiplicity, $newFieldMultiplicity)) {
      $allowedTransitions = self::ALLOWED_TRANSITIONS[(string)$oldFieldMultiplicity] ?? [];
      if (empty($allowedTransitions)) {
        $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER, $softDeleteableState);
        throw new Exceptions\EnduserNotificationException(
          $this->l->t(
            'The field is already in use, therefore the multiplicity may no longer be changed from "%1$s" to "%2$s".', [
              $this->l->t($oldFieldMultiplicity),
              $this->l->t($newFieldMultiplicity),
            ]));
      } else {
        $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER, $softDeleteableState);
        throw new Exceptions\EnduserNotificationException(
          $this->l->t(
            'The field is already in use, therefore the multiplicity may only be changed from "%1$s" to "%2$s", but not to "%3$s".', [
              $this->l->t($oldFieldMultiplicity),
              implode(', ', array_map(fn($value) => $this->l->t($value), $allowedTransitions)),
              $this->l->t($newFieldMultiplicity),
            ]));
      }
    }

    $needsFlush = false;

    switch ($newFieldMultiplicity) {
      case FieldMultiplicity::SIMPLE:
        if ($dataType != FieldDataType::TEXT && $dataType != FieldDataType::HTML) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t(
              'The field is already in use, therefore changing the multiplicity from "%1$s" to "%2$s" is only supported for text or HTML fields, but the actual data-type is "%3$s".', [
                $this->l->t($oldFieldMultiplicity), $this->l->t($newFieldMultiplicity), $this->l->t($dataType),
              ]));
        }
        /** @var Entities\ProjectParticipantFieldDataOption $dataOption */
        foreach ($field->getSelectableOptions() as $dataOption) {
          if (!$dataOption->isDeleted()) {
            break;
          }
        }
        $tooltips = [];
        $participantData = [];
        $field->setMultiplicity($oldFieldMultiplicity);
        /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
        foreach ($field->getFieldData() as $fieldDatum) {
          /** @var Entities\ProjectParticipant $participant */
          $participant = $fieldDatum->getProjectParticipant();
          $musicianId = $participant->getMusician()->getId();
          if (empty($participantData[$musicianId])) {
            $participantData[$musicianId] = [
              'data' => [],
              'activeDatum' => $participant->getParticipantFieldsDatum($dataOption->getKey()),
              'tooltips' => [],
              'participant' => $participant,
            ];
          }
          $participantData[$musicianId]['data'][] = $fieldDatum;
          $tooltip = trim($fieldDatum->getDataOption()->getTooltip());
          if (!empty($tooltip)) {
            $tooltips[(string)$fieldDatum->getDataOption()->getKey()] = $tooltip;
          }
        }
        if (!empty($tooltips)) {
          $needsFlush = true;
          $dataOption->setTooltip(implode('<br/>', $tooltips));
        }
        foreach ($participantData as $musicianId => $dataInfo) {
          $values = [];
          foreach ($dataInfo['data'] as $fieldDatum) {
            $value = $fieldDatum->getDataOption()->getLabel();
            $effectiveData = $this->printEffectiveFieldDatum($fieldDatum);
            if (!empty($effectiveData)) {
              $value .= ':' . $effectiveData;
            }
            $values[] = $value;
          }
          $value = implode(',', $values);
          $participant = $dataInfo['participant'];
          /** @var Entities\ProjectParticipantFieldDatum $activeDatum */
          $activeDatum = $dataInfo['activeDatum'];
          if (empty($activeDatum)) {
            $activeDatum = (new Entities\ProjectParticipantFieldDatum)
              ->setProjectParticipant($participant)
              ->setField($field)
              ->setDataOption($dataOption);
            $dataOption->getFieldData()->set($musicianId, $activeDatum);
            $field->getFieldData()->add($activeDatum);
            $this->persist($activeDatum);
          }
          $activeDatum->setOptionValue($value);
          $needsFlush = true;
        }
        $field->setMultiplicity($newFieldMultiplicity);
        $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER, $softDeleteableState);
        break;
      case FieldMultiplicity::PARALLEL:
        // nothing to do
        break;
      case FieldMultiplicity::MULTIPLE:
        // nothing to do;
        break;
      default:
        // errors already checked
        break;
    }

    if ($needsFlush) {
      $this->entityManager->registerPreCommitAction(new Common\GenericUndoable(fn() => $this->flush()));
    }

    $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER, $softDeleteableState);
  }

  /**
   * Populate the field-datum for the given field and musician with the actual
   * folder contents.
   *
   * @param Entities\ProjectParticipantField $field
   *
   * @param Entities\Musician $musician
   *
   * @param bool $flush If \true call flush as needed, otherwise just report
   * back the need for flush with the return value. Defaults to \true.
   *
   * @param null|Entities\ProjectParticipantFieldDatum $fieldDatum
   *
   * @return true \true if flush is needed repectively if something has been changed, \false otherwise.
   */
  public function populateCloudFolderField(
    Entities\ProjectParticipantField $field,
    Entities\Musician $musician,
    bool $flush = true,
    ?Entities\ProjectParticipantFieldDatum &$fieldDatum = null
  ):bool {
    $needsFlush = false;

    if ($field->getDataType() != FieldDataType::CLOUD_FOLDER) {
      return $needsFlush;
    }

    $filterState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    if (!$this->containsEntity($musician)) {
      $musician = $this->getReference(Entities\Musician::class, $musician->getId());
    }

    $folderName = $this->doGetFieldFolderPath($field, $musician);
    /** @var UserStorage $userStorage */
    $userStorage = $this->di(UserStorage::class);

    $folderNode = $userStorage->getFolder($folderName);
    $folderContents = array_filter(
      array_map(
        fn(CloudFiles\Node $node) => $node->getName(),
        empty($folderNode) ? [] : $folderNode->getDirectoryListing(),
      ),
      fn(string $nodeName) => $nodeName != Constants::README_NAME
    );
    /** @var Entities\ProjectParticipantFieldDataOption $fieldOption */
    $fieldOption = $field->getDataOption();
    if (empty($fieldOption)) {
      $this->logError('There should be a single field-option for field ' . $field->getName() . '@' . $field->getId() . ', but there is none.');
      $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER, $filterState);
      return $needsFlush;
    }
    $fieldData = $fieldOption->getMusicianFieldData($musician);
    if (empty($folderContents)) {
      /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
      foreach ($fieldData as $fieldDatum) {
        $this->remove($fieldDatum, hard: true);
        $fieldOption->getFieldData()->removeElement($fieldDatum);
        $fieldDatum = null;
        $needsFlush = true;
      }
    } else {
      /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
      if ($fieldData->count() !== 1) {
        foreach ($fieldData as $fieldDatum) {
          $this->remove($fieldDatum, hard: true);
          $field->getFieldData()->removeField($fieldDatum);
          $fieldOption->getFieldData()->removeElement($fieldDatum);
          $project->getParticipantFieldsData()->removeField($fieldDatum);
          $musician->getProjectParticipantFieldsData()->removeField($fieldDatum);
        }
        $project = $field->getProject();
        $fieldDatum = new Entities\ProjectParticipantFieldDatum;
        $fieldDatum->setField($field)
          ->setDataOption($fieldOption)
          ->setMusician($musician)
          ->setProject($project);
        $field->getFieldData()->add($fieldDatum);
        $fieldOption->getFieldData()->set($musician->getId(), $fieldDatm);
        $project->getParticipantFieldsData()->add($fieldDatum);
        $musician->getProjectParticipantFieldsData()->set($fieldDatum->getOptionKey()->getBytes(), $fieldDatum);
        $this->persist($fieldDatum);
        $needsFlush = true;
      } else {
        $fieldDatum = $fieldData->first();
        if ($fieldDatum->isDeleted()) {
          $fieldDatum->setDeleted(null);
          $needsFlush = true;
        }
      }
      $newValue = json_encode($folderContents);
      if ($fieldDatum->getOptionValue() != $newValue) {
        $fieldDatum->setOptionValue($newValue);
        $needsFlush = true;
      }
    }
    if ($needsFlush && $flush) {
      $this->flush();
    }

    $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER, $filterState);

    return $needsFlush;
  }

  /**
   * Populate the field-data for the given field and musician with the
   * actual file-system content. This can be used to sanitize the
   * field-data after changing the field type, or after adding fields.
   *
   * @param Entities\ProjectParticipantField $field
   *
   * @param Entities\Musician $musician
   *
   * @param bool $flush If true call flush as needed, otherwise just report
   * back the need for flush with the return value.
   *
   * @param array $fieldData The field-data for the field and musician.
   *
   * @return true \true if flush is needed repectively if something has been changed, \false otherwise.
   */
  public function populateCloudFileField(Entities\ProjectParticipantField $field, Entities\Musician $musician, bool $flush = true, ?array &$fieldData = null):bool
  {
    $needsFlush = false;
    $fieldData = [];
    if ($field->getDataType() != FieldDataType::CLOUD_FILE) {
      return $needsFlush;
    }

    $multiplicity = $field->getMultiplicity();

    if (!$this->containsEntity($musician)) {
      $musician = $this->getReference(Entities\Musician::class, $musician->getId());
    }

    $folderName = $this->doGetFieldFolderPath($field, $musician);
    /** @var UserStorage $userStorage */
    $userStorage = $this->di(UserStorage::class);

    $folderNode = $userStorage->getFolder($folderName);
    $folderContents = array_filter(
      array_map(
        fn(CloudFiles\Node $node) => $node->getName(),
        empty($folderNode) ? [] : $folderNode->getDirectoryListing(),
      ),
      fn(string $nodeName) => $nodeName != Constants::README_NAME
    );
    $folderContents = array_combine(
      $folderContents,
      array_map(fn($baseName) => pathinfo($baseName, PATHINFO_FILENAME), $folderContents)
    );

    /** @var Collections\Collection $fieldOptions */
    $fieldOptions = $field->getSelectableOptions();
    if ($fieldOptions->count() == 0 && $multiplicity == FieldMultiplicity::SIMPLE) {
      $this->logError('There should be one field-option for field ' . $field->getName() . '@' . $field->getId() . ', but there is none.');
      return $needsFlush;
    }

    /** @var Entities\ProjectParticipantFieldDataOption $fieldOption */
    foreach ($fieldOptions as $fieldOption) {
      if ($multiplicity == FieldMultiplicity::SIMPLE) {
        $fileSystemName = $this->getFileSystemFieldName($field);
      } else {
        $fileSystemName = $this->getFileSystemOptionLabel($fieldOption);
      }
      $fileName = MusicianService::slugifyFileName($fileSystemName, $musician->getUserIdSlug());

      $baseName = array_search($fileName, $folderContents);

      $musicianFieldData = $fieldOption->getMusicianFieldData($musician);
      if ($baseName === false) {
        foreach ($musicianFieldData as $fieldDatum) {
          $this->remove($fieldDatum, hard: true);
          $fieldOption->getFieldData()->removeElement($fieldDatum);
          $needsFlush = true;
        }
      } else {
        if ($musicianFieldData->count() !== 1) {
          foreach ($musicianFieldData as $fieldDatum) {
            $this->remove($fieldDatum, hard: true);
            $field->getFieldData()->removeElement($fieldDatum);
            $fieldOption->getFieldData()->removeElement($fieldDatum);
            $project->getParticipantFieldsData()->removeElement($fieldDatum);
            $musician->getProjectParticipantFieldsData()->removeElement($fieldDatum);
          }
          $project = $field->getProject();
          $fieldDatum = new Entities\ProjectParticipantFieldDatum;
          $fieldDatum->setField($field)
                     ->setDataOption($fieldOption)
                     ->setMusician($musician)
                     ->setProject($project);
          $field->getFieldData()->add($fieldDatum);
          $fieldOption->getFieldData()->set($musician->getId(), $fieldDatum);
          $project->getParticipantFieldsData()->add($fieldDatum);
          $musician->getProjectParticipantFieldsData()->set($fieldDatum->getOptionKey()->getBytes(), $fieldDatum);
          $this->persist($fieldDatum);
          $needsFlush = true;
        } else {
          $fieldDatum = $musicianFieldData->first();
          if ($fieldDatum->isDeleted()) {
            $fieldDatum->setDeleted(null);
            $needsFlush = true;
          }
        }
        if ($fieldDatum->getOptionValue() != $baseName) {
          $fieldDatum->setOptionValue($baseName);
          $needsFlush = true;
        }
        $fieldData[] = $fieldDatum;
      }
    }

    if ($needsFlush && $flush) {
      $this->flush();
    }
    return $needsFlush;
  }

  /**
   * Called via ORM events as pre-remove hook.
   *
   * @param Entities\ProjectParticipantField $field
   *
   * @return void
   */
  public function handleRemoveField(Entities\ProjectParticipantField $field):void
  {
    // check if we have to do something
    if ($field->getDataType() != FieldDataType::CLOUD_FOLDER) {
      return;
    }

    /** @var Entities\ProjectParticipant $participant */
    foreach ($field->getProject()->getParticipants() as $participant) {
      $musician = $participant->getMusician();

      // currently we only remove empty (READMEs are ignored) folders, also in
      // order to mitigate user-errors: if deleting the field in error it can
      // be added again and the files are still there.
      $this->entityManager
        ->registerPreCommitAction(
          new Common\UndoableFolderRemove(fn() => $this->doGetFieldFolderPath($field, $musician), gracefully: true, recursively: false)
        );
    }
  }

  /**
   * Rename all linked cloud-folders as necessary. This is done by registering
   * suitable Common\IUndoable instances for the entity-manager's pre-commit
   * run-queue. In case of an error the change is undone, i.e. the files are
   * renamed back.
   *
   * @param Entities\ProjectParticipantField $field
   *
   * @param string $oldName
   *
   * @param string $newName
   *
   * @return void
   *
   * @todo
   * - perhaps we should do a read-dir instead or additionally
   * - handle "soft-deleted" entities
   */
  public function handleRenameField(Entities\ProjectParticipantField $field, string $oldName, string $newName):void
  {
    if ($oldName == $newName) {
      return;
    }

    $type = $field->getDataType();
    switch ($type) {
      case FieldDataType::CLOUD_FOLDER:
        // We have to rename the folder which is just named after the
        // field-name.
        $mkdir = true;
        break;
      case FieldDataType::CLOUD_FILE:
        switch ($field->getMultiplicity()) {
          case FieldMultiplicity::SIMPLE:
            // The file is name after the field, so we have to rename the file.
            break;
          default:
            // should be FieldMultiplicity::PARALLEL ...  the individual files are
            // named after the option and stored in a sub-folder which is just
            // the field-name, so we have to rename the folder
            $type = FieldDataType::CLOUD_FOLDER;
            $mkdir = false;
            break;
        }
        break;
      case FieldDataType::DB_FILE:
        break;
      default:
        return;
    }

    $softDeleteableState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    /** @var ProjectService $projectService */
    $projectService = $this->di(ProjectService::class);
    $project = $field->getProject();

    /** @var Entities\ProjectParticipant $participant */
    foreach ($field->getProject()->getParticipants() as $participant) {
      $musician = $participant->getMusician();
      $participantsFolder = $projectService->ensureParticipantFolder($project, $musician, true);
      switch ($type) {
        case FieldDataType::CLOUD_FOLDER:
          $oldPath = $participantsFolder . UserStorage::PATH_SEP . $oldName;
          $newPath = $participantsFolder . UserStorage::PATH_SEP . $newName;
          $this->entityManager->registerPreCommitAction(
            new Common\UndoableFolderRename($oldPath, $newPath, gracefully: true, mkdir: $mkdir)
          );
          break;
        case FieldDataType::CLOUD_FILE:
          /** @var Entities\ProjectParticipantFieldDataOption $option */
          foreach ($field->getSelectableOptions(true) as $option) {
            /** @var Entities\ProjectParticipantFieldDatum $datum */
            $datum = $participant->getParticipantFieldsDatum($option->getKey());
            if (!empty($datum)) {
              $extension = pathinfo($datum->getOptionValue(), PATHINFO_EXTENSION);
              $oldBaseName = $projectService->participantFilename($oldName, $musician) . '.' . $extension;
              $newBaseName = $projectService->participantFilename($newName, $musician) . '.' . $extension;
              $oldPath = $participantsFolder . UserStorage::PATH_SEP . $oldBaseName;
              $newPath = $participantsFolder . UserStorage::PATH_SEP . $newBaseName;
              $this->entityManager->registerPreCommitAction(
                new Common\UndoableFileRename($oldPath, $newPath, gracefully: true)
              );
            }
          }
          break;
        case FieldDataType::DB_FILE:
          break;
      }
    }
    if ($type == FieldDataType::DB_FILE) {
      $this->entityManager->registerPreCommitAction(
        new Common\GenericUndoable(function() use ($field) {
          $needsFlush = false;
          /** @var DatabaseStorageFactory $storageFactory */
          $storageFactory = $this->di(DatabaseStorageFactory::class);
          // it is enough to loop over the actually existing entries
          /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
          foreach ($field->getFieldData() as $fieldDatum) {
            /** @var ProjectParticipantsStorage $participantsStorage */
            $participantsStorage = $storageFactory->getProjectParticipantsStorage($fieldDatum->getProjectParticipant());
            // a surrounding transaction should be active ...
            if ($participantsStorage->updateFieldDatumDocument($fieldDatum, flush: false)) {
              $needsFlush = true;
            }
          }
          $needsFlush && $this->flush();
        })
      );
    }

    $softDeleteableState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
  }

  /**
   * @param Entities\ProjectParticipantFieldDataOption $option
   *
   * @param string $oldLabel
   *
   * @param string $newLabel
   *
   * @return void
   */
  public function handleRenameOption(Entities\ProjectParticipantFieldDataOption $option, string $oldLabel, string $newLabel):void
  {
    if ($oldLabel == $newLabel) {
      return;
    }

    $field = $option->getField();
    if ($field->getDataType() != FieldDataType::CLOUD_FILE) {
      return;
    }
    if ($field->getMultiplicity() == FieldMultiplicity::SIMPLE) {
      // name based on field name
      return;
    }

    $softDeleteableState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    /** @var ProjectService $projectService */
    $projectService = $this->di(ProjectService::class);

    /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
    foreach ($option->getFieldData() as $fieldDatum) {
      $musician = $fieldDatum->getMusician();
      $extension = pathinfo($fieldDatum->getOptionValue(), PATHINFO_EXTENSION);
      $oldBaseName = $projectService->participantFilename($oldLabel, $musician) . '.' . $extension;
      $newBaseName = $projectService->participantFilename($newLabel, $musician) . '.' . $extension;

      $this->entityManager->registerPreCommitAction(
        new Common\UndoableFileRename(
          generator: function() use ($oldBaseName, $newBaseName, $field, $musician) {
            // invoke only here s.t. any changes to $field are already there
            $folderPath = $this->doGetFieldFolderPath($field, $musician);
            return [
              $folderPath . UserStorage::PATH_SEP . $oldBaseName,
              $folderPath . UserStorage::PATH_SEP . $newBaseName,
            ];
          },
          gracefully: true)
      );

    }

    $softDeleteableState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
  }
}
