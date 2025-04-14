<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2022, 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Storage\Database;

use Psr\Container\ContainerInterface;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Service\ConfigService;

/** Register some utiltiy services in order to ease dependency injection. */
class Registration
{
  /**
   * Static service registration routine.
   *
   * @param IRegistrationContext $context
   *
   * @return void
   */
  public static function register(IRegistrationContext $context):void
  {
    $context->registerService(Storage::class, function(ContainerInterface $container) {
      return new Storage([ 'configService' => $container->get(ConfigService::class), ]);
    });
    $context->registerService(BankTransactionsStorage::class, function(ContainerInterface $container) {
      return new BankTransactionsStorage([ 'configService' => $container->get(ConfigService::class), ]);
    });
    $context->registerService(TaxExemptionNoticesStorage::class, function(ContainerInterface $container) {
      return new TaxExemptionNoticesStorage([ 'configService' => $container->get(ConfigService::class), ]);
    });
    $context->registerService(DonationReceiptsStorage::class, function(ContainerInterface $container) {
      return new DonationReceiptsStorage([ 'configService' => $container->get(ConfigService::class), ]);
    });
  }
}
