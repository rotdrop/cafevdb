<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Common;

use \PDO;
use \malkusch\bav as BAV;
use \malkusch\bav\BAV as BaseClass;

use OCP\IConfig;

/**
 * A small wrapper class which makes sure BAV uses the correct configuration.
 */
class BankAccountValidator extends BaseClass
{
  /** @var IConfig */
  private $cloudConfig;

  /**
   * Construct \malkusch\bav\BAV from a PDO configuration.
   *
   * @param IConfig $cloudConfig
   */
  public function __construct(IConfig $cloudConfig)
  {
    $this->cloudConfig = $cloudConfig;

    parent::__construct($this->generateConfiguration());
  }

  /**
   * Generate a suitable default configuration for \malkusch\bav\BAV.
   *
   * @return BAV\Configuration
   */
  public function generateConfiguration():BAV\Configuration
  {
    $bavConfig = new BAV\DefaultConfiguration();

    $dbType = $this->cloudConfig->getSystemValue('dbtype', 'mysql');
    $dbHost = $this->cloudConfig->getSystemValue('dbhost', 'localhost');
    $dbName = $this->cloudConfig->getSystemValue('dbname', false);
    $dbUser = $this->cloudConfig->getSystemValue('dbuser', false);
    $dbPass = $this->cloudConfig->getSystemValue('dbpassword', false);

    $dbURI = $dbType.':'.'host='.$dbHost.';dbname='.$dbName;

    $pdo = new PDO($dbURI, $dbUser, $dbPass);
    $bavConfig->setDataBackendContainer(new BAV\PDODataBackendContainer($pdo));

    $bavConfig->setUpdatePlan(new BAV\AutomaticUpdatePlan());

    return $bavConfig;
  }
}
