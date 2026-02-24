<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

use OCA\CAFEVDB\Common\DecimalRationalMonetary as MonetaryNumberType;
use OCA\CAFEVDB\Controller\DTO\ParticipantFieldPropertyGetDefaultValue as DefaultValue;
use OCA\CAFEVDB\Controller\EnumParticipantFieldPropertyGet as EnumPropertyGet;
use OCA\CAFEVDB\Toolkit\Doctrine\ORM;

/**
 * Response by the ProjectParticipantFieldsController to a "property/get"
 * request.
 */
class ParticipantFieldPropertyGetResponse extends MessagesResponse
{
  /** {@inheritdoc} */
  public function __construct(
    array $messages,
    public readonly int $fieldId,
    public readonly EnumPropertyGet $property,
    public readonly null|DefaultValue|MonetaryNumberType $value,
  ) {
    parent::__construct($messages);
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

    if (is_array($value)) {
      $value = DefaultValue::fromArray($value);
    }

    return new self(
      messages: $messages,
      fieldId: $fieldId,
      property: $property,
      value: $value,
    );
  }
}
