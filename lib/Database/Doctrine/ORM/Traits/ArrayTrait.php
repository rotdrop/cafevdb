<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Traits;

use ReflectionClass;
use ReflectionProperty;
use Exception;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

/** Generic \ArrayAccess implementation for entities. */
trait ArrayTrait
{
  private $keys;

  /**
   * Use reflection inspection to export all of the private keys;
   * automatically called on post-load if the entity used lifecycle-callbacks.
   *
   * @return void
   *
   * @ORM\PostLoad
   */
  protected function arrayCTOR():void
  {
    $this->keys = (new ReflectionClass(__CLASS__))
      ->getProperties(ReflectionProperty::IS_PRIVATE|ReflectionProperty::IS_PROTECTED);

    $this->keys = array_map(function($property) {
      $doc = $property->getDocComment();
      $name = $property->getName();
      if (preg_match('/@ORM\\\\(Column|(Many|One)To(Many|One))/i', $doc)) {
        return $name;
      }
      // also include the untranslated variants of fields
      if (str_starts_with($name, 'untranslated')) {
        return $name;
      }
      return false;
    }, $this->keys);

    unset($this->keys['keys']);
    $this->keys = array_filter($this->keys);
  }

  /** {@inheritdoc} */
  public function __wakeup()
  {
    $this->arrayCTOR();
  }

  /** @return array */
  public function toArray():array
  {
    $result = [];
    foreach ($this->keys as $key) {
      $result[$key] = $this->offsetGet($key);
    }
    return $result;
  }

  /** {@inheritdoc} */
  public function offsetExists($offset):bool
  {
    if (empty($this->keys)) {
      $this->arrayCTOR();
    }
    return is_array($this->keys) && in_array(self::offsetNormalize($offset), $this->keys);
  }

  /** {@inheritdoc} */
  public function offsetGet($offset)
  {
    if (!$this->offsetExists($offset)) {
      throw new Exception('Offset '.self::offsetNormalize($offset).' does not exist in '.__CLASS__.', keys '.print_r($this->keys, true));
    }
    $method = self::methodName('get', $offset);
    if (!method_exists($this, $method)) {
      throw new Exception('Method '.$method.' does not exist in '.__CLASS__.', please implement it.');
    }
    return $this->$method();
  }

  /** {@inheritdoc} */
  public function offsetSet($offset, $value):void
  {
    if (!$this->offsetExists($offset)) {
      throw new Exception('Offset '.self::offsetNormalize($offset).' does not exist in '.__CLASS__.', keys '.print_r($this->keys, true));
    }
    $method = self::methodName('set', $offset);
    if (!method_exists($this, $method)) {
      throw new Exception('Method '.$method.' does not exist in '.__CLASS__.', please implement it.');
    }
    $this->$method($value);
  }

  /** {@inheritdoc} */
  public function offsetUnset($offset):void
  {
    $this->offsetSet($offset, null);
  }

  /**
   * @param string $prefix
   *
   * @param string $offset
   *
   * @return string
   */
  private static function methodName(string $prefix, string $offset):string
  {
    return $prefix . ucfirst(self::offsetNormalize($offset));
  }

  /**
   * @param string $offset
   *
   * @return string
   */
  private static function offsetNormalize(string $offset):string
  {
    $words = explode('_', $offset);
    if ($words[0] == strtoupper($words[0])) {
      $words[0] = strtolower($words[0]);
    }
    $words = array_map('ucfirst', $words);
    return lcfirst(implode('', $words));
  }
}
