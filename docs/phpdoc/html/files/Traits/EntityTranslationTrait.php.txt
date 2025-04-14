<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022, 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Traits;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\TableFieldTranslation as TranslationEntity;
use OCA\CAFEVDB\Wrapped\Gedmo\Translatable\Entity\Repository\TranslationRepository;

/**
 * Translate data-base columns on the fly.
 */
trait EntityTranslationTrait
{
  protected EntityManager $entityManager;

  protected TranslationRepository $translationRepository;

  /**
   * Convenience, forward potential translation attempts to the
   * underlying translation backend. The idea is to hide the underlying backend.
   *
   * @param mixed $entity
   *
   * @param string $field
   *
   * @param string|null $locale If null the default locale is used.
   *
   * @param mixed $value
   *
   * @return EntityRepository $this
   */
  public function translate(mixed $entity, string $field, ?string $locale, mixed $value)
  {
    if (empty($this->translationRepository)) {
      $this->translationRepository = $this->entityManager->getRepository(TranslationEntity::class);
    }
    if (empty($locale)) {
      $locale = ConfigService::DEFAULT_LOCALE;
    }
    $this->translationRepository->translate($entity, $field, $locale, $value);
    return $this;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
