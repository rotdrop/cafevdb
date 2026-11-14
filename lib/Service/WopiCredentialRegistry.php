<?php
/**
 * Orchestra member, musician and project management application.
 *
 * @author Copilot <223556219+Copilot@users.noreply.github.com>
 * @copyright 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 */

namespace OCA\CAFEVDB\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** Track issued credentials without querying Nextcloud's authentication tables. */
class WopiCredentialRegistry
{
  public const TABLE_NAME = 'cafevdb_wopi_credentials';

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(private IDBConnection $db)
  {
  }

  public function remember(int $tokenId, string $tokenUid, int $expiry): void
  {
    $this->db->setValues(
      '*PREFIX*' . self::TABLE_NAME,
      ['token_id' => $tokenId],
      ['token_uid' => $tokenUid, 'expires' => $expiry],
    );
  }

  /**
   * @param integer $cutoff
   *
   * @param integer $limit
   *
   * @return list<array{token_id: int|string, token_uid: string, expires: int|string}>
   */
  public function findExpired(int $cutoff, int $limit): array
  {
    $query = $this->db->getQueryBuilder();
    $query->select('token_id', 'token_uid', 'expires')
      ->from(self::TABLE_NAME)
      ->where($query->expr()->gt('expires', $query->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
      ->andWhere($query->expr()->lt('expires', $query->createNamedParameter($cutoff, IQueryBuilder::PARAM_INT)))
      ->orderBy('expires')
      ->addOrderBy('token_id')
      ->setMaxResults($limit);
    $result = $query->executeQuery();
    try {
      return $result->fetchAllAssociative();
    } finally {
      $result->closeCursor();
    }
  }

  public function forget(int $tokenId, string $tokenUid, int $expiry): void
  {
    $query = $this->db->getQueryBuilder();
    $query->delete(self::TABLE_NAME)
      ->where($query->expr()->eq('token_id', $query->createNamedParameter($tokenId, IQueryBuilder::PARAM_INT)))
      ->andWhere($query->expr()->eq('token_uid', $query->createNamedParameter($tokenUid)))
      ->andWhere($query->expr()->eq('expires', $query->createNamedParameter($expiry, IQueryBuilder::PARAM_INT)))
      ->executeStatement();
  }
  // phpcs:enable
}
