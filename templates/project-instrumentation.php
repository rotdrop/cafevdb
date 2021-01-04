<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB;

$cssPfx = $renderer->cssPrefix();
$project = $renderer->getProjectName();
$projectId = $renderer->getProjectId();

$nav = '';
if ($project != '') {
  $nav .= $pageNavigation->pageControlElement('projectlabel', $project, $projectId);
  $nav .= $pageNavigation->pageControlElement('detailed', $project, $projectId);
  $nav .= $pageNavigation->pageControlElement('projects');
  $nav .= $pageNavigation->pageControlElement('instruments', $project, $projectId);
} else {
  $nav .= $pageNavigation->pageControlElement('projects');
  $nav .= $pageNavigation->pageControlElement('instruments');
  $nav .= $pageNavigation->pageControlElement('all');
}

echo $this->inc(
  'part.common.header',
  [
    'css-prefix' => $cssPfx,
    'navigationcontrols' => $nav,
    'header' => $renderer->headerText(),
  ]);

$renderer->render();

// Close some still opened divs
echo $this->inc('part.common.footer', [ 'css-prefix' => $cssPfx, ]);
