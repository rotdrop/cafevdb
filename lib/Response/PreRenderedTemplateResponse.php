<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
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

  public function __construct($appName, $templateName, array $params=[],
                              $renderAs = self::RENDER_AS_USER)
  {
    parent::__construct($appName, $templateName, $params, $renderAs);
  }

  /**
   * Call parent::render() and cache its output.
   */
  public function preRender()
  {
    \OCP\Util::writeLog('cafevdb', 'PRE-RENDER', \OCP\Util::INFO);
    $this->contentsCache = parent::render();
    return $this->contentsCache;
  }

  public function render()
  {
    if (!empty($this->contentsCache)) {
      \OCP\Util::writeLog('cafevdb', 'PRE-RENDERED CACHED', \OCP\Util::INFO);
      return $this->contentsCache;
    } else {
      \OCP\Util::writeLog('cafevdb', 'RENDER NEW', \OCP\Util::INFO);
      return parent::render();
    }
  }

}
