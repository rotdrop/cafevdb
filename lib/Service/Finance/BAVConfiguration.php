<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service\Finance;

use InvalidArgumentException;

use OCP\IConfig;

use OCA\CAFEVDB\Wrapped\rotdrop\bav\Configuration\DefaultConfiguration;
use OCA\CAFEVDB\Wrapped\rotdrop\bav\DataBackend\Orm\Doctrine\DoctrineBackendContainer;

/** Store bank data in the Nextcloud database, sharing the BAV app's tables. */
class BAVConfiguration extends DefaultConfiguration
{
  /** {@inheritdoc} */
  public function __construct(IConfig $cloudConfig)
  {
    parent::__construct();

    $dbType = $cloudConfig->getSystemValue('dbtype', 'sqlite');
    $dbName = $cloudConfig->getSystemValue('dbname', 'nextcloud');
    $connectionParams = [
      'driver' => match ($dbType) {
        'mysql' => 'pdo_mysql',
        'pgsql' => 'pdo_pgsql',
        'sqlite', 'sqlite3' => 'pdo_sqlite',
        default => throw new InvalidArgumentException('Unsupported Nextcloud database type: ' . $dbType),
      },
      'user' => $cloudConfig->getSystemValue('dbuser', ''),
      'password' => $cloudConfig->getSystemValue('dbpassword', ''),
      'driverOptions' => $cloudConfig->getSystemValue('dbdriveroptions', []),
    ];

    if ($dbType === 'sqlite' || $dbType === 'sqlite3') {
      $dataDir = $cloudConfig->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data');
      $connectionParams['path'] = $dataDir . '/' . $dbName . '.db';
    } else {
      $connectionParams['dbname'] = $dbName;
      $dbHost = $cloudConfig->getSystemValue('dbhost', 'localhost');
      $matches = [];
      if (preg_match('/^(\[[^\]]+\]|[^:]+):(.+)$/', $dbHost, $matches)) {
        $dbHost = $matches[1];
        if (ctype_digit($matches[2])) {
          $connectionParams['port'] = (int)$matches[2];
        } else {
          $connectionParams['unix_socket'] = $matches[2];
        }
      }
      $connectionParams['host'] = trim($dbHost, '[]');
    }

    if ($dbType === 'pgsql') {
      $ssl = $cloudConfig->getSystemValue('pgsql_ssl', false);
      if (is_array($ssl)) {
        foreach (['mode', 'rootcert', 'cert', 'key', 'crl'] as $key) {
          if (isset($ssl[$key])) {
            $connectionParams['ssl' . $key] = $ssl[$key];
          }
        }
      }
    }

    // BAV deliberately uses unprefixed bav_* tables, also in the companion app.
    $this->setDataBackendContainer(DoctrineBackendContainer::buildByConnection($connectionParams));
  }
}
