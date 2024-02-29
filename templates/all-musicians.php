<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011 - 2021, 2023 Claus-Justus Heine
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

$nav = '';
$nav .= $pageNavigation->pageControlElement('all');
$nav .= $pageNavigation->pageControlElement('projects');
if ($roles->inTreasurerGroup()) {
  $nav .= $pageNavigation->pageControlElement('instrument-insurance');
  $nav .= $pageNavigation->pageControlElement('sepa-bank-accounts');
}
$nav .= $pageNavigation->pageControlElement('blog');
$nav .= $pageNavigation->pageControlElement('instruments');
$nav .= $pageNavigation->pageControlElement('instrument-families');

echo $this->inc(
  'part.common.header',
  [ 'css-prefix' => $css_pfx,
    'css-class' => $css_class,
    'navigationcontrols' => $nav,
    'header' => $renderer->headerText(),
  ]);

// Issue the main part
echo $this->inc('pme-table', []);

// Close some still opened divs
echo $this->inc('part.common.footer', [ 'css-prefix' => $css_pfx ]);
