<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Maintenance\Migrations;

use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Crypto\AsymmetricKeyService;
use OCA\CAFEVDB\Controller\EncryptionController;
use OCA\CAFEVDB\Exceptions;

/**
 * Generate and distribute access-token to all musicians with a
 * private/public key pair.
 */
class FillMusicianRowAccessTokens extends AbstractMigration
{
  const ROW_ACCESS_TOKEN_KEY = EncryptionController::ROW_ACCESS_TOKEN_KEY;

  /** @var AsymmetricKeyService $keyService */
  private $keyService;

  public function __construct(
    ILogger $logger
    , IL10N $l10n
    , EntityManager $entityManager
    , AsymmetricKeyService $keyService
  ) {
    parent::__construct($logger, $l10n, $entityManager);
    $this->keyService = $keyService;
  }

  public function description():string
  {
    return $this->l->t('Generate a row-access-token for everyone with a public key.');
  }

  public function execute():bool
  {
    $this->entityManager->beginTransaction();
    try {
      $musicians = $this->getDatabaseRepository(Entities\Musician::class)->findAll();
      /** @var Entities\Musician $musician */
      foreach ($musicians as $musician) {

        $userId = $musician->getUserIdSlug();

        if (empty($userId)) {
          $this->logWarn('Missing user-id for user ' . $musician->getPublicName());
          continue;
        }

        $cryptor = $this->keyService->getCryptor($userId);
        if (!$cryptor->canEncrypt()) {
          // no public key, ignore
          continue;
        }

        $accessToken = \random_bytes(Entities\MusicianRowAccessToken::HASH_LENGTH / 8);
        $this->keyService->setSharedPrivateValue($userId, self::ROW_ACCESS_TOKEN_KEY, $accessToken);
        $tokenEntity = $musician->getRowAccessToken();
        if (empty($tokenEntity)) {
          $tokenEntity = new Entities\MusicianRowAccessToken($musician, $accessToken);
          $this->persist($tokenEntity);
        } else {
          $tokenEntity->setAccessToken($accessToken);
        }
        $this->flush();
      }
      if ($this->entityManager->getTransactionNestingLevel() > 0) {
        $this->entityManager->commit();
      }
    } catch (\Throwable $t) {
      if ($this->entityManager->getTransactionNestingLevel() > 0) {
        try {
          $this->entityManager->rollback();
          foreach ($musicians as $musician) {
            $userId = $musician->getUserIdSlug();
            $this->keyService->setSharedPrivateValue($userId, self::ROW_ACCESS_TOKEN_KEY, null);
          }
        } catch (\Throwable $t2) {
          $t = new Exceptions\DatabaseMigrationException($this->l->t('Rollback of Migration "%s" failed.', $this->description()), $t->getCode(), $t);
        }
      }
      throw new Exceptions\DatabaseMigrationException($this->l->t('Transactional part of Migration "%s" failed.', $this->description()), $t->getCode(), $t);
    }

    return true;
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
