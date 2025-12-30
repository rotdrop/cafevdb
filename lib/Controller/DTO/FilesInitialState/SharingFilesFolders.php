<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022-2025 Claus-Justus Heine
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
 *
 * @phpcs:disable PEAR.Commenting.ClassComment.Missing
 * @phpcs:disable PEAR.Commenting.FunctionComment.Missing
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
 * @phpcs:disable Squiz.Commenting.ClassComment.Missing
 * @phpcs:disable Squiz.Commenting.FunctionComment.Missing
 */

namespace OCA\CAFEVDB\Controller\DTO\FilesInitialState;

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

use OCA\CAFEVDB\Toolkit\DTO\AbstractDTO;

#[TSAttributes\InlineTypeScriptType]
class SharingFilesFolders extends AbstractDTO
{
  /** {@inheritdoc} */
  public function __construct(
    public readonly string $root,
    public readonly string $balances,
    public readonly string $donationReceipts,
    public readonly string $finance,
    public readonly string $invoices,
    public readonly string $projectBalances,
    public readonly string $projectManagement,
    public readonly string $templates,
  ) {
  }

  /**
   * Initialize from the given array.
   *
   * @param array $data
   *
   * @return self
   *
   * @SuppressWarnings(PHPMD.UndefinedVariable)
   * @SuppressWarnings(PHPMD.UnusedLocalVariable)
   */
  public static function fromArray(array $data): self
  {
    static::initKeys();
    extract(array_intersect_key($data, array_flip(static::$keys[__CLASS__])));
    return new self(
      root: $root,
      balances: $balances,
      donationReceipts: $donationReceipts,
      finance: $finance,
      invoices: $invoices,
      projectBalances: $projectBalances,
      projectManagement: $projectManagement,
      templates: $templates,
    );
  }
}
