<?php use CAFEVDB\L; ?>
<?php use CAFEVDB\Config; ?>
<div class="personalblock">
<!-- SHARED CALENDARS and stuff -->
  <hr/>
  <div id="eventsettings">
<!-- VIRTUAL USER -->
    <form id="shareowner">
      <input type="hidden" id="user-saved" name="shareowner-saved" value="<?php echo $_['shareowner']; ?>" />
      <input <?php echo $_['shareowner'] != '' ? 'disabled="disabled"' : ''; ?> type="text" id="user" name="shareowner" placeholder="<?php echo L::t('shareowner');?>" value="<?php echo $_['shareowner']; ?>" />
      <label for="shareowner-force" title="<?php echo L::t(Config::toolTips('shareowner-force')); ?>" ><input type="checkbox" id="shareowner-force" name="shareowner-force" /><?php echo L::t('force');?></label>
      <input name="shareownercheck" id="check" type="button" value="<?php echo L::t('Check');?>" />
    </form>
<!-- CHANGE ITS PASSWORD -->
    <form id="sharingpassword">
      <input type="password" id="password" name="sharingpassword" placeholder="<?php echo L::t('Share-Password');?>" data-typetoggle="#sharingpassword-show" />
      <input type="checkbox" id="sharingpassword-show" name="sharingpassword-show" /><label for="sharingpassword-show"><?php echo L::t('show');?></label>
      <input name="passwordgenerate" id="generate" type="button" value="<?php echo L::t('Generate');?>" />
      <input name="passwordchange" id="change" type="button" value="<?php echo L::t('Change');?>" />
    </form>
<!-- CALENDARS -->
    <form id="calendars">
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
    </form>
    <div class="statusmessage" id="msg"></div>
  </div>
</div>
