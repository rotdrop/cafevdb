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
$nav .= $pageNavigation->pageControlElement('projectlabel', $projectName, $projectId);
$nav .= $pageNavigation->pageControlElement('detailed', $projectName, $projectId);
$nav .= $pageNavigation->pageControlElement('project-extra-fields', $projectName, $projectId);
$nav .= $pageNavigation->pageControlElement('project-instrumentation-numbers', $projectName, $projectId);
// @TODO CHECK! TO ID!
// if ($oles->inTreasurerGroup()) {
//   $nav .= $pageNavigation->pageControlElement('project-payments', $projectName, $projectId);
//   $nav .= $pageNavigation->pageControlElement('debit-mandates', $projectName, $projectId);
//   $nav .= $pageNavigation->pageControlElement('debit-notes', $projectName, $projectId);
//   if ($projectName === $appConifg->getConfigValue('memberTable', false)) {
//     $nav .= $pageNavigation->pageControlElement('insurances');
//   }
// }
$nav .= $pageNavigation->pageControlElement('projects');
$nav .= $pageNavigation->pageControlElement('all');
$nav .= $pageNavigation->pageControlElement('instruments', $projectName, $projectId);

echo $this->inc(
  'part.common.header',
  [
    'css-prefix' => $cssPfx,
    'css-class' => $css_class,
    'navigationcontrols' => $nav,
    'header' => $renderer->headerText(),
    //'navBarInfo' => $missing
  ]);

try {
  $renderer->render();
} catch (\Throwable $t) {
  $keys = [
    'projectId' => true,
    'projectName' => true,
  ];
  throw new \Exception(print_r(array_intersect_key($_, $keys), true), $t->getCode(), $t);
}

// Close some still opened divs
echo $this->inc('part.common.footer', [ 'css-prefix' => $cssPfx, ]);
