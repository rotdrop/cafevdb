<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2020-2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB;

use OCA\CAFEVDB\Service\CloudUserConnectorService;
use OCA\CAFEVDB\Settings\ConfigConstants;

$alloff = $_[ConfigConstants::ORCHESTRA_NAME_KEY] == '' ? $alloff = 'disabled' : '';
$off = $_[ConfigConstants::SHARE_OWNER_KEY] == '' ? 'disabled' : $alloff;

?>
<div id="tabs-<?= $_['tabNr']; ?>" class="personalblock admin sharing">
<!-- SHARED CALENDARS and stuff -->
  <div id="sharing-settings">
    <!-- VIRTUAL USER -->
    <h4><?= $l->t('Share owner') ; ?></h4>
    <form id="<? ConfigConstants::SHARE_OWNER_KEY ?>form">
      <fieldset id=<?= ConfigConstants::SHARE_OWNER_KEY ?> <?= $alloff; ?> >
        <input type="hidden" id="user-saved" name="<? ConfigConstants::SHARE_OWNER_KEY ?>-saved" value="<?= $_[ConfigConstants::SHARE_OWNER_KEY]; ?>" />
        <input type="text"
               id="user"
               name=<?= ConfigConstants::SHARE_OWNER_KEY ?>
               placeholder="<?= $l->t(ConfigConstants::SHARE_OWNER_KEY);?>"
               value="<?= $_[ConfigConstants::SHARE_OWNER_KEY]; ?>"
               title="<?= $_[ConfigConstants::SHARE_OWNER_KEY]; ?>"
               <?= $_[ConfigConstants::SHARE_OWNER_KEY] != '' ? 'disabled' : '';?>
               autocomplete="username"
        />
        <input type="checkbox" id="<? ConfigConstants::SHARE_OWNER_KEY ?>-force" name="<? ConfigConstants::SHARE_OWNER_KEY ?>-force" class="checkbox"/>
           <label for="<? ConfigConstants::SHARE_OWNER_KEY ?>-force" title="<?= $toolTips['shareowner-force']; ?>"  class="tooltip-auto">
             <?= $l->t('force');?>
           </label>
        <input name="<? ConfigConstants::SHARE_OWNER_KEY ?>check" id="check" type="button" value="<?= $l->t('Check');?>" <?= $off; ?> />
      </fieldset>
<!-- CHANGE ITS PASSWORD -->
      <fieldset class="<?= ConfigConstants::SHARE_OWNER_PASSWORD_KEY ?> flex-container" <?= $off; ?> >
        <div class="password-container">
          <input type="password"
                 id="<?= ConfigConstants::SHARE_OWNER_PASSWORD_KEY ?>"
                 class="randompassword"
                 name="<?= ConfigConstants::SHARE_OWNER_PASSWORD_KEY ?>"
                 placeholder="<?= $l->t('Share-Password');?>"
                 data-typetoggle="#<?= ConfigConstants::SHARE_OWNER_PASSWORD_KEY ?>-show"
                 autocomplete="current-password"
          />
          <input class="<?= $appName ?>-password-show" type="checkbox" id="<?= ConfigConstants::SHARE_OWNER_PASSWORD_KEY ?>-show" name="<?= ConfigConstants::SHARE_OWNER_PASSWORD_KEY ?>-show" />
          <label class="<?= $appName ?>-password-show" for="<?= ConfigConstants::SHARE_OWNER_PASSWORD_KEY ?>-show"><?= $l->t('show');?></label>
        </div>
        <input name="passwordgenerate" id="generate" type="button" value="<?= $l->t('Generate');?>" />
        <input name="passwordchange" id="change" type="button" value="<?= $l->t('Change');?>" />
      </fieldset>
      <div>
        <span class="statusmessage"></span><span>&nbsp;</span>
      </div>
    </form>
    <!-- CALENDARS -->
    <h4><?= $l->t('Calendars'); ?></h4>
    <form id="calendars">
      <fieldset  <?= $off; ?> >
        <input type="text" id="<?= ConfigConstants::CONCERTS_CALENDAR_URI ?>" name="<?= ConfigConstants::CONCERTS_CALENDAR_URI ?><?= ConfigConstants::CALENDAR_KEY_POSTFIX ?>" placeholder="<?= $l->t('calendarname');?>" value="<?= $_['concerts' . ConfigConstants::CALENDAR_KEY_POSTFIX]; ?>" />
        <label for="<?= ConfigConstants::CONCERTS_CALENDAR_URI ?>"><?= $l->t('Calendar for Concerts');?></label>
        <br/>
        <input type="text" id="<?= ConfigConstants::REHEARSALS_CALENDAR_URI ?>" name="<?= ConfigConstants::REHEARSALS_CALENDAR_URI ?><?= ConfigConstants::CALENDAR_KEY_POSTFIX ?>" placeholder="<?= $l->t('calendarname');?>" value="<?= $_['rehearsals' . ConfigConstants::CALENDAR_KEY_POSTFIX]; ?>" />
        <label for="<?= ConfigConstants::REHEARSALS_CALENDAR_URI ?>"><?= $l->t('Calendar for Rehearsals');?></label>
        <br/>
        <input type="text" id="<?= ConfigConstants::OTHER_CALENDAR_URI ?>" name="<?= ConfigConstants::OTHER_CALENDAR_URI ?><?= ConfigConstants::CALENDAR_KEY_POSTFIX ?>" placeholder="<?= $l->t('calendarname');?>" value="<?= $_['other' . ConfigConstants::CALENDAR_KEY_POSTFIX]; ?>" />
        <label for="<?= ConfigConstants::OTHER_CALENDAR_URI ?>"><?= $l->t('Calendar for other Events');?></label>
        <br/>
        <input type="text" id="<?= ConfigConstants::MANAGEMENT_CALENDAR_URI ?>" name="<?= ConfigConstants::MANAGEMENT_CALENDAR_URI ?><?= ConfigConstants::CALENDAR_KEY_POSTFIX ?>" placeholder="<?= $l->t('calendarname');?>" value="<?= $_['management' . ConfigConstants::CALENDAR_KEY_POSTFIX]; ?>" />
        <label for="<?= ConfigConstants::MANAGEMENT_CALENDAR_URI ?>"><?= $l->t('Management-Calendar');?></label>
        <br/>
        <input type="text" id="<?= ConfigConstants::FINANCE_CALENDAR_URI ?>" name="<?= ConfigConstants::FINANCE_CALENDAR_URI ?><?= ConfigConstants::CALENDAR_KEY_POSTFIX ?>" placeholder="<?= $l->t('calendarname');?>" value="<?= $_['finance' . ConfigConstants::CALENDAR_KEY_POSTFIX]; ?>" />
        <label for="<?= ConfigConstants::FINANCE_CALENDAR_URI ?>"><?= $l->t('Finance-Calendar');?></label>
        <br/>
