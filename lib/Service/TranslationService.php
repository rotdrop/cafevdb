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
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\TranslationLocation;

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
        $translationKey = $this->merge($translationKey);
      } catch (\Throwable $t) {
        $this->logException($t);
      }
      //$this->flush();
      $this->logInfo(__METHOD__.' translation key for '.$phrase.' was empty, new id '.$translationKey->getId());
    } else {
      $this->logInfo(__METHOD__.' existing translation key for '.$phrase.' has id '.$translationKey->getId());
    }

    $this->setDataBaseRepository(TranslationLocation::class);
    $keyId= $translationKey->getId();
    $location = $this->findOneBy([
      'keyId' => $keyId,
      'file' => $file,
      'line' => $line ]);
    if (empty($location)) {
      $this->logInfo(__METHOD__.' empty location for key '.$keyId);
      $location = TranslationLocation::create()
                ->setKeyId($keyId)
                ->setTranslationKey($translationKey)
                ->setFile($file)
                ->setLine($line);
      try {
        $this->merge($location);
      } catch (\Throwable $t) {
        $this->logException($t);
      }
    }
  }

  /**
   * Record a translation for a phrase
   *
   * @param string $phrase The phrase to translate
   *
   * @param string $translation The translation for $phrase
   *
   * @parma string $locale The locale for the translation
   *
   * @return boolean
   */
  public function recordTranslation(string $phrase, string $translatedPhrase, string $locale)
  {
    $this->setDataBaseRepository(TranslationKey::class);
    $translationKey = $this->findOneBy([ 'phrase' => $phrase ]);
    if (empty($translationKey)) {
      $translationKey = TranslationKey::create()->setPhrase($phrase);
      try {
        $translationKey = $this->merge($translationKey);
      } catch (\Throwable $t) {
        $this->logException($t);
        return false;
      }
    }

    $this->setDataBaseRepository(Translation::class);
    $keyId= $translationKey->getId();
    $changed = false;
    $translation = $this->findOneBy([
      'keyId' => $keyId,
      'locale' => $locale ]);
    if (empty($translation)) {
      $translation = Translation::create()
                ->setKeyId($keyId)
                ->setTranslationKey($translationKey)
                ->setTranslation($translatedPhrase)
                ->setLocale($locale);
      $changed = true;
    } else if ($translation->getTranslation() != $translatedPhrase) {
      $translation->setTranslation($translatedPhrase);
      $changed = true;
    }
    if ($changed) {
      try {
        $this->merge($translation);
      } catch (\Throwable $t) {
        $this->logException($t);
        return false;
      }
    }
    return true;
  }

  /**
   * Return an associative array with all translation informations.
   *
   * [
   *   ID => [
   *     'key' => KEY,
   *     'translations' => [
   *        LOCALE => 'translation'
   *     ]
   *   ];
   */
  public function getTranslations()
  {
    $translations = [];
    $this->setDataBaseRepository(TranslationKey::class);
    $translationKeys = $this->findAll();
    foreach ($translationKeys as $key) {
      $keyId = $key->getId();
      $translations[$keyId] = [
        'key' => $key->getPhrase(),
        'translations' => [],
      ];
      foreach ($key->getTranslations()->getIterator() as $i => $translation) {
        $translations[$keyId]['translations'][$translation->getLocale()] = $translation->getTranslation();
      }
    }
    return $translations;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
