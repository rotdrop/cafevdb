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

namespace OCA\CAFEVDB\Service;

use Throwable;

use OCP\IL10N;
use Psr\Log\LoggerInterface;

use OCA\CAFEVDB\Crypto\AsymmetricKeyService;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\EncryptionService;

/**
 * Helper for managing sealed encrypted columns which should be decodable by
 * the user.
 */
class UserEncryptionService
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /**
   * @var
   *
   * Config key for a "shared private value".
   */
  public const ROW_ACCESS_TOKEN_KEY = 'rowAccessToken';

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected AsymmetricKeyService $asymmetricKeyService,
    protected AuthorizationService $authorizationService,
    protected EncryptionService $encryptionService,
    protected EntityManager $entityManager,
    protected IL10N $l,
    protected LoggerInterface $logger,
  ) {
  }
  // phpcs:enable

  /**
   * @param string $userId
   *
   * @return bool \true if also the app encryption key is valid, that is,
   * \true is returned if $userId belongs to the orchester group and no error
   * was encountered, if an error is encounted then an exception is thrown. If
   * $userId does not belong to the orchestra group and no error was
   * encounted, then \false is returned.
   *
   * @throws Exceptions\EncryptionFailedException
   * @throws Exceptions\RecryptionFailedException
   * @throws Exception\EncryptionKeyException
   */
  public function recrypt(string $userId): bool
  {
    $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
    /** @var Entities\Musician $musician */
    $musician = $this->getDatabaseRepository(Entities\Musician::class)->findByUserId($userId);
    if (!empty($musician)) {
      $accessToken = \random_bytes(Entities\MusicianRowAccessToken::HASH_LENGTH / 8);
      $this->entityManager->beginTransaction();
      try {
        $this->asymmetricKeyService->setSharedPrivateValue($userId, self::ROW_ACCESS_TOKEN_KEY, $accessToken);
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
      } catch (Throwable $t) {
        $this->entityManager->rollback();
        $this->asymmetricKeyService->setSharedPrivateValue($userId, self::ROW_ACCESS_TOKEN_KEY, null);
        $this->logException($t, 'Unable to set row access-token for user ' . $userId);
        throw new Exceptions\EncryptionFailedException($this->l->t('Unable to set row access-token for user "%s".', $userId), $t);
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
      } catch (Throwable $t) {
        $this->logException($t, 'Unable to recrypt encrypted data for user ' . $userId);
        throw new Exceptions\RecryptionFailedException($this->l->t('Unable to recrypt encrypted data for user "%s".', $userId), previous: $t);
      }
    }

    // Restore access to the CAFEVDB database, if the user should be able to access it.

    $appEncryptionKey = null;

    if ($this->authorizationService->getUserPermissions($userId) != AuthorizationService::PERMISSION_NONE) {
      // set encryption key for this user
      /** @var EncryptionService $encryptionService */
      if (!$this->encryptionService->encryptionKeyValid()) {
        throw new Exceptions\EncryptionKeyException($this->l->t('Encryption key is invalid'));
      }
      try {
        $appEncryptionKey = $this->encryptionService->getAppEncryptionKey();
        $this->encryptionService->setUserEncryptionKey($appEncryptionKey, $userId);
      } catch (Exceptions\EncryptionException $e) {
        throw new Exception\EncryptionKeyException($this->l->t('Unable to set app-encryption-key for user "%s".', $userId), previous: $e);
      }
    }

    return $appEncryptionKey !== null;
  }
}
