<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/**@file
 * Load a PME table without outer controls, intended usage are
 * jQuery dialogs. It is assumed that $_['displayClass'] can be
 * constructed from $_['ClassArguments'] and provides a method
 * display() which actually echos the HTML code to show.
 */

namespace OCA\CAFEVDB;

$css   = $template;
if (!empty($class)) {
  $css .= ' '.$class;
}

// This is here because otherwise PHP leaks content to stdout (and
// thus to the client) on fatal errors.
try {
  ob_start(function() { return''; });
  $renderer->render();
  $pmeTable = ob_get_contents();
  ob_end_clean();
} catch (\Throwable $t) {
  ob_end_clean();
  throw new \Exception($l->t('Renderer failed: %s', $t->getMessage()), $t->getCode(), $t);
}

//echo '<div id="pme-table-container" class="pme-table-container '.$css.'" style="height:auto;">';
echo '<div id="pme-table-container" class="pme-table-container '.$css.'">';
//$renderer->render();
echo $pmeTable;
echo '</div>';

// add a hidden "short title" span
echo '<span id="pme-short-title" class="pme-short-title" style="display:none;">'.$renderer->shortTitle().'</span>';
