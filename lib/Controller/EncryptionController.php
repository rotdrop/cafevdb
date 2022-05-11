<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\IAppContainer;
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Crypto\AsymmetricKeyService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Exceptions;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

class EncryptionController extends OCSController
{
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  // @todo move this definition somewhere else
  const ROW_ACCESS_TOKEN_KEY = 'rowAccessToken';

  /** @var IAppContainer */
  private $appContainer;

  /** @var AsymmetricKeyService */
  private $keyService;

  /** @var IL10N */
  protected $l;

  public function __construct(
    $appName
    , IRequest $request
    , IAppContainer $appContainer
    , AsymmetricKeyService $keyService
    , ILogger $logger
    , IL10N $l10n
  ) {
    parent::__construct($appName, $request);
    $this->appContainer = $appContainer;
    $this->logger = $logger;
    $this->l = $l10n;
    $this->keyService = $keyService;
  }

  /**
   * @AuthorizedAdminSetting(settings=OCA\CAFEVDB\Settings\Admin)
   */
  public function getRecryptRequests(?string $userId = null)
  {
    $recryptRequests = $this->keyService->getRecryptionRequests();
    // Testing
    // $testUser = 'bilbo.baggins';
    // $recryptRequests[$testUser] = time(); // $this->keyService->getCryptor($testUser)->getPublicKey();
    // $testUser = 'claus';
    // $recryptRequests[$testUser] = time(); $this->keyService->getCryptor($testUser)->getPublicKey();
    //
    if (!empty($userId)) {
      $recryptRequests = $recryptRequests[$userId] || [];
      if (empty($recryptRequests)) {
        throw new OCS\OCSNotFoundException($this->l->t('Recryption-request for user "%s" not found.', $userId));
      }
    }
    return new DataResponse([
      'requests' => $recryptRequests,
    ]);
  }

  /**
   * @AuthorizedAdminSetting(settings=OCA\CAFEVDB\Settings\Admin)
   */
  public function deleteRecryptRequest(string $userId)
  {
    try {
      $this->keyService->pushRecryptionRequestDeniedNotification($userId);
      $this->keyService->removeRecryptionRequestNotification($userId);
    } catch (Exceptions\RecryptionRequestNotFoundException $e) {
      throw new OCS\OCSNotFoundException($this->l->t('Recryption-request for user "%s" not found.', $userId), $e);
    }
    return new DataResponse([
      'ownerId' => $userId,
    ]);
  }

  /**
   * @NoAdminRequired
   * @NoGroupMemberRequired
   */
  public function putRecryptRequest(string $userId)
  {
    try {
      $this->keyService->pushRecryptionRequestNotification($userId, []);
    } catch (Exceptions\RecryptionRequestNotFoundException $e) {
      throw new OCS\OCSBadRequestException($this->l->t('Unable to issue encryption request'), $e);
    }
    return new DataResponse([
      'ownerId' => $userId,
    ]);
  }

  /**
   * @AuthorizedAdminSetting(settings=OCA\CAFEVDB\Settings\Admin)
   */
  public function handleRecryptRequest(string $userId)
  {
    try {
      // As long as we do not support access to the personal data we simply
      // install the management encryption key for members of the management
      // board and otherwise do nothing. Once we have personally sealed data
      // we need to re-crypt all data records related to the target-user of
      // the recryption request.
      //
      // However: do install a new personal access token.

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
            $tokenEntity->setAccessToken($accessToken);
          }
          $this->flush();
          $this->entityManager->commit();
        } catch (\Throwable $t) {
          $this->entityManager->rollback();
          $this->keyService->setSharedPrivateValue($userId, self::ROW_ACCESS_TOKEN_KEY, null);
          throw new OCS\OCSBadRequestException($this->l->t('Unable to set row access-token for user "%s".', $userId), $t);
        }
      }

      /** @var AuthorizationService $authorizationService */
      $authorizationService = $this->appContainer->get(AuthorizationService::class);
      if ($authorizationService->authorized($userId)) {
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

      $this->keyService->pushRecryptionRequestHandledNotification($userId);
      $this->keyService->removeRecryptionRequestDeniedNotification($userId);
      $this->keyService->removeRecryptionRequestNotification($userId);

      return new DataResponse([
        'keyStatus' => empty($appEncryptionKey) ? 'unset' : 'set',
      ]);

    } catch (Exceptions\RecryptionRequestNotFoundException $e) {
      throw new OCS\OCSNotFoundException($this->l->t('Recryption-request for user "%s" not found.', $userId), $e);
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
