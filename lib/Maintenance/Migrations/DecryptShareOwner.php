<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022-2025 Claus-Justus Heine
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

use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Maintenance\IMigration;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Settings\ConfigConstants;

/**
 * Decrypt the shareowner config value.
 */
class DecryptShareOwner implements IMigration
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected ILogger $logger,
    protected IL10N $l,
    protected EncryptionService $encryptionService,
  ) {
  }

  // phpcs:enable
  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Decrypt the "%s" configuration value.', ConfigConstants::SHAREOWNER_KEY);
  }

  /** {@inheritdoc} */
  public function execute():bool
  {
    try {
      $shareOwner = $this->encryptionService->getConfigValue(ConfigConstants::SHAREOWNER_KEY);
      $this->encryptionService->setConfigValue(ConfigConstants::SHAREOWNER_KEY, $shareOwner);
    } catch (Throwable $t) {
      return false;
    }

    return true;
  }
}
