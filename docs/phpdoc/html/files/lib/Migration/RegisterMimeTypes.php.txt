<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022, 2023, 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Migration;

use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Storage\Database\MountProvider;

/**
 * Register some extra mime-types, in particuluar in order to have custom
 * folder icons for the database storage. Nextcloud uses the `dir-MOUNTTYPE`
 * pseudo mime-type in order to select icons for directories.
 *
 * @see OCA\CAFEVDB\Storage\Database\MountProvider
 */
class RegisterMimeTypes implements IRepairStep
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  const MIMETYPE_MAPPING_FILE = 'mimetypemapping.json';
  const MIMETYPE_ALIASES_FILE = 'mimetypealiases.json';

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected string $appName,
    protected ILogger $logger,
  ) {
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function getName()
  {
    return 'Register MIME types for ' . $this->appName;
  }

  /** {@inheritdoc} */
  public function run(IOutput $output)
  {
    $mimeData = [
      [
        'file' => \OC::$configDir . self::MIMETYPE_MAPPING_FILE,
        'data' => [],
      ], [
        'file' => \OC::$configDir . self::MIMETYPE_ALIASES_FILE,
        'data' => [ 'dir-' . MountProvider::MOUNT_TYPE => 'dir-encrypted', ],
      ],
    ];

    foreach ($mimeData as $dataSet) {
      $data = $dataSet['data'];
      if (empty($data)) {
        continue;
      }
      $file = $dataSet['file'];
      $this->logInfo('Modifying "' . $file . '" ...');
      if (file_exists($file)) {
        $existingData = json_decode(file_get_contents($file), true);
        $data = array_merge($existingData, $data);
      }
      file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
      $this->logInfo('... added mime-data ' . print_r($dataSet['data'], true));
    }

    // @todo Check whether `occ maintenance:mimetype:update-js` and/or `occ
    // maintenance:mimetype:update-db` must be run. This should be automated.
    //
    // @todo Implement a mime-type cleanup on uninstall (not sooo important
    // but should be done one day).
  }
}
