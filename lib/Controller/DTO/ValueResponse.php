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

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

/**
 * DTO for as simple response containing one value and optional messages and hints.x
 */
#[TSAttributes\TypeScript]
class ValueResponse extends MessagesResponse
{
  /** {@inheritdoc} */
  public function __construct(
    array $messages,
    /** @var string|int|float|array<int|float|string|object>|array<string, int|float|string|object> */
    public readonly int|float|string|array|\JsonSerializable $value,
    ?array $hints = null,
  ) {
    parent::__construct($messages, $hints);
  }

  /** {@inheritdoc} */
  public static function create(
    string|array $messages,
    int|float|string|array|\JsonSerializable $value,
    ?array $hints = null,
  ): self {
    return new self(
      messages: is_array($messages) ? $messages : [$messages],
      value: $value,
      hints: $hints ?? null,
    );
  }

  /**
   * Initialize from the given array.
   *
   * @param array $data
   *
   * @return self
   */
  public static function fromArray(array $data): self
  {
    static::initKeys();
    extract(array_intersect_key($data, array_flip(static::$keys[__CLASS__])));
    if (empty($messages) && !empty($data['message'])) {
      $messages = [$data['message']];
    }
    return new self(
      messages: $messsages,
      hints: $hints ?? null,
      value: $value,
    );
  }
}
