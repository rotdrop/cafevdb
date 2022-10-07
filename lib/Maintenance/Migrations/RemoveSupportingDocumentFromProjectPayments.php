<?php
/**
 * Orchestra member, musician and project management application.
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

namespace OCA\CAFEVDB\Maintenance\Migrations;

use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Maintenance\IMigration;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Exceptions;

/**
 * Add some non-null constraints and such.
 */
class RemoveSupportingDocumentFromProjectPayments extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "ALTER TABLE ProjectPayments DROP FOREIGN KEY FK_F6372AE22423759C",
      "DROP INDEX UNIQ_F6372AE22423759C ON ProjectPayments",
      "ALTER TABLE ProjectPayments DROP supporting_document_id",
    ],
  ];

  public function description():string
  {
    return $this->l->t('Add supporting-document to composite payments.');
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
