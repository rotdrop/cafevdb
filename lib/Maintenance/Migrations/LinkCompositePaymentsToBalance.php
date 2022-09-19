<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 * Remember the id of a mailing list.
 */
class LinkCompositePaymentsToBalance extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      'ALTER TABLE CompositePayments ADD COLUMN IF NOT EXISTS project_id INT DEFAULT NULL, ADD balance_document_sequence INT DEFAULT NULL',
      'ALTER TABLE CompositePayments ADD CONSTRAINT FK_65D9920C166D1F9C FOREIGN KEY IF NOT EXISTS (project_id) REFERENCES Projects (id)',
      'ALTER TABLE CompositePayments ADD CONSTRAINT FK_65D9920C166D1F9C9523AA8A FOREIGN KEY IF NOT EXISTS (project_id, musician_id) REFERENCES ProjectParticipants (project_id, musician_id)',
      'ALTER TABLE CompositePayments ADD CONSTRAINT FK_65D9920C166D1F9C6A022FD1 FOREIGN KEY IF NOT EXISTS (project_id, balance_document_sequence) REFERENCES ProjectBalanceSupportingDocuments (project_id, sequence)',
      'CREATE INDEX IF NOT EXISTS IDX_65D9920C166D1F9C ON CompositePayments (project_id)',
      'CREATE INDEX IF NOT EXISTS IDX_65D9920C166D1F9C9523AA8A ON CompositePayments (project_id, musician_id)',
      'CREATE INDEX IF NOT EXISTS IDX_65D9920C166D1F9C6A022FD1 ON CompositePayments (project_id, balance_document_sequence)',
    ],
    self::TRANSACTIONAL => [
      'UPDATE CompositePayments cp
LEFT JOIN ProjectPayments pp
ON cp.id = pp.composite_payment_id
SET cp.project_id = pp.project_id
WHERE cp.project_id IS NULL',
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Optionally link composite payments to the project balance supporting documents.');
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
