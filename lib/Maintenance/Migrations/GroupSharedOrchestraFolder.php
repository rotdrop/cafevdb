<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023, 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Maintenance\Migrations;

use Throwable;

use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Maintenance\IMigration;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ConfigCheckService;
use OCA\CAFEVDB\Service\EncryptionService;

/**
 * Decrypt the shareowner config value.
 */
class GroupSharedOrchestraFolder implements IMigration
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected ILogger $logger,
    protected IL10N $l,
    protected ConfigCheckService $configCheckService,
    protected EncryptionService $encryptionService,
  ) {
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t(
      'Abandon the orchestra folder shared by the share-owner "%s" and instead use a group-shared folder with the same name.'
      . ' This has several advantages.'
      . ' The first is that this is one step towards the removal of the shareowner dummy user altogether,'
      . ' the second is that the mount-point name of the group-shared folder is more stable; it cannot be renamed.'
      . ' Further this change opens the possibility to a finer grained access control of sub-folders, modelling them as additional group-shared folders.'
      . ' Please note that this may be a long running operation, depending on the size of the shared orchestra folder.'
      . ' Please note also that the old folder is not removed by this automatic migration step.'
      . ' This also implies that there must be enough storage space left on the server in order to hold the new copy.',
      $this->encryptionService->getConfigValue(ConfigService::SHAREOWNER_KEY)
    );
  }

  /** {@inheritdoc} */
  public function execute():bool
  {
    try {
      throw new Exceptions\EnduserNotificationException('TODO: NOT YET IMPLEMENTED');
      // roadmap:
      //
      // - create a new group-shared folder FOLDERNAME-new
      //
      // - copy all the data from the old shared folder to the new one, recursively
      //
      // - unshare the old folder, removing all shares. This will also
      //   invalidate member download shares, but so what. Or could this be
      //   avoided by tweaking the database?
      //
      // - rename the new group share to FOLDERNAME
    } catch (Throwable $t) {
      if ($t instanceof Exceptions\EnduserNotificationException) {
        throw $t;
      }
      $this->logException($t);
      return false;
    }

    return true;
  }
}
