<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or1
 * modify it under th52 terms of the GNU GENERAL PUBLIC LICENSE
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

namespace OCA\CAFEVDB\Service;

use \Doctrine\ORM\Query\Expr\Join;

use OCA\CAFEVDB\Common\Util;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Translation;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\TranslationKey;

class TranslationService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  public function __construct(
    ConfigService $configService,
    EntityManager $entityManager
  ) {
    $this->configService = $configService;
    $this->entityManager = $entityManager;
  }

  public function recordUntranslated($phrase, $locale, $file, $line)
  {
    $this->setDataBaseRepository(TranslationKey::class);
    $translationKey = $this->findOneBy([ 'phrase' => $phrase ]);
    if (empty($translationKey)) {
      $translationKey = TranslationKey::create()->setPhrase($phrase);
      try {
        $this->merge($translationKey);
      } catch (\Throwable $t) {
        $this->logException($exception);
      }
      //$this->flush();
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
