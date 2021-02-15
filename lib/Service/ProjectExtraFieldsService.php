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
        //$allowed = $this->explodeAllowedValues($field['allowed_values'], false, true);
        $monetary[$field['id']] = $field;
        break;
      }
    }
    return $monetary;
  }

  /**
   * Internal function: given a (multi-select) surcharge choice
   * compute the associated amount of money and return that as float.
   *
   * @param string $value Value for the extra-fields table
   *
   * @param array $allowedValues Allowed-values array from the field
   * definition.
   *
   * @parma string $multiplicity Multiplicity value from the
   * field-definition as defined in
   * OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumExtraFieldMultiplicity.
   */
  public function extraFieldSurcharge($value, $allowedValues, $multiplicity)
  {
    if (!is_array($allowedValues)) {
      $allowedValues = $this->explodeAllowedValues($allowedValues);
    }
    //error_log('value '.$value);
    switch ($multiplicity) {
    case 'simple':
      return (float)$value;
    case 'groupofpeople':
    case 'single':
      // Non empty value means "yes".
      $key = $allowedValues[0]['key'];
      if ($key !== $value) {
        $this->logWarn('Stored value "'.$value.'" unequal to stored key "'.$key.'"');
      }
      return (float)$allowedValues[0]['data'];
    case 'groupsofpeople':
    case 'multiple':
      foreach($allowedValues as $item) {
        if ($item['key'] === $value) {
          return (float)$item['data'];
        }
      }
      $this->logError('No data item for multiple choice key "'.$value.'"');
      return 0.0;
    case 'parallel':
      $keys = Util::explode(',', $value);
      $found = false;
      $amount = 0.0;
      foreach($allowedValues as $item) {
        if (array_search($item['key'], $keys) !== false) {
          $amount += (float)$item['data'];
          $found = true;
        }
      }
      if (!$found) {
        $this->logError('No data item for parallel choice key "'.$value.'"');
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
   * Prototype for allowed values, i.e. multiple-value options.
   */
  public static function allowedValuesPrototype()
  {
    return [
      'key' => false,
      'label' => false,
      'data' => false,
      'tooltip' => false,
      'flags' => 'active',
      'limit' => false,
    ];
  }

  /**
   * Explode the given json encoded string into a PHP array.
   */
  public function explodeAllowedValues($values, $addProto = true, $trimInactive = false)
  {
    $options = empty($values) ? [] : (is_array($values) ? $values : json_decode($values, true));
    if (is_string($values) && !empty($values) && empty($options)) {
      $options = [
        array_merge(
          $this->allowedValuesPrototype(),
          [ 'key' => Uuid::uuid1(), 'label' => $values, ]),
      ];
    }
    if (isset($options[-1])) {
      throw new \Exception($this->l->t('Option index -1 should not be present here, options: %s', print_r($options, true)));
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

    /**
     * Make keys unique for multi-choice fields.
     *
     * @param array Item as return by self::explodeAllowedValues.
     *
     * @param array $keys Existing keys.
     *
     * @return Something "close" to $key, but not contained in $keys.
     *
     * @note ATM we use UUIDs. This function is a no-op.
     */
  public function allowedValuesUniqueKey($item, $keys)
  {
    return $item['key'];
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
