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

/**
 * DTO for the page navigation item of a template renderer, this is used to
 * generate navigation links in the left side-bar of the vue-app.
 */
class SidebarNavigationItem extends \OCA\CAFEVDB\Toolkit\DTO\AbstractDTO
{
  /** {@inheritdoc} */
  public function __construct(
    /** Template name, e.g. \OCA\CAFEVDB\PageRenderer\Projects::TEMPLATE. */
    public readonly string $template,
    /** Translated name */
    public readonly string $name,
    /** Key into OCA\CAFEVDB\Service\ToolTipsService in order to fetch a translated name. */
    public readonly string $nameKey,
    /** Translated tooltip */
    public readonly string $tooltip,
    /** Key into OCA\CAFEVDB\Service\ToolTipsService in order to fetch a translated tooltip. */
    public readonly string $tooltipKey,
    /** Template parameters when opening sub-page from sidebar. */
    public readonly array $templateParameters,
    /** Permissions, used to filter out itmes that a user is not allow to access. */
    public readonly int $permissions,
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
      template: $template,
      name: $name,
      nameKey: $nameKey,
      tooltip: $tooltip,
      tooltipKey: $tooltipKey,
      templateParameters: $templateParameters,
      permissions: $permissions,
    );
  }
}
