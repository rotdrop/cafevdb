<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

$rowClass = $appName.'-'.'row';

?>

<div id="emailformwrapper">
  <ul id="emailformtabs">
    <li id="emailformrecipients-tab">
      <a href="#emailformrecipients"><?php echo $l->t('Recipients'); ?></a>
    </li>
    <li id="emailformcomposer-tab">
      <a href="#emailformcomposer"><?php echo $l->t('Message Composition'); ?></a>
    </li>
    <li id="emailformdebug-tab">
      <a href="#emailformdebug"><?php echo $l->t('Status and Preview'); ?></a>
    </li>
  </ul>
  <form method="post"
        name="cafevdb-email-form"
        id="cafevdb-email-form"
        class="cafevdb-email-form">
    <fieldset id="cafevdb-email-form-data" class="form-data">
      <?php echo PageNavigation::persistentCGI($formData); ?>
    </fieldset>
    <div id="emailformrecipients" class="resize-target"><?php echo $this->inc('emailform/part.emailform.recipients', $_); ?></div>
    <div id="emailformcomposer" class="resize-target"><?php echo $this->inc('emailform/part.emailform.composer', $_); ?></div>
    <div id="emailformdebug" class="resize-target"><pre><?php print_r($_POST); ?></pre></div>
  </form>

  <!-- Upload support via blueimp. FIXME: is this still up-to-date? Probably got this from OC4 -->
  <div id="attachment_upload_wrapper" class="data_upload_wrapper">
    <form data-upload-id='1'
          id="attachment_upload_form"
          class="file-upload-form"
          action="<?php print_unescaped($urlGenerator->linkToRoute($appName.'.email_form.attachment', [ 'source' => 'upload' ])); ?>"
          method="post"
          enctype="multipart/form-data"
          target="attachment_upload_target_1">
      <!-- at least some php-flavours (even 5.6, on Ubuntu) seem to have a 32bit bug with MAX_FILE_SIZE -->
      <!-- <input type="hidden" name="MAX_FILE_SIZE" id="max_upload"
	     value="<?php p($uploadMaxFilesize) ?>"> -->
      <!-- Send the requesttoken, this is needed for older IE versions
      because they don't send the CSRF token via HTTP header in this case -->
      <input type="hidden" name="requesttoken" value="<?php p($requesttoken) ?>" id="requesttoken">
      <input type="hidden" class="max_human_file_size"
	     value="(max <?php p($uploadMaxHumanFilesize); ?>)">
      <input type="file" class="file_upload_start" id="attachment_upload_start" name="files[]" multiple="multiple">
    </form>
    <div class="uploadprogresswrapper">
      <div class="uploadprogressbar"></div>
      <input type="button" class="stop" style="display:none"
	     value="<?php p($l->t('Cancel upload'));?>"
	     />
    </div>
  </div>
  <div id="sendingprogresswrapper">
    <div class="messagecount"></div>
    <div class="progress <?php p($rowClass); ?> smtp">
      <span class="title"><?php echo $l->t('Message Delivery'); ?></span>
      <span class="progressbar"></span>
    </div>
    <div class="progress <?php p($rowClass); ?> imap">
      <span class="title"><?php echo $l->t('Copy to Sent'); ?></span>
      <span class="progressbar"></span>
    </div>
  </div>
</div>
