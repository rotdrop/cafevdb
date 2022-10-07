<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  $nav .= $pageNavigation->pageControlElement('projectlabel', $projectName, $projectId);
  $nav .= $pageNavigation->pageControlElement('detailed', $projectName, $projectId);
  $nav .= $pageNavigation->pageControlElement('project-participant-fields', $projectName, $projectId);
  $nav .= $pageNavigation->pageControlElement('sepa-bank-accounts', $projectName, $projectId);
  $nav .= $pageNavigation->pageControlElement('project-payments', $projectName, $projectId);
  $nav .= $pageNavigation->pageControlElement('sepa-bulk-transactions', $projectName, $projectId);
  if ($projectId === $appConfig->getConfigValue('memberProjectId', false)) {
    $nav .= $pageNavigation->pageControlElement('insurances');
  }
  $nav .= $pageNavigation->pageControlElement('project-instrumentation-numbers', $projectName, $projectId);
  $nav .= $pageNavigation->pageControlElement('projects');
} else {
  $nav .= $pageNavigation->pageControlElement('projects');
  $nav .= $pageNavigation->pageControlElement('all');
  $nav .= $pageNavigation->pageControlElement('instruments');
}

echo $this->inc(
  'part.common.header',
  [
    'css-prefix' => $css_pfx,
    'navigationcontrols' => $nav,
    'header' => $renderer->headerText(),
  ]);

if ($roles->inTreasurerGroup()) {
  echo $this->inc('pme-table', []);
} else {
  echo '<div class="specialrole error">'.
       $l->t("Sorry, this view is only available to the %s.", $l->t('treasurer')).
       '</div>';
}

// Close some still opened divs
echo $this->inc('part.common.footer', [ 'css-prefix' => $css_pfx ]);
