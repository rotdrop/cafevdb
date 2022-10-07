<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Common;

use OCA\CAFEVDB\Wrapped\Ramsey\Uuid\UuidInterface;

/**
 * Customize with some defaults, like a random node.
 */
class Uuid extends \OCA\CAFEVDB\Wrapped\Ramsey\Uuid\Uuid
{
  /** @var Ramsey\Uuid\Provider\NodeProviderInterface */
  private static $nodeProvider;

  /**
   * Create a default Uuid. Currently a version 1 uuid with random
   * node.
   *
   * @return UuidInterface
   */
  public static function create()
  {
    return self::uuid1();
  }

  /**
   * {@inheritdoc}
   */
  public static function uuid1($node = null, ?int $clockSeq = null): UuidInterface
  {
    if (empty($node)) {
      $node = self::createNode();
    }
    return parent::Uuid1($node, $clockSeq);
  }

  /**
   * Convert "anything" to a UuidInterface
   *
   * @return null|UuitInterface
   */
  public static function asUuid($data):?UuidInterface
  {
    if ($data instanceof UuidInterface) {
      return $data;
    }
    if (is_string($data)) {
      if (strlen($data) == 36) {
        return self::fromString($data);
      }
      if (strlen($data) == 16) {
        return self::fromBytes($data);
      }
    }
    return null;
  }

  public static function nil():UuidInterface
  {
    return self::fromString(self::NIL);
  }

  /**
   * Convert "anything" to a binary UUID representation.
   *
   * @return null|string Binary string of length 16
   */
  public static function uuidBytes($data):?string
  {
    $uuid = self::asUuid($data);
    if (empty($uuid)) {
      return null;
    }
    return $uuid->getBytes();
  }

  /**
   * Internal helper function.
   *
   * @return string Node for Uuid generation.
   */
  private static function createNode()
  {
    if (empty(self::$nodeProvider)) {
      self::$nodeProvider = new \OCA\CAFEVDB\Wrapped\Ramsey\Uuid\Provider\Node\RandomNodeProvider;
    }
    return self::$nodeProvider->getNode();
  }
}
