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

namespace OCA\CAFEVDB\Controller\DTO\EmailFormComposerRequestDataTypes;

/**
 * Response DTO of the email-form controller.
 */
class ElementData extends \OCA\CAFEVDB\Toolkit\DTO\AbstractDTO
{
  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.ShortVariable)
   */
  public function __construct(
    /** @var array<string> */
    public readonly ?array $to,
    public readonly ?string $subjectTag,
    public readonly ?ElementDataFileAttachments $fileAttachments,
    public readonly ?ElementDataEventAttachments $eventAttachments,
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
   * @SuppressWarnings(PHPMD.ShortVariable)
   */
  public static function fromArray(array $data): self
  {
    static::initKeys();
    extract(array_intersect_key($data, array_flip(static::$keys[__CLASS__])));
    if (($fileAttachments ?? null) && !($fileAttachments instanceof ElementDataFileAttachments)) {
      $fileAttachments = ElementDataFileAttachments::fromArray($fileAttachments);
    }
    if (($eventAttachments ?? null) && !($eventAttachments instanceof ElementDataEventAttachments)) {
      $eventAttachments = ElementDataEventAttachments::fromArray($eventAttachments);
    }
    return new self(
      to: $to ?? null,
      subjectTag: $subjectTag ?? null,
      fileAttachments: $fileAttachments ?? null,
      eventAttachments : $eventAttachments ?? null,
    );
  }
}
