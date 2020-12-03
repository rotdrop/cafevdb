<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

$alloff = $_['orchestra'] == '' ? $alloff = 'disabled="disabled"' : '';
$off = $_['shareowner'] == '' ? 'disabled="disabled"' : $alloff;

?>
<div id="tabs-<?php echo $_['tabNr']; ?>" class="personalblock admin sharing">
<!-- SHARED CALENDARS and stuff -->
  <div id="sharing-settings">
<!-- VIRTUAL USER -->
    <form id="shareownerform"><legend><?php echo $l->t('Share owner') ; ?></legend>
      <fieldset id="shareowner" <?php echo $alloff; ?> >
        <input type="hidden" id="user-saved" name="shareowner-saved" value="<?php echo $_['shareowner']; ?>" />
        <input <?php echo $_['shareowner'] != '' ? 'disabled="disabled"' : ''; ?> type="text" id="user" name="shareowner" placeholder="<?php echo $l->t('shareowner');?>" value="<?php echo $_['shareowner']; ?>" />
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
    <form id="calendars">
      <fieldset  <?php echo $off; ?> ><legend><?php echo $l->t('Calendars'); ?></legend>
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
    <form id="contacts">
      <fieldset  <?php echo $off; ?> ><legend><?php echo $l->t('Contacts'); ?></legend>
        <input type="text" id="generaladdressbook" name="generaladdressbook" placeholder="<?php echo $l->t('addressbook');?>" value="<?php echo $_['generaladdressbook']; ?>" />
        <label for="generaladdressbook"><?php echo $l->t('General Addresbook');?></label>
        <br/>
        <input type="text" id="musiciansaddressbook" name="musiciansaddressbook" placeholder="<?php echo $l->t('addressbook');?>" value="<?php echo $_['musiciansaddressbook']; ?>" />
        <label for="musiciansaddressbook"><?php echo $l->t('Addresbook for Musicians');?></label>
      </fieldset>
    </form>
<!-- Shared folders -->
    <form id="sharedfolder-form">
      <fieldset <?php echo $off; ?> ><legend><?php echo $l->t('Shared folder'); ?></legend>
        <input type="hidden" id="sharedfolder-saved" name="sharedfolder-saved" value="<?php echo $_['sharedfolder']; ?>" />
        <input <?php echo $_['sharedfolder'] != '' ? 'disabled="disabled"' : ''; ?>
          type="text"
          id="sharedfolder"
          name="sharedfolder"
          placeholder="<?php echo $l->t('shared folder'); ?>"
          value="<?php echo $_['sharedfolder']; ?>" />
        <label for="sharedfolder-force" title="<?php echo $toolTips['sharedfolder-force']; ?>" >
          <input type="checkbox" id="sharedfolder-force" name="sharedfolder-force" class="checkbox"/>
          <?php echo $l->t('force');?>
        </label>
        <input name="sharedfolder-check" id="sharedfolder-check" type="button" value="<?php echo $l->t('Check');?>" />
      </fieldset>
    </form>
    <form id="projectsfolder-form">
      <fieldset <?php echo $off; ?> >
        <span id="project-folder-prefix"><b>.../</b><?php echo $_['sharedfolder']; ?><b>/</b></span>
        <input type="hidden" id="projectsfolder-saved" name="projectsfolder-saved" value="<?php echo $_['projectsfolder']; ?>" />
        <input <?php echo $_['projectsfolder'] != '' ? 'disabled="disabled"' : ''; ?>
          type="text"
          id="projectsfolder"
          name="projectsfolder"
          placeholder="<?php echo $l->t('Project folder'); ?>"
          value="<?php echo $_['projectsfolder']; ?>" />
        <span id="project-folder-suffix"><b>/</b><?php echo $l->t('YEAR').'<b>/</b>'.$l->t('PROJECT').'<b>/</b>'; ?></span>
        <input type="checkbox" id="projectsfolder-force" name="projectsfolder-force" class="checkbox"/>
        <label for="projectsfolder-force" title="<?php echo $toolTips['projectsfolder-force']; ?>" >
          <?php echo $l->t('force');?>
        </label>
        <input name="projectsfolder-check" id="projectsfolder-check" type="button" value="<?php echo $l->t('Check');?>" />
      </fieldset>
    </form>
    <form id="projectsbalancefolder-form">
        <fieldset <?php echo $_['projectsfolder'] != '' ? $off : 'disabled'; ?> >
        <span id="project-folder-prefix"><b>.../</b><?php echo $_['sharedfolder']; ?><b>/</b></span>
        <input type="hidden" id="projectsbalancefolder-saved" name="projectsbalancefolder-saved" value="<?php echo $_['projectsbalancefolder']; ?>" />
        <input <?php echo $_['projectsbalancefolder'] != '' ? 'disabled="disabled"' : ''; ?>
          type="text"
          id="projectsbalancefolder"
          name="projectsbalancefolder"
          placeholder="<?php echo $l->t('Financial balance folder'); ?>"
          value="<?php echo $_['projectsbalancefolder']; ?>" />
        <span id="project-folder-suffix"><b>/</b><span id="projectsbalanceprojectsfolder"><?php echo $_['projectsfolder'];?></span><b>/</b><?php echo $l->t('YEAR').'<b>/</b>'.$l->t('PROJECT').'<b>/</b>'; ?></span>
        <input type="checkbox" id="projectsbalancefolder-force" name="projectsbalancefolder-force" class="checkbox"/>
        <label for="projectsbalancefolder-force" title="<?php echo $toolTips['projectsbalancefolder-force']; ?>" >
          <?php echo $l->t('force');?>
        </label>
        <input name="projectsbalancefolder-check" id="projectsbalancefolder-check" type="button" value="<?php echo $l->t('Check');?>" />
      </fieldset>
    </form>
    <span class="statusmessage sharing-settings"></span><span>&nbsp;</span>
  </div>
</div>
