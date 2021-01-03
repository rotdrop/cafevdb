<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library se Doctrine\ORM\Tools\Setup;is free software; you can redistribute it and/or
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Traits;

use Doctrine\ORM\Mapping as ORM;

trait ArrayTrait
{
  private $keys;

  /**
   * Use reflection inspection to export all of the private keys;
   * automatically called on post-load.
   *
   * @ORM\PostLoad
   */
  protected function arrayCTOR() {
    $this->keys = (new \ReflectionClass(__CLASS__))
                ->getProperties(\ReflectionProperty::IS_PRIVATE);

    $this->keys = array_map(function($property) {
      $doc = $property->getDocComment();
      $name = $property->getName();
      if (preg_match('/@ORM\\\\(Column|(Many|One)To(Many|One))/', $doc)) {
        return $name;
      }
      return false;
    }, $this->keys);

    unset($this->keys['keys']);
    $this->keys = array_filter($this->keys);
  }

  public function offsetExists($offset):bool {
    if (empty($this->keys)) {
      $this->arrayCTOR();
    }
    return is_array($this->keys) && in_array(self::offsetNormalize($offset), $this->keys);
  }

  public function offsetGet($offset) {
    if ($this->offsetExists($offset)) {
      $method = self::methodName('get', $offset);
      return $this->$method();
    }
    return null;
  }

  public function offsetSet($offset, $value):void
  {
    if ($this->offsetExists($offset)) {
      $method = self::methodName('set', $offset);
      $this->$method($value);
    } else {
      throw new \Exception("Offset ".self::offsetNormalize($offset)." does not exist, keys ".print_r($this->keys, true));
    }
  }

  public function offsetUnset($offset):void
  {
    $this->offsetSet($offset, null);
  }

  private static function methodName($prefix, $offset) {
    return $prefix . ucfirst(self::offsetNormalize($offset));
  }

  private static function offsetNormalize($offset)
  {
    return lcfirst(str_replace('_', '', ucwords($offset, '_')));
  }
}
