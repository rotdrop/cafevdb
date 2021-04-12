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

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as Multiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as DataType;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;

use OCA\CAFEVDB\Service\Finance\DoNothingReceivablesGenerator;
use OCA\CAFEVDB\Service\Finance\AlwaysReceivablesGenerator;

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
      DataType::FILE_DATA,
    ],
    Multiplicity::MULTIPLE => [
      DataType::BOOLEAN,
      DataType::FILE_DATA,
    ],
    Multiplicity::PARALLEL => [
      DataType::BOOLEAN,
    ],
    Multiplicity::RECURRING => [
      DataType::BOOLEAN,
      DataType::TEXT,
      DataType::HTML,
      DataType::INTEGER,
      DataType::FLOAT,
      DataType::DATE,
      DataType::DATETIME,
      DataType::DEPOSIT,
      DataType::FILE_DATA,
    ],
    Multiplicity::GROUPOFPEOPLE => [
      DataType::BOOLEAN,
      DataType::FILE_DATA,
    ],
    Multiplicity::GROUPSOFPEOPLE => [
      DataType::BOOLEAN,
      DataType::FILE_DATA,
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
  static public function recurringReceivablesGenerators()
  {
    return [
      DoNothingReceivablesGenerator::class,
      AlwaysReceivablesGenerator::class,
    ];
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
    //   case DataType::DEPOSIT:
    //     $monetary[$field['id']] = $field;
    //     break;
    //   }
    // }
    return $project->getParticipantFields()->matching(DBUtil::criteriaWhere([
      'dataType' => [ DataType::SERVICE_FEE, DataType::DEPOSIT ],
    ]));
  }

  /**
   * Generate a drop-down select for service-fees to be used when
   * generating debit notes. Only recurring fees are split into single
   * items (we think hear of recurring fees on a yearly basis), which
   * all other sub-options can only be charged together.
   *
   *
   */
  public function monetarySelectOptions(Entities\Project $project)
  {
    $nonRecurringGroup = $this->l->t('One-time Receivables');
    $selectOptions = [];
    /** @var Entities\ProjectParticipantField $field */
    foreach ($this->monetaryFields($project) as $field) {
      switch ($field->getMultiplicity()) {
      case Multiplicity::SIMPLE:
      case Multiplicity::SINGLE:
      case Multiplicity::MULTIPLE:
      case Multiplicity::GROUPOFPEOPLE:
      case Multiplicity::GROUPSOFPEOPLE:
      case Multiplicity::PARALLEL:
        $selectionOptions[] = [
          'group' => $nonRecurringGroup,
          'name' => $field->getName(),
          'value' => '', // list of keys?
          'data' => [], // relevant data from field definition
        ];
        // only a single option
        break;
      case Multiplicity::RECURRING:
        // option groups with multiple options
        $group = $field->getName();
        /** @var Entities\ProjectParticipantFieldDataOption $option */
        foreach ($field->getSelectableOptions() as $option) {
          $selectionOptions[] = [
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
      'tooltip' => false,
      'limit' => false,
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

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
