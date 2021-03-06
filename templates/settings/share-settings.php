<?php
/**
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

$alloff = $_['orchestra'] == '' ? $alloff = 'disabled' : '';
$off = $_['shareowner'] == '' ? 'disabled' : $alloff;

?>
<div id="tabs-<?php echo $_['tabNr']; ?>" class="personalblock admin sharing">
<!-- SHARED CALENDARS and stuff -->
  <div id="sharing-settings">
    <!-- VIRTUAL USER -->
    <h4><?php echo $l->t('Share owner') ; ?></h4>
    <form id="shareownerform"><!-- <legend><?php echo $l->t('Share owner') ; ?></legend> -->
      <fieldset id="shareowner" <?php echo $alloff; ?> >
        <input type="hidden" id="user-saved" name="shareowner-saved" value="<?php echo $_['shareowner']; ?>" />
        <input <?php echo $_['shareowner'] != '' ? 'disabled' : ''; ?> type="text" id="user" name="shareowner" placeholder="<?php echo $l->t('shareowner');?>" value="<?php echo $_['shareowner']; ?>" />
        <input type="checkbox" id="shareowner-force" name="shareowner-force" class="checkbox"/>
           <label for="shareowner-force" title="<?php echo $toolTips['shareowner-force']; ?>"  class="tooltip-auto">
             <?php echo $l->t('force');?>
           </label>
        <input name="shareownercheck" id="check" type="button" value="<?php echo $l->t('Check');?>" <?php echo $off; ?> />
      </fieldset>
<!-- CHANGE ITS PASSWORD -->
      <fieldset class="shareownerpassword" <?php echo $off; ?> >
        <input type="password" id="shareownerpassword" class="randompassword" name="shareownerpassword" placeholder="<?php echo $l->t('Share-Password');?>" data-typetoggle="#shareownerpassword-show" />
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
      <fieldset  <?php echo $off; ?> ><!-- <legend><?php echo $l->t('Calendars'); ?></legend> -->
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
      <fieldset  <?php echo $off; ?> ><!-- <legend><?php echo $l->t('Contacts'); ?></legend> -->
        <input type="text" id="generaladdressbook" name="generaladdressbook" placeholder="<?php echo $l->t('addressbook');?>" value="<?php echo $_['generaladdressbook']; ?>" />
        <label for="generaladdressbook"><?php echo $l->t('General Addresbook');?></label>
        <br/>
        <input type="text" id="musiciansaddressbook" name="musiciansaddressbook" placeholder="<?php echo $l->t('addressbook');?>" value="<?php echo $_['musiciansaddressbook']; ?>" />
        <label for="musiciansaddressbook"><?php echo $l->t('Addresbook for Musicians');?></label>
      </fieldset>
    </form>
    <!-- Shared folders -->
    <h4><?php echo $l->t('Shared folder'); ?></h4>
    <form id="sharedfolder-form">
      <fieldset id="sharedfolder-fieldset" <?php echo $off; ?> ><!-- <legend><?php echo $l->t('Shared folder'); ?></legend> -->
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
           class="sharedfolder-view button<?php empty($sharedFolderLink) && p(' hidden'); ?>"></a>
      </fieldset>
      <fieldset id="documenttemplatesfolder-fieldset" <?php echo $_['projectsfolder'] != '' ? $off : 'disabled'; ?> >
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
      <fieldset id="projectsfolder-fieldset" <?php echo $off; ?> >
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
      <fieldset id="projectparticipantsfolder-fieldset" class="projectparticipantsfolder" <?php echo $off; ?> >
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
      <fieldset id="projectsbalancefolder-fieldset" <?php echo $_['projectsfolder'] != '' ? $off : 'disabled'; ?> >
        <span><b>.../</b></span><span class="sharedfolder"><?php echo $_['sharedfolder']; ?></span><span><b>/</b></span>
        <input type="hidden" id="projectsbalancefolder-saved" name="projectsbalancefolder-saved" value="<?php echo $_['projectsbalancefolder']; ?>" />
        <input <?php echo $_['projectsbalancefolder'] != '' ? 'disabled' : ''; ?>
          type="text"
          id="projectsbalancefolder"
          name="projectsbalancefolder"
          placeholder="<?php echo $l->t('Financial balance folder'); ?>"
          value="<?php echo $_['projectsbalancefolder']; ?>"
          title="<?php echo $toolTips['projectsbalancefolder']; ?>"
        />
        <span><b>/</b></span><span class="projectsfolder"><?php echo $_['projectsfolder'];?></span>
        <span><b>/</b></span><span><?php echo $l->t('YEAR'); ?></span>
        <span><b>/</b></span><span><?php echo $l->t('PROJECT'); ?></span><span><b>/</b></span>
        <input type="checkbox" id="projectsbalancefolder-force" name="projectsbalancefolder-force" class="checkbox"/>
        <label for="projectsbalancefolder-force" title="<?php echo $toolTips['projectsbalancefolder-force']; ?>" >
          <?php echo $l->t('force');?>
        </label>
        <input name="projectsbalancefolder-check" id="projectsbalancefolder-check" type="button" value="<?php echo $l->t('Check');?>" />
      </fieldset>
    </form>
  </div>
  <span class="statusmessage sharing-settings"></span><span>&nbsp;</span>
</div>
