<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Response;

use OCP\AppFramework\Http\TemplateResponse;

/**
 * Some parts of the legacy code, in particular the myPhpEdit relicts,
 * may need services like the session which are no longer available
 * (session closed) when the response object is rendered.
 *
 * As a hack around we render the template before returning the
 * response, cache the HTML output and finally just return the cached
 * string in $this->render().
 */
class PreRenderedTemplateResponse extends TemplateResponse
{
  /** @var string The renderered HTTP output. */
  protected $contentsCache = null;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    string $templateName,
    array $params = [],
    string $renderAs = self::RENDER_AS_USER
  ) {
    parent::__construct($appName, $templateName, $params, $renderAs);
  }
  // phpcs:enable

  /**
   * Call parent::render() and cache its output.
   *
   * @return string
   */
  public function preRender()
  {
    $this->contentsCache = parent::render();
    return $this->contentsCache;
  }

  /** {@inheritdoc} */
  public function render()
  {
    if (!empty($this->contentsCache)) {
      return $this->contentsCache;
    } else {
      return parent::render();
    }
  }
}
