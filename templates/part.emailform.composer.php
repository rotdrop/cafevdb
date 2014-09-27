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
use CAFEVDB\EmailComposer;
use CAFEVDB\Events;

$eventAttachmentOptions =
  EmailComposer::eventAttachmentOptions($_['ProjectId'], $_['eventAttachments']);
$fileAttachmentOptions = EmailComposer::fileAttachmentOptions($_['fileAttachments']);
$attachmentData = json_encode($_['fileAttachments'], 0); // JSON_FORCE_OBJECT);

?>

<fieldset id="cafevdb-email-composition-fieldset" class="email-composition page">
  <!-- <legend id="cafevdb-email-form-legend"><?php echo L::t('Compose Em@il'); ?></legend> -->
  <?php echo Navigation::persistentCGI('emailComposer', $_['ComposerFormData']); ?>
  <table class="cafevdb-email-composition-form">
    <tr class="email-template">
      <td class="caption email-template"><?php echo L::t("Templates"); ?></td>
      <td class="email-template-choose email-template">
        <label notitle="<?php echo Config::toolTips('select-email-template'); ?>">
          <select size="<?php echo count($_['templateNames']); ?>"
                  class="email-template-selector"
                  title="<?php echo Config::toolTips('select-email-template'); ?>"
                  data-placeholder="<?php echo L::t("Select email template"); ?>"
                  name="emailComposer[TemplateSelector]"
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
      <td class="email-template-storage email-template">
        <input size="20" placeholder="<?php echo L::t('New Template Name'); ?>"
        <?php echo ($_['templateName'] != '' ? 'value="'.$_['templateName'].'"' : ''); ?>
               title="<?php echo Config::toolTips('new-email-template'); ?>"
               name="emailComposer[TemplateName]"
               type="text"
               id="emailCurrentTemplate">
        <input title="<?php echo Config::toolTips('save-email-template'); ?>"
               type="submit"
               class="submit save-template"
               name="emailComposer[SaveTemplate]"
               value="<?php echo L::t('Save as Template'); ?>"/> 
        <input title="<?php echo Config::toolTips('delete-email-template'); ?>"
               type="submit"
               class="submit delete-template"
               name="emailComposer[DeleteTemplate]"
               value="<?php echo L::t('Delete Template'); ?>"/>
      </td>
    </tr>
    
    <tr class="email-address">
      <td class="email-address email-recipients caption"><?php echo L::t('Recipients'); ?></td>
      <td class="email-address email-recipients display" colspan="2">
        <span title="<?php echo Config::tooltips('email-recipients-listing').'</br>'.htmlspecialchars($_['TO']); ?>"
              data-placeholder="<?php echo L::t('No recipients selected.'); ?>"
              data-title-intro="<?php echo Config::tooltips('email-recipients-listing'); ?>"
              class="email-recipients tipsy-s tipsy-wide">
          <?php echo $_['TO'] == '' ? L::t('No recipients selected.') :  $_['TO']; ?>
        </span>
      </td>
    </tr>
    <tr class="email-address">
      <td class="email-address caption"><?php echo L::t('Carbon Copy'); ?></td>
      <td class="email-address input" colspan="2">
        <input size="40"
               title="<?php echo Config::toolTips('email-recipients-freeform-CC'); ?>"
               class="tipsy-s"
               value="<?php echo htmlspecialchars($_['CC']); ?>"
               name="emailComposer[CC]"
               type="text"
               id="carbon-copy" />
        <input title="<?php echo Config::toolTips('address-book-emails'); ?>"
               type="submit"
               class="submit address-book-emails CC"
               data-for="#carbon-copy"
               name="emailComposer[AddressBookCC]"
               value="<?php echo L::t('Address Book'); ?>"/>        
      </td>
    </tr>
    <tr class="email-address">
      <td class="email-address caption"><?php echo L::t('Blind CC'); ?></td>
      <td colspan="2" class="email-address input">
        <input size="40"
               title="<?php echo Config::toolTips('email-recipients-freeform-BCC'); ?>"
               class="tipsy-s"
               value="<?php echo htmlspecialchars($_['BCC']); ?>"
               name="emailComposer[BCC]"
               type="text"
               id="blind-carbon-copy"/>
        <input title="<?php echo Config::toolTips('address-book-emails'); ?>"
               type="submit"
               class="submit address-book-emails BCC"
               data-for="#blind-carbon-copy"
               name="emailComposer[AddressBookBCC]"
               value="<?php echo L::t('Address Book'); ?>"/>
      </td>
    </tr>
    <tr>
      <td class="subject caption"><?php echo L::t('Subject'); ?></td>
      <td colspan="2" class="subject input">
        <div class="subject container">
          <span class="subject tag"><?php echo htmlspecialchars($_['mailTag']); ?></span>
          <span class="subject input">
            <input value="<?php echo $_['subject']; ?>"
                   size="40" name="emailComposer[Subject]"
                   type="text"
                   class="email-subject"
                   spellcheck="true"
                   id="email-composer-subject">
          </span>
        </div>
      </td>
    </tr>
    <tr>
      <td class="body caption"><?php echo L::t('Message-Body'); ?></td>
      <td colspan="2" class="messagetext"><textarea name="emailComposer[MessageText]" class="wysiwygeditor" cols="60" rows="20" id="message-text"><?php echo $_['message']; ?></textarea></td>
    </tr>
    <tr>
      <td class="caption"><?php echo L::t('Sender-Name'); ?></td>
      <td colspan="2">
        <input value="<?php echo $_['sender']; ?>"
               class="sender-name"
               size="40" value="CAFEV"
               name="emailComposer[FromName]"
               type="text"></td>
    </tr>
    <tr>
      <td class="caption"><?php echo L::t('Sender-Email'); ?></td>
      <td colspan="2"><?php echo L::t('Tied to'); ?> "<?php echo $_['catchAllEmail']; ?>"</td>
    </tr>
    <tr class="attachments">
      <td class="attachments caption"><?php echo L::t('Add Attachment'); ?></td>
      <td class="attachments" colspan="2">
        <button type="button"
                class="attachment upload"
                title="<?php echo Config::toolTips('upload-attachment'); ?>"
                value="<?php echo L::t('Upload new File'); ?>">
          <img class="svg"
               src="<?php echo \OCP\Util::imagePath('core', 'actions/upload.svg'); ?>"
               alt="<?php echo L::t('Upload new File'); ?>"/>
        </button>
        <button type="button"
                class="attachment owncloud"
                title="<?php echo Config::toolTips('owncloud-attachment'); ?>"
                value="<?php echo L::t('Select from Owncloud'); ?>">
          <img class="svg small"
               src="<?php echo \OCP\Util::imagePath('cafevdb', 'cloud.svg'); ?>"
               alt="<?php echo L::t('Select from Owncloud'); ?>"/>
        </button>
        <button type="button"
                <?php echo ($_['ProjectId'] < 0 ? 'style="display:none;"' : ''); ?>
                class="attachment events"
                title="<?php echo Config::tooltips('events-attachment'); ?>"
                value="<?php echo L::t('Project Events'); ?>">
          <img class="svg events"
               src="<?php echo \OCP\Util::imagePath('cafevdb', 'calendar-dark.svg'); ?>"
               alt="<?php echo L::t('Select Events'); ?>"
        </button>
      </td>
    </tr>
    <tr class="event-attachments"
      <?php echo count($_['eventAttachments']) == 0 ? 'style="display:none;"' : ''; ?>">
      <td class="event-attachments caption">
        <?php echo L::t('Attached Events'); ?>
      </td>
      <td class="event-attachments events" colspan="2">
        <select multiple="multiple"
                title="<?php echo Config::toolTips('event-attachments-select'); ?>"
                name="emailComposer[AttachedEvents][]"
                class="event-attachments select"
                id="event-attachments-selector">
          <?php echo Navigation::selectOptions($eventAttachmentOptions); ?>
        </select>
        <input title="<?php echo Config::toolTips('delete-all-event-attachments'); ?>"
               type="submit"
               class="submit delete-all-event-attachments"
               name="emailComposer[DeleteAllAttachments]"
               value="<?php echo L::t('Delete Event Attachments'); ?>"/>
      </td>
    </tr>
    <tr class="file-attachments"
      <?php echo count($fileAttachmentOptions) == 0 ? 'style="display:none;"' : ''; ?>">
      <td class="file-attachments caption">
        <?php echo L::t('Attached Files'); ?>
      </td>
      <td class="file-attachments" colspan="2">
        <select multiple="multiple"
                title="<?php echo Config::toolTips('file-attachments-select'); ?>"
                name="emailComposer[AttachedFiles][]"
                class="file-attachments select"
                id="file-attachments-selector">
          <?php echo Navigation::selectOptions($fileAttachmentOptions); ?>
        </select>
        <input title="<?php echo Config::toolTips('delete-all-file-attachments'); ?>"
               type="submit"
               class="submit delete-all-file-attachments"
               name="emailComposer[DeleteAllAttachments]"
               value="<?php echo L::t('Delete All Attachments'); ?>"/>
      </td>
    </tr>
    <tr class="spacer rule below"><td colspan="3"></td></tr>
    <tr class="submit">
      <td class="send">
        <input title="<?php echo Config::toolTips('send-mass-email'); ?>"
               class="email-composer submit send"
               type="submit" name="emailComposer[Send]"
               value="<?php echo L::t('Send Em@il'); ?>"/>
      </td>
      <td></td>
      <td class="cancel">
        <input title="<?php echo Config::tooltips('cancel-email-composition'); ?>"
               class="email-composer submit cancel"
               type="submit" name="emailComposer[Cancel]"
               value="<?php echo L::t('Cancel'); ?>" />
      </td>
    </tr>
  </table>
  <!-- various data fields ... -->
  <fieldset id="cafevdb-email-form-attachments" class="attachments">
    <input type="hidden"
           name="emailComposer[FileAttach]"
           value="<?php echo htmlspecialchars($attachmentData); ?>"
           id="file-attach"
           class="file-attach">
  </fieldset>
</fieldset>

