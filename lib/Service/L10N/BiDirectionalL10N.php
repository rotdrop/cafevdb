<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\IL10N;

use OCA\CAFEVDB\Common\Util;

/**
 * Simple bidectional translations fetch from a CSV file.
 */
class BiDirectionalL10N
{
  private const FORWARD = 'forward';
  private const REVERSE = 'reverse';

  /** @var IL10N */
  protected $l10n;

  /** @var string */
  protected $targetLang;

  /** @var string */
  protected $keyLang;

  /* @var array */
  protected $translations;

  public function __construct(
    string $appName
    , IL10N $l10n
    , string $keyLang = 'en'
  ) {
    $this->l10n = $l10n;
    $this->targetLang = locale_get_primary_language($l10n->getLanguageCode());
    $this->keyLang = $keyLang;
    $this->translations = [];
  }

  public function t($phrase)
  {
    if (empty($this->translations)) {
      $this->loadLanguageData();
    }

    if (isset($this->translations[self::FORWARD][$phrase])) {
      return $this->translations[self::FORWARD][$phrase];
    }
    return $this->l->t($phrase);
  }

  public function backTranslate($translation)
  {
    if (empty($this->translations)) {
      $this->loadLanguageData();
    }

    return $this->translations[self::FORWARD][$translation]?:$translation;
  }

  protected function loadLanguageData()
  {
    $dir = realpath('');
    $appDir = substr($dir, 0, strrpos($dir, 'cafevdb')).'cafevdb';

    foreach (glob($appDir.DIRECTORY_SEPARATOR.'l10n'.DIRECTORY_SEPARATOR.'*.csv') as $file) {
      $this->mergeCSV($file);
    }
  }

  public function mergeCSV($fileName)
  {
    $newTranslations = self::parseCSV($fileName, $this->targetLang, $this->keyLang);
    $this->translations = Util::arrayMergeRecursive($this->translations, $newTranslations);
  }

  static protected function parseCSV($fileName, $targetLang, $keyLang = 'en')
  {
    if (($file = fopen($fileName, 'r')) === false) {
      return null;
    }
    if (empty($header = fgetcsv($file))) {
      return null;
    }

    $targetColumn = array_search($targetLang, $header);
    $keyColumn = array_search($keyLang, $header);

    $targetLookup = [];
    $keyLookup = [];
    while (!empty(($row = fgetcsv($file)))) {
      if (empty($row[$targetColumn]) || empty($row[$keyColumn])) {
        continue;
      }
      $keyPhrases = explode(';', $row[$keyColumn]);
      $targetPhrases = explode(';', $row[$targetColumn]);
      foreach ($keyPhrases as $keyPhrase) {
        $keyPhrase = trim($keyPhrase);
        foreach ($targetPhrases as $targetPhrase) {
          $targetPhrase = trim($targetPhrase);
          if (empty($targetLookup[$keyPhrase])) {
            $targetLookup[$keyPhrase] = $targetPhrase;
          }
          if (empty($keyLookup[$targetPhrase])) {
            $keyLookup[$targetPhrase] = $keyPhrase;
          }
        }
      }
    }
    return [
      'forward' => $targetLookup,
      'inverse' => $keyLookup,
    ];
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
