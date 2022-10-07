<?php
/*
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use Psr\Container\ContainerInterface;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IConfig;

use OCA\CAFEVDB\Crypto\CloudSymmetricCryptor;

class Registration
{
  const MANAGEMENT_GROUP_ID = 'ManagementGroupId';

  static public function register(IRegistrationContext $context)
  {
    $context->registerServiceAlias(
      'export:bank-bulk-transactions:aqbanking',
      Finance\AqBankingBulkTransactionExporter::class);

    $context->registerService('AppCryptor', function(ContainerInterface $container) {
      return $container->get(EncryptionService::class)->getAppCryptor();
    });
    $context->registerService('AppAsymmetricCryptor', function(ContainerInterface $container) {
      return $container->get(EncryptionService::class)->getAppAsymmetricCryptor();
    });

    $context->registerService(ucfirst(self::MANAGEMENT_GROUP_ID), function(ContainerInterface $container) {
      return $container->get(IConfig::class)->getAppValue(
        $container->get('AppName'),
        'usergroup', // TODO: use class-const from somewhere
        null
      );
    });
    $context->registerServiceAlias(lcfirst(self::MANAGEMENT_GROUP_ID), ucfirst(self::MANAGEMENT_GROUP_ID));
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
