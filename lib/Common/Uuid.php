<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Common;

/**
 * Customize with some defaults, like a random node.
 */
class Uuid extends \Ramsey\Uuid\Uuid
{
  /** @var Ramsey\Uuid\Provider\NodeProviderInterface */
  private static $nodeProvider;

  public static function create()
  {
    return self::uuid1();
  }

  public static function uuid1($node = null, ?int $clockSeq = null): \Ramsey\Uuid\UuidInterface
  {
    if (empty($node)) {
      $node = self::createNode();
    }
    return parent::Uuid1($node, $clockSeq);
  }

  private static function createNode()
  {
    if (empty(self::$nodeProvider)) {
      self::$nodeProvider = new \Ramsey\Uuid\Provider\Node\RandomNodeProvider;
    }
    return self::$nodeProvider->getNode();
  }
}
