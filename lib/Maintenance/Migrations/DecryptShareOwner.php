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

use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Maintenance\IMigration;

/**
 * Decrypt the shareowner config value.
 */
class DecryptShareOwner implements IMigration
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  /** @var EncryptionService */
  private $encryptionService;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ILogger $logger,
    IL10N $l10n,
    EncryptionService $encryptionService,
  ) {
    $this->logger = $logger;
    $this->l = $l10n;
    $this->encryptionService = $encryptionService;
  }

  // phpcs:enable
  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Decrypt the "%s" configuration value.', ConfigService::SHAREOWNER_KEY);
  }

  /** {@inheritdoc} */
  public function execute():bool
  {
    try {
      $shareOwner = $this->encryptionService->getConfigValue(ConfigService::SHAREOWNER_KEY);
      $this->encryptionService->setConfigValue(ConfigService::SHAREOWNER_KEY, $shareOwner);
    } catch (Throwable $t) {
      return false;
    }

    return true;
  }
}
