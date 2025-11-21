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
 */

namespace OCA\CAFEVDB\Controller\DTO;

/**
 * DTO for IBAN meta-data.
 */
class IBANMetaData extends \OCA\CAFEVDB\Toolkit\DTO\AbstractResponseDTO
{
  /** {@inheritdoc} */
  public function __construct(
    public readonly string $iban,
    public readonly string $country,
    public readonly ?string $bic = null,
    public readonly ?string $blz = null,
    public readonly ?string $account = null,
    public readonly ?string $bank = null,
    public readonly ?string $city = null,
  ) {
  }

  /**
   * Create from FinanceService::getIbanInfo().
   *
   * @param array $ibanMetaData
   *
   * @return IBANMetatData
   */
  public static function fromArray(array $ibanMetaData): IBANMetaData
  {
    static::initKeys();
    extract(array_intersect_key($ibanMetaData, array_flip(static::$keys[__CLASS__])));
    return new IBANMetaData($iban, $country, $bic ?? null, $blz ?? null, $account ?? null, $bank ?? null, $city ?? null);
  }
}
