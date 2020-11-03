<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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
1 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB;

$css_pfx = $renderer->cssPrefix();
$project = $renderer->getProjectName();
$projectId = $renderer->getProjectId();

$nav = '';
if ($projectId >= 0) {
  $nav .= $navigation->pageControlElement('projectlabel', $project, $projectId);
  $nav .= $navigation->pageControlElement('detailed', $project, $projectId);
  $nav .= $navigation->pageControlElement('project-extra', $project, $projectId);
  $nav .= $navigation->pageControlElement('projectinstruments', $project, $projectId);
  if (/*Config::isTreasurer()*/true) { // @@TODO
    $nav .= $navigation->pageControlElement('project-payments', $project, $projectId);
    $nav .= $navigation->pageControlElement('debit-mandates', $project, $projectId);
    $nav .= $navigation->pageControlElement('debit-notes', $project, $projectId);
    if ($project === Config::getValue('memberTable', false)) {
      $nav .= $navigation->pageControlElement('insurances');
    }
  }
  $nav .= $navigation->pageControlElement('projects');
  $nav .= $navigation->pageControlElement('all');
  $nav .= $navigation->pageControlElement('instruments', $project, $projectId);
} else {
  $nav .= $navigation->pageControlElement('projects');
  $nav .= $navigation->pageControlElement('all');
  $nav .= $navigation->pageControlElement('instruments');
}

echo $this->inc('part.common.header',
                [ 'css-prefix' => $css_pfx,
                  'navigationcontrols' => $nav,
                  'header' => $renderer->headerText() ]);

// Issue the main part. The method will echo itself
$renderer->render();

// Close some still opened divs
echo $this->inc('part.common.footer', array('css-prefix' => $css_pfx));

?>
