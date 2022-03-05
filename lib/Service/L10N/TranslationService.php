<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Service\L10N;

use OCP\ILogger;
use OCP\L10N\IFactory as IL10NFactory;

use \Doctrine\ORM\Query\Expr\Join;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Translation;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\TranslationKey;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\TranslationLocation;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MissingTranslation;
use OCA\CAFEVDB\Common\Util;

/**
 * Runtime translation service for recording untranslated phrases in a
 * database.
 */
class TranslationService
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var array */
  private $availableLanguages;

  public function __construct(
    $appName
    , EntityManager $entityManager
    , IL10NFactory $l10nFactory
    , ILogger $logger
  ) {
    $this->entityManager = $entityManager;
    $this->logger = $logger;
    $this->availableLanguages = array_values(
      array_filter(
        $l10nFactory->findAvailableLanguages($appName),
        function($lang) { return !str_starts_with($lang, 'en'); }
      )
    );
  }

  public function recordUntranslated($phrase, $locale, $file, $line)
  {
    if (array_search($locale, $this->availableLanguages) === false) {
      return;
    }
    $this->entityManager->suspendLogging();
    $this->setDataBaseRepository(TranslationKey::class);
    $translationKey = $this->findOneBy([ 'phraseHash' => md5($phrase) ]);
    if (empty($translationKey)) {
      $translationKey = TranslationKey::create()->setPhrase($phrase);
      try {
        $this->persist($translationKey);
        $this->flush();
      } catch (\Throwable $t) {
        $this->logException($t);
      }
      $this->logDebug('Translation key for "'.$phrase.'" was empty, new id '.$translationKey->getId());
    } else {
      $this->logDebug('Existing translation key for "'.$phrase.'" has id '.$translationKey->getId());
    }

    $this->setDataBaseRepository(TranslationLocation::class);
    $location = $this->findOneBy([
      'translationKey' => $translationKey,
      'file' => $file,
      'line' => $line,
    ]);
    if (empty($location)) {
      $this->logDebug('Empty location for key '.$translationKey->getId());
      $location = TranslationLocation::create()
                ->setTranslationKey($translationKey)
                ->setFile($file)
                ->setLine($line);
      try {
        $this->persist($location);
        $this->flush();
      } catch (\Throwable $t) {
        $this->logException($t);
      }
    }
    $this->setDatabaseRepository(MissingTranslation::class);
    $missingLocale = $this->findOneBy([
      'translationKey' => $translationKey,
      'locale' => $locale,
    ]);
    if (empty($missingLocale)) {
      $missingLocale = MissingTranslation::create()
        ->setTranslationKey($translationKey)
        ->setLocale($locale);
      try {
        $this->persist($missingLocale);
        $this->flush();
      } catch (\Throwable $t) {
        $this->logException($t);
      }
    }
    try {
      $this->flush();
    } catch (\Throwable $t) {
      $this->logException($t);
    }
    $this->entityManager->resumeLogging();
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
    if (empty(Util::normalizeSpaces($translatedPhrase))) {
      throw new \Exception('Translation for %s is empty.', $phrase);
    }
    $this->setDataBaseRepository(TranslationKey::class);
    $translationKey = $this->findOneBy([ 'phrase' => $phrase ]);
    if (empty($translationKey)) {
      $translationKey = TranslationKey::create()->setPhrase($phrase);
      try {
        $this->persist($translationKey);
        $this->flush();
      } catch (\Throwable $t) {
        $this->logException($t);
        return false;
      }
    }

    $this->setDataBaseRepository(Translation::class);
    $changed = false;
    $translation = $this->findOneBy([
      'translationKey' => $translationKey,
      'locale' => $locale ]);
    if (empty($translation)) {
      $translation = Translation::create()
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
        $this->persist($translation);
        $this->flush();
      } catch (\Throwable $t) {
        $this->logException($t);
        return false;
      }
    }
    $this->setDatabaseRepository(MissingTranslation::class);
    $missingLocale = $this->findOneBy([
      'translationKey' => $translationKey,
      'locale' => $locale,
    ]);
    if (!empty($missingLocale)) {
      $this->remove($missingLocale, flush: true);
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

  public function generateCatalogueTemplates()
  {
    $this->setDataBaseRepository(TranslationKey::class);
    $translationKeys = $this->findAll();
    $catalogue = [];
    foreach ($translationKeys as $translationKey) {
      $entry = [];
      foreach ($translationKey['locations'] as $location) {
        $entry[] = "#: {$location['file']}:{$location['line']}";
      }
      $entry[] = "#, php-format";
      $entry[] = 'msgid "'.str_replace('"', '\\"', $translationKey['phrase']).'"';
      $entry[] = 'msgstr ""';
      $entry[] = '';
      $catalogue[] = implode("\n", $entry);
    }
    $contents = implode("\n", $catalogue);

    return $contents;
  }

  public function eraseTranslationKeys(string $phrase)
  {
    $repository = $this->getDatabaseRepository(TranslationKey::class);
    $translationKeys = $repository->findLike([ 'phrase' => $phrase ]);
    if (count($translationKeys) == 0) {
      $this->logWarn('Not translation-keys found to erase for phrase "' . $phrase . '".');
    } else {
      $this->logWarn('About to erase '.count($translationKeys).' translation-keys.');
    }
    foreach ($translationKeys as $translationKey) {
      $this->remove($translationKey);
    }
    $this->flush();
    return true;
  }


}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
