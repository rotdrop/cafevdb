<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Cloud\Mapper;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

use OCA\CAFEVDB\Database\Cloud\Entities\TOSException;

/** Mapper for TOS exception entities. */
class TOSExceptionMapper extends Mapper
{
  /**
   * Return the configured ToS exceptions for the share token, if any.
   *
   * @param string $shareToken
   *
   * @return array<TOSException>
   */
  public function getToSExeptions(string $shareToken): array
  {
    $qb = $this->db->getQueryBuilder();
    $qb->select('*')
      ->from($this->tableName)
      ->where(
        $qb->expr()->eq('share_token', $qb->createNamedParameter($shareToken, IQueryBuilder::PARAM_STR)),
      );
    return $this->findEntities($qb);
  }

  /**
   * Delete all entities referring to the given share-token.
   *
   * @param string $shareToken
   *
   * @return void
   */
  public function deleteByShareToken(string $shareToken): void
  {
    $exceptions = $this->getToSExeptions($shareToken);
    foreach ($exceptions as $exception) {
      $this->delete($exception);
    }
  }

  /**
   * Generate a new or update an existing TosS exception to include $ipRange.
   *
   * @param string $shareToken
   *
   * @param array|string $ipRange
   *
   * @param bool $replace Replace any existing ip-ranges with the given ones.
   *
   * @return TOSException
   */
  public function addToSException(
    string $shareToken,
    array|string $ipRanges,
    bool $replace = false,
  ): TOSException {
    if (is_string($ipRanges)) {
      $ipRanges = explode(',', $ipRanges);
    }
    if ($replace) {
      $this->deleteByShareToken($shareToken);
      $exceptions = [];
    } else {
      $exceptions = $this->getToSExeptions($shareToken);
    }
    if (empty($exceptions)) {
      $exception = new TOSException();
      $exception->setShareToken($shareToken);
      $exception->setIpRanges('');
    } else {
      $exception = $exceptions[0];
    }
    $existingRanges = array_filter($replace ? [] :  explode(',', $exception->getIpRanges()));
    $ipRanges = array_merge($existingRanges, $ipRanges);
    $exception->setIpRanges(implode(',', array_unique($ipRanges)));
    if (empty($exception->id)) {
      $this->insert($exception);
    } else {
      $this->update($exception);
    }
    return $exception;
  }
}
