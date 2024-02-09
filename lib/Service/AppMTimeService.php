<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
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

use InvalidArgumentException;
use RecursiveDirectoryIterator;

use OCP\IConfig as CloudConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;

/**
 * Register some extra mime-types, in particuluar in order to have custom
 * folder icons for the database storage. Nextcloud uses the `dir-MOUNTTYPE`
 * pseudo mime-type in order to select icons for directories.
 *
 * @see OCA\CAFEVDB\Storage\Database\MountProvider
 */
class AppMTimeService
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  public const ASSETS_MTIME_KEY = 'assets_mtime';
  public const L10N_MTIME_KEY = 'l10n_mtime';
  public const PHP_LIB_MTIME_KEY = 'php_lib_mtime';
  public const TEMPLATES_MTIME_KEY = 'templates_mtime';

  public const PARTS = [
    self::ASSETS_MTIME_KEY => [ 'js', 'css' ],
    self::L10N_MTIME_KEY => [ 'l10n', ],
    self::PHP_LIB_MTIME_KEY => [ 'lib', '3rdparty/phpMyEdit', 'appinfo', ],
    self::TEMPLATES_MTIME_KEY => [ 'templates', ],
  ];

  /**
   * @param string $appName
   *
   * @param ILogger $logger
   *
   * @param IL10N $l
   *
   * @param CloudConfig $cloudConfig
   */
  public function __construct(
    protected string $appName,
    protected ILogger $logger,
    protected IL10N $l,
    protected CloudConfig $cloudConfig,
  ) {
  }

  /**
   * Return the modification time according to key, see
   * AppMTimeService::PARTS. The result of the scan is stored in the app
   * configuration space. If the configuration keys are not present or $rescan
   * === \true, the respective part of the distribution will be rescanned
   * recursively.
   *
   * @param string $key
   *
   * @param bool $rescan
   *
   * @return int The file modification time of all regular files in the
   * respective part of the distribution.
   *
   * @throws InvalidArgumentException
   */
  public function getMTime(string $key, bool $rescan = false):int
  {
    if (empty(self::PARTS[$key])) {
      throw new InvalidArgumentException($this->l->t('Key must be one of "%1$s", got "%2$s".', [
        implode('","', array_keys(self::PARTS)), $key,
      ]));
    }
    $cachedMTime = $this->cloudConfig->getAppValue($this->appName, $key, 0);
    if ($cachedMTime > 0 && !$rescan) {
      return $cachedMTime;
    }
    $appDir = __DIR__ . '/../../';
    $flags = RecursiveDirectoryIterator::SKIP_DOTS
      |RecursiveDirectoryIterator::CURRENT_AS_FILEINFO;
    $mTime = 1; // 0 is illegal in some places, so start with 1.
    foreach (self::PARTS[$key] as $subDirPath) {
      /** @var SplFileInfo $fileInfo */
      foreach (new RecursiveDirectoryIterator($appDir . $subDirPath, $flags) as $fileInfo) {
        $fileMTime = (int)$fileInfo->getMTime(); // false will be 0 and thus smaller than the start value
        $mTime = max($mTime, $fileMTime);
      }
    }
    $this->cloudConfig->setAppValue($this->appName, $key, $mTime);
    return $mTime;
  }
}
