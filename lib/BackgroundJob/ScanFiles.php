<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023, 2024, 2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\BackgroundJob;

use OCA\Files\BackgroundJob\ScanFiles as FilesAppScanFiles;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/**
 * Class ScanFiles is a background job used to run the file scanner over the user
 * accounts to ensure integrity of the file cache. This is a copy of
 *
 * OCA\Files\BackgroundJob\ScanFiles
 *
 * which is triggered by a JS timer via Ajax from the front-end in order to
 * get authenticated background jobs.
 */
class ScanFiles extends FilesAppScanFiles
{
  /** {@inheritdoc} */
  public function __construct(
    IConfig $config,
    IEventDispatcher $dispatcher,
    LoggerInterface $logger,
    IDBConnection $connection,
    ITimeFactory $time,
  ) {
    parent::__construct(
      config: $config,
      dispatcher: $dispatcher,
      logger: $logger,
      connection: $connection,
      time: $time,
    );
  }

  /** {@inheritdoc} */
  public function run($argument)
  {
    parent::run($argument);
  }
}
