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

/**
 * Expected parameters:
 *
 * @param \OCA\CAFEVDB\PageRenderer\IPageRenderer $renderer
 *
 * @param string $template The plain template name $template.php
 * resides in this directory.
 */

/** @var \OCA\CAFEVDB\PageRenderer\IPageRenderer $renderer */

$css = $template;
$css .= ' ' . $renderer->cssClass();

if ($outputBufferWorkAround??false) {
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
} else {
  $pmeTable = null;
}

?>

<div id="pme-table-container" class="pme-table-container <?php p($css); ?>">
  <?php if (empty($pmeTable)) { $renderer->render(); } else { echo $pmeTable; } ?>
</div>

<?php
$operation = $renderer->operation();
if (!empty($operation)) {
  $operation = explode('?', $operation)[0];
  if ($operation === 'false' || $operation === $l->t('false')) {
    $operation = '';
  } else {
    $operation = $l->t($operation) . ': ';
  }
}
?>

<span id="pme-short-title" class="pme-short-title" style="display:none;">
  <?php echo $operation . $renderer->shortTitle(); ?>
</span>
