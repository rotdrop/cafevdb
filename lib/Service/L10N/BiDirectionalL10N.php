<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service\L10N;

use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Common\Util;

/**
 * Simple bidectional translations fetch from a CSV file.
 *
 * @SuppressWarnings(PHPMD.ShortMethodName)
 */
class BiDirectionalL10N
{
  use \OCA\RotDrop\Toolkit\Traits\LoggerTrait;

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

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    ILogger $logger,
    IL10N $l10n,
    string $keyLang = 'en',
  ) {
    $this->appName = $appName;
    $this->logger = $logger;
    $this->setL10N($l10n);
    $this->keyLang = $keyLang;
  }
  // phpcs:enable

  /**
   * @param IL10N $l10n
   *
   * @return void
   */
  public function setL10N(IL10N $l10n):void
  {
    $this->l = $l10n;
    $this->targetLang = locale_get_primary_language($l10n->getLanguageCode());
    $this->translations = []; // void when changing language
  }

  /**
   * Attempt a translation. If it fails, return the original string.
   *
   * @param string $phrase
   *
   * @return string
   */
  public function t(string $phrase):string
  {
    if (empty($phrase)) {
      return $phrase;
    }

    if (empty($this->translations)) {
      $this->loadLanguageData();
    }

    if (isset($this->translations[self::FORWARD][$phrase])) {
      return $this->translations[self::FORWARD][$phrase];
    }
    return $this->l->t(str_replace('%', '%%', $phrase));
  }

  /**
   * Attempt an un-translation. If it fails, return the original string.
   *
   * @param string $translation
   *
   * @return null|string
   */
  public function backTranslate(string $translation):?string
  {
    if (empty($this->translations)) {
      $this->loadLanguageData();
    }

    $backTranslation = $this->translations[self::REVERSE][$translation]??null;
    if (!empty($backTranslation)) {
      return $backTranslation;
    }

    if (method_exists($this->l, 'getTranslations')) {
      $cloudTranslations = $this->l->getTranslations();
      $backTranslation = array_search($translation, $cloudTranslations);
    }

    return !empty($backTranslation) ? $backTranslation : $translation;
  }

  /**
   * @return void
   */
  protected function loadLanguageData():void
  {
    $dir = realpath(__DIR__);
    $appDir = substr($dir, 0, strrpos($dir, $this->appName)).$this->appName;

    foreach (glob($appDir.DIRECTORY_SEPARATOR.'l10n'.DIRECTORY_SEPARATOR.'*.csv') as $file) {
      $this->mergeCSV($file);
    }
  }

  /**
   * @param string $fileName
   *
   * @return void
   */
  public function mergeCSV(string $fileName):void
  {
    $newTranslations = self::parseCSV($fileName, $this->targetLang, $this->keyLang);
    $this->translations = Util::arrayMergeRecursive($this->translations, $newTranslations);
  }

  /**
   * @param string $fileName
   *
   * @param string $targetLang
   *
   * @param string $keyLang
   *
   * @return null|array
   */
  protected static function parseCSV(string $fileName, string $targetLang, string $keyLang = 'en'):?array
  {
    $file = fopen($fileName, 'r');
    if ($file === false) {
      return null;
    }
    $header = fgetcsv($file);
    if (empty($header)) {
      return null;
    }

    $targetColumn = array_search($targetLang, $header);
    $keyColumn = array_search($keyLang, $header);

    $targetLookup = [];
    $keyLookup = [];
    for ($row = fgetcsv($file); !empty($row); $row = fgetcsv($file)) {
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
      self::FORWARD => $targetLookup,
      self::REVERSE => $keyLookup,
    ];
  }
}
