<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2020, 2021, 2022, 2023, 2024 Claus-Justus Heine
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

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Service\CloudUserConnectorService;

$alloff = $_['orchestra'] == '' ? $alloff = 'disabled' : '';
$off = $_['shareowner'] == '' ? 'disabled' : $alloff;

?>
<div id="tabs-<?php echo $_['tabNr']; ?>" class="personalblock admin sharing">
<!-- SHARED CALENDARS and stuff -->
  <div id="sharing-settings">
    <!-- VIRTUAL USER -->
    <h4><?php echo $l->t('Share owner') ; ?></h4>
    <form id="shareownerform">
      <fieldset id="shareowner" <?php echo $alloff; ?> >
        <input type="hidden" id="user-saved" name="shareowner-saved" value="<?php echo $_['shareowner']; ?>" />
        <input type="text"
               id="user"
               name="shareowner"
               placeholder="<?php echo $l->t('shareowner');?>"
               value="<?php echo $_['shareowner']; ?>"
               <?php echo $_['shareowner'] != '' ? 'disabled' : '';?>
               autocomplete="username"
        />
        <input type="checkbox" id="shareowner-force" name="shareowner-force" class="checkbox"/>
           <label for="shareowner-force" title="<?php echo $toolTips['shareowner-force']; ?>"  class="tooltip-auto">
             <?php echo $l->t('force');?>
           </label>
        <input name="shareownercheck" id="check" type="button" value="<?php echo $l->t('Check');?>" <?php echo $off; ?> />
      </fieldset>
<!-- CHANGE ITS PASSWORD -->
      <fieldset class="shareownerpassword" <?php echo $off; ?> >
        <input type="password"
               id="shareownerpassword"
               class="randompassword"
               name="shareownerpassword"
               placeholder="<?php echo $l->t('Share-Password');?>"
               data-typetoggle="#shareownerpassword-show"
               autocomplete="current-password"
        />
        <input class="cafevdb-password-show" type="checkbox" id="shareownerpassword-show" name="shareownerpassword-show" />
        <label class="cafevdb-password-show" for="shareownerpassword-show"><?php echo $l->t('show');?></label>
        <input name="passwordgenerate" id="generate" type="button" value="<?php echo $l->t('Generate');?>" />
        <input name="passwordchange" id="change" type="button" value="<?php echo $l->t('Change');?>" />
      </fieldset>
      <div>
        <span class="statusmessage"></span><span>&nbsp;</span>
      </div>
    </form>
    <!-- CALENDARS -->
    <h4><?php echo $l->t('Calendars'); ?></h4>
    <form id="calendars">
      <fieldset  <?php echo $off; ?> >
        <input type="text" id="concerts" name="concertscalendar" placeholder="<?php echo $l->t('calendarname');?>" value="<?php echo $_['concertscalendar']; ?>" />
        <label for="concerts"><?php echo $l->t('Calendar for Concerts');?></label>
        <br/>
        <input type="text" id="rehearsals" name="rehearsalscalendar" placeholder="<?php echo $l->t('calendarname');?>" value="<?php echo $_['rehearsalscalendar']; ?>" />
        <label for="rehearsals"><?php echo $l->t('Calendar for Rehearsals');?></label>
        <br/>
        <input type="text" id="other" name="othercalendar" placeholder="<?php echo $l->t('calendarname');?>" value="<?php echo $_['othercalendar']; ?>" />
        <label for="other"><?php echo $l->t('Calendar for other Events');?></label>
        <br/>
        <input type="text" id="management" name="managementcalendar" placeholder="<?php echo $l->t('calendarname');?>" value="<?php echo $_['managementcalendar']; ?>" />
        <label for="management"><?php echo $l->t('Management-Calendar');?></label>
        <br/>
        <input type="text" id="finance" name="financecalendar" placeholder="<?php echo $l->t('calendarname');?>" value="<?php echo $_['financecalendar']; ?>" />
        <label for="finance"><?php echo $l->t('Finance-Calendar');?></label>
        <br/>
