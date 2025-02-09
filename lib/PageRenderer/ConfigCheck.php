<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024, 2025 Claus-Justus Heine
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

use OCP\IL10N;

/** Dummy config-check renderer */
class ConfigCheck extends AbstractPageRenderer
{
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;

  /**
   * @var string
   *
   * The legacy template to load.
   */
  public const TEMPLATE = 'maintenance/configcheck';

  /**
   * @param $appName
   *
   * @param IL10N $l
   */
  public function __construct(
    protected $appName,
    protected IL10N $l,
  ) {
  }

  /** {@inheritdoc} */
  public function shortTitle()
  {
    return 'config-check';
  }

  /** {@inheritdoc} */
  public function headerText()
  {
    return self::templateResponse('fragments/header-texts/configcheck', [ 'cssPrefix' => $this->cssPrefix() ], self::RENDER_AS_BLANK)->render();
  }

  /** {@inheritdoc} */
  public function cssClass():string
  {
    return 'config-check';
  }
}
