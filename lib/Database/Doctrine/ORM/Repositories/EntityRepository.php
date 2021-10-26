<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library se Doctrine\ORM\Tools\Setup;is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

use OCA\CAFEVDB\Wrapped\Gedmo\Translatable\Entity\Translation as TranslationEntity;
use OCA\CAFEVDB\Wrapped\Gedmo\Translatable\Entity\Repository\TranslationRepository;

class EntityRepository extends \OCA\CAFEVDB\Wrapped\Doctrine\ORM\EntityRepository
{
  use \OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FindLikeTrait;

  /** @var TranslationRepository */
  protected $translationRepository;

  /**
   * Convenience, forward potential translation attempts to the
   * underlying translation backend. The idea is to hide the underlying backend.
   *
   * @param object $entity
   * @param string $field
   * @param string $locale
   * @param mixed  $value
   *
   * @return EntityRepository $this
   */
  public function translate($entity, string $field, string $locale, $value)
  {
    if (empty($this->translationRepository)) {
      $this->translationRepository = $this->getEntityManager()->getRepository(TranslationEntity::class);
    }
    $this->translationRepository->translate($entity, $field, $locale, $value);
    return $this;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
