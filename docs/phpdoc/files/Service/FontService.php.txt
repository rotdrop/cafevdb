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

namespace OCA\CAFEVDB\Service;

use Throwable;
use RegexIterator;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;

use PhpOffice\PhpSpreadsheet;

use Psr\Log\LoggerInterface as ILogger;
use OCP\IConfig as CloudConfig;
use OCP\Files\Folder;

use OCA\CAFEVDB\Storage\AppStorageDisclosure;
use OCA\CAFEVDB\Constants;

/** Office font-file locator, in particular for PhpOffice. */
class FontService
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  public const OFFICE_FONTS_FOLDER_CONFIG = 'officeFontsFolder';

  public const DEFAULT_OFFICE_FONT_CONFIG = 'defaultOfficeFont';

  /**
   * @var
   *
   * Folder of the true-type fonts below the app-storage folder.
   */
  public const OFFICE_FOLDER = 'office-fonts';

  public const FONT_FILE_NAMES = PhpSpreadsheet\Shared\Font::FONT_FILE_NAMES;

  const CARLITO = 'Carlito-Regular.ttf';
  const CARLITO_BOLD = 'Carlito-Bold.ttf';
  const CARLITO_ITALIC = 'Carlito-Italic.ttf';
  const CARLITO_BOLD_ITALIC = 'Carlito-BoldItalic.ttf';

  public const FONT_STYLE_PLAIN = 'x';
  public const FONT_STYLE_BOLD = self::FONT_STYLE_PLAIN . 'b';
  public const FONT_STYLE_ITALIC = self::FONT_STYLE_PLAIN . 'i';
  public const FONT_STYLE_BOLD_ITALIC = self::FONT_STYLE_PLAIN . 'bi';

  public const FONT_STYLES = [
    self::FONT_STYLE_PLAIN,
    self::FONT_STYLE_BOLD,
    self::FONT_STYLE_ITALIC,
    self::FONT_STYLE_BOLD_ITALIC,
  ];

  public const FONT_ALIAS_NAMES = [
    'Calibri' => [
      self::FONT_STYLE_PLAIN => self::CARLITO,
      self::FONT_STYLE_BOLD => self::CARLITO_BOLD,
      self::FONT_STYLE_ITALIC => self::CARLITO_ITALIC,
      self::FONT_STYLE_BOLD_ITALIC => self::CARLITO_BOLD_ITALIC,
    ],
  ];

  private const SYSTEM_FONT_DATA_DIR = '/usr/share/fonts/';

  /** @var string */
  protected $appName;

  /** @var CloudConfig */
  protected $cloudConfig;

  /** @var AppStorageDisclosure */
  protected $appStorage;

  /** @var Folder */
  protected $fontsFolder;

  /** @var string */
  protected $fontsFolderLocalPath;

  /**
   * @var array
   *
   * Cache the found system font-files for this request.
   */
  protected $systemFontDataCache = [];

  /**
   *@var array
   *
   * Cache the contents of the app's font-data directory for this request.
   */
  protected $fontFolderEntries;

  /** @var string */
  protected $defaultFont;

  // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    CloudConfig $cloudConfig,
    ILogger $logger,
    AppStorageDisclosure $appStorage,
  ) {
    $this->appName = $appName;
    $this->cloudConfig = $cloudConfig;
    $this->logger = $logger;
    $this->appStorage = $appStorage;
  }
  // phcs:enable

  /** @return string The name of the fonts-folder in the file-system. */
  public function getFontsFolder():Folder
  {
    if (empty($this->fontsFolder)) {
      $this->fontsFolder = $this->appStorage->getFilesystemFolder(self::OFFICE_FOLDER);
    }
    return $this->fontsFolder;
  }

  /** @return string The name of the fonts-folder in the file-system. */
  public function getFontsFolderName():string
  {
    if (!empty($this->fontsFolderLocalPath)) {
      return $this->fontsFolderLocalPath;
    }
    $fontsFolder = $this->cloudConfig->getAppValue($this->appName, self::OFFICE_FONTS_FOLDER_CONFIG, null);
    if (!empty($fontsFolder) && is_dir($fontsFolder)) {
      $this->fontsFolderLocalPath = $fontsFolder;
      return $fontsFolder;
    }

    $this->getFontsFolder();
    $path = $this->fontsFolder->getPath();
    $fontsFolder = $this->fontsFolder->getStorage()->getLocalFile($path);

    $this->logInfo('Font data directory: ' . $fontsFolder);

    $this->cloudConfig->setAppValue($this->appName, self::OFFICE_FONTS_FOLDER_CONFIG, $fontsFolder);
    $this->fontsFolderLocalPath = $fontsFolder;

    return $fontsFolder;
  }

  /**
   * Return the chosen font-default if it is available and set, otherwise one
   * of the installed fonts.
   *
   * @return null|string
   */
  public function getDefaultFontName():?string
  {
    if (!empty($this->defaultFont)) {
      return $this->defaultFont;
    }
    $defaultFont = $this->cloudConfig->getAppValue($this->appName, self::DEFAULT_OFFICE_FONT_CONFIG, null);
    if (!empty($defaultFont) && $this->isFontAvailable($defaultFont)) {
      $this->defaultFont = $defaultFont;
      return $defaultFont;
    }
    $defaultFont = 'Calibri';
    if ($this->isFontAvailable($defaultFont)) {
      $this->defaultFont = $defaultFont;
      return $defaultFont;
    }
    $defaultFont = 'Arial';
    if ($this->isFontAvailable($defaultFont)) {
      $this->defaultFont = $defaultFont;
      return $defaultFont;
    }
    // just take any availabe font
    foreach (array_keys(self::FONT_FILE_NAMES) as $defaultFont) {
      if ($this->isFontAvailable($defaultFont)) {
        $this->defaultFont = $defaultFont;
        return $defaultFont;
      }
    }
    return null;
  }

  /**
   * Check whether the given font
   *
   * @param string $fontName A font name, like 'Arial'.
   *
   * @return bool \true if the font is there, \false if not.
   */
  public function isFontAvailable(string $fontName):bool
  {
    $fonts = $this->getFontFolderEntries();
    foreach (self::FONT_STYLES as $style) {
      if (empty($fonts[$fontName][$style])) {
        return false;
      }
    }
    return true;
  }

  /**
   * Obtain the cached contents of the font-folder, grouped by family.
   *
   * @return array
   */
  public function getFontFolderEntries():array
  {
    if (!empty($this->fontFolderEntries)) {
      return $this->fontFolderEntries;
    }
    return $this->scanFontsFolder();
  }

  /**
   * Scan the app's fonts folder and return the contents grouped by font
   * family.
   *
   * @return array Return the array of found font-files, grouped by family.
   */
  public function scanFontsFolder():array
  {
    $fontFolderEntries = scandir($this->getFontsFolderName());
    $fonts = [];
    foreach (self::FONT_FILE_NAMES as $family => $variants) {
      $fonts[$family] = [ 'family' => $family ];
      foreach ($variants as $variant => $fontFileName) {
        if (array_search($fontFileName, $fontFolderEntries) !== false) {
          $fonts[$family][$variant] = $fontFileName;
        } else {
          $fonts[$family][$variant] = false;
        }
      }
    }

    $this->fontFolderEntries = $fonts;

    return $fonts;
  }

  /**
   * Delete all files in the app's font folder and clear all cache entries.
   *
   * @return void
   */
  public function purgeFontsFolder():void
  {
    $fontsFolder = $this->getFontsFolder();
    $fontsFolder->delete();
    $this->fontsFolder = null;
    $this->fontsFolderLocalPath = null;
    $this->systemFontDataCache = null;
    $this->fontFolderEntries = null;
    $this->getFontsFolder();
    $this->getFontsFolderName();
  }

  /**
   * Populate the true-type fonts folder with font-files from the system or
   * from other sources (download?). This is time-consuming and meant as
   * action initiated by console commands or the admin settings page.
   *
   * @return array Return the array of found font-files, grouped by family.
   */
  public function populateFontsFolder():array
  {
    $fontsFolder = $this->getFontsFolder();
    $fontsFolderLocalPath = $this->getFontsFolderName();

    $fonts = [];
    foreach (self::FONT_FILE_NAMES as $family => $variants) {
      $fonts[$family] = [ 'family' => $family ];
      foreach ($variants as $variant => $fontFileName) {
        $fontFilePath = $this->findOfficeFontFile($fontFileName);
        if (empty($fontFilePath)) {
          $this->logInfo('Unable to find ' . $fontFileName);
          $fontAliasName = self::FONT_ALIAS_NAMES[$family][$variant] ?? null;
          if (!empty($fontAliasName)) {
            $this->logInfo('Substituting ' . $fontAliasName . ' for ' . $fontFileName);
            $fontFilePath = $this->findOfficeFontFile($fontAliasName);
            if (empty($fontFilePath)) {
              $this->logInfo('Unable to find ' . $fontAliasName);
            }
          }
        }
        if (empty($fontFilePath)) {
          $fonts[$family][$variant] = false;
          continue;
        }
        $fonts[$family][$variant] = $fontFileName;
        $this->logInfo('Found ' . $fontFilePath);
        if (str_starts_with($fontFilePath, $fontsFolderLocalPath)) {
          // already installed, just skip
          continue;
        }
        $fontData = file_get_contents($fontFilePath);
        if ($fontsFolder->nodeExists($fontFileName)) {
          $fontsFolder->get($fontFileName)->putContent($fontData);
        } else {
          $fontsFolder->newFile($fontFileName, $fontData);
        }
      }
    }

    return $fonts;
  }

  /**
   * @param string $fontFileName
   *
   * @param bool $useLocate Use `locate` program to find font files in
   * addition to searching in the default system font directory.
   *
   * @return string The full path to the font file.
   */
  public function findOfficeFontFile(string $fontFileName, bool $useLocate = false):?string
  {
    $this->getFontsFolder();
    $fontsFolderLocalName = $this->getFontsFolderName();
    $cacheRealPath = $fontsFolderLocalName . Constants::PATH_SEP . $fontFileName;
    if (is_file($cacheRealPath)) {
      return $cacheRealPath;
    }
    if (empty($this->systemFontDataCache)) {
      $fontDataDir = realpath(self::SYSTEM_FONT_DATA_DIR);
      if (!empty($fontDataDir) && file_exists($fontDataDir) && is_dir($fontDataDir)) {
        $this->logInfo('SYSTEM FONT DIR ' . $fontDataDir);
        $iterator = new RegexIterator(
          new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fontDataDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
          ),
          '/^.*\\.ttf$/i',
          RegexIterator::GET_MATCH);
        foreach ($iterator as $key => $info) {
          // $this->logInfo('INFO ' . print_r($info, true));
          $pathName = $key;
          $this->systemFontDataCache[basename($pathName)] = $pathName;
        }
        $info = null;
      }
      $this->logInfo('FONT CACHE ' . print_r($this->systemFontDataCache, true));
    }
    if (!empty($this->systemFontDataCache[$fontFileName])) {
      return $this->systemFontDataCache[$fontFileName];
    }

    if ($useLocate) {
      // try locate if not found in standard path
      $outputLines = [];
      $resultCode = -1;
      try {
        exec('locate ' . $fontFileName, $outputLines, $resultCode);
        foreach ($outputLines as $fontPathCandidate) {
          if (file_exists($fontPathCandidate)) {
            $this->systemFontDataCache[$fontFileName] = $fontPathCandidate;
            return $fontPathCandidate;
          }
        }
      } catch (Throwable $t) {
        $this->logException($t, 'Locating "' . $fontFileName . '" caught an exception.');
      }
    }

    unset($this->systemFontDataCache[$fontFileName]);

    return null;
  }
}
