<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests\Unit\Controller\DTO;

use InvalidArgumentException;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\Constants as CoreConstants;

use OCA\CAFEVDB\Controller\EnumSpecialProjectsAction;
use OCA\CAFEVDB\Controller\DTO\SpecialProjectsResponse\ConfirmFeedback;
use OCA\CAFEVDB\Controller\DTO\SpecialProjectsResponse as TestedDTO;

/** Consistency test for SepaBankAccount DTO. */
#[Attributes\CoversClass(ConfirmFeedback::class)]
#[Attributes\CoversClass(TestedDTO::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Controller\DTO\ConfirmFeedback::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
class SpecialProjectsResponseDTOTest extends TestCase
{
  use TestResponseDTOTrait;

  private const DTO_CLASS = TestedDTO::class;

  private TestedDTO $dto;

  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function setup(): void
  {
    $this->dto = new TestedDTO(
      messages: ['a message string'],
      project: 'a string',
      projectId: 42, // an integer
      feedback: null,
      newName: null,
      suggestions: null,
    );
  }

  /** @return void */
  public function testFeedback(): void
  {
    foreach (EnumSpecialProjectsAction::cases() as $action) {
      $feedback = new ConfirmFeedback(
        action: $action,
        title: 'a title',
        message: 'a message',
      );
      $this->dto = new TestedDTO(
        messages: ['a message string'],
        project: 'a string',
        projectId: 42, // an integer
        feedback: $feedback,
        newName: null,
        suggestions: null,
      );
      $this->testFromArray();
    }
    $serialized = $this->dto->jsonSerialize();
    $serialized['feedback']['action'] = 'invalid';
    $this->expectException(InvalidArgumentException::class);
    static::DTO_CLASS::fromArray($serialized);
  }
}
