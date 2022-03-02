<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Crypto;

use Psr\Container\ContainerInterface;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IConfig;

class Registration
{
  static public function register(IRegistrationContext $context)
  {
    $context->registerService(CryptoFactoryInterface::class, function(ContainerInterface $container) {
      return self::getCryptoFactory($container);
    });

    $context->registerService(SymmetricCryptorInterface::class, function(ContainerInterface $container) {
      return self::getCryptoFactory($container)->getSymmetricCryptor();
    });

    $context->registerService(AsymmetricCryptorInterface::class, function(ContainerInterface $container) {
      return self::getCryptoFactory($container)->getAsymmetricCryptor();
    });

    $context->registerService(AsymmetricKeyStorageInterface::class, function(ContainerInterface $container) {
      return self::getCryptoFactory($container)->getAsymmetricKeyStorage();
    });
  }

  static private function getCryptoFactory(ContainerInterface $container):CryptoFactoryInterface
  {
    return $container->get(HaliteCryptoFactory::class);
  }
}
