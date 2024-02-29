<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2023 Claus-Justus Heine
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

$css_pfx = $renderer->cssPrefix();
$projectName = $renderer->getProjectName();
$projectId = $renderer->getProjectId();

$nav = '';
if (!empty($projectId)) {
  $nav .= $pageNavigation->pageControlElement('projectlabel', $project, $projectId);
  $nav .= $pageNavigation->pageControlElement('project-participants', $project, $projectId);
  $nav .= $pageNavigation->pageControlElement('project-participant-fields', $project, $projectId);
  $nav .= $pageNavigation->pageControlElement('project-instrumentation-numbers', $project, $projectId);
  if ($roles->inTreasurerGroup()) { // @@TODO
    $nav .= $pageNavigation->pageControlElement('project-payments', $project, $projectId);
    $nav .= $pageNavigation->pageControlElement('sepa-bank-accounts', $project, $projectId);
    $nav .= $pageNavigation->pageControlElement('sepa-bulk-transactions', $project, $projectId);
    if ($projectId == $appConfig->getConfigValue('memberProjectId', false)) {
      $nav .= $pageNavigation->pageControlElement('instrument-insurance');
    }
  }
  $nav .= $pageNavigation->pageControlElement('projects');
  $nav .= $pageNavigation->pageControlElement('all');
  $nav .= $pageNavigation->pageControlElement('instruments', $project, $projectId);
  $nav .= $pageNavigation->pageControlElement('instrument-families');
} else {
  $nav .= $pageNavigation->pageControlElement('projects');
  $nav .= $pageNavigation->pageControlElement('all');
  $nav .= $pageNavigation->pageControlElement('instruments');
  $nav .= $pageNavigation->pageControlElement('instrument-families');
}

echo $this->inc('part.common.header', [
  'css-prefix' => $css_pfx,
  'navigationcontrols' => $nav,
  'header' => $renderer->headerText(),
]);

echo $this->inc('pme-table', []);

// Close some still opened divs
echo $this->inc('part.common.footer', [ 'css-prefix' => $css_pfx, ]);
