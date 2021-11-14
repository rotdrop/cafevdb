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

use OCA\CAFEVDB\Controller\DownloadsController;

?>

<div id="<?php p($appName.'-'.'email-preview'); ?>" class="email-preview">
  <div class="preview-heading">
    <?php p($l->t('Email preview for project %s', $projectName)); ?>
  </div>
  <?php foreach ($messages as $message) { ?>
    <div class="email-header"><pre><?php p($message['headers']); ?></pre></div>
    <div class="email-body reset-css">
      <?php echo $message['body']; ?>
    </div>
    <div class="email-attachments reset-css <?php empty($message['attachments']) && p('empty'); ?>">
      <h4 class="centered-in-rule"><?php p('Attachments'); ?></h4>
      <ul>
        <?php foreach ($message['attachments'] as $attachment) { ?>
          <li>
            <span class="attachment-item">
              <span class="filename">
                <a href="<?php echo $urlGenerator->linkToRoute($appName . '.downloads.get', [ 'section' => DownloadsController::SECTION_FILECACHE, 'object' => $attachment['data'], ])  . '?' . 'requesttoken' . '=' . urlencode($requesttoken); ?>"
                   class="download-link ajax-download"
                   data-section="<?php p(DownloadsController::SECTION_FILECACHE); ?>"
                   data-object="<?php p($attachment['data']); ?>"
                >
                  <?php p($attachment['name']); ?>
                </a>
              </span>
              <span class="separator">|</span>
              <span class="size"><?php p(\OC_Helper::humanFileSize($attachment['size'])); ?></span>
              <span class="separator">|</span>
              <span class="mime-type"><?php p($attachment['mimeType']); ?></span>
            </span>
          </li>
        <?php } ?>
      </ul>
    </div>
    <hr class="email-preview-separator"/>
  <?php } ?>
</div>
