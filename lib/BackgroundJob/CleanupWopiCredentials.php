<?php
/**
 * Orchestra member, musician and project management application.
 *
 * @author Copilot <223556219+Copilot@users.noreply.github.com>
 * @copyright 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 */

namespace OCA\CAFEVDB\BackgroundJob;

use OC\Authentication\Token\IProvider;
use OC\Authentication\Token\PublicKeyToken;
use OC\Authentication\Token\PublicKeyTokenMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Authentication\Token\IToken;
use OCP\BackgroundJob\TimedJob;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use RuntimeException;
use Throwable;

/** Remove expired password credentials without depending on richdocuments records. */
class CleanupWopiCredentials extends TimedJob
{
  private const BATCH_SIZE = 1000;
  private const EXPIRY_GRACE_SECONDS = 60;
  private const TOKEN_NAME_PREFIX = 'wopi_token_';

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ITimeFactory $time,
    private IDBConnection $db,
    private PublicKeyTokenMapper $tokenMapper,
    private IProvider $tokenProvider,
  ) {
    parent::__construct($time);
    $this->setInterval(600);
  }
  // phpcs:enable

  /** {@inheritdoc} */
  protected function run($argument): void
  {
    try {
      $this->cleanupExpiredCredentials();
    } catch (Throwable $exception) {
      // Cron logs failures; do not attach exceptions that may contain credentials.
      throw new RuntimeException('WOPI credential cleanup failed: ' . $exception::class);
    }
  }

  /** @return void */
  private function cleanupExpiredCredentials(): void
  {
    $cutoff = $this->time->getTime() - self::EXPIRY_GRACE_SECONDS;
    $query = $this->db->getQueryBuilder();
    $query->select('id')
      ->from('authtoken')
      ->where($query->expr()->gt('expires', $query->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
      ->andWhere($query->expr()->lt('expires', $query->createNamedParameter($cutoff, IQueryBuilder::PARAM_INT)))
      ->andWhere($query->expr()->eq('name', $query->func()->concat(
        $query->createNamedParameter(self::TOKEN_NAME_PREFIX),
        'uid',
      )))
      ->andWhere($query->expr()->eq('type', $query->createNamedParameter(IToken::TEMPORARY_TOKEN, IQueryBuilder::PARAM_INT)))
      ->andWhere($query->expr()->eq('version', $query->createNamedParameter(PublicKeyToken::VERSION, IQueryBuilder::PARAM_INT)))
      ->orderBy('id')
      ->setMaxResults(self::BATCH_SIZE);
    $result = $query->executeQuery();
    try {
      $ids = $result->fetchFirstColumn();
    } finally {
      $result->closeCursor();
    }

    foreach ($ids as $id) {
      try {
        // The provider's lookup rejects expired tokens, so recheck through its mapper.
        $token = $this->tokenMapper->getTokenById((int)$id);
        $expiry = $token->getExpires();
        if ($expiry === null || $expiry <= 0 || $expiry >= $cutoff
            || $token->getType() !== IToken::TEMPORARY_TOKEN
            || $token->getName() !== self::TOKEN_NAME_PREFIX . $token->getUID()) {
          continue;
        }
        $this->tokenProvider->invalidateTokenById($token->getUID(), $token->getId());
      } catch (DoesNotExistException) {
        // Nextcloud's own cleanup or another worker already removed this record.
        continue;
      }
    }
  }
}
