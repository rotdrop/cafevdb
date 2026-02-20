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
 * DTO for the orchestra locale.
 */
class ConfigCheckResponse extends \OCA\CAFEVDB\Toolkit\DTO\AbstractResponseDTO
{
  /** {@inheritdoc} */
  public function __construct(
    public readonly bool $summary,
    public readonly ConfigCheckItem $orchestra,
    public readonly ConfigCheckItem $userGroup,
    public readonly ConfigCheckItem $shareOwner,
    public readonly ConfigCheckItem $sharedFolder,
    public readonly ConfigCheckItem $database,
    public readonly ConfigCheckItem $encryptionKey,
    public readonly ConfigCheckItem $migrations,
    public readonly ConfigCheckItem $sharedAddressBooks,
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
      summary: $summary,
      orchestra: is_array($orchestra) ? ConfigCheckItem::fromArray($orchestra) : $orchestra,
      userGroup: is_array($userGroup) ? ConfigCheckItem::fromArray($userGroup) : $userGroup,
      shareOwner: is_array($shareOwner) ? ConfigCheckItem::fromArray($shareOwner) : $shareOwner,
      sharedFolder: is_array($sharedFolder) ? ConfigCheckItem::fromArray($sharedFolder) : $sharedFolder,
      database: is_array($database) ? ConfigCheckItem::fromArray($database) : $database,
      encryptionKey: is_array($encryptionKey) ? ConfigCheckItem::fromArray($encryptionKey) : $encryptionKey,
      migrations: is_array($migrations) ? ConfigCheckItem::fromArray($migrations) : $migrations,
      sharedAddressBooks: is_array($sharedAddressBooks) ? ConfigCheckItem::fromArray($sharedAddressBooks) : $sharedAddressBooks,
    );
  }
}
