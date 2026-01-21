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

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

use OCA\CAFEVDB\Controller\EnumEmailFormComposerOperation;
use OCA\CAFEVDB\Controller\EnumEmailFormComposerTopic;

/**
 * Response DTO of the email-form controller.
 */
class EmailFormComposerResponse extends \OCA\CAFEVDB\Toolkit\DTO\AbstractResponseDTO
{
  /** {@inheritdoc} */
  public function __construct(
    public readonly EnumEmailFormComposerOperation $operation,
    public readonly EnumEmailFormComposerTopic $topic,
    public readonly ?string $projectName,
    public readonly ?int $projectId,
    public readonly ?string $caption,
    /** @var array<string> */
    public readonly array $messages,
    public readonly EmailFormComposerRequestData $requestData,
    public readonly string $debug,
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
    if (!($requestData instanceof EmailFormComposerRequestData)) {
      $requestData = EmailFormComposerRequestData::fromArray($requestData);
    }
    return new self(
      operation: $operation,
      topic: $topic,
      projectName: $projectName,
      projectId: $projectId,
      caption: $caption,
      messages: $messages,
      requestData: $requestData,
      debug: $debug,
    );
  }
}
