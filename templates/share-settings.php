<?php
use CAFEVDB\L;
use CAFEVDB\Config;

$alloff = $_['orchestra'] == '' ? $alloff = 'disabled="disabled"' : '';
$off = $_['shareowner'] == '' ? 'disabled="disabled"' : $alloff;

?>
<div id="tabs-3" class="personalblock sharing admin">
<!-- SHARED CALENDARS and stuff -->
  <div id="eventsettings">
<!-- VIRTUAL USER -->
    <form id="shareownerform"><legend><?php echo L::t('Share owner') ; ?></legend>
      <fieldset id="shareowner" <?php echo $alloff; ?> >
        <input type="hidden" id="user-saved" name="shareowner-saved" value="<?php echo $_['shareowner']; ?>" />
        <input <?php echo $_['shareowner'] != '' ? 'disabled="disabled"' : ''; ?> type="text" id="user" name="shareowner" placeholder="<?php echo L::t('shareowner');?>" value="<?php echo $_['shareowner']; ?>" />
        <label for="shareowner-force" title="<?php echo Config::toolTips('shareowner-force'); ?>" ><input type="checkbox" id="shareowner-force" name="shareowner-force" /><?php echo L::t('force');?></label>
        <input name="shareownercheck" id="check" type="button" value="<?php echo L::t('Check');?>" />
      </fieldset>
<!-- CHANGE ITS PASSWORD -->
      <fieldset id="sharingpassword" <?php echo $alloff; ?> >
        <input type="password" id="password" name="sharingpassword" placeholder="<?php echo L::t('Share-Password');?>" data-typetoggle="#sharingpassword-show" />
        <input type="checkbox" id="sharingpassword-show" name="sharingpassword-show" /><label for="sharingpassword-show"><?php echo L::t('show');?></label>
        <input name="passwordgenerate" id="generate" type="button" value="<?php echo L::t('Generate');?>" />
        <input name="passwordchange" id="change" type="button" value="<?php echo L::t('Change');?>" />
      </fieldset>
    </form>
<!-- CALENDARS -->
    <form id="calendars">
      <fieldset  <?php echo $off; ?> ><legend><?php echo L::t('Calendars'); ?></legend>
        <input type="text" id="concerts" name="concertscalendar" placeholder="<?php echo L::t('calendarname');?>" value="<?php echo $_['concertscalendar']; ?>" />
        <label for="concerts"><?php echo L::t('Calendar for Concerts');?></label>
        <br/>
        <input type="text" id="rehearsals" name="rehearsalscalendar" placeholder="<?php echo L::t('calendarname');?>" value="<?php echo $_['rehearsalscalendar']; ?>" />
        <label for="rehearsals"><?php echo L::t('Calendar for Rehearsals');?></label>
        <br/>
        <input type="text" id="other" name="othercalendar" placeholder="<?php echo L::t('calendarname');?>" value="<?php echo $_['othercalendar']; ?>" />
        <label for="other"><?php echo L::t('Calendar for other Events');?></label>
        <br/>
        <input type="text" id="management" name="managementcalendar" placeholder="<?php echo L::t('calendarname');?>" value="<?php echo $_['managementcalendar']; ?>" />
        <label for="management"><?php echo L::t('Management-Calendar');?></label>
        <br/>
<!-- DEFAULT DURATION FOR EVENTS -->
        <input type="text" id="duration" name="eventduration" placeholder="<?php echo L::t('#Minutes');?>" value="<?php echo $_['eventduration']; ?>" />
        <label for="duration"><?php echo L::t('Default Duration for Events');?></label>
      </fieldset>
    </form>
    <form id="sharedfolderform">
      <fieldset <?php echo $off; ?> ><legend><?php echo L::t('Shared folder'); ?></legend>
        <input type="hidden" id="sharedfolder-saved" name="sharedfolder-saved" value="<?php echo $_['sharedfolder']; ?>" />
        <input <?php echo $_['sharedfolder'] != '' ? 'disabled="disabled"' : ''; ?> 
          type="text" 
          id="sharedfolder"
          name="sharedfolder"
          placeholder="<?php echo L::t('shared folder'); ?>"
          value="<?php echo $_['sharedfolder']; ?>" />
        <label for="sharedfolder-force" title="<?php echo Config::toolTips('sharedfolder-force'); ?>" >
          <input type="checkbox" id="sharedfolder-force" name="sharedfolder-force" />
          <?php echo L::t('force');?>
        </label>
        <input name="sharedfoldercheck" id="sharedfoldercheck" type="button" value="<?php echo L::t('Check');?>" />
      </fieldset>
    </form>
    <form id="projectsfolderform">
      <fieldset <?php echo $off; ?> >
        <span id="project-folder-prefix"><b>.../</b><?php echo $_['sharedfolder']; ?><b>/</b></span>
        <input type="hidden" id="projectsfoldersaved" name="projectsfoldersaved" value="<?php echo $_['projectsfolder']; ?>" />
        <input <?php echo $_['projectsfolder'] != '' ? 'disabled="disabled"' : ''; ?> 
          type="text" 
          id="projectsfolder"
          name="projectsfolder"
          placeholder="<?php echo L::t('Project folder'); ?>"
          value="<?php echo $_['projectsfolder']; ?>" />
        <span id="project-folder-suffix"><b>/</b><?php echo L::t('YEAR').'<b>/</b>'.L::t('PROJECT').'<b>/</b>'; ?></span>
        <label for="projectsfolder-force" title="<?php echo Config::toolTips('projectsfolder-force'); ?>" >
          <input type="checkbox" id="projectsfolder-force" name="projectsfolder-force" />
          <?php echo L::t('force');?>
        </label>
        <input name="projectsfoldercheck" id="projectsfoldercheck" type="button" value="<?php echo L::t('Check');?>" />
      </fieldset>
    </form>
    <form id="projectsbalancefolderform">
        <fieldset <?php echo $_['projectsfolder'] != '' ? $off : 'disabled'; ?> >
        <span id="project-folder-prefix"><b>.../</b><?php echo $_['sharedfolder']; ?><b>/</b></span>
        <input type="hidden" id="projectsbalancefoldersaved" name="projectsbalancefoldersaved" value="<?php echo $_['projectsbalancefolder']; ?>" />
        <input <?php echo $_['projectsbalancefolder'] != '' ? 'disabled="disabled"' : ''; ?> 
          type="text" 
          id="projectsbalancefolder"
          name="projectsbalancefolder"
          placeholder="<?php echo L::t('Financial balance folder'); ?>"
          value="<?php echo $_['projectsbalancefolder']; ?>" />
        <span id="project-folder-suffix"><b>/</b><span id="projectsbalanceprojectsfolder"><?php echo $_['projectsfolder'];?></span><b>/</b><?php echo L::t('YEAR').'<b>/</b>'.L::t('PROJECT').'<b>/</b>'; ?></span>
        <label for="projectsbalancefolder-force" title="<?php echo Config::toolTips('projectsbalancefolder-force'); ?>" >
          <input type="checkbox" id="projectsbalancefolder-force" name="projectsbalancefolder-force" />
          <?php echo L::t('force');?>
        </label>
        <input name="projectsbalancefoldercheck" id="projectsbalancefoldercheck" type="button" value="<?php echo L::t('Check');?>" />
      </fieldset>
    </form>
    <span class="statusmessage" id="msg"></span>
  </div>
</div>

