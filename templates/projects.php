<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2021, 2023 Claus-Justus Heine
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

/** @var \OCP\Template $this */

namespace OCA\CAFEVDB;

$cssPrefix = $renderer->cssPrefix();
$cssClass = $renderer->cssClass();

$nav = '';
//$nav .= $pageNavigation->pageControlElement('projectinstrumets');
$nav .= $pageNavigation->pageControlElement('all');
$nav .= $pageNavigation->pageControlElement('projects');
$nav .= $pageNavigation->pageControlElement('instruments');
$nav .= $pageNavigation->pageControlElement('project-participant-fields');
$nav .= $pageNavigation->pageControlElement('project-instrumentation-numbers');
$nav .= $pageNavigation->pageControlElement('blog');

echo $this->inc('part.common.header', [
  'css-prefix' => $cssPrefix,
  'css-class' => $cssClass,
  'navigationcontrols' => $nav,
  'header' => $renderer->headerText()
]);

echo $this->inc('pme-table', []);

// Close some still opened divs
echo $this->inc('part.common.footer', [ 'css-prefix' => $cssPrefix, 'css-class' => $cssClass ]);
