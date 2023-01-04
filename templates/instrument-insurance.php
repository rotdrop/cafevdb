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
$nav .= $pageNavigation->pageControlElement('projectlabel', $projectName, $projectId);
$nav .= $pageNavigation->pageControlElement('detailed', $projectName, $projectId);
$nav .= $pageNavigation->pageControlElement('insurances');
$nav .= $pageNavigation->pageControlElement('insurance-rates');
$nav .= $pageNavigation->pageControlElement('insurance-brokers');
$nav .= $pageNavigation->pageControlElement('project-payments', $projectName, $projectId);
$nav .= $pageNavigation->pageControlElement('sepa-bank-accounts', $projectName, $projectId);
$nav .= $pageNavigation->pageControlElement('sepa-bulk-transactions', $projectName, $projectId);
$nav .= $pageNavigation->pageControlElement('projects');
$nav .= $pageNavigation->pageControlElement('all');

echo $this->inc(
  'part.common.header',
  [
    'css-prefix' => $css_pfx,
    'navigationcontrols' => $nav,
    'header' => $renderer->headerText(),
  ]);

// Issue the main part. The method will echo itself
if ($roles->inTreasurerGroup()) {
  echo $this->inc('pme-table', []);
} else {
  echo '<div class="specialrole error">'.
    $l->t(
      "Sorry, this view is only available to the %s.",
      [$l->t('treasurer')]).
    '</div>';
}

// Close some still opened divs
echo $this->inc('part.common.footer', [ 'css-prefix' => $css_pfx ]);
