<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Copilot <223556219+Copilot@users.noreply.github.com>
 * @copyright 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 */

namespace OCA\CAFEVDB\Service;

use OC\Authentication\Token\IProvider as TokenProvider;
use OCA\Richdocuments\Db\WopiMapper;
use OCA\Richdocuments\Middleware\WOPIMiddleware;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/** Establish the credentials needed by the database mounts. */
class MountAuthenticationService
{
  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    private IUserSession $userSession,
    private IRequest $request,
    private EncryptionService $encryptionService,
    private TokenProvider $tokenProvider,
    private ContainerInterface $appContainer,
    private LoggerInterface $logger,
  ) {
  }
  // phpcs:enable

  /**
   * Authenticate a mount user, using the fork's WOPI credentials if needed.
   *
   * The caller must first check the user's filesystem permission.
   *
   * @param IUser $user Mount user.
   *
   * @return boolean Whether the required credentials are available.
   */
  public function authenticate(IUser $user): bool
  {
    if (!preg_match('~(?:^|/)apps/richdocuments/wopi(?:/|$)~', $this->request->getRawPathInfo())) {
      return $this->userSession->isLoggedIn();
    }

    try {
      $middleware = $this->appContainer->get(WOPIMiddleware::class);
      if (!$middleware->isWOPIRequest()) {
        $this->logger->debug('Cannot authenticate mounts before WOPI request validation.');
        return false;
      }

      $accessToken = $this->request->getParam('access_token');
      if (!is_string($accessToken) || $accessToken === '') {
        return false;
      }
      $wopi = $this->appContainer->get(WopiMapper::class)->getWopiForToken($accessToken);
      $userId = $user->getUID();
      if ($wopi->getTokenType() !== $wopi::TOKEN_TYPE_USER
          || $wopi->getEditorUid() !== $userId
          || $wopi->getUserForFileAccess() !== $userId) {
        return false;
      }

      $activeUserId = $this->userSession->getUser()?->getUID();
      if ($activeUserId !== null && $activeUserId !== $userId) {
        return false;
      }
      $boundUserId = $this->encryptionService->getUserId();
      if ($boundUserId !== null && $boundUserId !== $userId) {
        return false;
      }
      if ($this->userSession->isLoggedIn() && $boundUserId === $userId) {
        return true;
      }

      $passphrase = $userId . '@' . $accessToken;
      $token = $this->tokenProvider->getToken($passphrase);
      if ($token->getUID() !== $accessToken || $token->getLoginName() !== $userId) {
        return false;
      }
      $password = $this->tokenProvider->getPassword($token, $passphrase);
      if ($password === '' || !$this->userSession->login($userId, $password)
          || $this->userSession->getUser()?->getUID() !== $userId) {
        return false;
      }
      if ($this->encryptionService->getUserId() !== $userId) {
        $this->encryptionService->bind($userId, $password);
      }
      return $this->encryptionService->getUserId() === $userId;
    } catch (Throwable $exception) {
      // Exceptions from token providers may contain credential material.
      $this->logger->warning('Unable to authenticate WOPI mounts: ' . $exception::class);
      return false;
    }
  }
}
