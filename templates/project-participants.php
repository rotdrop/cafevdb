<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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
$cssClass = 'instrumentation'; // @todo generalize
$project = $renderer->getProjectName();
$projectId = $renderer->getProjectId();

//$missing = Projects::missingInstrumentationTable($projectId);

$nav = '';
$nav .= $pageNaviagation->pageControlElement('projectlabel', $projectName, $projectId);
$nav .= $pageNaviagation->pageControlElement('detailed', $projectName, $projectId);
$nav .= $pageNaviagation->pageControlElement('project-extra', $projectName, $projectId);
$nav .= $pageNaviagation->pageControlElement('projectinstruments', $projectName, $projectId);
// @TODO CHECK! TO ID!
// if (Config::isTreasurer()) {
//   $nav .= $pageNaviagation->pageControlElement('project-payments', $projectName, $projectId);
//   $nav .= $pageNaviagation->pageControlElement('debit-mandates', $projectName, $projectId);
//   $nav .= $pageNaviagation->pageControlElement('debit-notes', $projectName, $projectId);
//   if ($projectName === Config::getValue('memberTable', false)) {
//     $nav .= $pageNaviagation->pageControlElement('insurances');
//   }
// }
$nav .= $pageNaviagation->pageControlElement('projects');
$nav .= $pageNaviagation->pageControlElement('all');
$nav .= $pageNaviagation->pageControlElement('instruments', $projectName, $projectId);

echo $this->inc(
  'part.common.header',
  [
    'css-prefix' => $cssPfx,
    'css-class' => $css_class,
    'navigationcontrols' => $nav,
    'header' => $table->headerText(),
    //'navBarInfo' => $missing
  ]);

$renderer->render();

// Close some still opened divs
echo $this->inc('part.common.footer', array('css-prefix' => $cssPfx));
