<?php
/**Orchestra member, musician and project management application.
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
use CAFEVDB\Projects;
use CAFEVDB\Config;
use CAFEVDB\Events;

/* Remember address filter for later */
//echo $filter->getPersistent(array('fileAttach' => $this->fileAttach));

$eventAttachButton = '';
$attachedEvents = '';
if ($_['ProjectId'] >= 0) {
  $EventSelect = Util::cgiValue('EventSelect', array());
  $eventAttachButton = Projects::eventButton(
    $_['ProjectId'], $_['ProjectName'], L::t('Events'), $EventSelect);
  if (!empty($EventSelect)) {
    $attachedEvents = ''
                    .'<tr class="eventattachments"><td>'.L::t('Attached Events').'</td>'
                                                             .'<td colspan="2"><span id="eventattachments">';
    foreach ($EventSelect as $id) {
      $event = Events::fetchEvent($id);
      $brief =
      $event['summary'].', '.
      Events::briefEventDate($event);

      $attachedEvents .= '
<button type="button"
        title="'.L::t('Edit Event %s',array($brief)).'"
        class="eventattachments edit"
        id="eventattachment-'.$id.'"
        name="eventattachment[]"
        value="'.$id.'">
  <img alt="'.$id.'"
       src="'.\OCP\Util::imagePath('calendar', 'calendar.svg').'"
       class="svg events small"/>
</button>';
    }
    $attachedEvents .= '</span></td></tr>';
  }
}

// Compose one large string with all recipients, separated by comma
$toString = array();
foreach($_['TO'] as $recipient) {
  $name = trim($recipient['name']);
  $email = trim($recipient['email']);
  if ($name == '') {
    $toString[] = $email;
  } else {
    $toString[] = $name.' <'.$email.'>';
  }
}
$toString = htmlspecialchars(implode(', ', $toString));

?>

<fieldset id="cafevdb-emial-composition-fieldset" class="email-composition page">
  <!-- <legend id="cafevdb-email-form-legend"><?php echo L::t('Compose Em@il'); ?></legend> -->
  <table class="cafevdb-email-composition-form">
    <tr class="email-template">
      <td><?php echo L::t("Template"); ?></td>
      <td class="email-template-choose">
        <label notitle="<?php echo Config::toolTips('select-email-template'); ?>">
          <select size="<?php echo count($_['templateNames']); ?>"
                  class="email-template-selector"
                  title="<?php echo Config::toolTips('select-email-template'); ?>"
                  data-placeholder="<?php echo L::t("Select email template"); ?>"
                  name="emailTemplateSelector"
                  id="cafevdb-email-template-selector">
            <?php
            foreach ($_['templateNames'] as $template) {
              echo '
            <option value="'.$template.'">'.$template.'</option>';
            }
            ?>
          </select>
        </label>
      </td>
      <td class="email-template-save">
        <input size="20" placeholder="<?php echo L::t('New Template Name'); ?>"
        <?php echo ($_['templateName'] != '' ? 'value="'.$_['templateName'].'"' : ''); ?>
               title="<?php echo Config::toolTips('new-email-template'); ?>"
               name="newEmailTemplate"
               type="text"
               id="newEmailTemplate">
        <input title="<?php echo Config::toolTips('save-email-template'); ?>"
               type="submit"
               name="saveEmailTemplate"
               value="<?php echo L::t('Save as Template'); ?>"/>
      </td>
    </tr>
    <tr class="email-address">
      <td class="email-address caption"><?php echo L::t('Recipients'); ?></td>
      <td class="email-address display" colspan="2">
        <span title="<?php echo htmlspecialchars($toString); ?>" class="tipsy-s">
          <?php echo $toString; ?>
        </span>
      </td>
    </tr>
    <tr class="email-address">
      <td class="email-address caption"><?php echo L::t('Carbon Copy'); ?></td>
      <td class="email-address input" colspan="2">
        <input size="40"
               value="<?php echo htmlspecialchars($_['CC']); ?>"
               name="txtCC"
               type="text"
               id="txtCC" />
      </td>
    </tr>
    <tr class="email-address">
      <td class="email-address caption"><?php echo L::t('Blind CC'); ?></td>
      <td colspan="2" class="email-address input">
        <input size="40"
               value="<?php echo htmlspecialchars($_['BCC']); ?>"
               name="txtBCC"
               type="text"
               id="txtBCC"/>
      </td>
    </tr>
    <tr>
      <td class="subject caption"><?php echo L::t('Subject'); ?></td>
      <td colspan="2" class="subject input">
        <div class="subject container">
          <span class="subject tag"><?php echo htmlspecialchars($_['mailTag']); ?></span>
          <span class="subject input">
            <input value="<?php echo $_['subject']; ?>" size="40" name="txtSubject" type="text" id="txtSubject">
          </span>
        </div>
      </td>
    </tr>
    <tr>
      <td class="body"><?php echo L::t('Message-Body'); ?></td>
      <td colspan="2" class="messagetext"><textarea name="txtDescription" class="wysiwygeditor" cols="60" rows="20" id="txtDescription"><?php echo $_['message']; ?></textarea></td>
    </tr>
    <tr>
      <td><?php echo L::t('Sender-Name'); ?></td>
      <td colspan="2"><input value="<?php echo $_['sender']; ?>" size="40" value="CAFEV" name="txtFromName" type="text"></td>
    </tr>
    <tr>
      <td><?php echo L::t('Sender-Email'); ?></td>
      <td colspan="2"><?php echo L::t('Tied to'); ?> "<?php echo $_['catchAllEmail']; ?>"</td>
    </tr>
    <tr class="attachments">
      <td class="attachments"><?php echo L::t('Add Attachment'); ?></td>
      <td class="attachments" colspan="2">
        <?php echo $eventAttachButton; ?>
        <button type="button"
                class="attachment upload"
                title="<?php echo Config::toolTips('upload-attachment'); ?>"
                value="<?php echo L::t('Upload new File'); ?>">
          <img src="<?php echo \OCP\Util::imagePath('core', 'actions/upload.svg'); ?>" alt="<?php echo L::t('Upload new File'); ?>"/>
        </button>
        <button type="button"
                class="attachment owncloud"
                title="<?php echo Config::toolTips('owncloud-attachment'); ?>"
                value="<?php echo L::t('Select from Owncloud'); ?>">
          <img src="<?php echo \OCP\Util::imagePath('core', 'places/file.svg'); ?>" alt="<?php echo L::t('Select from Owncloud'); ?>"/>
        </button>
      </td>
    </tr>

    <?php
    echo $attachedEvents;
    foreach ($_['fileAttach'] as $attachment) {
      $tmpName = $attachment['tmp_name'];
      $name    = $attachment['name'];
      $size    = $attachment['size'];
      $size    = \OC_Helper::humanFileSize($size);
      echo '
    <tr>
      <td><button type="submit" name="deleteAttachment[]" value="'.$tmpName.'" >'.L::t('Remove').'</button></td>
      <td colspan="2"><span class="attachmentName">'.$name.' ('.$size.')</span></td>
    </tr>';
    }
    ?>
    <tr class="submit">
      <td class="send">
        <input title="<?php echo Config::toolTips('send-mass-email'); ?>"
               type="submit" name="sendEmail" value="<?php echo L::t('Send Em@il'); ?>"/>
      </td>
      <td></td>
      <td class="reset">
        <input title="<?php echo Config::tooltips('cancel-email-composition'); ?>"
               type="submit" name="cancel" value="<?php echo L::t('Cancel'); ?>" />
      </td>
    </tr>
  </table>
</fieldset>

