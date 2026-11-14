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
use OCA\CAFEVDB\Service\WopiCredentialRegistry;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Authentication\Token\IToken;
use OCP\BackgroundJob\TimedJob;
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
    private WopiCredentialRegistry $registry,
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
    foreach ($this->registry->findExpired($cutoff, self::BATCH_SIZE) as $entry) {
      $tokenId = (int)$entry['token_id'];
      $tokenUid = $entry['token_uid'];
      $expiry = (int)$entry['expires'];
      if ($expiry <= 0 || $expiry >= $cutoff) {
        continue;
      }
      // Unlike getTokenById(), this lookup also returns expired credentials.
      foreach ($this->tokenProvider->getTokenByUser($tokenUid) as $token) {
        if ($token->getId() !== $tokenId) {
          continue;
        }
        if ($token->getUID() !== $tokenUid || $token->getType() !== IToken::TEMPORARY_TOKEN
            || $token->getName() !== self::TOKEN_NAME_PREFIX . $tokenUid) {
          throw new RuntimeException('Registered WOPI credential identity mismatch.');
        }
        try {
          $this->tokenProvider->invalidateTokenById($tokenUid, $tokenId);
        } catch (DoesNotExistException) {
          // Another cleanup already removed the credential.
          break;
        }
        break;
      }
      $this->registry->forget($tokenId, $tokenUid, $expiry);
    }
  }
}
