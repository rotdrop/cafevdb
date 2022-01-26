<?php
/**
 * Orchestra member, musician and project management application.
 *
 * @copyright Copyright (c) 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 *
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
 *
 */

namespace OCA\CAFEVDB\PageRenderer;

use OCA\CAFEVDB\Wrapped\MediaMonks\Doctrine\Transformable;
use OCA\CAFEVDB\Database\EntityManager;

trait CryptoTrait
{
  /** @var EntityManager */
  protected $entityManager;

  /** @var Transformable\Transformer\TransformerInterface */
  protected $encryptionTransformer;

  private function initCrypto()
  {
    $this->encryptionTransformer = $this->entityManager->getDataTransformer(EntityManager::TRANSFORM_ENCRYPT);
  }

  /** Use the encryption machine also used by the entity-manager. */
  private function ormEncrypt($value)
  {
    $context = null;
    return $this->encryptionTransformer->transform($value, $context);
  }

  /** Use the encryption machine also used by the entity-manager. */
  private function ormDecrypt($value)
  {
    $context = null;
    return $this->encryptionTransformer->reverseTransform($value, $context);
  }
}
