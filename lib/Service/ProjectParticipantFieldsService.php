<?php
/* Orchestra member, musician and project management application.
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

namespace OCA\CAFEVDB\Service;

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as Multiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as DataType;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Common\Functions;

use OCA\CAFEVDB\Service\Finance\ReceivablesGeneratorFactory;
use OCA\CAFEVDB\Service\Finance\DoNothingReceivablesGenerator;
use OCA\CAFEVDB\Service\Finance\PeriodicReceivablesGenerator;
use OCA\CAFEVDB\Service\Finance\InstrumentInsuranceReceivablesGenerator;
use OCA\CAFEVDB\Service\Finance\MembershipFeesReceivablesGenerator;

use OCA\CAFEVDB\Common\GenericUndoable;
use OCA\CAFEVDB\Common\UndoableFolderRename;
use OCA\CAFEVDB\Common\UndoableFileRename;
use OCA\CAFEVDB\Common\IUndoable;

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
    Multiplicity::SIMPLE => [
      DataType::BOOLEAN,
    ],
    Multiplicity::SINGLE => [
      DataType::CLOUD_FILE,
      DataType::DB_FILE,
      DataType::CLOUD_FOLDER,
    ],
    Multiplicity::MULTIPLE => [
      DataType::BOOLEAN,
      DataType::CLOUD_FILE,
      DataType::DB_FILE,
      DataType::CLOUD_FOLDER,
    ],
    Multiplicity::PARALLEL => [
      DataType::BOOLEAN,
      DataType::CLOUD_FOLDER,
    ],
    Multiplicity::RECURRING => [
      DataType::BOOLEAN,
      // DataType::TEXT,
      DataType::HTML,
      DataType::INTEGER,
      DataType::FLOAT,
      // DataType::DATE,
      // DataType::DATETIME,
      DataType::CLOUD_FILE,
      DataType::DB_FILE,
      DataType::CLOUD_FOLDER,
    ],
    Multiplicity::GROUPOFPEOPLE => [
      DataType::BOOLEAN,
      DataType::CLOUD_FILE,
      DataType::DB_FILE,
      DataType::CLOUD_FOLDER,
    ],
    Multiplicity::GROUPSOFPEOPLE => [
      DataType::BOOLEAN,
      DataType::CLOUD_FILE,
      DataType::DB_FILE,
      DataType::CLOUD_FOLDER,
    ],
  ];

  /** @var EntityManager */
  protected $entityManager;

  public function __construct(
    ConfigService $configService
    , EntityManager $entityManager
  ) {
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->l = $this->l10n();
  }

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

  public function resolveReceivableGenerator($value)
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
      throw new \RuntimeException($this->l->t('Generator "%s" does not appear to be valid PHP class name.', $value));
    }
    $this->di($value); // try to actually find it

    return $value; // return resolved class-name if we made it until here
  }

  /**
   * Fetch all monetary fields for the given project.
   *
   * @param $project
   */
  public function monetaryFields(Entities\Project $project)
  {
    // $participantFields = $project['participantFields'];

    // $monetary = [];
    // foreach ($participantFields as $field) {
    //   switch ($field['dataType']) {
    //   case DataType::SERVICE_FEE:
    //     $monetary[$field['id']] = $field;
    //     break;
    //   }
    // }
    // return $project->getParticipantFields()->matching(DBUtil::criteriaWhere([
    //   'dataType' => (string)DataType::SERVICE_FEE,
    // ]));
    return $project->getParticipantFields()->filter(function($field) {
      return $field->getDataType() == DataType::SERVICE_FEE;
    });
  }

  /**
   * Fetch all generator fields for the given project.
   *
   * @param $project
   */
  public function generatedFields(Entities\Project $project)
  {
    // return $project->getParticipantFields()->matching(DBUtil::criteriaWhere([
    //   'multiplicity' => Multiplicity::RECURRING
    // ]));
    return $project->getParticipantFields()->filter(function($field) {
      return $field->getMultiplicity() == Multiplicity::RECURRING;
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
   * parts. Note that options with Multiplicity::RECURRING are always
   * split.
   */
  public function monetarySelectOptions(Entities\Project $project, bool $distinct = false)
  {
    $nonRecurringGroup = $this->l->t('One-time Receivables');
    $selectOptions = [];
    /** @var Entities\ProjectParticipantField $field */
    foreach ($this->monetaryFields($project) as $field) {
      switch ($field->getMultiplicity()) {
      case Multiplicity::SIMPLE:
      case Multiplicity::SINGLE:
        // always only a single option
        $selectOptions[] = [
          'group' => $nonRecurringGroup,
          'name' => $field->getName(),
          'value' => $field->getId(), // list of keys?
          'data' => [], // relevant data from field definition
        ];
        break;
      case Multiplicity::MULTIPLE:
      case Multiplicity::GROUPOFPEOPLE:
      case Multiplicity::GROUPSOFPEOPLE:
      case Multiplicity::PARALLEL:
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
      case Multiplicity::RECURRING:
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
    return $selectOptions;
  }

  /**
   * Given a musician and a project, compute the total fees to invoice
   * or to pay, and the amounts already paid. The sign convention is
   * such that a positive sign means that the orchestra earns money, a
   * negative sign means that the participant earns money.
   *
   * If not project is given compute the totals for all projects.
   */
  static public function participantMonetaryObligations(Entities\Musician $musician, ?Entities\Project $project = null)
  {
    $obligations = [
      'sum' => 0.0, // total sum
      'received' => 0.0, // sum of payments
    ];

    if (empty($project)) {
      /** @var Entities\ProjectParticipant $projectParticipant */
      foreach ($musician->getProjectParticipation() as $projectParticipant) {
        list($sum, $received) = self::participantMonetaryObligations($musician, $projectParticipant->getProject());
        $obligations['sum'] += $sum;
        $obligations['received'] += $received;
      }
      return $obligations;
    }

    $projectParticipant = $musician->getProjectParticipantOf($project);
    /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
    foreach ($projectParticipant->getParticipantFieldsData() as $fieldDatum) {
      $fieldDataType = $fieldDatum->getField()->getDataType();
      if ($fieldDataType != DataType::SERVICE_FEE) {
        continue;
      }
      $obligations['sum'] += $fieldDatum->amountPayable();
      $obligations['received'] += $fieldDatum->amountPaid();
    }

    return $obligations;
  }

  /**
   * Internal function: given a surcharge choice compute the
   * associated amount of money and return that as float.
   *
   * @key string|null $key Key from the participant-fields data table. $key may
   * be a comma-separated list of keys.
   *
   * @param string|null $value Value form the participant-fields data table
   *
   * @param Entities\ProjectParticipantField $participantField Field definition.
   */
  public function participantFieldSurcharge(?string $key, ?string $value, Entities\ProjectParticipantField $participantField)
  {
    switch ($participantField->getMultiplicity()) {
    case Multiplicity::SIMPLE():
      return (float)$value;
    case Multiplicity::GROUPOFPEOPLE():
      if (empty($key)) {
        break;
      }
      return (float)$participantField->getDataOptions()->first()['data'];
    case Multiplicity::SINGLE():
      if (empty($key)) {
        break;
      }
      /** @var Entities\ProjectParticipantFieldDataOption */
      $dataOption = $participantField->getDataOptions()->first();
      // Non empty value means "yes".
      if ((string)$dataOption['key'] != $key) {
        $this->logWarn('Stored value "'.$key.'" unequal to stored key "'.$dataOption['key'].'"');
      }
      return (float)$dataOption['data'];
    case Multiplicity::GROUPSOFPEOPLE():
    case Multiplicity::MULTIPLE():
      if (empty($key)) {
        break;
      }
      foreach ($participantField->getDataOptions() as $dataOption) {
        if ((string)$dataOption['key'] == $key) {
          return (float)$dataOption['data'];
        }
      }
      $this->logError('No data item for multiple choice key "'.$key.'"');
      return 0.0;
    case Multiplicity::PARALLEL():
      if (empty($key)) {
        break;
      }
      $keys = Util::explode(',', $key);
      $found = false;
      $amount = 0.0;
      foreach($participantField->getDataOptions() as $dataOption) {
        if (array_search((string)$dataOption['key'], $keys) !== false) {
          $amount += (float)$dataOption['data'];
          $found = true;
        }
      }
      if (!$found) {
        $this->logError('No data item for parallel choice key "'.$key.'"');
      }
      return $amount;
    case Multiplicity::RECURRING():
      if (empty($key) || empty($value)) {
        break;
      }
      // $keys = Util::explode(',', $key);
      $values = Util::explodeIndexed($value);

      $amount = 0.0;
      foreach ($values as $key => $value) {
        $amount += $value;
      }
      return $amount;
    }
    return 0.0;
  }

  /**
   * Determine whether a multiplicity-type combination is supported.
   */
  public static function isSupportedType(string $multiplicity, string $type):bool
  {
    return isset(self::UNSUPPORTED[$multiplicity]) && empty(self::UNSUPPORTED[$multiplicity][$type]);
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
   * Return the cloud-folder name for the given $field which must be of
   * type DataType::CLOUD_FOLDER or DataType::CLOUD_FILE.
   */
  public function getFieldFolderPath(Entities\ProjectParticipantFieldDatum $datum):?string
  {
    $field = $datum->getField();

    switch ($field->getDataType()) {
    case DataType::CLOUD_FOLDER: {
      /** @var UserStorage $userStorage */
      $userStorage = $this->di(UserStorage::class);

      /** @var ProjectService $projectService */
      $projectService = $this->di(ProjectService::class);

      $participantFolder = $projectService->ensureParticipantFolder($field->getProject(), $datum->getMusician());

      return $participantFolder . UserStorage::PATH_SEP . $field->getUntranslatedName();
    }
    case DataType::CLOUD_FILE: {
     /** @var UserStorage $userStorage */
      $userStorage = $this->di(UserStorage::class);

      /** @var ProjectService $projectService */
      $projectService = $this->di(ProjectService::class);

      $participantFolder = $projectService->ensureParticipantFolder($field->getProject(), $datum->getMusician());

      $subDirPrefix = ($field->getMultiplicity() == Multiplicity::SIMPLE)
                    ? ''
                    : UserStorage::PATH_SEP . $field->getUntranslatedName();

      return $participantFolder . $subDirPrefix;
    }
    }
    return null;
  }

  /**
   * Return the effective value of the given datum. In particular
   * referenced files are returned as cloud file-node or DB
   * file-entity. Dates are converted to \DateTimeImmutable. Float
   * values to float, int to int, boolean to boolean.
   *
   * @return mixed
   */
  public function getEffectiveFieldDatum(Entities\ProjectParticipantFieldDatum $datum)
  {
    $value = $datum->getEffectiveValue();

    /** @var Entities\ProjectParticipantField $field */
    $field = $datum->getField();

    switch ($field->getDataType()) {
    case DataType::BOOLEAN:
      return boolval($value);
    case DataType::DATE:
    case DataType::DATETIME:
      return Util::convertToDateTime($value);
    case DataType::FLOAT:
    case DataType::SERVICE_FEE:
      return floatval($value);
    case DataType::INTEGER:
      return intval($value);
    case DataType::CLOUD_FILE:
      $folderPath = $this->getFieldFolderPath($datum);
      $filePath = $folderPath . UserStorage::PATH_SEP . $value;
      return $userStorage = $this->di(UserStorage::class)->getFile($filePath);
    case DataType::CLOUD_FOLDER:
      $folderPath = $this->getFieldFolderPath($datum);
      return $this->di(UserStorage::class)->getFolder($folderPath);
    case DataType::DB_FILE:
      return $this->getDatabaseRepository(Entities\EncryptedFile::class)->find($value);
    case DataType::TEXT:
    case DataType::HTML:
    default:
      return $value;
    }
  }

  /**
   * Return the row with the key matching the argument $key.
   *
   * @param sting $key Allowed values key to search for
   *
   * @param array $values Exploded allowed values data.
   *
   * @return null|array The matching row if found or null.
   */
  public static function findDataOption($key, array $values):?array
  {
    return $values[$key]?:null;
  }

  /**
   * Prototype for allowed values, i.e. multiple-value options.
   */
  public static function dataOptionPrototype()
  {
    return [
      'key' => false,
      'label' => false,
      'data' => false,
      'deposit' => false,
      'limit' => false,
      'tooltip' => false,
      'deleted' => false,
    ];
  }

  /**
   * Explode the given json encoded string into a PHP array.
   */
  public function explodeDataOptions($values, $addProto = true, $trimInactive = false)
  {
    $options = empty($values) ? [] : (is_array($values) ? $values : json_decode($values, true));
    if (is_string($options)) {
      // perhaps double encoded, try ...
      $options = json_decode($options, true);
    }
    if (is_string($values) && !empty($values) && empty($options)) {
      $options = [
        array_merge(
          $this->dataOptionPrototype(),
          [ 'key' => Uuid::create(), 'label' => $values, ]),
      ];
    }
    if (isset($options[-1])) {
      throw new \Exception(
        $this->l->t('Option index -1 should not be present here, options: %s',
                    print_r($options, true)));
    }
    $options = array_values($options);
    $protoType = $this->dataOptionPrototype();
    $protoKeys = array_keys($protoType);
    $result = [];
    foreach ($options as $option) {
      $keys = array_keys($option);
      if ($keys !== $protoKeys) {
        throw new \InvalidArgumentException(
          $this->l->t('Prototype keys "%s" and options keys "%s" differ',
                      [ implode(',', $protoKeys), implode(',', $keys) ])
        );
      }
      if ($trimInactive && !empty($option['deleted'])) {
        continue;
      }
      $result[$option['key']] = $option;
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
   *
   * as JSON for storing in the database. As a side-effect missing
   * keys are generated and empty missing fields are inserted.
   *
   * @param array As explained above.
   *
   * @return string JSON encoded data.
   */
  public function implodeDataOptions($options)
  {
    if (isset($options[-1])) {
      throw new \Exception($this->l->t('Option index -1 should not be present here, options: %s', print_r($options, true)));
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
   * Just forward to Repositories\ProjectParticipantFieldsRepository
   */
  public function find($id):?Entities\ProjectParticipantField
  {
    return $this->getDatabaseRepository(Entities\ProjectParticipantField::class)->find($id);
  }

  /**
   * Just forward to Repositories\ProjectParticipantFieldsRepository
   */
  public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null):Collection
  {
    return $this->getDatabaseRepository(Entities\ProjectParticipantField::class)
                ->findBy($criteria, $orderBy, $lmit, $offset);
  }

  /**
   * For the given Multiplicity::GROUPOFPEOPLE or
   * Multiplicity::GROUPSOFPEOPLE option find the group members.
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
    } else if ($groupOptionProvider instanceof Entities\ProjectParticipantFieldDataOption) {
      $groupKey = $groupOptionProvider->getKey();
    }

    $groupField = $groupOptionProvider->getField();
    $multipliciy = $groupField->getMultiplicity();
    if ($multipliciy != Multiplicity::GROUPOFPEOPLE
        && $multipliciy != Multiplicity::GROUPSOFPEOPLE) {
      throw new \RuntimeException(
        $this->l->t('Field "%s" is not a group of participants field.', $groupField->getName()));
    }

    // @todo The getBytes() MUST NOT BE NECESSARY
    $groupMembers = $this->getDatabaseRepository(Entities\ProjectParticipantFieldDatum::class)
                         ->findBy([ 'optionKey' => $groupKey ]);

    return $groupMembers;
  }

  /**
   * Find the members of all groups for the given option, indexed by the option-key.
   */
  public function findGroupMembers(Entities\ProjectParticipantField $groupField)
  {
    $multipliciy = $groupField->getMultiplicity();
    if ($multipliciy != Multiplicity::GROUPOFPEOPLE
        && $multipliciy != Multiplicity::GROUPSOFPEOPLE) {
      throw new \RuntimeException(
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
   * Select a field with matching names from a collection of fields
   */
  public function filterByFieldName(Collections\Collection $things, string $fieldName, $singleResult = true)
  {
    if ($things->count() == 0) {
      return $singleResult ? null :  new Collections\ArrayCollection;
    }
    $fieldNames = $this->translationVariants($fieldName);
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
    } else if ($things->first() instanceof Entities\ProjectParticipantFieldDataOption) {
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
    } else if ($things->first() instanceof Entities\ProjectParticipantFieldDatum) {

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
      throw new \RuntimeException($this->l->t('Only "field" collections can be filtered, not instances of "%s".', get_class($things->first())));
    }
    if ($singleResult) {
      if ($remaining->count() == 1) {
        return $remaining->first();
      } else if ($remaining->count() == 0) {
        return null;
      }
    }
    return $remaining;
  }

  /**
   * Create a field with given name and type. The field returned is not yet persisted.
   */
  public function createField($name, Multiplicity $multiplicity, DataType $dataType, $tooltip = null):?Entities\ProjectParticipantField
  {
    if ($multiplicity != Multiplicity::SIMPLE) {
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
  }

  /**
   * Delete the given field, taking default values and soft-deletion into account.
   *
   * @param int|Entities\ProjectParticipantField $fieldOrId
   *
   * @return bool true if the field was really deleted, false if it
   * was kept.
   *
   * @todo We might want to remove "side-effects", i.e. data-base files and cloud files.
   */
  public function deleteField($fieldOrId)
  {
    if (!($fieldOrId instanceof Entities\ProjectParticipantField)) {
      $field = $this->getDatabaseRepository(Entities\ProjectParticipantField::class)->find($fieldOrId);
      if (empty($field)) {
        throw new \RuntimeException($this->l->t('Unable to find participant field for id "%s"', $pme->rec));
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
   * Rename all linked cloud-folders as necessary. This is done by registering
   * suitable IUndoable instances for the entity-manager's pre-commit
   * run-queue. In case of an error the change is undone, i.e. the files are
   * renamed back.
   *
   * @todo
   * - perhaps we should do a read-dir instead or additionally
   * - handle "soft-deleted" entities
   */
  public function handleRenameField(Entities\ProjectParticipantField $field, string $oldName, string $newName)
  {
    switch ($field->getDataType()) {
      case DataType::CLOUD_FOLDER:
        // We have to rename the folder which is just named after the
        // field-name.
        $type = 'folder';
        break;
      case DataType::CLOUD_FILE:
        switch ($field->getMultiplicity()) {
          case Multiplicity::SIMPLE:
            // The file is name after the field, so we have to rename the file.
            $type = 'file';
            break;
          default:
            // should be Multiplicity::PARALLEL ...  the individual files are
            // named after the option and stored in a sub-folder which is just
            // the field-name, so we have to rename to folder
            $type = 'folder';
            break;
        }
        break;
      default:
        return;
    }

    $softDeleteableState = $this->disableFilter('soft-deleteable');

    /** @var ProjectService $projectService */
    $projectService = $this->di(ProjectService::class);
    $project = $field->getProject();

    /** @var Entities\ProjectParticipant $participant */
    foreach ($field->getProject()->getParticipants() as $participant) {
      $musician = $participant->getMusician();
      $participantsFolder = $projectService->ensureParticipantFolder($project, $musician, true);
      if ($type == 'folder') {
        $oldPath = $participantsFolder . UserStorage::PATH_SEP . $oldName;
        $newPath = $participantsFolder . UserStorage::PATH_SEP . $newName;
        $this->entityManager->registerPreCommitAction(
          new UndoableFolderRename($oldPath, $newPath, true /* gracefully */)
        );
      } else { // 'file'
        /** @var Entities\ProjectParticipantFieldDataOption $option */
        foreach ($field->getSelectableOptions(true) as $option) {
          /** @var Entities\ProjectParticipantFieldDatum $datum */
          $datum = $participant->getParticipantFieldsDatum($option->getKey());
          if (!empty($datum)) {
            $extension = pathinfo($datum->getOptionValue(), PATHINFO_EXTENSION);
            $oldBaseName = $projectService->participantFilename($oldName, $project, $musician) . '.' . $extension;
            $newBaseName = $projectService->participantFilename($newName, $project, $musician) . '.' . $extension;
            $oldPath = $participantsFolder . UserStorage::PATH_SEP . $oldBaseName;
            $newPath = $participantsFolder . UserStorage::PATH_SEP . $newBaseName;
            $this->entityManager->registerPreCommitAction(
              new UndoableFileRename($oldPath, $newPath, gracefully: true)
            );
          }
        }
      }
    }

    $softDeleteableState && $this->enableFilter('soft-deleteable');
  }

  public function handleRenameOption(Entities\ProjectParticipantFieldDataOption $option, string $oldLabel, string $newLabel)
  {
    $field = $option->getField();
    if ($field->getDataType() != DataType::CLOUD_FILE) {
      return;
    }
    if ($field->getMultiplicity() == Multiplicity::SIMPLE) {
      // name based on field name
      return;
    }

    $softDeleteableState = $this->disableFilter('soft-deleteable');

    /** @var ProjectService $projectService */
    $projectService = $this->di(ProjectService::class);
    $project = $field->getProject();

    /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
    foreach ($option->getFieldData() as $fieldDatum) {
      $musician = $fieldDatum->getMusician();
      $participantsFolder = $projectService->ensureParticipantFolder($project, $musician, true);
      $extension = pathinfo($fieldDatum->getOptionValue(), PATHINFO_EXTENSION);
      $oldBaseName = $projectService->participantFilename($oldLabel, $project, $musician) . '.' . $extension;
      $newBaseName = $projectService->participantFilename($newLabel, $project, $musician) . '.' . $extension;
      $oldPath = $participantsFolder . UserStorage::PATH_SEP
        . '%s' . UserStorage::PATH_SEP
        . $oldBaseName;
      $newPath = $participantsFolder . UserStorage::PATH_SEP
        . '%s' . UserStorage::PATH_SEP
        . $newBaseName;

      $this->entityManager->registerPreCommitAction(
        new UndoableFileRename(
          generator: function() use ($oldPath, $newPath, $field) {
            return [
              sprintf($oldPath, $field->getUntranslatedName()),
              sprintf($newPath, $field->getUntranslatedName()),
            ];
          },
          gracefully: true)
      );

    }

    $softDeleteableState && $this->enableFilter('soft-deleteable');
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
