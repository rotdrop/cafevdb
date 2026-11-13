<?php
/**
 * Orchestra member, musician and project management application.
 *
 * @author Copilot <223556219+Copilot@users.noreply.github.com>
 * @copyright 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 */

namespace OCA\CAFEVDB\Service;

use OC\Authentication\Token\IProvider;
use OC\Authentication\Token\PublicKeyToken;
use OCA\Richdocuments\Db\WopiMapper;
use OCP\Authentication\Exceptions\InvalidTokenException;
use OCP\Authentication\LoginCredentials\IStore;
use OCP\Files\IRootFolder;
use OCP\IUserSession;
use Psr\Container\ContainerInterface;
use RuntimeException;
use SensitiveParameter;

/** Store credentials in the format consumed by WOPI mount authentication. */
class WopiTokenService
{
  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    private IUserSession $userSession,
    private AuthorizationService $authorizationService,
    private IStore $credentialsStore,
    private IProvider $tokenProvider,
    private IRootFolder $rootFolder,
    private ContainerInterface $appContainer,
  ) {
  }
  // phpcs:enable

  /**
   * Provision credentials only for an authorized local user's authenticated mount.
   *
   * @param string $accessToken Token returned by richdocuments, not a request parameter.
   *
   * @return void
   */
  public function provideCredentials(#[SensitiveParameter] string $accessToken): void
  {
    $user = $this->userSession->getUser();
    if ($user === null || !$this->userSession->isLoggedIn()
        || !$this->authorizationService->authorized($user->getUID(), AuthorizationService::PERMISSION_FILESYSTEM)) {
      return;
    }
    $wopi = $this->appContainer->get(WopiMapper::class)->getWopiForToken($accessToken);
    $userId = $user->getUID();
    if ($wopi->getTokenType() !== $wopi::TOKEN_TYPE_USER
        || $wopi->getEditorUid() !== $userId || $wopi->getUserForFileAccess() !== $userId) {
      return;
    }
    $file = $this->rootFolder->getUserFolder($userId)->getFirstNodeById($wopi->getFileid());
    if ($file === null) {
      throw new RuntimeException('WOPI file is unavailable.');
    }
    if (!$file->getMountPoint()->getOption('authenticated', false)) {
      return;
    }
    $credentials = $this->credentialsStore->getLoginCredentials();
    $password = $credentials->getPassword();
    if ($credentials->getUID() !== $userId || $password === null || $password === '') {
      throw new RuntimeException('Matching password credentials are unavailable.');
    }

    $passphrase = $userId . '@' . $accessToken;
    try {
      $token = $this->tokenProvider->getToken($passphrase);
    } catch (InvalidTokenException) {
      $token = null;
    }
    if ($token === null) {
      $token = $this->tokenProvider->generateToken(
        $passphrase,
        $accessToken,
        $userId,
        $password,
        'wopi_token_' . $accessToken,
      );
    }
    if (!$token instanceof PublicKeyToken) {
      throw new RuntimeException('Unsupported WOPI credential token implementation.');
    }
    if ($token->getUID() !== $accessToken || $token->getLoginName() !== $userId) {
      throw new RuntimeException('Stored WOPI credential identity mismatch.');
    }
    if ($this->tokenProvider->getPassword($token, $passphrase) !== $password) {
      throw new RuntimeException('Stored WOPI password mismatch.');
    }
    $token->setExpires($wopi->getExpiry());
    // Keep temporary-token garbage collection from preceding the WOPI expiry.
    $token->setLastActivity($wopi->getExpiry());
    $this->tokenProvider->updateToken($token);
  }
}
