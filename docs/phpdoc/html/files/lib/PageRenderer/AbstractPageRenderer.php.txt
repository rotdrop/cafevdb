<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2020, 2022, 2025, 2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\PageRenderer;

use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\PageRenderer\DTO\SidebarNavigationItem;

/** Abstract page-renderer base class. */
abstract class AbstractPageRenderer extends Renderer implements IPageRenderer
{
  /** @param string $appName */
  public function __construct(protected $appName)
  {
  }

  /** @return string */
  protected function appName(): string
  {
    return $this->appName;
  }

  /*** {@inheritdoc} */
  public function navigation(bool $enable): void
  {
  }

  /*** {@inheritdoc} */
  public function navigationItems(): array
  {
    return [];
  }

  /*** {@inheritdoc} */
  public static function navigationItem(?int $projectId = null, ?string $projectName = null): SidebarNavigationItem
  {
    return SidebarNavigationItem::fromArray([
      'template' => static::TEMPLATE,
      'name' => 'templates:navigation:name:' . static::TEMPLATE,
      'tooltip' => 'templates:navigation:tooltips:' . static::TEMPLATE,
      'templateParameters' => [],
      'permissions' => static::requiredPermissions(),
    ]);
  }

  /*** {@inheritdoc} */
  public static function requiredPermissions(): int
  {
    return AuthorizationService\EnumAppPermissions::FRONTEND->value;
  }

  /*** {@inheritdoc} */
  public function execute(array $options = []): void
  {
  }
}
