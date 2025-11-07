<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

use OCP\IL10N;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/** Generate balancing items for a bank bulk transactions for use in an
 *  accounting software. */
class GnuCashBulkTransactionBalancingItemsExporter implements IBulkTransactionExporter
{
  const IDENTIFIER = 'gnucash';

  const CSV_DELIMITER = ';';

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected IL10N $l,
    protected GnuCashConnectorService $gnuCashConnectorService,
  ) {
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public static function identifier():string
  {
    return self::IDENTIFIER;
  }

  /** {@inheritdoc} */
  public function mimeType(Entities\SepaBulkTransaction $transaction):string
  {
    return 'text/csv';
  }

  /** {@inheritdoc} */
  public function fileExtension(Entities\SepaBulkTransaction $transaction):string
  {
    return 'csv';
  }

  /** {@inheritdoc} */
  public function fileData(Entities\SepaBulkTransaction $transaction):string
  {
    $data = $this->gnuCashConnectorService->exportBulkTransactionBalancingEntries($transaction);

    $csvData = str_putcsv(array_keys($data[0]), self::CSV_DELIMITER);
    foreach ($data as $record) {
      $csvData .= "\n" . str_putcsv($record, self::CSV_DELIMITER);
    }
    return $csvData;
  }
}
