<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use CAFEVDB\L;
use CAFEVDB\Util;
use CAFEVDB\Navigation;
use CAFEVDB\Email;

CAFEVDB\Error::exceptions(true);

?>

<div id="emailformwrapper">
  <ul id="emailformtabs">
    <li id="emailformrecipients-tab">
      <a href="#emailformrecipients"><?php echo L::t('Em@il Recipients'); ?></a>
    </li>
    <li id="emailformcomposer-tab">
      <a href="#emailformcomposer"><?php echo L::t('Em@il Message'); ?></a>
    </li>
    <li id="emailformdebug-tab">
      <a href="#emailformdebug"><?php echo L::t('Status Messages'); ?></a>
    </li>
  </ul>
  <form method="post"
        name="cafevdb-email-form"
        id="cafevdb-email-form"
        class="cafevdb-email-form">
    <fieldset id="cafevdb-email-form-data" class="form-data">
      <?php echo Navigation::persistentCGI($_['FormData']); ?>
    </fieldset>
    <div id="emailformrecipients"><?php echo $this->inc('part.emailform.recipients'); ?></div>
    <div id="emailformcomposer"><?php echo $this->inc('part.emailform.composer'); ?></div>
    <div id="emailformdebug"><pre><?php print_r($_POST); print_r($_); ?></pre></div>
  </form>


  <!-- Upload support via blueimp. FIXME: is this still up-to-date? Probably got this from OC4 -->
  <form data-upload-id='1'
        id="data-upload-form"
        class="file_upload_form"
        action="<?php print_unescaped(OCP\Util::linkTo('cafevdb', 'ajax/email/uploadattachment.php')); ?>"
        method="post"
        enctype="multipart/form-data"
        target="file_upload_target_1">
    <input type="hidden" name="MAX_FILE_SIZE" id="max_upload"
	   value="<?php p($_['uploadMaxFilesize']) ?>">
    <!-- Send the requesttoken, this is needed for older IE versions
    because they don't send the CSRF token via HTTP header in this case -->
    <input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>" id="requesttoken">
    <input type="hidden" class="max_human_file_size"
	   value="(max <?php p($_['uploadMaxHumanFilesize']); ?>)">
    <input type="file" id="file_upload_start" name="files[]" multiple>
  </form>
  <div id="uploadprogresswrapper">
    <div id="uploadprogressbar"></div>
    <input type="button" class="stop" style="display:none"
	   value="<?php p($l->t('Cancel upload'));?>"
	   />
  </div>
  <div id="sendingprogresswrapper">
    <div class="messagecount"></div>
    <div class="progress row smtp">
      <span class="title"><?php echo L::t('Message Delivery'); ?></span>
      <span class="progressbar"></span>
    </div>
    <div class="progress row imap">
      <span class="title"><?php echo L::t('Copy to Sent'); ?></span>
      <span class="progressbar"></span>
    </div>
  </div>
</div>

