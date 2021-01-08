<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

echo $this->inc('part.common.header',
                [
                  'css-prefix' => $css_pfx,
                  'css-class' => $css_class,
                  'navigationcontrols' => $nav,
                  'header' => $renderer->headerText(),
                  //'navBarInfo' => $missing,
                ]);

// Issue the main part. The method will echo itself
$renderer->render();

// Close some still opened divs
echo $this->inc('part.common.footer', [ 'css-prefix' => $css_pfx ]);
