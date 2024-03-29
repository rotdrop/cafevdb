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

namespace OCA\CAFEVDB\Migration;

use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Service\AppMTimeService;

/**
 * Register some extra mime-types, in particuluar in order to have custom
 * folder icons for the database storage. Nextcloud uses the `dir-MOUNTTYPE`
 * pseudo mime-type in order to select icons for directories.
 *
 * @see OCA\CAFEVDB\Storage\Database\MountProvider
 */
class RecordModificationTimes implements IRepairStep
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  public const ASSETS_MTIME_KEY = 'assets_mtime';
  public const L10N_MTIME_KEY = 'l10n_mtime';
  public const PHP_LIB_MTIME_KEY = 'php_lib_mtime';
  public const TEMPLATES_MTIME_KEY = 'templates_mtime';

  /** @var AppMTimeService */
  protected AppMTimeService $appMTimeService;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected string $appName,
    protected ILogger $logger,
    AppMTimeService $appMTimeService,
  ) {
    $this->appMTimeService = $appMTimeService;
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function getName()
  {
    return 'Record modification times of parts of the source code for ' . $this->appName;
  }

  /** {@inheritdoc} */
  public function run(IOutput $output)
  {
    foreach (array_keys(AppMTimeService::PARTS) as $appPart) {
      $this->appMTimeService->getMTime($appPart, rescan: true);
    }
  }
}
