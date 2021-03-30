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

use Ramsey\Uuid\Uuid;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumExtraFieldMultiplicity as Multiplicity;
use OCA\CAFEVDB\Common\Util;

use OCA\CAFEVDB\Service\Finance\DoNothingReceivablesGenerator;

/**
 * General support service, kind of inconsequent glue between
 * Doctrine\ORM and CAFEVDB\PageRenderer.
 */
class ProjectExtraFieldsService
{
  const UNSUPPORTED = [
    'simple' => [ 'boolean', ],
    'single' => [],
    'multiple' => [ 'boolean', ],
    'parallel' => [ 'boolean', ],
    'recurring' => [ 'boolean' ],
    'groupofpeople' => [], // like single
    'groupsofpeople' => [ 'boolean', ], // like multiple
  ];
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

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
    ];
  }

  /**
   * Fetch all monetary fields for the given project.
   *
   * @param $project
   */
  public function monetaryFields(Entities\Project $project)
  {
    $extraFields = $project['extraFields'];

    $monetary = [];
    foreach ($extraFields as $field) {
      switch ($field['dataType']) {
      case 'service-fee':
      case 'deposit':
        $monetary[$field['id']] = $field;
        break;
      }
    }
    return $monetary;
  }

  /**
   * Internal function: given a surcharge choice compute the
   * associated amount of money and return that as float.
   *
   * @key string|null $key Key from the extra-fields data table. $key may
   * be a comma-separated list of keys.
   *
   * @param string|null $value Value form the extra-fields data table
   *
   * @param Entities\ProjectExtraField $extraField Field definition.
   */
  public function extraFieldSurcharge(?string $key, ?string $value, Entities\ProjectExtraField $extraField)
  {
    switch ($extraField->getMultiplicity()) {
    case Multiplicity::SIMPLE():
      return (float)$value;
    case Multiplicity::GROUPOFPEOPLE():
      if (empty($key)) {
        break;
      }
      return (float)$extraField->getDataOptions()->first()['data'];
    case Multiplicity::SINGLE():
      if (empty($key)) {
        break;
      }
      /** @var Entities\ProjectExtraFieldDataOption */
      $dataOption = $extraField->getDataOptions()->first();
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
      foreach ($extraField->getDataOptions() as $dataOption) {
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
      foreach($extraField->getDataOptions() as $dataOption) {
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
      foreach($extraField->getDataOptions() as $dataOption) {
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
   * Return the row with the key matching the argument $key.
   *
   * @param sting $key Allowed values key to search for
   *
   * @param array $values Exploded allowed values data.
   *
   * @return null|array The matching row if found or null.
   */
  public static function findAllowedValue($key, array $values):?array
  {
    $rows = array_filter($values, function($row) use ($key) {
      return $row['key'] === $key;
    });
    return count($rows) == 1 ? array_shift($rows) : null;
  }

  /**
   * Prototype for allowed values, i.e. multiple-value options.
   */
  public static function allowedValuesPrototype()
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
  public function explodeAllowedValues($values, $addProto = true, $trimInactive = false)
  {
    $options = empty($values) ? [] : (is_array($values) ? $values : json_decode($values, true));
    if (is_string($options)) {
      // perhaps double encoded, try ...
      $options = json_decode($options, true);
    }
    if (is_string($values) && !empty($values) && empty($options)) {
      $options = [
        array_merge(
          $this->allowedValuesPrototype(),
          [ 'key' => Uuid::uuid1(), 'label' => $values, ]),
      ];
    }
    if (isset($options[-1])) {
      throw new \Exception(
        $this->l->t('Option index -1 should not be present here, options: %s',
                    print_r($options, true)));
    }
    $options = array_values($options);
    $protoType = $this->allowedValuesPrototype();
    $protoKeys = array_keys($protoType);
    foreach ($options as $index => &$option) {
      $keys = array_keys($option);
      if ($keys !== $protoKeys) {
        throw new \InvalidArgumentException(
          $this->l->t('Prototype keys "%s" and options keys "%s" differ',
                      [ implode(',', $protoKeys), implode(',', $keys) ])
        );
      }
      if ($trimInactive && $option['disabled'] === true) { //  @todo check for string boolean conversion
        unset($option);
      }
    }
    if ($addProto) {
      $options[] = $this->allowedValuesPrototype();
    }
    return $options;
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
  public function implodeAllowedValues($options)
  {
    if (isset($options[-1])) {
      throw new \Exception($this->l->t('Option index -1 should not be present here, options: %s', print_r($options, true)));
    }
    $proto = $this->allowedValuesPrototype();
    foreach ($options as &$option) {
      $option = array_merge($proto, $option);
      if (empty($option['key'])) {
        $option['key'] = Uuid::uuid1();
      }
    }
    return json_encode($options);
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
