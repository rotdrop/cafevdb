<?php
/*
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB;

?>

<div id="<?php p($appPrefix('email-preview')); ?>" class="email-preview">
  <div class="preview-heading">
    <?php p($l->t('Email preview for project %s', $projectName)); ?>
  </div>
  <?php foreach ($messages as $message) { ?>
    <div class="email-header"><pre><?php p($message['headers']); ?></pre></div>
    <div class="email-body">
      <?php print_unquoted($message['body']); ?>
    </div>
    <hr class="email-preview-separator"/>
  <?php } ?>
</div>
