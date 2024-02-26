<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB;

use OCA\CAFEVDB\Service\ConfigService;

$css_pfx = $renderer->cssPrefix();
$projectName = $renderer->getProjectName();
$projectId = $renderer->getProjectId();

if (empty($projectId)) {
  $projectId = (int)$appConfig->getConfigValue(ConfigService::EXECUTIVE_BOARD_PROJECT_ID_KEY, 0);
  $projectName = $appConfig->getConfigValue(ConfigService::EXECUTIVE_BOARD_PROJECT_KEY, '');
}

$nav = '';
$nav .= $pageNavigation->pageControlElement('projectlabel', $projectName, $projectId);
$nav .= $pageNavigation->pageControlElement('project-participants', $projectName, $projectId);
$nav .= $pageNavigation->pageControlElement('project-participant-fields', $projectName, $projectId);
$nav .= $pageNavigation->pageControlElement('projects');
$nav .= $pageNavigation->pageControlElement('all');

echo $this->inc('part.common.header', [
  'css-prefix' => $css_pfx,
  'navigationcontrols' => $nav,
  'header' => $renderer->headerText(),
]);

echo $this->inc('pme-table', []);

// Close some still opened divs
echo $this->inc('part.common.footer', [ 'css-prefix' => $css_pfx, ]);
