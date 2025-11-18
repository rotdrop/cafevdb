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

namespace OCA\CAFEVDB\PageRenderer\DTO;

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

/**
 * DTO for project web-pages.
 *
 * @todo This should inherit from the Redaxo app.
 */
#[TSAttributes\TypeScript]
class ProjectWebPage extends \OCA\CAFEVDB\Toolkit\DTO\AbstractDTO
{
  /** {@inheritdoc} */
  public function __construct(
    public readonly int $articleId,
    public readonly string $articleName,
    public readonly int $categoryId,
    public readonly ?string $categoryName,
    public readonly string $templateName,
    public readonly int $priority,
  ) {
  }

  /**
   * Create an instance from a data array.
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
      articleId: $articleId,
      articleName: $articleName,
      categoryId: $categoryId,
      categoryName: $categoryName,
      templateName: $templateName,
      priority: $priority,
    );
  }
}