<!-- DEFAULT DURATION FOR EVENTS -->
        <input type="number"
               min="0"
               id="<?= ConfigConstants::EVENT_DURATION_KEY ?>"
               name="<?= ConfigConstants::EVENT_DURATION_KEY ?>"
               placeholder="<?= $l->t('#Minutes');?>"
               value="<?= $_[ConfigConstants::EVENT_DURATION_KEY]; ?>"
        />
        <label for="<?= ConfigConstants::EVENT_DURATION_KEY ?>"><?= $l->t('Default Duration for Events');?></label>
      </fieldset>
    </form>
    <!-- Contacts, adressbooks -->
    <h4><?= $l->t('Contacts'); ?></h4>
    <form id="contacts">
      <fieldset  <?= $off; ?> >
        <input type="text" id="<?= ConfigConstants::GENERAL_ADDRESS_BOOK_KEY ?>" name="<?= ConfigConstants::GENERAL_ADDRESS_BOOK_KEY ?>" placeholder="<?= $l->t('addressbook');?>" value="<?= $_[ConfigConstants::GENERAL_ADDRESS_BOOK_KEY]; ?>" />
        <label for="<?= ConfigConstants::GENERAL_ADDRESS_BOOK_KEY ?>"><?= $l->t('General Addresbook');?></label>
        <br/>
        <input type="text" id=<?= ConfigConstants::MUSICIANS_ADDRESS_BOOK_KEY ?> name=<?= ConfigConstants::MUSICIANS_ADDRESS_BOOK_KEY ?> placeholder="<?= $l->t('addressbook');?>" value="<?= $_[ConfigConstants::MUSICIANS_ADDRESS_BOOK_KEY]; ?>" />
        <label for=<?= ConfigConstants::MUSICIANS_ADDRESS_BOOK_KEY ?>><?= $l->t('Addresbook for Musicians');?></label>
      </fieldset>
    </form>
    <!-- Shared folders -->
    <h4><?= $l->t('Shared folder'); ?></h4>
    <form id="<?= ConfigConstants::SHARED_FOLDER ?>-form" class="<?= ConfigConstants::SHARED_FOLDER ?>s">
      <fieldset id="<?= ConfigConstants::SHARED_FOLDER ?>-fieldset" <?= $off; ?> >
        <input type="hidden" id="<?= ConfigConstants::SHARED_FOLDER ?>-saved" name="<?= ConfigConstants::SHARED_FOLDER ?>-saved" value="<?= $_[ConfigConstants::SHARED_FOLDER]; ?>" />
        <input <?= $_[ConfigConstants::SHARED_FOLDER] != '' ? 'disabled' : ''; ?>
          type="text"
          id="<?= ConfigConstants::SHARED_FOLDER ?>"
          name="<?= ConfigConstants::SHARED_FOLDER ?>"
          placeholder="<?= $l->t('shared folder'); ?>"
          value="<?= $_[ConfigConstants::SHARED_FOLDER]; ?>"
          title="<?= $toolTips[ConfigConstants::SHARED_FOLDER]; ?>"
        />
        <input type="checkbox" id="<?= ConfigConstants::SHARED_FOLDER ?>-force" name="<?= ConfigConstants::SHARED_FOLDER ?>-force" class="checkbox"/>
        <label for="<?= ConfigConstants::SHARED_FOLDER ?>-force" title="<?= $toolTips[ConfigConstants::SHARED_FOLDER . '-force']; ?>" >
          <?= $l->t('force');?>
        </label>
        <input name="<?= ConfigConstants::SHARED_FOLDER ?>-check" id="<?= ConfigConstants::SHARED_FOLDER ?>-check" type="button" value="<?= $l->t('Check');?>" />
        <a name="<?= ConfigConstants::SHARED_FOLDER ?>-view"
           href="<?php p($sharedFolderLink); ?>"
           target="<?php p($appName . '-sharedfolder-view'); ?>"
           class="<?= ConfigConstants::SHARED_FOLDER ?>-view button<?php empty($_[ConfigConstants::SHARED_FOLDER]) && p(' reallyhidden'); ?>"></a>
      </fieldset>
      <fieldset id="<?= ConfigConstants::POSTBOX_FOLDER ?>-fieldset"
                class="needs-<?= ConfigConstants::SHARED_FOLDER ?>"
                <?= $_[ConfigConstants::SHARED_FOLDER] != '' ? $off : 'disabled'; ?> >
        <span><b>.../</b></span><span class="<?= ConfigConstants::SHARED_FOLDER ?>"><?= $_[ConfigConstants::SHARED_FOLDER]; ?></span><span><b>/</b></span>
        <input type="hidden" id="<?= ConfigConstants::POSTBOX_FOLDER ?>-saved" name="<?= ConfigConstants::POSTBOX_FOLDER ?>-saved" value="<?= $_[ConfigConstants::POSTBOX_FOLDER]; ?>" />
        <input <?= $_[ConfigConstants::POSTBOX_FOLDER] != '' ? 'disabled' : ''; ?>
          type="text"
          id="<?= ConfigConstants::POSTBOX_FOLDER ?>"
          name="<?= ConfigConstants::POSTBOX_FOLDER ?>"
          placeholder="<?= $l->t('Postbox-Folder'); ?>"
          value="<?= $_[ConfigConstants::POSTBOX_FOLDER]; ?>"
          title="<?= $toolTips[ConfigConstants::POSTBOX_FOLDER]; ?>"
        />
        <input type="checkbox" id="<?= ConfigConstants::POSTBOX_FOLDER ?>-force" name="<?= ConfigConstants::POSTBOX_FOLDER ?>-force" class="checkbox"/>
        <label for="<?= ConfigConstants::POSTBOX_FOLDER ?>-force" title="<?= $toolTips[ConfigConstants::POSTBOX_FOLDER . '-force']; ?>" >
          <?= $l->t('force');?>
        </label>
        <input name="<?= ConfigConstants::POSTBOX_FOLDER ?>-check" id="<?= ConfigConstants::POSTBOX_FOLDER ?>-check" type="button" value="<?= $l->t('Check');?>" />
        <div class="<?= ConfigConstants::POSTBOX_FOLDER ?>-sharelink<?php empty($postboxFolderShareLink) && p(' hidden'); ?>"><?php p($postboxFolderShareLink); ?></div>
      </fieldset>
      <fieldset id="<?= ConfigConstants::OUTBOX_FOLDER ?>-fieldset"
                class="needs-<?= ConfigConstants::SHARED_FOLDER ?>"
                <?= $_[ConfigConstants::SHARED_FOLDER] != '' ? $off : 'disabled'; ?> >
        <span><b>.../</b></span><span class="<?= ConfigConstants::SHARED_FOLDER ?>"><?= $_[ConfigConstants::SHARED_FOLDER]; ?></span><span><b>/</b></span>
        <input type="hidden" id="<?= ConfigConstants::OUTBOX_FOLDER ?>-saved" name="<?= ConfigConstants::OUTBOX_FOLDER ?>-saved" value="<?= $_[ConfigConstants::OUTBOX_FOLDER]; ?>" />
        <input <?= $_[ConfigConstants::OUTBOX_FOLDER] != '' ? 'disabled' : ''; ?>
          type="text"
          id="<?= ConfigConstants::OUTBOX_FOLDER ?>"
          name="<?= ConfigConstants::OUTBOX_FOLDER ?>"
          placeholder="<?= $l->t('Outbox-Folder'); ?>"
          value="<?= $_[ConfigConstants::OUTBOX_FOLDER]; ?>"
          title="<?= $toolTips[ConfigConstants::OUTBOX_FOLDER]; ?>"
        />
        <input type="checkbox" id="<?= ConfigConstants::OUTBOX_FOLDER ?>-force" name="<?= ConfigConstants::OUTBOX_FOLDER ?>-force" class="checkbox"/>
        <label for="<?= ConfigConstants::OUTBOX_FOLDER ?>-force" title="<?= $toolTips[ConfigConstants::OUTBOX_FOLDER . '-force']; ?>" >
          <?= $l->t('force');?>
        </label>
        <input name="<?= ConfigConstants::OUTBOX_FOLDER ?>-check" id="<?= ConfigConstants::OUTBOX_FOLDER ?>-check" type="button" value="<?= $l->t('Check');?>" />
      </fieldset>
      <fieldset id="<?= ConfigConstants::DOCUMENT_TEMPLATES_FOLDER ?>-fieldset"
                class="needs-<?= ConfigConstants::SHARED_FOLDER ?>"
                <?= $_[ConfigConstants::SHARED_FOLDER] != '' ? $off : 'disabled'; ?> >
        <span><b>.../</b></span><span class="<?= ConfigConstants::SHARED_FOLDER ?>"><?= $_[ConfigConstants::SHARED_FOLDER]; ?></span><span><b>/</b></span>
        <input type="hidden" id="<?= ConfigConstants::DOCUMENT_TEMPLATES_FOLDER ?>-saved" name="<?= ConfigConstants::DOCUMENT_TEMPLATES_FOLDER ?>-saved" value="<?= $_[ConfigConstants::DOCUMENT_TEMPLATES_FOLDER]; ?>" />
        <input <?= $_[ConfigConstants::DOCUMENT_TEMPLATES_FOLDER] != '' ? 'disabled' : ''; ?>
          type="text"
          id="<?= ConfigConstants::DOCUMENT_TEMPLATES_FOLDER ?>"
          name="<?= ConfigConstants::DOCUMENT_TEMPLATES_FOLDER ?>"
          placeholder="<?= $l->t('Document templates folder'); ?>"
          value="<?= $_[ConfigConstants::DOCUMENT_TEMPLATES_FOLDER]; ?>"
          title="<?= $toolTips[ConfigConstants::DOCUMENT_TEMPLATES_FOLDER]; ?>"
        />
        <input type="checkbox" id="<?= ConfigConstants::DOCUMENT_TEMPLATES_FOLDER ?>-force" name="<?= ConfigConstants::DOCUMENT_TEMPLATES_FOLDER ?>-force" class="checkbox"/>
        <label for="<?= ConfigConstants::DOCUMENT_TEMPLATES_FOLDER ?>-force" title="<?= $toolTips[ConfigConstants::DOCUMENT_TEMPLATES_FOLDER . '-force']; ?>" >
          <?= $l->t('force');?>
        </label>
        <input name="<?= ConfigConstants::DOCUMENT_TEMPLATES_FOLDER ?>-check" id="<?= ConfigConstants::DOCUMENT_TEMPLATES_FOLDER ?>-check" type="button" value="<?= $l->t('Check');?>" />
      </fieldset>
      <fieldset id="<?= ConfigConstants::PROJECTS_FOLDER ?>-fieldset"
                class="needs-<?= ConfigConstants::SHARED_FOLDER ?>"
                <?= $_[ConfigConstants::SHARED_FOLDER] != '' ? $off : 'disabled'; ?> >
        <span><b>.../</b></span><span class="<?= ConfigConstants::SHARED_FOLDER ?>"><?= $_[ConfigConstants::SHARED_FOLDER]; ?></span>
        <span><b>/</b></span>
        <input type="hidden" id="<?= ConfigConstants::PROJECTS_FOLDER ?>-saved" name="<?= ConfigConstants::PROJECTS_FOLDER ?>-saved" value="<?= $_[ConfigConstants::PROJECTS_FOLDER]; ?>" />
        <input <?= $_[ConfigConstants::PROJECTS_FOLDER] != '' ? 'disabled' : ''; ?>
          type="text"
          id="<?= ConfigConstants::PROJECTS_FOLDER ?>"
          name="<?= ConfigConstants::PROJECTS_FOLDER ?>"
          placeholder="<?= $l->t('Project folder'); ?>"
          value="<?= $_[ConfigConstants::PROJECTS_FOLDER]; ?>"
          title="<?= $toolTips[ConfigConstants::PROJECTS_FOLDER]; ?>"
        />
        <span><b>/</b></span><span><?= $l->t('YEAR'); ?></span>
        <span><b>/</b></span><span><?= $l->t('PROJECT'); ?></span><span><b>/</b></span>
        <input type="checkbox" id="<?= ConfigConstants::PROJECTS_FOLDER ?>-force" name="<?= ConfigConstants::PROJECTS_FOLDER ?>-force" class="checkbox"/>
        <label for="<?= ConfigConstants::PROJECTS_FOLDER ?>-force" title="<?= $toolTips[ConfigConstants::PROJECTS_FOLDER . '-force']; ?>" >
          <?= $l->t('force');?>
        </label>
        <input name="<?= ConfigConstants::PROJECTS_FOLDER ?>-check" id="<?= ConfigConstants::PROJECTS_FOLDER ?>-check" type="button" value="<?= $l->t('Check');?>" />
      </fieldset>
      <fieldset id="<?= ConfigConstants::PROJECT_PARTICIPANTS_FOLDER ?>-fieldset"
                class="needs-<?= ConfigConstants::SHARED_FOLDER ?> needs-<?= ConfigConstants::PROJECTS_FOLDER ?>"
                class="<?= ConfigConstants::PROJECT_PARTICIPANTS_FOLDER ?>" <?= $_[ConfigConstants::PROJECTS_FOLDER] != '' ? $off : 'disabled'; ?> >
        <span><b>.../</b></span><span class="<?= ConfigConstants::SHARED_FOLDER ?>"><?= $_[ConfigConstants::SHARED_FOLDER]; ?></span>
        <span><b>/</b></span><span class="<?= ConfigConstants::PROJECTS_FOLDER ?>"><?= $_[ConfigConstants::PROJECTS_FOLDER]; ?></span>
        <span><b>/</b></span><span><?= $l->t('YEAR'); ?></span>
        <span><b>/</b></span><span><?= $l->t('PROJECT'); ?></span>
        <span><b>/</b></span>
        <input type="hidden" id="<?= ConfigConstants::PROJECT_PARTICIPANTS_FOLDER ?>-saved" name="<?= ConfigConstants::PROJECT_PARTICIPANTS_FOLDER ?>-saved" value="<?= $_[ConfigConstants::PROJECT_PARTICIPANTS_FOLDER]; ?>" />
        <input <?= $_[ConfigConstants::PROJECT_PARTICIPANTS_FOLDER] != '' ? 'disabled' : ''; ?>
          type="text"
          id="<?= ConfigConstants::PROJECT_PARTICIPANTS_FOLDER ?>"
          name="<?= ConfigConstants::PROJECT_PARTICIPANTS_FOLDER ?>"
          placeholder="<?= $l->t('Participants Folder'); ?>"
          value="<?= $_[ConfigConstants::PROJECT_PARTICIPANTS_FOLDER]; ?>"
          title="<?= $toolTips[ConfigConstants::PROJECT_PARTICIPANTS_FOLDER]; ?>"
        />
        <span><b>/</b></span><span><?= $l->t('john.doe'); ?></span><span><b>/</b></span>
        <input type="checkbox" id="<?= ConfigConstants::PROJECT_PARTICIPANTS_FOLDER ?>-force" name="<?= ConfigConstants::PROJECT_PARTICIPANTS_FOLDER ?>-force" class="checkbox"/>
        <label for="<?= ConfigConstants::PROJECT_PARTICIPANTS_FOLDER ?>-force" title="<?= $toolTips[ConfigConstants::PROJECT_PARTICIPANTS_FOLDER . '-force']; ?>" >
          <?= $l->t('force');?>
        </label>
        <input name="<?= ConfigConstants::PROJECT_PARTICIPANTS_FOLDER ?>-check" id="<?= ConfigConstants::PROJECT_PARTICIPANTS_FOLDER ?>-check" type="button" value="<?= $l->t('Save');?>" />
      </fieldset>
      <fieldset id="<?= ConfigConstants::PROJECT_POSTERS_FOLDER ?>-fieldset"
                class="<?= ConfigConstants::PROJECT_POSTERS_FOLDER ?> needs-<?= ConfigConstants::SHARED_FOLDER ?> needs-<?= ConfigConstants::PROJECTS_FOLDER ?>"
                <?= $_[ConfigConstants::PROJECTS_FOLDER] != '' ? $off : 'disabled'; ?> >
        <span><b>.../</b></span><span class="<?= ConfigConstants::SHARED_FOLDER ?>"><?= $_[ConfigConstants::SHARED_FOLDER]; ?></span>
        <span><b>/</b></span><span class="<?= ConfigConstants::PROJECTS_FOLDER ?>"><?= $_[ConfigConstants::PROJECTS_FOLDER]; ?></span>
        <span><b>/</b></span><span><?= $l->t('YEAR'); ?></span>
        <span><b>/</b></span><span><?= $l->t('PROJECT'); ?></span>
        <span><b>/</b></span>
        <input type="hidden" id="<?= ConfigConstants::PROJECT_POSTERS_FOLDER ?>-saved" name="<?= ConfigConstants::PROJECT_POSTERS_FOLDER ?>-saved" value="<?= $_[ConfigConstants::PROJECT_POSTERS_FOLDER]; ?>" />
        <input <?= $_[ConfigConstants::PROJECT_POSTERS_FOLDER] != '' ? 'disabled' : ''; ?>
          type="text"
          id="<?= ConfigConstants::PROJECT_POSTERS_FOLDER ?>"
          name="<?= ConfigConstants::PROJECT_POSTERS_FOLDER ?>"
          placeholder="<?= $l->t('Posters Folder'); ?>"
          value="<?= $_[ConfigConstants::PROJECT_POSTERS_FOLDER]; ?>"
          title="<?= $toolTips[ConfigConstants::PROJECT_POSTERS_FOLDER]; ?>"
        />
        <input type="checkbox" id="<?= ConfigConstants::PROJECT_POSTERS_FOLDER ?>-force" name="<?= ConfigConstants::PROJECT_POSTERS_FOLDER ?>-force" class="checkbox"/>
        <label for="<?= ConfigConstants::PROJECT_POSTERS_FOLDER ?>-force" title="<?= $toolTips[ConfigConstants::PROJECT_POSTERS_FOLDER . '-force']; ?>" >
          <?= $l->t('force');?>
        </label>
        <input name="<?= ConfigConstants::PROJECT_POSTERS_FOLDER ?>-check" id="<?= ConfigConstants::PROJECT_POSTERS_FOLDER ?>-check" type="button" value="<?= $l->t('Save');?>" />
      </fieldset>
      <fieldset id="<?= ConfigConstants::PROJECT_PUBLIC_DOWNLOADS_FOLDER ?>-fieldset"
                class="<?= ConfigConstants::PROJECT_PUBLIC_DOWNLOADS_FOLDER ?> needs-<?= ConfigConstants::SHARED_FOLDER ?> needs-<?= ConfigConstants::PROJECTS_FOLDER ?>"
                <?= $_[ConfigConstants::PROJECTS_FOLDER] != '' ? $off : 'disabled'; ?> >
        <span><b>.../</b></span><span class="<?= ConfigConstants::SHARED_FOLDER ?>"><?= $_[ConfigConstants::SHARED_FOLDER]; ?></span>
        <span><b>/</b></span><span class="<?= ConfigConstants::PROJECTS_FOLDER ?>"><?= $_[ConfigConstants::PROJECTS_FOLDER]; ?></span>
        <span><b>/</b></span><span><?= $l->t('YEAR'); ?></span>
        <span><b>/</b></span><span><?= $l->t('PROJECT'); ?></span>
        <span><b>/</b></span>
        <input type="hidden" id="<?= ConfigConstants::PROJECT_PUBLIC_DOWNLOADS_FOLDER ?>-saved" name="<?= ConfigConstants::PROJECT_PUBLIC_DOWNLOADS_FOLDER ?>-saved" value="<?= $_[ConfigConstants::PROJECT_PUBLIC_DOWNLOADS_FOLDER]; ?>" />
        <input <?= $_[ConfigConstants::PROJECT_PUBLIC_DOWNLOADS_FOLDER] != '' ? 'disabled' : ''; ?>
          type="text"
          id="<?= ConfigConstants::PROJECT_PUBLIC_DOWNLOADS_FOLDER ?>"
          name="<?= ConfigConstants::PROJECT_PUBLIC_DOWNLOADS_FOLDER ?>"
          placeholder="<?= $l->t('Participants Downloads'); ?>"
          value="<?= $_[ConfigConstants::PROJECT_PUBLIC_DOWNLOADS_FOLDER]; ?>"
          title="<?= $toolTips[ConfigConstants::PROJECT_PUBLIC_DOWNLOADS_FOLDER]; ?>"
        />
        <input type="checkbox" id="<?= ConfigConstants::PROJECT_PUBLIC_DOWNLOADS_FOLDER ?>-force" name="<?= ConfigConstants::PROJECT_PUBLIC_DOWNLOADS_FOLDER ?>-force" class="checkbox"/>
        <label for="<?= ConfigConstants::PROJECT_PUBLIC_DOWNLOADS_FOLDER ?>-force" title="<?= $toolTips[ConfigConstants::PROJECT_PUBLIC_DOWNLOADS_FOLDER . '-force']; ?>" >
          <?= $l->t('force');?>
        </label>
        <input name="<?= ConfigConstants::PROJECT_PUBLIC_DOWNLOADS_FOLDER ?>-check" id="<?= ConfigConstants::PROJECT_PUBLIC_DOWNLOADS_FOLDER ?>-check" type="button" value="<?= $l->t('Save');?>" />
      </fieldset>
      <fieldset id="<?= ConfigConstants::FINANCE_FOLDER ?>-fieldset"
                class="needs-<?= ConfigConstants::SHARED_FOLDER ?>"
                <?= $_[ConfigConstants::SHARED_FOLDER] != '' ? $off : 'disabled'; ?> >
        <span><b>.../</b></span><span class="<?= ConfigConstants::SHARED_FOLDER ?>"><?= $_[ConfigConstants::SHARED_FOLDER]; ?></span><span><b>/</b></span>
        <input type="hidden" id="<?= ConfigConstants::FINANCE_FOLDER ?>-saved" name="<?= ConfigConstants::FINANCE_FOLDER ?>-saved" value="<?= $_[ConfigConstants::FINANCE_FOLDER]; ?>" />
        <input <?= $_[ConfigConstants::FINANCE_FOLDER] != '' ? 'disabled' : ''; ?>
          type="text"
          id="<?= ConfigConstants::FINANCE_FOLDER ?>"
          name="<?= ConfigConstants::FINANCE_FOLDER ?>"
          placeholder="<?= $l->t('finance folder'); ?>"
          value="<?= $_[ConfigConstants::FINANCE_FOLDER]; ?>"
          title="<?= $toolTips[ConfigConstants::FINANCE_FOLDER]; ?>"
        />
        <input type="checkbox" id="<?= ConfigConstants::FINANCE_FOLDER ?>-force" name="<?= ConfigConstants::FINANCE_FOLDER ?>-force" class="checkbox"/>
        <label for="<?= ConfigConstants::FINANCE_FOLDER ?>-force" title="<?= $toolTips[ConfigConstants::FINANCE_FOLDER . '-force']; ?>" >
          <?= $l->t('force');?>
        </label>
        <input name="<?= ConfigConstants::FINANCE_FOLDER ?>-check" id="<?= ConfigConstants::FINANCE_FOLDER ?>-check" type="button" value="<?= $l->t('Check');?>" />
      </fieldset>
      <fieldset id="<?= ConfigConstants::TRANSACTIONS_FOLDER ?>-fieldset"
                class="needs-<?= ConfigConstants::SHARED_FOLDER ?> needs-<?= ConfigConstants::PROJECTS_FOLDER ?> needs-<?= ConfigConstants::FINANCE_FOLDER ?>"
                <?= !empty($_[ConfigConstants::PROJECTS_FOLDER]) && !empty($_[ConfigConstants::FINANCE_FOLDER]) ? $off : 'disabled'; ?> >
        <span><b>.../</b></span><span class="<?= ConfigConstants::SHARED_FOLDER ?>"><?= $_[ConfigConstants::SHARED_FOLDER]; ?></span>
        <span><b>/</b></span><span class="<?= ConfigConstants::FINANCE_FOLDER ?>"><?= $_[ConfigConstants::FINANCE_FOLDER];?></span>
        <span><b>/</b></span>
        <input type="hidden" id="<?= ConfigConstants::TRANSACTIONS_FOLDER ?>-saved" name="<?= ConfigConstants::TRANSACTIONS_FOLDER ?>-saved" value="<?= $_[ConfigConstants::TRANSACTIONS_FOLDER]; ?>" />
        <input <?= $_[ConfigConstants::TRANSACTIONS_FOLDER] != '' ? 'disabled' : ''; ?>
          type="text"
          id="<?= ConfigConstants::TRANSACTIONS_FOLDER ?>"
          name="<?= ConfigConstants::TRANSACTIONS_FOLDER ?>"
          placeholder="<?= $l->t('transactions'); ?>"
          value="<?= $_[ConfigConstants::TRANSACTIONS_FOLDER]; ?>"
          title="<?= $toolTips[ConfigConstants::TRANSACTIONS_FOLDER]; ?>"
        />
        <input type="checkbox" id="<?= ConfigConstants::TRANSACTIONS_FOLDER ?>-force" name="<?= ConfigConstants::TRANSACTIONS_FOLDER ?>-force" class="checkbox"/>
        <label for="<?= ConfigConstants::TRANSACTIONS_FOLDER ?>-force" title="<?= $toolTips[ConfigConstants::TRANSACTIONS_FOLDER . '-force']; ?>" >
          <?= $l->t('force');?>
        </label>
        <input name="<?= ConfigConstants::TRANSACTIONS_FOLDER ?>-check" id="<?= ConfigConstants::TRANSACTIONS_FOLDER ?>-check" type="button" value="<?= $l->t('Check');?>" />
      </fieldset>
      <fieldset id="<?= ConfigConstants::BALANCES_FOLDER ?>-fieldset"
                class="needs-<?= ConfigConstants::SHARED_FOLDER ?> needs-<?= ConfigConstants::PROJECTS_FOLDER ?> needs-<?= ConfigConstants::FINANCE_FOLDER ?>"
                <?= !empty($_[ConfigConstants::PROJECTS_FOLDER]) && !empty($_[ConfigConstants::FINANCE_FOLDER]) ? $off : 'disabled'; ?>
      >
        <span><b>.../</b></span><span class="<?= ConfigConstants::SHARED_FOLDER ?>"><?= $_[ConfigConstants::SHARED_FOLDER]; ?></span>
        <span><b>/</b></span><span class="<?= ConfigConstants::FINANCE_FOLDER ?>"><?= $_[ConfigConstants::FINANCE_FOLDER];?></span>
        <span><b>/</b></span>
        <input type="hidden" id="<?= ConfigConstants::BALANCES_FOLDER ?>-saved" name="<?= ConfigConstants::BALANCES_FOLDER ?>-saved" value="<?= $_[ConfigConstants::BALANCES_FOLDER]; ?>" />
        <input <?= $_[ConfigConstants::BALANCES_FOLDER] != '' ? 'disabled' : ''; ?>
          type="text"
          id="<?= ConfigConstants::BALANCES_FOLDER ?>"
          name="<?= ConfigConstants::BALANCES_FOLDER ?>"
          placeholder="<?= $l->t('balances'); ?>"
          value="<?= $_[ConfigConstants::BALANCES_FOLDER]; ?>"
          title="<?= $toolTips[ConfigConstants::BALANCES_FOLDER]; ?>"
        />
        <span><b>/</b></span><span class="<?= ConfigConstants::PROJECTS_FOLDER ?>"><?= $_[ConfigConstants::PROJECTS_FOLDER];?></span>
        <span><b>/</b></span><span><?= $l->t('YEAR'); ?></span>
        <span><b>/</b></span><span><?= $l->t('PROJECT'); ?></span><span><b>/</b></span>
        <input type="checkbox" id="<?= ConfigConstants::BALANCES_FOLDER ?>-force" name="<?= ConfigConstants::BALANCES_FOLDER ?>-force" class="checkbox" />
        <label for="<?= ConfigConstants::BALANCES_FOLDER ?>-force" title="<?= $toolTips[ConfigConstants::BALANCES_FOLDER . '-force']; ?>">
          <?= $l->t('force');?>
        </label>
        <input name="<?= ConfigConstants::BALANCES_FOLDER ?>-check" id="<?= ConfigConstants::BALANCES_FOLDER ?>-check" type="button" value="<?= $l->t('Check');?>" />
      </fieldset>
    </form>
    <!-- Cloud-User Connector -->
    <h4><?php p($l->t('Members as Cloud-Users')); ?></h4>
    <form id="cloud-user-form"
          class="cloud-user">
      <fieldset id="user-sql-fieldset"
                class="user-sql"
                <?php ($cloudUserRequirements['status'] != CloudUserConnectorService::REQUIREMENTS_OK) && p('disabled'); ?>
      >
        <?php $cloudUserBackend = CloudUserConnectorService::CLOUD_USER_BACKEND; ?>
        <legend class="user-sql">
          <?php p($l->t('Import club-members as cloud-user-accounts into the cloud')); ?>
        </legend>
        <div>
          <input id="user-sql-backend-checkbox"
                 <?php $importClubMembersAsCloudUsers && p('checked'); ?>
                 name="importClubMembersAsCloudUsers"
                 type="checkbox"
                 class="checkbox user-sql"/>
          <label for="user-sql-backend-checkbox"
                 title="<?php p($toolTips['settings:personal:sharing:user-sql:enable']); ?>">
            <?php p($l->t('Generate database-views for the "%s" cloud user-backend', $cloudUserBackend)); ?>
          </label>
          <?php $link = 'https://apps.nextcloud.com/apps/' . $cloudUserBackend; ?>
          (<a target="<?php p(\md5($link)); ?>"
             href="<?php p($link); ?>"
             class="external info"
          >
            <?php p($l->t('%s info', $cloudUserBackend)); ?>
          </a>)
          <div class="show-if-user-sql-backend info<?php empty($importClubMembersAsCloudUsers) && p(' hidden'); ?>">
            <?php p($l->t('Please log in as administrator and configure the "%1$s"-user-backend.', $cloudUserBackend)); ?>
          </div>
        </div>
        <div class="enable-if-user-sql-backend flex-container flex-center flex-wrap">
          <input id="user-sql-separate-database-checkbox"
                 type="checkbox"
                 class="checkbox user-sql separate-database"
                 <?php !empty($cloudUserViewsDatabase) && p('checked'); ?>
                 <?php empty($importClubMembersAsCloudUsers) && p('disabled'); ?>
          />
          <label for="user-sql-separate-database-checkbox"
                 title="<?php p($toolTips['settings:personal:sharing:user-sql:separate-database']); ?>">
            <span class="hide-if-user-sql-separate-database">
              <?php p($l->t('Use a separate dedicated database for the SQL-views.')); ?>
            </span>
            <span class="show-if-user-sql-separate-database">
              <?php p($l->t('Views-Database')); ?>
            </span>
          </label>
          <input type="text"
                 placeholder="<?php p($l->t('Databasename')); ?>"
                 class="show-if-user-sql-separate-database flex-grow"
                 name="cloudUserViewsDatabase"
                 value="<?php p($cloudUserViewsDatabase); ?>"
                 <?php empty($importClubMembersAsCloudUsers) && p('disabled'); ?>
          />
          <div class="flex-wrapper"></div>
          <div class="show-if-user-sql-separate-database show-if-user-sql-backend info<?php empty($importClubMembersAsCloudUsers) && p(' hidden'); ?>">
            <?php p($l->t('Please make sure the data-base user "%1$s@%2$s" has all -- and in particluar: GRANT -- privileges on the dedicated database.', [ $dbuser, $dbserver ])); ?>
          </div>
        </div>
        <div class="enable-if-user-sql-backend flex-container flex-center">
          <input id="user-sql-recreate-views-button"
                 class="user-sql recreate-views"
                 <?php empty($importClubMembersAsCloudUsers) && p('disabled'); ?>
                 type="button"
                 name="userSqlBackendRecreateViews"
                 value="<?php p($l->t('Recreate')); ?>"
                 title="<?php p($toolTips['settings:personal:sharing:user-sql:recreate-views']); ?>"
          />
          <label for="user-sql-recreate-views-button"
                 title="<?php p($toolTips['settings:personal:sharing:user-sql:recreate-views']); ?>">
            <?php p($l->t('Recreate the database-views for the "%1$s"-user-backend.', $cloudUserBackend)); ?>
          </label>
        </div>
      </fieldset>
      <fieldset class="cloud-user-connector personalized-views">
        <legend class="cloud-user-connector personalized-views">
          <?php p($l->t('Give club-members access to their personal data')); ?>
        </legend>
        <div class="enable-if-user-sql-backend">
          <input id="personalized-views-checkbox"
                 <?php $musicianPersonalizedViews && p('checked'); ?>
                 name="musicianPersonalizedViews"
                 type="checkbox"
                 class="checkbox personalized-views"/>
          <label for="personalized-views-checkbox"
                 title="<?php p($toolTips['settings:personal:sharing:personalized-views']); ?>">
            <?php p($l->t('Generate personalized single-row database-views')); ?>
          </label>
        </div>
        <div class="enable-if-user-sql-backend enable-if-personalized-views flex-container flex-center">
          <input id="personalized-views-recreate-views-button"
                 class="personalized-views recreate-views"
                 <?php empty($musicianPersonalizedViews) && p('disabled'); ?>
                 type="button"
                 name="musicianPersonalizedViewsRecreateViews"
                 value="<?php p($l->t('Recreate')); ?>"
                 title="<?php p($toolTips['settings:personal:sharing:personalized-views:recreate-views']); ?>"
          />
          <label for="personalized-views-recreate-views-button"
                 title="<?php p($toolTips['settings:personal:sharing:personalized-views:recreate-views']); ?>">
            <?php p($l->t('Recreate the personalized single-row database-views')); ?>
          </label>
        </div>
      </fieldset>
      <fieldset class="cloud-user-connector hints<?php !$importClubMembersAsCloudUsers && p(' hidden'); ?>">
        <legend class="cloud-user-connector hints">
          <?php p($l->t('Hints')); ?>
        </legend>
        <div class="cloud-user hints">
          <?php if (!empty($importClubMembersAsCloudUsers)) {
            foreach (($cloudUserRequirements['hints']??[]) as $hint) {
              ?>
          <div class="cloud-user hint"><?php p($hint); ?></div>
              <?php
            }
          }
          ?>
        </div>
      </fieldset>
    </form>
  </div>
  <span class="statusmessage sharing-settings"></span><span>&nbsp;</span>
</div>
