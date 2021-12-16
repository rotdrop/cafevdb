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

use OCA\CAFEVDB\Database\EntityManager;

/**
 * Remember the reference to the message template for mail-merged messages.
 */
class SentEmailReferences extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      'ALTER TABLE SentEmails ADD reference_id VARCHAR(256) DEFAULT NULL COLLATE `ascii_bin`',
      'ALTER TABLE SentEmails ADD CONSTRAINT FK_80F49BA01645DEA9 FOREIGN KEY (reference_id) REFERENCES SentEmails (message_id)',
      'CREATE INDEX IDX_80F49BA01645DEA9 ON SentEmails (reference_id)',
    ],
    self::TRANSACTIONAL => [
    ],
  ];

  public function description():string
  {
    return $this->l->t('Remember a reference to the message template for mail-merged emails.');
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
