<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020-2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\LegacyTemplates\EmailForm\Composer;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\EmailForm\Composer;
use OCA\CAFEVDB\EmailForm\ComposerCgiKeys;
use OCA\CAFEVDB\EmailForm\ComposerCssClasses;
use OCA\CAFEVDB\EmailForm\EnumFromTag;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Settings\ConfigConstants;

$containerClass = $appName.'-'.'container';

$selectedFileAttachments = 0;
foreach ($fileAttachmentOptions as $option) {
  $selectedFileAttachments += (int)($option['flags'] &  PageNavigation::SELECTED);
}
$selectedEventAttachments = 0;
foreach ($eventAttachmentOptions as $option) {
  $selectedEventAttachments += (int)($option['flags'] &  PageNavigation::SELECTED);
}

if (!function_exists(__NAMESPACE__ . '\\e')) {
  function e(string $string)
  {
    echo $string;
    return true;
  }
}

?>

<fieldset id="cafevdb-email-composition-fieldset" class="email-composition page">
  <!-- <legend id="cafevdb-email-form-legend"><?php echo $l->t('Compose Em@il'); ?></legend> -->
  <?php echo PageNavigation::persistentCGI(Composer::POST_TAG, $composerFormData); ?>
  <table class="cafevdb-email-composition-form">
    <tr class="column-layout">
      <td class="first"></td>
      <td class="second"></td>
      <td class="third"></td>
    </tr>
    <tr class="stored-messages">
      <td colspan="2" class="stored-messages-choose stored-messages">
        <span class="select-container flex-container flex-center flex-justify-full">
          <select size="<?php echo count($templateEmails); ?>"
                  class="template email-message-selector"
                  title="<?php echo $toolTips['emailform:storage:select:templates']; ?>"
                  data-placeholder="<?php echo $l->t("Templates"); ?>"
                  name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::TEMPLATE_MESSAGES_SELECTOR ?>]"
                  id="cafevdb-template-messages-selector"
          >
            <option></option>
            <?php echo $this->inc('emailform/part.template-email-options', []); ?>
          </select>
          <select size="<?php echo count($draftEmails); ?>"
                  class="draft email-message-selector"
                  title="<?php echo $toolTips['emailform:storage:select:drafts']; ?>"
                  data-placeholder="<?php echo $l->t("Drafts"); ?>"
                  name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::DRAFT_MESSAGES_SELECTOR ?>]"
                  id="cafevdb-draft-messages-selector"
          >
            <option></option>
            <?php echo $this->inc('emailform/part.draft-email-options', []); ?>
          </select>
          <select class="sent email-message-selector"
                  size="<?php echo count($sentEmails); ?>"
                  title="<?php echo $toolTips['emailform:storage:select:sent']; ?>"
                  data-placeholder="<?php p($l->t('Reply To')); ?>"
                  name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::SENT_MESSAGES_SELECTOR ?>]"
                  id="cafevdb-sent-messages-selector"
          >
            <option></option>
            <?php echo $this->inc('emailform/part.sent-email-options', []); ?>
          </select>
        </span>
      </td>
      <td class="stored-messages-storage stored-messages">
        <span class="button-container flex-container flex-center">
          <span class="inner vmiddle <?php p($containerClass); ?> checkbox-button save-as-template flex-container flex-center">
            <input type="checkbox"
                   id="check-save-as-template"
                   class="save-as-template tooltip-wide tooltip-bottom"
                   name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::SAVE_AS_TEMPLATE ?>]"
            />
            <label for="check-save-as-template"
                   class="tip save-as-template"
                   title="<?php echo $toolTips['emailform:storage:messages:save-as-template']; ?>">
              <span class="save-as-template button no-margin-right"></span>
            </label>
          </span>
          <input title="<?php echo $toolTips['emailform:storage:messages:save-message']; ?>"
                 type="submit"
                 class="submit save-message tooltip-wide tooltip-bottom no-margin-right"
                 name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::SAVE_MESSAGE ?>]"
                 value="<?php echo $l->t('Save Message'); ?>"
          />
          <span class="inner vmiddle <?php p($containerClass); ?> checkbox-button draft-auto-save flex-container flex-center">
            <input type="checkbox"
                   id="check-draft-auto-save"
                   class="draft-auto-save tooltip-auto"
                   data-auto-save-interval="<?php e((int)$_[Composer::POST_TAG][ComposerCgiKeys::DRAFT_AUTO_SAVE]) ?>"
                   <?php $_[Composer::POST_TAG][ComposerCgiKeys::DRAFT_AUTO_SAVE] && e('checked'); ?>
                   name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::DRAFT_AUTO_SAVE ?>]"
            />
            <label for="check-draft-auto-save"
                   class="draft-auto-save tooltip-auto"
                   title="<?php p($toolTips['emailform:storage:messages:draft-auto-save']); ?>">
              <span class="draft-auto-save button no-margin-right"></span>
            </label>
          </span>
          <input title="<?php echo $toolTips['emailform:storage:messages:delete-saved-message']; ?>"
                 type="submit"
                 class="submit delete-message tooltip-bottom no-margin-right"
                 name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::DELETE_MESSAGE ?>]"
                 value="<?php echo $l->t('Delete Message'); ?>"
          />
        </span>
      </td>
    </tr>
    <tr><td colspan="3" class="rule"><hr /></td></tr>
    <tr class="email-from">
      <td class="email-from caption"><?php echo $l->t('Sender'); ?></td>
      <td class="email-from" colspan="2">
        <span class="flex-container flex-justify-full">
          <span class="flex-container flex-column flex-grow">
            <span>
              <input id="email-from-orchestra"
                     class="radio"
                     type="radio"
                     name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::FROM_TAG ?>]"
                     value="<?= EnumFromTag::ORCHESTRA->value ?>"
                     <?php EnumFromTag::get($_[Composer::POST_TAG][ComposerCgiKeys::FROM_TAG]) === EnumFromTag::ORCHESTRA && e('checked') ?>
              />
              <label for="email-from-orchestra">
                <?php p($_['fromName'][EnumFromTag::ORCHESTRA->value] . ' <' . $_['fromAddress'][EnumFromTag::ORCHESTRA->value] . '>') ?>
              </label>
            </span>
            <span>
              <input id="email-from-personal"
                     class="radio"
                     type="radio"
                     name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::FROM_TAG ?>]"
                     value="<?= EnumFromTag::PERSONAL->value ?>"
                     <?php EnumFromTag::get($_[Composer::POST_TAG][ComposerCgiKeys::FROM_TAG]) === EnumFromTag::PERSONAL && e('checked') ?>
              />
              <label for="email-from-personal">
                <?php p($_['fromName'][EnumFromTag::PERSONAL->value] . ' <' . $_['fromAddress'][EnumFromTag::PERSONAL->value] . '>') ?>
              </label>
            </span>
          </span>
          <input type="button"
                 class="button no-margin-right save-from-tag flex-shrink"
                 name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::SAVE_FROM_TAG ?>]"
          >
        </span>
      </td>
    </tr>
    <tr class="email-address">
      <td class="email-address email-recipients caption"><?php echo $l->t('Recipients'); ?></td>
      <td class="email-address email-recipients display" colspan="2">
        <span class="flex-container flex-center">
          <span class="email-address-holder email-recipients inner vmiddle tooltip-bottom tooltip-mostwide"
                title="<?php echo $toolTips['emailform:composer:recipients-listing'].'</br>'.htmlspecialchars(implode(', ', $TO)); ?>"
                data-placeholder="<?php echo $l->t('No recipients selected.'); ?>"
                data-title-intro="<?php echo $toolTips['emailform:composer:recipients:listing']; ?>"
          >
            <?php echo empty($TO) ? $l->t('No recipients selected.') : implode(', ', $TO); ?>
          </span>
          <span class="inner vmiddle <?php p($containerClass); ?> checkbox-button inverted disclosed-recipients tooltip-auto flex-container flex-center"
                title="<?php echo Util::htmlEscape($toolTips['emailform:composer:recipients:disclosed-recipients']); ?>"
          >
            <input type="checkbox"
                   <?php $_[Composer::POST_TAG][ComposerCgiKeys::DISCLOSED_RECIPIENTS] && e('checked'); ?>
                   id="check-disclosed-recipients"
                   <?php ($projectId <= 0) && p('disabled'); ?>
                   class="disclosed-recipients tooltip-top"
                   name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::DISCLOSED_RECIPIENTS ?>]"
            />
            <label for="check-disclosed-recipients"
                   <?php ($projectId <= 0) && p('disabled'); ?>
                   class="disclosed-recipients">
              <span class="disclosed-recipients button no-margin-right">
                <span clas="label">BCC</span>
              </span>
            </label>
            <span class="checkbox-alert alert-checked"></span>
          </span>
        </span>
      </td>
    </tr>
    <tr class="email-address">
      <td class="email-address caption"><?php echo $l->t('Carbon Copy'); ?></td>
      <td class="email-address input" colspan="2">
        <input size="40"
               title="<?php $toolTips['emailform:composer:recipients:freeform-CC']; ?>"
               class="email-address-holder tooltip-top"
               value="<?php p($_[Composer::POST_TAG][ComposerCgiKeys::CC]); ?>"
               name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::CC ?>]"
               type="text"
               id="carbon-copy"
        />
        <input title="<?php echo $toolTips['emailform:composer:recipients:address-book']; ?>"
               type="submit"
               class="submit address-book-emails CC tooltip-bottom no-margin-right"
               data-for="#carbon-copy"
               name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::ADDRESS_BOOK_CC ?>]"
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
               value="<?php p($_[Composer::POST_TAG][ComposerCgiKeys::BCC]); ?>"
               name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::BCC ?>]"
               type="text"
               id="blind-carbon-copy"
        />
        <input title="<?php echo $toolTips['emailform:composer:recipients:address-book']; ?>"
               type="submit"
               class="submit address-book-emails BCC tooltip-bottom no-margin-right"
               data-for="#blind-carbon-copy"
               name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::ADDRESS_BOOK_BCC ?>]"
               value="<?php echo $l->t('Address Book'); ?>"
        />
      </td>
    </tr>
    <tr>
      <td class="subject caption"><?php echo $l->t('Subject'); ?></td>
      <td colspan="2" class="subject input">
        <div class="subject <?php p($containerClass); ?> flex-container flex-justify-full flex-center">
          <span class="subject tag container display flex-container flex-center">
            <span class="prefix">
              [<?php p($_[Composer::POST_TAG][ConfigConstants::BULK_EMAIL_SUBJECT_TAG]) ?>-
            </span>
            <span class="content">
              <input class="editable"
                     type="text"
                     name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::SUBJECT_TAG ?>]"
                     value="<?php p($_[Composer::POST_TAG][ComposerCgiKeys::SUBJECT_TAG]); ?>"
              />
              <span class="display"><?php p($_[Composer::POST_TAG][ComposerCgiKeys::SUBJECT_TAG]) ?></span>
            </span>
            <span class="postfix">
              ]
            </span>
            <input type="button"
                   class="button edit-subject-tag display no-margin-right"
                   name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::EDIT_SUBJECT_TAG ?>]"
            />
          </span>
          <span class="subject input">
            <input value="<?php p($_[Composer::POST_TAG][ComposerCgiKeys::SUBJECT]); ?>"
                   size="40"
                   name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::SUBJECT ?>]"
                   type="text"
                   class="email-subject"
                   spellcheck="true"
                   id="email-composer-subject"
            />
          </span>
        </div>
      </td>
    </tr>
    <tr>
      <td class="body caption"><?php echo $l->t('Message-Body'); ?></td>
      <td colspan="2" class="messagetext">
        <textarea class="wysiwyg-editor external-documents"
                  name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::MESSAGE_TEXT ?>]"
                  cols="60"
                  rows="20"
                  id="message-text"
        >
          <?php echo htmlspecialchars($_[Composer::POST_TAG][ComposerCgiKeys::MESSAGE_TEXT]); ?>
        </textarea>
      </td>
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
               src="<?php echo $urlGenerator->imagePath($appName, 'cloud.svg'); ?>"
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
               src="<?php echo $urlGenerator->imagePath($appName, 'calendar-dark.svg'); ?>"
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
    <tr class="attachments event-attachments<?php empty($eventAttachmentOptions) && p(' ' . ComposerCssClasses::NO_ATTACHMENTS); ?><?php $selectedEventAttachments == 0 && p(' ' . ComposerCssClasses::EMPTY_SELECTION); ?>">
      <td class="event-attachments caption">
        <?php echo $l->t('Attached Events'); ?>
      </td>
      <td class="event-attachments events content chosen-dropup menu-dropup" colspan="2">
        <span class="flex-container flex-begin flex-justify-full">
          <select multiple="multiple"
                  title="<?php echo $toolTips['emailform:composer:attachments:event-select']; ?>"
                  name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::ATTACHED_EVENTS ?>][]"
                  class="event-attachments email-attachments select"
                  id="event-attachments-selector">
            <?php echo PageNavigation::selectOptions($eventAttachmentOptions, initialIndent: 12); ?>
          </select>
          <div class="attachment-controls flex-container flex-wrap flex-justify-end">
            <input title="<?php p($toolTips['emailform:composer:attachments:toggle-visibility:event']); ?>"
                   type="button"
                   class="visibility-toggle tooltip-auto"
                   name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::ATTACHMENT_VISIBILITY_TOGGLE ?>]"
                   value="<?php p($l->t('Toggle Visibility')); ?>"
            />
            <input title="<?php echo $toolTips['emailform:composer:attachments:delete-all-events']; ?>"
                   type="submit"
                   class="submit delete-all-attachments delete-all-event-attachments tooltip-top"
                   name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::DELETE_ALL_ATTACHMENTS ?>]"
                   value="<?php echo $l->t('Delete Event Attachments'); ?>"
            />
          </div>
        </span>
      </td>
    </tr>
    <tr class="attachments file-attachments<?php (count($fileAttachmentOptions) == 0) && p(' ' . ComposerCssClasses::NO_ATTACHMENTS); ?><?php $selectedFileAttachments == 0 && p(' ' . ComposerCssClasses::EMPTY_SELECTION); ?>">
      <td class="file-attachments caption">
        <?php echo $l->t('Attached Files'); ?>
      </td>
      <td class="file-attachments files content chosen-dropup menu-dropup" colspan="2">
        <span class="flex-container flex-begin flex-justify-full">
          <select multiple="multiple"
                  title="<?php echo $toolTips['emailform:composer::attachments:file-select']; ?>"
                  name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::ATTACHED_FILES ?>][]"
                  class="file-attachments email-attachments select"
                  id="file-attachments-selector">
            <?php echo PageNavigation::selectOptions($fileAttachmentOptions, initialIndent: 12); ?>
          </select>
          <div class="attachment-controls flex-container flex-wrap flex-justify-end">
            <input title="<?php p($toolTips['emailform:composer:attachments:toggle-visibility:file']); ?>"
                   type="button"
                   class="visibility-toggle tooltip-auto"
                   name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::ATTACHMENT_VISIBILITY_TOGGLE ?>]"
                   value="<?php p($l->t('Toggle Visibility')); ?>"
            />
            <input title="<?php echo $toolTips['emailform:composer:attachments:delete-all-files']; ?>"
                   type="submit"
                   class="submit delete-all-attachments delete-all-file-attachments tooltip-top"
                   name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::DELETE_ALL_ATTACHMENTS ?>]"
                   value="<?php echo $l->t('Delete All Attachments'); ?>"
            />
          </div>
        </span>
      </td>
    </tr>
    <tr><td colspan="3" class="rule"><hr /></td></tr>
    <tr class="submit">
      <td class="send cancel preview" colspan="3">
        <div class="container send preview">
          <input title="<?php echo $toolTips['emailform:composer:send']; ?>"
                 class="email-composer submit send"
                 type="submit"
                 name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::SEND ?>]"
                 value="<?php echo $l->t('Send Em@il'); ?>"
          />
          <input title="<?php echo $toolTips['emailform:composer:export']; ?>"
                 class="email-composer submit message-export"
                 type="submit"
                 name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::MESSAGE_EXPORT ?>]"
                 value="<?php echo $l->t('Message Preview'); ?>"
          />
        </div>
        <div class="container cancel">
          <input title="<?php echo $toolTips['emailform:composer:cancel']; ?>"
                 class="email-composer submit cancel tooltip-top"
                 type="submit"
                 name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::CANCEL ?>]"
                 value="<?php echo $l->t('Cancel'); ?>"
          />
        </div>
      </td>
    </tr>
  </table>
  <!-- various data fields ... -->
  <fieldset id="cafevdb-email-form-attachments" class="attachments">
    <input type="hidden"
           name="<?= Composer::POST_TAG ?>[<?= ComposerCgiKeys::FILE_ATTACHMENTS ?>]"
           value="<?php echo htmlspecialchars($_[Composer::POST_TAG][ComposerCgiKeys::FILE_ATTACHMENTS]); ?>"
           id="file-attachments"
           class="file-attachments"
    />
  </fieldset>
</fieldset>
<div class="scrollbar-compensator"></div>
