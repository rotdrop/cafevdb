<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license GNU AGPL version 3 or any later version
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
use OCA\CAFEVDB\Wrapped\Carbon\CarbonImmutable as DateTime;
use OCA\CAFEVDB\Common\Util;

$containerClass = $appName.'-'.'container';

$selectedFileAttachments = 0;
foreach ($fileAttachmentOptions as $option) {
  $selectedFileAttachments += (int)($option['flags'] &  PageNavigation::SELECTED);
}
$selectedEventAttachments = 0;
foreach ($eventAttachmentOptions as $option) {
  $selectedEventAttachments += (int)($option['flags'] &  PageNavigation::SELECTED);
}

?>

<fieldset id="cafevdb-email-composition-fieldset" class="email-composition page">
  <!-- <legend id="cafevdb-email-form-legend"><?php echo $l->t('Compose Em@il'); ?></legend> -->
  <?php echo PageNavigation::persistentCGI('emailComposer', $composerFormData); ?>
  <table class="cafevdb-email-composition-form">
    <tr class="stored-messages">
     <!-- <td class="caption stored-messages"><?php echo $l->t("Drafts"); ?>, <?php echo $l->t('Templates')?></td> -->
      <td colspan="2" class="stored-messages-choose stored-messages">
        <label notitle="<?php echo $toolTips['emailform:storage:messages:select']; ?>">
          <select size="<?php echo
                              count($storedEmails['drafts']) +
                              count($storedEmails['templates']); ?>"
                  class="stored-messages-selector"
                  title="<?php echo $toolTips['emailform:storage:messages:select']; ?>"
                  data-placeholder="<?php echo $l->t("Select draft or template"); ?>"
                  name="emailComposer[storedMessagesSelector]"
                  id="cafevdb-stored-messages-selector">
            <?php echo $this->inc('emailform/part.stored-email-options', []); ?>
          </select>
        </label>
      </td>
      <td class="stored-messages-storage stored-messages">
        <input size="20"
               placeholder="<?php echo $l->t('New Template Name'); ?>"
               value="<?php p($emailTemplateName); ?>"
               title="<?php echo $toolTips['emailform:storage:messages:new-template']; ?>"
               name="emailComposer[emailTemplateName]"
               type="text"
               class="tooltip-bottom"
               id="emailCurrentTemplate"
               disabled>
        <span class="inner vmiddle <?php p($containerClass); ?> checkbox-button save-as-template">
          <input type="checkbox"
                 id="check-save-as-template"
                 class="save-as-template tooltip-wide tooltip-bottom"
                 name="emailComposer[saveAsTemplate]"/>
          <label for="check-save-as-template"
                 class="tip save-as-template"
                 title="<?php echo $toolTips['emailform:storage:messages:save-as-template']; ?>">
            <span class="save-as-template button"></span>
          </label>
        </span>
        <input title="<?php echo $toolTips['emailform:storage:messages:save-message']; ?>"
               type="submit"
               class="submit save-message tooltip-wide tooltip-bottom"
               name="emailComposer[saveMessage]"
               value="<?php echo $l->t('Save Message'); ?>"/>
        <span class="inner vmiddle <?php p($containerClass); ?> checkbox-button draft-auto-save">
          <input type="checkbox"
                 id="check-draft-auto-save"
                 class="draft-auto-save tooltip-auto"
                 data-auto-save-interval="<?php p((int)$emailDraftAutoSave); ?>"
                 <?php !empty($emailDraftAutoSave) && p('checked'); ?>
                 name="emailComposer[draftAutoSave]"/>
          <label for="check-draft-auto-save"
                 class="draft-auto-save tooltip-auto"
                 title="<?php p($toolTips['emailform:storage:messages:draft-auto-save']); ?>">
            <span class="draft-auto-save button"></span>
          </label>
        </span>
        <input title="<?php echo $toolTips['emailform:storage:messages:delete-saved-message']; ?>"
               type="submit"
               class="submit delete-message tooltip-bottom"
               name="emailComposer[deleteMessage]"
               value="<?php echo $l->t('Delete Message'); ?>"/>
      </td>
    </tr>
    <tr class="email-address">
      <td class="email-address email-recipients caption"><?php echo $l->t('Recipients'); ?></td>
      <td class="email-address email-recipients display" colspan="2">
        <span class="flex-container">
          <span class="email-address-holder email-recipients inner vmiddle tooltip-bottom tooltip-mostwide"
                title="<?php echo $toolTips['emailform:composer:recipients-listing'].'</br>'.htmlspecialchars($_['TO']); ?>"
                data-placeholder="<?php echo $l->t('No recipients selected.'); ?>"
                data-title-intro="<?php echo $toolTips['emailform:composer:recipients:listing']; ?>"
          >
            <?php echo $_['TO'] == '' ? $l->t('No recipients selected.') :  $_['TO']; ?>
          </span>
          <span class="inner vmiddle <?php p($containerClass); ?> checkbox-button disclose-recipients tooltip-auto"
                title="<?php echo Util::htmlEscape($toolTips['emailform:composer:recipients:disclose-recipients']); ?>"
          >
            <input type="checkbox"
                   <?php !empty($discloseRecipients) && p('checked'); ?>
                   id="check-disclose-recipients"
                   class="disclose-recipients tooltip-top"
                   name="emailComposer[discloseRecipients]"
                   type="disclose-recipients"
            />
            <label for="check-disclose-recipients"
                   class="disclose-recipients">
              <span class="disclose-recipients button">
                <span class="undisclosed">CC</span>
                <span class="disclosed">CC</span>
              </span>
            </label>
          </span>
        </span>
      </td>
    </tr>
    <tr class="email-address">
      <td class="email-address caption"><?php echo $l->t('Carbon Copy'); ?></td>
      <td class="email-address input" colspan="2">
        <input size="40"
               title="<?php echo Util::htmlEscape($toolTips['emailform:composer:recipients:freeform-CC']); ?>"
               class="email-address-holder tooltip-top"
               value="<?php echo htmlspecialchars($_['CC']); ?>"
               name="emailComposer[CC]"
               type="text"
               id="carbon-copy" />
        <input title="<?php echo Util::htmlEscape($toolTips['emailform:composer:recipients:address-book']); ?>"
               type="submit"
               class="submit address-book-emails CC tooltip-bottom"
               data-for="#carbon-copy"
               name="emailComposer[addressBookCC]"
               value="<?php echo $l->t('Address Book'); ?>"
        />
      </td>
    </tr>
    <tr class="email-address">
      <td class="email-address caption"><?php echo $l->t('Blind CC'); ?></td>
      <td colspan="2" class="email-address input">
        <input size="40"
               title="<?php echo $toolTips['emailform:composer:recipients:freeform-BCC']; ?>"
               class="email-address-holder tooltip-top"
               value="<?php echo htmlspecialchars($_['BCC']); ?>"
               name="emailComposer[BCC]"
               type="text"
               id="blind-carbon-copy"/>
        <input title="<?php echo $toolTips['emailform:composer:recipients:address-book']; ?>"
               type="submit"
               class="submit address-book-emails BCC tooltip-bottom"
               data-for="#blind-carbon-copy"
               name="emailComposer[addressBookBCC]"
               value="<?php echo $l->t('Address Book'); ?>"
        />
      </td>
    </tr>
    <tr>
      <td class="subject caption"><?php echo $l->t('Subject'); ?></td>
      <td colspan="2" class="subject input">
        <div class="subject <?php p($containerClass); ?>">
          <span class="subject tag"><?php echo htmlspecialchars($mailTag); ?></span>
          <span class="subject input">
            <input value="<?php echo htmlspecialchars($subject); ?>"
                   size="40" name="emailComposer[subject]"
                   type="text"
                   class="email-subject"
                   spellcheck="true"
                   id="email-composer-subject">
          </span>
        </div>
      </td>
    </tr>
    <tr>
      <td class="body caption"><?php echo $l->t('Message-Body'); ?></td>
      <td colspan="2" class="messagetext"><textarea name="emailComposer[messageText]" class="wysiwyg-editor" cols="60" rows="20" id="message-text"><?php echo htmlspecialchars($message); ?></textarea></td>
    </tr>
    <tr>
      <td class="caption"><?php echo $l->t('Sender-Name'); ?></td>
      <td colspan="2">
        <input value="<?php echo $sender; ?>"
               class="sender-name"
               size="40" value="CAFEV"
               name="emailComposer[fromName]"
               type="text"></td>
    </tr>
    <tr>
      <td class="caption"><?php echo $l->t('Sender-Email'); ?></td>
      <td colspan="2"><?php echo $l->t('Tied to "%s"', $catchAllEmail); ?></td>
    </tr>
    <tr class="all-attachments">
      <td class="attachments caption"><?php echo $l->t('Add Attachment'); ?></td>
      <td class="attachments" colspan="2">
        <div class="flex-container">
        <button type="button"
                class="attachment upload"
                title="<?php echo $toolTips['emailform:composer:attachments:upload']; ?>"
                value="<?php echo $l->t('Upload new File'); ?>">
          <img class="svg"
               src="<?php echo $urlGenerator->imagePath('core', 'actions/upload.svg'); ?>"
               alt="<?php echo $l->t('Upload new File'); ?>"/>
        </button>
        <button type="button"
                class="attachment cloud"
                title="<?php echo $toolTips['emailform:composer:attachments:cloud']; ?>"
                value="<?php echo $l->t('Select from Owncloud'); ?>">
          <img class="svg small"
               src="<?php echo $urlGenerator->imagePath('cafevdb', 'cloud.svg'); ?>"
               alt="<?php echo $l->t('Select from Owncloud'); ?>"/>
        </button>
        <button type="button"
                class="attachment personal"
                title="<?php echo $toolTips['emailform:composer:attachments:personal']; ?>"
                value="<?php echo $l->t('Select from participant file attachments'); ?>">
          <img class="svg small"
               src="<?php echo $urlGenerator->imagePath('core', 'actions/projects.svg'); ?>"
               alt="<?php echo $l->t('Select from participant file attachments'); ?>"/>
        </button>
        <button type="button"
                class="attachment events<?php ($projectId <= 0) && p(' hidden'); ?>"
                title="<?php echo $toolTips['emailform:composer:attachments:events']; ?>"
                value="<?php echo $l->t('Project Events'); ?>">
          <img class="svg events"
               src="<?php echo $urlGenerator->imagePath('cafevdb', 'calendar-dark.svg'); ?>"
               alt="<?php echo $l->t('Select Events'); ?>"/>
        </button>
        <div class="separator"></div>
        <button type="button"
                class="attachment visibility-toggle tooltip-auto"
                title="<?php p($toolTips['emailform:composer:attachments:toggle-visibility']); ?>"
                value="<?php echo $l->t('Toggle Visibily'); ?>">
          <img class="svg visibility"
               src="<?php echo $urlGenerator->imagePath('core', 'actions/toggle.svg'); ?>"
               alt="<?php echo $l->t('toggle'); ?>"/>
        </button>
        </div>
      </td>
    </tr>
    <tr class="attachments event-attachments<?php empty($eventAttachmentOptions) && p(' no-attachments'); ?><?php $selectedEventAttachments == 0 && p(' empty-selection'); ?>">
      <td class="event-attachments caption">
        <?php echo $l->t('Attached Events'); ?>
      </td>
      <td class="event-attachments events content chosen-dropup" colspan="2">
        <select multiple="multiple"
                title="<?php echo $toolTips['emailform:composer:attachments:event-select']; ?>"
                name="emailComposer[attachedEvents][]"
                class="event-attachments select"
                id="event-attachments-selector">
          <?php echo PageNavigation::selectOptions($eventAttachmentOptions); ?>
        </select>
        <div class="attachment-controls">
          <input title="<?php p($toolTips['emailform:composer:attachments:toggle-visibility:event']); ?>"
                 type="button"
                 class="visibility-toggle tooltip-auto"
                 name="emailComposer[attachmentVisibilityToggle]"
                 value="<?php p($l->t('Toggle Visibility')); ?>"/>
          <input title="<?php echo $toolTips['emailform:composer:attachments:delete-all-events']; ?>"
                 type="submit"
                 class="submit delete-all-attachments delete-all-event-attachments tooltip-top"
                 name="emailComposer[deleteAllAttachments]"
                 value="<?php echo $l->t('Delete Event Attachments'); ?>"/>
        </div>
      </td>
    </tr>
    <tr class="attachments file-attachments<?php (count($fileAttachmentOptions) == 0) && p(' no-attachments'); ?><?php $selectedFileAttachments == 0 && p(' empty-selection'); ?>">
      <td class="file-attachments caption">
        <?php echo $l->t('Attached Files'); ?>
      </td>
      <td class="file-attachments files content chosen-dropup" colspan="2">
        <select multiple="multiple"
                title="<?php echo $toolTips['emailform:composer::attachments:file-select']; ?>"
                name="emailComposer[attachedFiles][]"
                class="file-attachments select "
                id="file-attachments-selector">
          <?php echo PageNavigation::selectOptions($fileAttachmentOptions); ?>
        </select>
        <div class="attachment-controls">
          <input title="<?php p($toolTips['emailform:composer:attachments:toggle-visibility:file']); ?>"
                 type="button"
                 class="visibility-toggle tooltip-auto"
                 name="emailComposer[attachmentVisibilityToggle]"
                 value="<?php p($l->t('Toggle Visibility')); ?>"/>
          <input title="<?php echo $toolTips['emailform:composer:attachments:delete-all-files']; ?>"
                 type="submit"
                 class="submit delete-all-attachments delete-all-file-attachments tooltip-top"
                 name="emailComposer[deleteAllAttachments]"
                 value="<?php echo $l->t('Delete All Attachments'); ?>"/>
        </div>
      </td>
    </tr>
    <tr class="spacer rule below"><td class="caption"></td><td></td><td></td></tr>
    <tr class="submit">
      <td class="send cancel preview" colspan="3">
        <div class="container send preview">
          <input title="<?php echo $toolTips['emailform:composer:send']; ?>"
                 class="email-composer submit send"
                 type="submit" name="emailComposer[send]"
                 value="<?php echo $l->t('Send Em@il'); ?>"/>
          <input title="<?php echo $toolTips['emailform:composer:export']; ?>"
                 class="email-composer submit message-export"
                 type="submit" name="emailComposer[messageExport]"
                 value="<?php echo $l->t('Message Preview'); ?>"/>
        </div>
        <div class="container cancel">
          <input title="<?php echo $toolTips['emailform:composer:cancel']; ?>"
                 class="email-composer submit cancel tooltip-top"
                 type="submit" name="emailComposer[cancel]"
                 value="<?php echo $l->t('Cancel'); ?>" />
        </div>
      </td>
    </tr>
  </table>
  <!-- various data fields ... -->
  <fieldset id="cafevdb-email-form-attachments" class="attachments">
    <input type="hidden"
           name="emailComposer[fileAttachments]"
           value="<?php echo htmlspecialchars($fileAttachmentData); ?>"
           id="file-attachments"
           class="file-attachments">
  </fieldset>
</fieldset>
<div class="scrollbar-compensator"></div>
