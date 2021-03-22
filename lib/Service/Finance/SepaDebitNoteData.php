<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Service\Finance;

/**
 * Not a debit-node database row, just something to sort out what is
 * needed to export debit-notes for submission to a bank.
 */
class SepaDebitNoteData implements \ArrayAccess
{
  private $keys;

  private $bic;
  private $iban;
  private $amount;
  private $bankAccountOwner;
  private $mandateReference;
  private $mandateDate;
  private $mandateDebitorName;
  private $mandateSequenceType;
  private $purpose;

  public function __construct()
  {
    $this->keys = (new \ReflectionClass(__CLASS__))
                ->getProperties(\ReflectionProperty::IS_PRIVATE|\ReflectionProperty::IS_PROTECTED);
    unset($this->keys['keys']);
    $this->keys = array_filter($this->keys);
  }

  public function __call($method, $args)
  {
    if (substr($method, 0, 3) === 'set') {
      $property = lcfirst(substr($method, 3));
      if (in_array($property, $this->keys) && count($args) == 1) {
        $this->property = $args[0];
        return $this;
      }
    } else if (substr($method, 0, 3) === 'get') {
      $property = lcfirst(substr($method, 3));
      if (in_array($property, $this->keys)) {
        return $this->$property;
      }
    }
    throw new \RuntimeException('Method '.$method.' does not exist.');
  }

  public function offsetExists($offset):bool
  {
    return in_array($offset, $this->keys);
  }

  public function offsetGet($offset)
  {
    if (!in_array($offset, $this->keys)) {
      throw new \RuntimeException('Offset '.$offset.' does not exist.');
    }
    return $this->$offset;
  }

  public function offsetSet($offset, $value):void
  {
    if (!in_array($offset, $this->keys)) {
      throw new \RuntimeException('Offset '.$offset.' does not exist.');
    }
    $this->$offset = $value;
  }

  public function offsetUnset($offset):void
  {
    $this->offsetSet($offset, null);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
