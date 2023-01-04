<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine
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
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Database\Cloud\Entities;

use Doctrine\DBAL\Types\Types;

use OCP\AppFramework\Db\Entity;

use Psr\Log\LoggerInterface as ILogger;

/** Cloud progress status entity. */
class ProgressStatus extends Entity
{
  public $id;
  protected $userId;
  protected $current;
  protected $target;
  protected $data;
  protected $lastModified;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    // $this->addType('id', 'int'); this is default
    $this->addType('current', 'int');
    $this->addType('target', 'int');
    $this->addType('data', 'string');
    $this->addType('lastModified', 'int');
    $this->lastModified = time();
    $this->markFieldUpdated('lastModified');
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function __call($methodName, $args)
  {
    if (strpos($methodName, 'set') === 0) {
      $this->lastModified = time();
      $this->markFieldUpdated('lastModified');
    }
    return parent::__call($methodName, $args);
  }
}
