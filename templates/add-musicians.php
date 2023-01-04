<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2021, 2023 Claus-Justus Heine
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
namespace OCA\CAFEVDB\Templates;

$css_pfx = $renderer->cssPrefix();
$css_class = $renderer->cssClass();

//$missing = Projects::missingInstrumentationTable($renderer->getProjectId());

$nav = '';
$nav .= $pageNavigation->pageControlElement('projectlabel', $renderer->getProjectName(), $renderer->getProjectId());
$nav .= $pageNavigation->pageControlElement('projects');
$nav .= $pageNavigation->pageControlElement('detailed', $renderer->getProjectName(), $renderer->getProjectId());
$nav .= $pageNavigation->pageControlElement('project-instrumentation-numbers', $renderer->getProjectName(), $renderer->getProjectId());
$nav .= $pageNavigation->pageControlElement('instruments', $renderer->getProjectName(), $renderer->getProjectId());
//$nav .= $pageNavigation->pageControlElement('detailed', $renderer->getProjectName(), $renderer->getProjectId());

echo $this->inc(
  'part.common.header',
  [
    'css-prefix' => $css_pfx,
    'css-class' => $css_class,
    'navigationcontrols' => $nav,
    'header' => $renderer->headerText(),
    //'navBarInfo' => $missing,
  ]);

// Issue the main part. The method will echo itself
echo $this->inc('pme-table', []);

// Close some still opened divs
echo $this->inc('part.common.footer', [ 'css-prefix' => $css_pfx ]);