<!-- DEFAULT DURATION FOR EVENTS -->
        <input type="number" min="0" id="duration" name="eventduration" placeholder="<?php echo $l->t('#Minutes');?>" value="<?php echo $_['eventduration']; ?>" />
        <label for="duration"><?php echo $l->t('Default Duration for Events');?></label>
      </fieldset>
    </form>
    <!-- Contacts, adressbooks -->
    <h4><?php echo $l->t('Contacts'); ?></h4>
    <form id="contacts">
      <fieldset  <?php echo $off; ?> >
        <input type="text" id="generaladdressbook" name="generaladdressbook" placeholder="<?php echo $l->t('addressbook');?>" value="<?php echo $_['generaladdressbook']; ?>" />
        <label for="generaladdressbook"><?php echo $l->t('General Addresbook');?></label>
        <br/>
        <input type="text" id="musiciansaddressbook" name="musiciansaddressbook" placeholder="<?php echo $l->t('addressbook');?>" value="<?php echo $_['musiciansaddressbook']; ?>" />
        <label for="musiciansaddressbook"><?php echo $l->t('Addresbook for Musicians');?></label>
      </fieldset>
    </form>
    <!-- Shared folders -->
    <h4><?php echo $l->t('Shared folder'); ?></h4>
    <form id="sharedfolder-form" class="sharedfolders">
      <fieldset id="sharedfolder-fieldset" <?php echo $off; ?> >
        <input type="hidden" id="sharedfolder-saved" name="sharedfolder-saved" value="<?php echo $_['sharedfolder']; ?>" />
        <input <?php echo $_['sharedfolder'] != '' ? 'disabled' : ''; ?>
          type="text"
          id="sharedfolder"
          name="sharedfolder"
          placeholder="<?php echo $l->t('shared folder'); ?>"
          value="<?php echo $_['sharedfolder']; ?>"
          title="<?php echo $toolTips['sharedfolder']; ?>"
        />
        <input type="checkbox" id="sharedfolder-force" name="sharedfolder-force" class="checkbox"/>
        <label for="sharedfolder-force" title="<?php echo $toolTips['sharedfolder-force']; ?>" >
          <?php echo $l->t('force');?>
        </label>
        <input name="sharedfolder-check" id="sharedfolder-check" type="button" value="<?php echo $l->t('Check');?>" />
        <a name="sharedfolder-view"
           href="<?php p($sharedFolderLink); ?>"
           target="<?php p($appName . '-sharedfolder-view'); ?>"
           class="sharedfolder-view button<?php empty($_['sharedfolder']) && p(' really hidden'); ?>"></a>
      </fieldset>
      <fieldset id="postboxfolder-fieldset"
                class="needs-sharedfolder"
                <?php echo $_['sharedfolder'] != '' ? $off : 'disabled'; ?> >
        <span><b>.../</b></span><span class="sharedfolder"><?php echo $_['sharedfolder']; ?></span><span><b>/</b></span>
        <input type="hidden" id="postboxfolder-saved" name="postboxfolder-saved" value="<?php echo $_['postboxfolder']; ?>" />
        <input <?php echo $_['postboxfolder'] != '' ? 'disabled' : ''; ?>
          type="text"
          id="postboxfolder"
          name="postboxfolder"
          placeholder="<?php echo $l->t('Postbox-Folder'); ?>"
          value="<?php echo $_['postboxfolder']; ?>"
          title="<?php echo $toolTips['postboxfolder']; ?>"
        />
        <input type="checkbox" id="postboxfolder-force" name="postboxfolder-force" class="checkbox"/>
        <label for="postboxfolder-force" title="<?php echo $toolTips['postboxfolder-force']; ?>" >
          <?php echo $l->t('force');?>
        </label>
        <input name="postboxfolder-check" id="postboxfolder-check" type="button" value="<?php echo $l->t('Check');?>" />
        <div class="postboxfolder-sharelink<?php empty($postboxFolderShareLink) && p(' hidden'); ?>"><?php p($postboxFolderShareLink); ?></div>
      </fieldset>
      <fieldset id="outboxfolder-fieldset"
                class="needs-sharedfolder"
                <?php echo $_['sharedfolder'] != '' ? $off : 'disabled'; ?> >
        <span><b>.../</b></span><span class="sharedfolder"><?php echo $_['sharedfolder']; ?></span><span><b>/</b></span>
        <input type="hidden" id="outboxfolder-saved" name="outboxfolder-saved" value="<?php echo $_['outboxfolder']; ?>" />
        <input <?php echo $_['outboxfolder'] != '' ? 'disabled' : ''; ?>
          type="text"
          id="outboxfolder"
          name="outboxfolder"
          placeholder="<?php echo $l->t('Outbox-Folder'); ?>"
          value="<?php echo $_['outboxfolder']; ?>"
          title="<?php echo $toolTips['outboxfolder']; ?>"
        />
        <input type="checkbox" id="outboxfolder-force" name="outboxfolder-force" class="checkbox"/>
        <label for="outboxfolder-force" title="<?php echo $toolTips['outboxfolder-force']; ?>" >
          <?php echo $l->t('force');?>
        </label>
        <input name="outboxfolder-check" id="outboxfolder-check" type="button" value="<?php echo $l->t('Check');?>" />
      </fieldset>
      <fieldset id="documenttemplatesfolder-fieldset"
                class="needs-sharedfolder"
                <?php echo $_['sharedfolder'] != '' ? $off : 'disabled'; ?> >
        <span><b>.../</b></span><span class="sharedfolder"><?php echo $_['sharedfolder']; ?></span><span><b>/</b></span>
        <input type="hidden" id="documenttemplatesfolder-saved" name="documenttemplatesfolder-saved" value="<?php echo $_['documenttemplatesfolder']; ?>" />
        <input <?php echo $_['documenttemplatesfolder'] != '' ? 'disabled' : ''; ?>
          type="text"
          id="documenttemplatesfolder"
          name="documenttemplatesfolder"
          placeholder="<?php echo $l->t('Document templates folder'); ?>"
          value="<?php echo $_['documenttemplatesfolder']; ?>"
          title="<?php echo $toolTips['documenttemplatesfolder']; ?>"
        />
        <input type="checkbox" id="documenttemplatesfolder-force" name="documenttemplatesfolder-force" class="checkbox"/>
        <label for="documenttemplatesfolder-force" title="<?php echo $toolTips['documenttemplatesfolder-force']; ?>" >
          <?php echo $l->t('force');?>
        </label>
        <input name="documenttemplatesfolder-check" id="documenttemplatesfolder-check" type="button" value="<?php echo $l->t('Check');?>" />
      </fieldset>
      <fieldset id="projectsfolder-fieldset"
                class="needs-sharedfolder"
                <?php echo $_['sharedfolder'] != '' ? $off : 'disabled'; ?> >
        <span><b>.../</b></span><span class="sharedfolder"><?php echo $_['sharedfolder']; ?></span>
        <span><b>/</b></span>
        <input type="hidden" id="projectsfolder-saved" name="projectsfolder-saved" value="<?php echo $_['projectsfolder']; ?>" />
        <input <?php echo $_['projectsfolder'] != '' ? 'disabled' : ''; ?>
          type="text"
          id="projectsfolder"
          name="projectsfolder"
          placeholder="<?php echo $l->t('Project folder'); ?>"
          value="<?php echo $_['projectsfolder']; ?>"
          title="<?php echo $toolTips['projectsfolder']; ?>"
        />
        <span><b>/</b></span><span><?php echo $l->t('YEAR'); ?></span>
        <span><b>/</b></span><span><?php echo $l->t('PROJECT'); ?></span><span><b>/</b></span>
        <input type="checkbox" id="projectsfolder-force" name="projectsfolder-force" class="checkbox"/>
        <label for="projectsfolder-force" title="<?php echo $toolTips['projectsfolder-force']; ?>" >
          <?php echo $l->t('force');?>
        </label>
        <input name="projectsfolder-check" id="projectsfolder-check" type="button" value="<?php echo $l->t('Check');?>" />
      </fieldset>
      <fieldset id="projectparticipantsfolder-fieldset"
                class="needs-sharedfolder needs-projectsfolder"
                class="projectparticipantsfolder" <?php echo $_['projectsfolder'] != '' ? $off : 'disabled'; ?> >
        <span><b>.../</b></span><span class="sharedfolder"><?php echo $_['sharedfolder']; ?></span>
        <span><b>/</b></span><span class="projectsfolder"><?php echo $_['projectsfolder']; ?></span>
        <span><b>/</b></span><span><?php echo $l->t('YEAR'); ?></span>
        <span><b>/</b></span><span><?php echo $l->t('PROJECT'); ?></span>
        <span><b>/</b></span>
        <input type="hidden" id="projectparticipantsfolder-saved" name="projectparticipantsfolder-saved" value="<?php echo $_['projectparticipantsfolder']; ?>" />
        <input <?php echo $_['projectparticipantsfolder'] != '' ? 'disabled' : ''; ?>
          type="text"
          id="projectparticipantsfolder"
          name="projectparticipantsfolder"
          placeholder="<?php echo $l->t('Participants Folder'); ?>"
          value="<?php echo $_['projectparticipantsfolder']; ?>"
          title="<?php echo $toolTips['projectparticipantsfolder']; ?>"
        />
        <span><b>/</b></span><span><?php echo $l->t('john.doe'); ?></span><span><b>/</b></span>
        <input type="checkbox" id="projectparticipantsfolder-force" name="projectparticipantsfolder-force" class="checkbox"/>
        <label for="projectparticipantsfolder-force" title="<?php echo $toolTips['projectparticipantsfolder-force']; ?>" >
          <?php echo $l->t('force');?>
        </label>
        <input name="projectparticipantsfolder-check" id="projectparticipantsfolder-check" type="button" value="<?php echo $l->t('Save');?>" />
      </fieldset>
      <fieldset id="projectpostersfolder-fieldset"
                class="projectpostersfolder needs-sharedfolder needs-projectsfolder"
                <?php echo $_['projectsfolder'] != '' ? $off : 'disabled'; ?> >
        <span><b>.../</b></span><span class="sharedfolder"><?php echo $_['sharedfolder']; ?></span>
        <span><b>/</b></span><span class="projectsfolder"><?php echo $_['projectsfolder']; ?></span>
        <span><b>/</b></span><span><?php echo $l->t('YEAR'); ?></span>
        <span><b>/</b></span><span><?php echo $l->t('PROJECT'); ?></span>
        <span><b>/</b></span>
        <input type="hidden" id="projectpostersfolder-saved" name="projectpostersfolder-saved" value="<?php echo $_['projectpostersfolder']; ?>" />
        <input <?php echo $_['projectpostersfolder'] != '' ? 'disabled' : ''; ?>
          type="text"
          id="projectpostersfolder"
          name="projectpostersfolder"
          placeholder="<?php echo $l->t('Posters Folder'); ?>"
          value="<?php echo $_['projectpostersfolder']; ?>"
          title="<?php echo $toolTips['projectpostersfolder']; ?>"
        />
        <input type="checkbox" id="projectpostersfolder-force" name="projectpostersfolder-force" class="checkbox"/>
        <label for="projectpostersfolder-force" title="<?php echo $toolTips['projectpostersfolder-force']; ?>" >
          <?php echo $l->t('force');?>
        </label>
        <input name="projectpostersfolder-check" id="projectpostersfolder-check" type="button" value="<?php echo $l->t('Save');?>" />
      </fieldset>
      <fieldset id="projectpublicdownloadsfolder-fieldset"
                class="projectpublicdownloadsfolder needs-sharedfolder needs-projectsfolder"
                <?php echo $_['projectsfolder'] != '' ? $off : 'disabled'; ?> >
        <span><b>.../</b></span><span class="sharedfolder"><?php echo $_['sharedfolder']; ?></span>
        <span><b>/</b></span><span class="projectsfolder"><?php echo $_['projectsfolder']; ?></span>
        <span><b>/</b></span><span><?php echo $l->t('YEAR'); ?></span>
        <span><b>/</b></span><span><?php echo $l->t('PROJECT'); ?></span>
        <span><b>/</b></span>
        <input type="hidden" id="projectpublicdownloadsfolder-saved" name="projectpublicdownloadsfolder-saved" value="<?php echo $_['projectpublicdownloadsfolder']; ?>" />
        <input <?php echo $_['projectpublicdownloadsfolder'] != '' ? 'disabled' : ''; ?>
          type="text"
          id="projectpublicdownloadsfolder"
          name="projectpublicdownloadsfolder"
          placeholder="<?php echo $l->t('Participants Downloads'); ?>"
          value="<?php echo $_['projectpublicdownloadsfolder']; ?>"
          title="<?php echo $toolTips['projectpublicdownloadsfolder']; ?>"
        />
        <input type="checkbox" id="projectpublicdownloadsfolder-force" name="projectpublicdownloadsfolder-force" class="checkbox"/>
        <label for="projectpublicdownloadsfolder-force" title="<?php echo $toolTips['projectpublicdownloadsfolder-force']; ?>" >
          <?php echo $l->t('force');?>
        </label>
        <input name="projectpublicdownloadsfolder-check" id="projectpublicdownloadsfolder-check" type="button" value="<?php echo $l->t('Save');?>" />
      </fieldset>
      <fieldset id="financefolder-fieldset"
                class="needs-sharedfolder"
                <?php echo $_['sharedfolder'] != '' ? $off : 'disabled'; ?> >
        <span><b>.../</b></span><span class="sharedfolder"><?php echo $_['sharedfolder']; ?></span><span><b>/</b></span>
        <input type="hidden" id="financefolder-saved" name="financefolder-saved" value="<?php echo $_['financefolder']; ?>" />
        <input <?php echo $_['financefolder'] != '' ? 'disabled' : ''; ?>
          type="text"
          id="financefolder"
          name="financefolder"
          placeholder="<?php echo $l->t('finance folder'); ?>"
          value="<?php echo $_['financefolder']; ?>"
          title="<?php echo $toolTips['financefolder']; ?>"
        />
        <input type="checkbox" id="financefolder-force" name="financefolder-force" class="checkbox"/>
        <label for="financefolder-force" title="<?php echo $toolTips['financefolder-force']; ?>" >
          <?php echo $l->t('force');?>
        </label>
        <input name="financefolder-check" id="financefolder-check" type="button" value="<?php echo $l->t('Check');?>" />
      </fieldset>
      <fieldset id="transactionsfolder-fieldset"
                class="needs-sharedfolder needs-projectsfolder needs-financefolder"
                <?php echo !empty($_['projectsfolder']) && !empty($_['financefolder']) ? $off : 'disabled'; ?> >
        <span><b>.../</b></span><span class="sharedfolder"><?php echo $_['sharedfolder']; ?></span>
        <span><b>/</b></span><span class="financefolder"><?php echo $_['financefolder'];?></span>
        <span><b>/</b></span>
        <input type="hidden" id="transactionsfolder-saved" name="transactionsfolder-saved" value="<?php echo $_['transactionsfolder']; ?>" />
        <input <?php echo $_['transactionsfolder'] != '' ? 'disabled' : ''; ?>
          type="text"
          id="transactionsfolder"
          name="transactionsfolder"
          placeholder="<?php echo $l->t('transactions'); ?>"
          value="<?php echo $_['transactionsfolder']; ?>"
          title="<?php echo $toolTips['transactionsfolder']; ?>"
        />
        <input type="checkbox" id="transactionsfolder-force" name="transactionsfolder-force" class="checkbox"/>
        <label for="transactionsfolder-force" title="<?php echo $toolTips['transactionsfolder-force']; ?>" >
          <?php echo $l->t('force');?>
        </label>
        <input name="transactionsfolder-check" id="transactionsfolder-check" type="button" value="<?php echo $l->t('Check');?>" />
      </fieldset>
      <fieldset id="balancesfolder-fieldset"
                class="needs-sharedfolder needs-projectsfolder needs-financefolder"
                <?php echo !empty($_['projectsfolder']) && !empty($_['financefolder']) ? $off : 'disabled'; ?>
      >
        <span><b>.../</b></span><span class="sharedfolder"><?php echo $_['sharedfolder']; ?></span>
        <span><b>/</b></span><span class="financefolder"><?php echo $_['financefolder'];?></span>
        <span><b>/</b></span>
        <input type="hidden" id="balancesfolder-saved" name="balancesfolder-saved" value="<?php echo $_['balancesfolder']; ?>" />
        <input <?php echo $_['balancesfolder'] != '' ? 'disabled' : ''; ?>
          type="text"
          id="balancesfolder"
          name="balancesfolder"
          placeholder="<?php echo $l->t('balances'); ?>"
          value="<?php echo $_['balancesfolder']; ?>"
          title="<?php echo $toolTips['balancesfolder']; ?>"
        />
        <span><b>/</b></span><span class="projectsfolder"><?php echo $_['projectsfolder'];?></span>
        <span><b>/</b></span><span><?php echo $l->t('YEAR'); ?></span>
        <span><b>/</b></span><span><?php echo $l->t('PROJECT'); ?></span><span><b>/</b></span>
        <input type="checkbox" id="balancesfolder-force" name="balancesfolder-force" class="checkbox" />
        <label for="balancesfolder-force" title="<?php echo $toolTips['balancesfolder-force']; ?>">
          <?php echo $l->t('force');?>
        </label>
        <input name="balancesfolder-check" id="balancesfolder-check" type="button" value="<?php echo $l->t('Check');?>" />
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
