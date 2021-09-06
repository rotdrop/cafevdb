<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Maintenance\Migrations;

use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Maintenance\IMigration;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/**
 * Generate some needed procedures and functions. MySQL specific.
 */
class RemoveGenerateNumbers implements IMigration
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  public function description():string
  {
    return $this->l->t('Remove obsolete generateNumbers() stored procedure.');
  }

  private const SQL = [
    "DROP PROCEDURE IF EXISTS `generateNumbers`",
  ];

  public function __construct(
    ILogger $logger
    , IL10N $l10n
    , EntityManager $entityManager
  ) {
    $this->logger = $logger;
    $this->l = $l10n;
    $this->entityManager = $entityManager;
  }

  public function execute():bool
  {
    $connection = $this->entityManager->getConnection();

    foreach (self::SQL as $sql) {
      $statement = $connection->prepare($sql);
      $statement->execute();
    }
    return true;
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
