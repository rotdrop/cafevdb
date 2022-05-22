<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

class AddEncryptedFileOwners extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "CREATE TABLE EncryptedFileOwners (musician_id INT NOT NULL, encrypted_file_id INT NOT NULL, INDEX IDX_5697DE239523AA8A (musician_id), INDEX IDX_5697DE23EC15E76C (encrypted_file_id), PRIMARY KEY(musician_id, encrypted_file_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB",
      "ALTER TABLE EncryptedFileOwners ADD CONSTRAINT FK_5697DE239523AA8A FOREIGN KEY (musician_id) REFERENCES Musicians (id) ON DELETE CASCADE",
      "ALTER TABLE EncryptedFileOwners ADD CONSTRAINT FK_5697DE23EC15E76C FOREIGN KEY (encrypted_file_id) REFERENCES Files (id) ON DELETE CASCADE",
    ],
  ];

  public function description():string
  {
    return $this->l->t('Store the owner-ship information for encrypted files in a join table.');
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
