<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2023 Claus-Justus Heine
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

$cssPfx = $renderer->cssPrefix();
$cssClass = 'instrumentation'; // @todo generalize
$project = $renderer->getProjectName();
$projectId = $renderer->getProjectId();

//$missing = Projects::missingInstrumentationTable($projectId);

$nav = '';
$nav .= $pageNavigation->pageControlElement('projectlabel', $projectName, $projectId);
$nav .= $pageNavigation->pageControlElement('detailed', $projectName, $projectId);
$nav .= $pageNavigation->pageControlElement('project-participant-fields', $projectName, $projectId);
$nav .= $pageNavigation->pageControlElement('project-instrumentation-numbers', $projectName, $projectId);
if ($roles->inTreasurerGroup()) {
  $nav .= $pageNavigation->pageControlElement('project-payments', $projectName, $projectId);
  $nav .= $pageNavigation->pageControlElement('sepa-bank-accounts', $projectName, $projectId);
  $nav .= $pageNavigation->pageControlElement('sepa-bulk-transactions', $projectName, $projectId);
  if ($projectId == $appConfig->getConfigValue('memberProjectId', false)) {
    $nav .= $pageNavigation->pageControlElement('insurances');
  }
}
$nav .= $pageNavigation->pageControlElement('projects');
$nav .= $pageNavigation->pageControlElement('all');
$nav .= $pageNavigation->pageControlElement('instruments', $projectName, $projectId);

echo $this->inc(
  'part.common.header',
  [
    'css-prefix' => $cssPfx,
    'css-class' => $cssClass,
    'navigationcontrols' => $nav,
    'header' => $renderer->headerText(),
    //'navBarInfo' => $missing
  ]);

try {
  // Issue the main part
  echo $this->inc('pme-table', []);
} catch (\Throwable $t) {
  $keys = [
    'projectId' => true,
    'projectName' => true,
  ];
  throw new \Exception(print_r(array_intersect_key($_, $keys), true), $t->getCode(), $t);
}

// Close some still opened divs
echo $this->inc('part.common.footer', [ 'css-prefix' => $cssPfx, ]);
