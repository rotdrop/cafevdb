<?php
/*
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnuorg/licenses/>.
 */

namespace OCA\CAFEVDB\Service;

use Psr\Container\ContainerInterface;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IConfig;

use OCA\CAFEVDB\Common\Crypto\CloudSymmetricCryptor;

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
    $context->registerService(CloudSymmetricCryptor::class, function(ContainerInterface $container) {
      return $container->get('AppCryptor');
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
