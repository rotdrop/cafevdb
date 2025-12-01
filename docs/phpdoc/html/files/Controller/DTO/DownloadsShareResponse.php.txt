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

use DateTimeInterface;
use OCA\CAFEVDB\Wrapped\Carbon\CarbonImmutable;

/**
 * DTO upload file data as reported by PHP, a bit enhanced.
 */
class DownloadsShareResponse extends \OCA\CAFEVDB\Toolkit\DTO\AbstractResponseDTO
{
  public readonly ?CarbonImmutable $expires;

  /** {@inheritdoc} */
  public function __construct(
    /** @var string[] */
    public readonly array $messages,
    public readonly ?string $share,
    public readonly ?string $folder,
    ?DateTimeInterface $expires,
  ) {
    $this->expires = CarbonImmutable::instance($expires);
  }

  /**
   * Create from $data array.
   *
   * @param array $data
   *
   * @return self
   */
  public static function fromArray(array $data): self
  {
    static::initKeys();
    extract(array_intersect_key($data, array_flip(static::$keys[__CLASS__])));
    return new self(
      $messages ?? [],
      $share ?? null,
      $folder ?? null,
      $expires ?? null,
    );
  }
}
