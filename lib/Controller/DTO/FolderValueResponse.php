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
 * DTO for lazy decryption endpoint.
 */
class FolderValueResponse extends MessagesResponse
{
  /** {@inheritdoc} */
  public function __construct(
    array $messages,
    public readonly string $value,
    /** @var null|string Files-app link. */
    public readonly ?string $folderLink,
    /** @var null|string Public link if appropriate. */
    public readonly ?string $url,
  ) {
    parent::__construct($messages);
  }

  /**
   * Initialize from the given array.
   *
   * @param array $data
   *
   * @return NameIdValueResponse
   */
  public static function fromArray(array $data): FolderValueResponse
  {
    static::initKeys();
    extract(array_intersect_key($data, array_flip(static::$keys[__CLASS__])));
    if (empty($messages) && !empty($data['message'])) {
      $messages = [$data['message']];
    }
    return new self($messages, $value, $folderLink, $url ?? null);
  }
}
