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
  $nav .= $pageNavigation->pageControlElement('projectlabel', $project, $projectId);
  $nav .= $pageNavigation->pageControlElement('detailed', $project, $projectId);
  $nav .= $pageNavigation->pageControlElement('project-extra-fields', $project, $projectId);
  $nav .= $pageNavigation->pageControlElement('project-instrumentation', $project, $projectId);
  if ($roles->inTreasurerGroup()) {
    $nav .= $pageNavigation->pageControlElement('project-payments', $project, $projectId);
    $nav .= $pageNavigation->pageControlElement('debit-mandates', $project, $projectId);
    $nav .= $pageNavigation->pageControlElement('debit-notes', $project, $projectId);
    if ($project === $appConifg->getConfigValue('memberTable', false)) {
      $nav .= $pageNavigation->pageControlElement('insurances');
    }
  }
  $nav .= $pageNavigation->pageControlElement('projects');
  $nav .= $pageNavigation->pageControlElement('all');
  $nav .= $pageNavigation->pageControlElement('blog');
  $nav .= $pageNavigation->pageControlElement('instruments', $project, $projectId);
  $nav .= $pageNavigation->pageControlElement('instrument-families', $project, $projectId);
} else {
  $nav .= $pageNavigation->pageControlElement('projects');
  $nav .= $pageNavigation->pageControlElement('all');
  $nav .= $pageNavigation->pageControlElement('blog');
  $nav .= $pageNavigation->pageControlElement('instruments');
  $nav .= $pageNavigation->pageControlElement('instrument-families');
}

echo $this->inc('part.common.header',
                [ 'css-prefix' => $css_pfx,
                  'navigationcontrols' => $nav,
                  'header' => $renderer->headerText() ]);

// Issue the main part. The method will echo itself
$renderer->render();

// Close some still opened divs
echo $this->inc('part.common.footer', [ 'css-prefix' => $css_pfx ]);

?>
