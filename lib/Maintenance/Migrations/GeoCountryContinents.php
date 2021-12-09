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
use OCA\CAFEVDB\Exceptions;

/**
 * Replace a legacy one-table solution by a clean join-table.
 */
class GeoCountryContinents implements IMigration
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  public function description():string
  {
    return $this->l->t('Replace the legacy country-continent solution by a simple join-table.');
  }

  private const SQL = [
    'CREATE TABLE geo_country_continents (continent_code CHAR(2) NOT NULL COLLATE `ascii_general_ci`, target CHAR(2) NOT NULL COLLATE `ascii_general_ci`, country_iso CHAR(2) NOT NULL COLLATE `ascii_general_ci`, INDEX IDX_47A8687816C569B466F2FFC (continent_code, target), INDEX IDX_47A868785A7049D0466F2FFC (country_iso, target), PRIMARY KEY(continent_code, target, country_iso)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;',
    'ALTER TABLE geo_country_continents ADD CONSTRAINT FK_47A8687816C569B466F2FFC FOREIGN KEY (continent_code, target) REFERENCES GeoContinents (code, target);',
    'ALTER TABLE geo_country_continents ADD CONSTRAINT FK_47A868785A7049D0466F2FFC FOREIGN KEY (country_iso, target) REFERENCES GeoCountries (iso, target);',
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

    // $connection->beginTransaction();
    try {
      foreach (self::SQL as $sql) {
        $statement = $connection->prepare($sql);
        $statement->execute();
      }
      // if ($connection->getTransactionNestingLevel() > 0) {
      //   $connection->commit();
      // }
    } catch (\Throwable $t) {
      // if ($connection->getTransactionNestingLevel() > 0) {
      //   try {
      //     $connection->rollBack();
      //   } catch (\Throwable $t2) {
      //     $t = new Exceptions\DatabaseMigrationException($this->l->t('Rollback of Migration "%s" failed.', $this->description()), $t->getCode(), $t);
      //   }
      // }
      throw new Exceptions\DatabaseMigrationException($this->l->t('Migration "%s" failed.', $this->description()), $t->getCode(), $t);
    }
    return true;
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
