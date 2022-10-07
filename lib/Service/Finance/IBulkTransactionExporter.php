<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use \DateTimeImmutable as DateTime;

/**
 * Extremely generic debit-note exporter. The purpose of this class is
 * to generate the export data-sets for a given banking
 * application. The only input is a Entities\SepaBulkTransaction
 * entity, in either of its specializations as
 * Entities\SepaBankTransfer or Entities\SepaDebitNote.
 *
 * The idea is to generate mime-type, file-extension and the actual
 * file data and leave the file-name to the calling higher-level
 * controller code.
 */
interface IBulkTransactionExporter
{
  /**
   * Return a name to construct slugs etc.
   */
  static public function identifier():string;

  /**
   * Generate the mime-type for the given bulk-transaction.
   *
   * @param Entities\SepaBulkTransaction $transaction
   */
  public function mimeType(Entities\SepaBulkTransaction $transaction):string;

  /**
   * Generate the file-extension for the given bulk-transaction, with out the dot.
   *
   * @param Entities\SepaBulkTransaction $transaction
   */
  public function fileExtension(Entities\SepaBulkTransaction $transaction):string;

  /**
   * Generate the actual file-data for the given bulk-transaction.
   *
   * @param Entities\SepaBulkTransaction $transaction
   */
  public function fileData(Entities\SepaBulkTransaction $transaction):string;
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
