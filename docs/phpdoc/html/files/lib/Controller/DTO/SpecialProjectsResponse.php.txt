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
 * DTO special projects config (members, executive board).
 */
class SpecialProjectsResponse extends MessagesResponse
{
  /** {@inheritdoc} */
  public function __construct(
    string $message,
    public readonly string $project,
    public readonly int $projectId,
    public readonly ?bool $feedback,
    public readonly ?string $newName,
    /** @var array<ProjectOption> */
    public readonly ?array $suggestions,
  ) {
    parent::__construct([ $message ]);
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
    extract($data);
    $suggestions = array_map(fn(array $projectOption) => ProjectOption::fromArray($projectOption), $suggestions);
    return new self($messsage, $project, $projectId, $feedback ?? null, $newName ?? null, $suggestions ?? null);
  }
}
