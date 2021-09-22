<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/** @var \OCP\Template $this */

namespace OCA\CAFEVDB;

$cssPrefix = $renderer->cssPrefix();
$cssClass = $renderer->cssClass();

$nav = '';
//$nav .= $pageNavigation->pageControlElement('projectinstrumets');
$nav .= $pageNavigation->pageControlElement('all');
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
