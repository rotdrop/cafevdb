<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2022, 2023 Claus-Justus Heine
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
/**
 * @file Handle various requests associated with asymmetric encryption
 */

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\OCSController;
use OCP\AppFramework\OCS;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\IAppContainer;
use OCP\IUserSession;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Crypto\AsymmetricKeyService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Exceptions;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

/** AJAX end-points in order to support encryption. */
class EncryptionController extends OCSController
{
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Traits\FlattenEntityTrait;

  // @todo move this definition somewhere else
  const ROW_ACCESS_TOKEN_KEY = 'rowAccessToken';

  /** @var IAppContainer */
  private $appContainer;

  /** @var AsymmetricKeyService */
  private $keyService;

  /** @var IL10N */
  protected IL10N $l;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    $appName,
    IRequest $request,
    IAppContainer $appContainer,
    AsymmetricKeyService $keyService,
    ILogger $logger,
    IL10N $l10n,
  ) {
    parent::__construct($appName, $request);
    $this->appContainer = $appContainer;
    $this->logger = $logger;
    $this->l = $l10n;
    $this->keyService = $keyService;
  }
  // phpcs:enable

  /**
   * @param null|string $userId
   *
   * @return Response
   *
   * @NoAdminRequired
   * @NoGroupMemberRequired
   */
  public function getRecryptRequests(?string $userId = null):Response
  {
    if (!$this->isMatchingUserOrAdmin($userId)) {
      throw new OCS\OCSForbiddenException($this->l->t('Access denied for mis-matching user id "%s".', $userId));
    }
    $recryptRequests = $this->keyService->getRecryptionRequests();
    // Testing
    // $testUser = 'bilbo.baggins';
    // $recryptRequests[$testUser] = time(); // $this->keyService->getCryptor($testUser)->getPublicKey();
    // $testUser = 'claus';
    // $recryptRequests[$testUser] = time(); $this->keyService->getCryptor($testUser)->getPublicKey();
    //
    if (!empty($userId)) {
      $recryptRequest = $recryptRequests[$userId] ?? null;
      if (empty($recryptRequest)) {
        throw new OCS\OCSNotFoundException($this->l->t('Recryption-request for user "%s" not found.', $userId));
      }
      return new DataResponse([
        'request' => $recryptRequest,
        'userId' => $userId,
      ]);
    }
    return new DataResponse([
      'requests' => $recryptRequests,
    ]);
  }

  /**
   * @param null|string $userId
   *
   * @param bool $notifyUser
   *
   * @return Response
   *
   * @AuthorizedAdminSetting(settings=OCA\CAFEVDB\Settings\Admin)
   */
  public function deleteRecryptRequest(string $userId, bool $notifyUser = true):Response
  {
    try {
      if ($notifyUser) {
        $this->keyService->pushRecryptionRequestDeniedNotification($userId);
      }
      $this->keyService->removeRecryptionRequestNotification($userId);
    } catch (Exceptions\RecryptionRequestNotFoundException $e) {
      throw new OCS\OCSNotFoundException($this->l->t('Recryption-request for user "%s" not found.', $userId), $e);
    }
    return new DataResponse([
      'ownerId' => $userId,
    ]);
  }

  /**
   * @param null|string $userId
   *
   * @return Response
   *
   * @NoAdminRequired
   * @NoGroupMemberRequired
   */
  public function putRecryptRequest(string $userId):Response
  {
    if (!$this->isMatchingUserOrAdmin($userId)) {
      throw new OCS\OCSForbiddenException($this->l->t('Access denied for mis-matching user id "%s".', $userId));
    }
    $this->keyService->removeRecryptionRequestNotification($userId);
    $notification = $this->keyService->pushRecryptionRequestNotification($userId);
    return new DataResponse([
      'ownerId' => $userId,
      'request' => $notification->getSubjectParameters(),
    ]);
  }

  /**
   * Remove the row access token from the database table and the cloud's user
   * settings.
   *
   * @param string $userId
   *
   * @param bool $allowFailure
   *
   * @return Response
   *
   * @AuthorizedAdminSetting(settings=OCA\CAFEVDB\Settings\Admin)
   */
  public function revokeCloudAccess(string $userId, bool $allowFailure = false):Response
  {
    try {
      $this->entityManager = $this->appContainer->get(EntityManager::class);
      /** @var Entities\Musician $musician */
      $musician = $this->getDatabaseRepository(Entities\Musician::class)->findByUserId($userId);
      $rowAccessToken = $musician->getRowAccessToken();
      if (!empty($rowAccessToken)) {
        $this->remove($musician->getRowAccessToken());
        $musician->setRowAccessToken(null);
        $this->flush();
      }
      $musician->setCloudAccountDeactivated(true);
      $musician->setCloudAccountDisabled(true);
      $this->flush();
      $this->keyService->deleteSharedPrivateValue($userId, self::ROW_ACCESS_TOKEN_KEY);
      return new DataResponse([
        'userId' => $userId,
        'access' => 'revoked',
      ]);
    } catch (\Throwable $t) {
      if ($allowFailure) {
        return new DataResponse([
          'userId' => $userId,
          'status' => 'failure',
          'message' => $t->getMessage(),
        ]);
      }
      throw $t;
    }
  }

  /**
   * @param null|string $userId
   *
   * @param bool $notifyUser
   *
   * @param bool $allowFailure
   *
   * @return Response
   *
   * @AuthorizedAdminSetting(settings=OCA\CAFEVDB\Settings\Admin)
   */
  public function handleRecryptRequest(string $userId, bool $notifyUser = true, bool $allowFailure = false):Response
  {
    try {
      try {
        $appEncryptionKey = $this->recryptForUser($userId);
      } catch (\Throwable $t) {
        if ($allowFailure) {
          return new DataResponse([
            'userId' => $userId,
            'status' => 'failure',
            'message' => $t->getMessage(),
          ]);
        }
        throw $t;
      }

      if ($notifyUser) {
        $this->keyService->pushRecryptionRequestHandledNotification($userId);
      }
      $this->keyService->removeRecryptionRequestDeniedNotification($userId);
      $this->keyService->removeRecryptionRequestNotification($userId);

      return new DataResponse([
        'keyStatus' => empty($appEncryptionKey) ? 'unset' : 'set',
        'userId' => $userId,
        'status' => 'granted',
      ]);

    } catch (Exceptions\RecryptionRequestNotFoundException $e) {
      throw new OCS\OCSNotFoundException($this->l->t('Recryption-request for user "%s" not found.', $userId), $e);
    }
  }

  /**
   * Handle bulk recryption requests.
   *
   * @param bool $grantAccess If true grant access, if false deny access.
   *
   * @param bool $includeDisabled
   *
   * @param bool $includeDeactivated
   *
   * @param int $offset Start at the given offset.
   *
   * @param int $limit Work at most on that many musicians before returning.
   *
   * @param int $projectId
   *
   * @return Response
   *
   * @AuthorizedAdminSetting(settings=OCA\CAFEVDB\Settings\Admin)
   */
  public function bulkRecryptionRequest(bool $grantAccess, bool $includeDisabled, bool $includeDeactivated, int $offset, int $limit = 1, int $projectId = 0):Response
  {
    $this->entityManager = $this->appContainer->get(EntityManager::class);
    /** @var Repositories\MusiciansRepository */
    $musiciansRepository = $this->getDatabaseRepository(Entities\Musician::class);

    $criteria = [ [ '!userIdSlug' => null ], [ '!userIdSlug' => '' ] ];
    if (!$includeDeactivated) {
      $criteria[] = [ '(|cloudAccountDeactivated' => false ];
      $criteria[] = [ 'cloudAccountDeactivated' => null ];
      $criteria[] = [ ')' => true ];
    }
    if (!$includeDisabled) {
      $criteria[] = [ '(|cloudAccountDisabled' => false ];
      $criteria[] = [ 'cloudAccountDisabled' => null ];
      $criteria[] = [ ')' => true ];
    }
    $criteria[] = [ 'deleted' => null ];
    if ($projectId > 0) {
      $criteria[] = [ 'projectParticipation.project' => $projectId ];
    }

    $orderBy = [ 'surName' => 'ASC', 'firstName' => 'ASC', 'userIdSlug' => 'ASC' ];

    if ($limit == 0 && $limit == 0) {
      return self::dataResponse([
        'count' => $musiciansRepository->count($criteria),
      ]);
    } else {
      $musicians = $musiciansRepository->findBy($criteria, $orderBy, $limit, $offset);
      return self::dataResponse(
        array_map(function(Entities\Musician $musician) use ($grantAccess) {
          try {
            $userId = $musician->getUserIdSlug();
            $this->recryptForUser($userId);
            return [ 'userId' => $userId, 'status' => $grantAccess ? 'granted' : 'revoked' ];
          } catch (\Throwable $t) {
            $this->logException($t);
            return [ 'userId' => $userId, 'status' => 'failure' ];
          }
        }, $musicians)
      );
    }
  }

  /**
   * @param string $userId
   *
   * @return null|string Encryption-key.
   */
  private function recryptForUser(string $userId):?string
  {
    $this->entityManager = $this->appContainer->get(EntityManager::class);
    /** @var Entities\Musician $musician */
    $musician = $this->getDatabaseRepository(Entities\Musician::class)->findByUserId($userId);
    if (!empty($musician)) {
      $accessToken = \random_bytes(Entities\MusicianRowAccessToken::HASH_LENGTH / 8);
      $this->entityManager->beginTransaction();
      try {
        $this->keyService->setSharedPrivateValue($userId, self::ROW_ACCESS_TOKEN_KEY, $accessToken);
        $tokenEntity = $musician->getRowAccessToken();
        if (empty($tokenEntity)) {
          $tokenEntity = new Entities\MusicianRowAccessToken($musician, $accessToken);
          $this->persist($tokenEntity);
        } else {
          $tokenEntity->setAccessToken($accessToken)
            ->setUserId($musician->getUserIdSlug());
        }
        $this->flush();
        $this->entityManager->commit();
      } catch (\Throwable $t) {
        $this->entityManager->rollback();
        $this->keyService->setSharedPrivateValue($userId, self::ROW_ACCESS_TOKEN_KEY, null);
        $this->logException($t, 'Unable to set row access-token for user ' . $userId);
        throw new OCS\OCSBadRequestException($this->l->t('Unable to set row access-token for user "%s".', $userId), $t);
      }

      // next we should try to recrypt all encrypted entities of the user ...
      $encryptedEntities = [];
      $encryptedEntities = array_merge($encryptedEntities, $musician->getSepaBankAccounts()->toArray());
      /** @var Entities\EncryptedFile $encryptedFile */
      foreach ($musician->getEncryptedFiles() as $encryptedFile) {
        $encryptedEntities[] = $encryptedFile->getFileData();
      }
      try {
        $this->entityManager->recryptEntityList($encryptedEntities);
      } catch (\Throwable $t) {
        $this->logException($t, 'Unable to recrypt encrypted data for user ' . $userId);
        throw new OCS\OCSBadRequestException($this->l->t('Unable to recrypt encrypted data for user "%s".', $userId), $t);
      }
    }

    $appEncryptionKey = null;

    /** @var AuthorizationService $authorizationService */
    $authorizationService = $this->appContainer->get(AuthorizationService::class);
    if ($authorizationService->getUserPermissions($userId) != AuthorizationService::PERMISSION_NONE) {
      // set encryption key for this user
      /** @var EncryptionService $encryptionService */
      $encryptionService = $this->appContainer->get(EncryptionService::class);
      if (!$encryptionService->encryptionKeyValid()) {
        throw new OCS\OCSBadRequestException($this->l->t('Encryption key is invalid'));
      }
      try {
        $appEncryptionKey = $encryptionService->getAppEncryptionKey();
        $encryptionService->setUserEncryptionKey($appEncryptionKey, $userId);
      } catch (Exceptions\EncryptionException $e) {
        throw new OCS\OCSBadRequestException($this->l->t('Unable to set app-encryption-key for user "%s".', $userId), $e);
      }
    }

    return $appEncryptionKey;
  }

  /**
   * @param null|string $userId
   *
   * @return bool
   */
  private function isMatchingUserOrAdmin(?string $userId):bool
  {
    /** @var IUserSession $userSession */
    $userSession = $this->appContainer->get(IUserSession::class);

    $currentUserId = $userSession->getUser()
      ? $userSession->getUser()->getUID()
      : null;

    if (!empty($userId) && $currentUserId === $userId) {
      return true;
    }

    /** @var AuthorizationService $authorizationService */
    $authorizationService = $this->appContainer->get(AuthorizationService::class);
    return $authorizationService->isAdmin($currentUserId);
  }
}
