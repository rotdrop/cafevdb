<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\PageRenderer\PME;

use \OCP\IURLGenerator;

use OCA\CAFEVDB\Database\Legacy\PME\DefaultOptions;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ToolTipsService;

class Config extends DefaultOptions
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  public function __construct(
    ConfigService $configService,
    ToolTipsService $toolTipsService,
    IURLGenerator $urlGenerator
  ) {
    $this->configService = $configService;

    $options = [
      'language' => locale_get_primary_language($this->l10n()->getLanguageCode()),
      'url' => [
        'images' => $urlGenerator->imagePath($this->appName(), ''),
      ],
      'page_name' => $urlGenerator->linkToRoute($this->appName().'.page.index'),
      'tooltips' => $toolTipsService,
      'inc' => $this->getUserValue('pagerows', 20),
      'debug' => 0 != ($this->getConfigValue('debugmode', 0) & ConfigService::DEBUG_QUERY),
      'misc' => [
        'css' => [ 'minor' => 'pme-misc' ],
      ],
    ];
    parent::__construct($options);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
