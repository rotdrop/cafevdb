<?php
/**
 * Orchestra member, musician and project management application.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Copyright (c) 2022, 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\PageRenderer\FieldTraits;

use OCA\CAFEVDB\Wrapped\MediaMonks\Doctrine\Transformable;
use OCA\CAFEVDB\Database\EntityManager;

/**
 * Helper function for directing PME legacy decrypt/encrypt to then functions
 * used by the ORM.
 */
trait CryptoTrait
{
  /** @var EntityManager */
  protected EntityManager $entityManager;

  /** @var Transformable\Transformer\TransformerInterface */
  protected $encryptionTransformer;

  /** @return void */
  private function initCrypto():void
  {
    $this->encryptionTransformer = $this->entityManager->getDataTransformer(EntityManager::TRANSFORM_ENCRYPT);
  }

  /**
   * Use the encryption machine also used by the entity-manager.
   *
   * @param string $value
   *
   * @return string
   */
  private function ormEncrypt(string $value):string
  {
    $context = null;
    return $this->encryptionTransformer->transform($value, $context);
  }

  /**
   * Use the encryption machine also used by the entity-manager.
   *
   * @param string $value
   *
   * @return string
   */
  private function ormDecrypt(string $value):string
  {
    $context = null;
    return $this->encryptionTransformer->reverseTransform($value, $context);
  }
}
