<?php use CAFEVDB\L; ?>
<div class="personalblock">
 <hr /><strong>Admin settings for Camerata DB</strong><br />
  <form id="cafevdbkey">
    <div id="changed"><?php echo L::t('The encryption key was changed');?></div>
    <div id="error"><?php echo L::t('Unable to change the encryption key');?></div>
    <div id="insecure"><?php echo L::t('Data will be stored unencrypted');?></div>
    <input type="password" id="dbkey1" name="dbkey1" placeholder="<?php echo L::t('Current Key');?>" />
    <input type="password" id="CAFEVDBkey" name="CAFEVDBkey" placeholder="<?php echo L::t('New Key');?>" data-typetoggle="#cafevdbkeyshow" />
    <input type="checkbox" id="cafevdbkeyshow" name="show" /><label for="cafevdbkeyshow"><?php echo L::t('show');?></label>
    <input id="button" type="button" value="<?php echo L::t('Change Encryption Key');?>" />
  </form>
  <br/>
  <form id="cafevdbkeydistribute">
    <input type="hidden" name="CAFEVDBkeydistribute" value="clicked"/>
    <input id="button" type="button" name="CAFEVDBkeydistribute" value="<?php echo L::t('Distribute Encryption Key');?>" title="<?php echo L::t('Insert the data-base encryption key into the user preferences of all users belonging to the user group. The data-base key will be encrypted by the respective user\'s public key.') ?>" />
    <span id="msg">Hello</span>
  </form>
  <br/>
  <form id="cafevdbgeneral">
    <input type="text" name="CAFEVdbserver" id="CAFEVdbserver" value="<?php echo $_['dbserver']; ?>" placeholder="<?php echo L::t('Server');?>" />
    <label for="CAFEVdbserver"><?php echo L::t('Database Server');?></label>
    <br/>
    <input type="text" name="CAFEVdbname" id="CAFEVdbname" value="<?php echo $_['dbname']; ?>" placeholder="<?php echo L::t('Database');?>" />
    <label for="CAFEVdbname"><?php echo L::t('Database Name');?></label>
    <br/>
    <input type="text" name="CAFEVdbuser" id="CAFEVdbuser" value="<?php echo $_['dbuser']; ?>" placeholder="<?php echo L::t('User');?>" />
    <label for="CAFEVdbuser"><?php echo L::t('Database User');?></label>
    <br/>
    <span class="msg"></span>
  </form>
  <form id="cafevdbpass">
    <div id="changed"><?php echo L::t('The database password was changed');?></div>
    <div id="error"><?php echo L::t('Unable to change the database password');?></div>
    <!-- <input type="password" id="dbpass1" name="dbpass1" placeholder="<?php echo L::t('Current Password');?>" /> -->
    <input type="password" id="CAFEVDBpass" name="CAFEVDBpass" placeholder="<?php echo L::t('New Password');?>" data-typetoggle="#cafevdbpassshow" />
    <input type="checkbox" id="cafevdbpassshow" name="show" /><label for="cafevdbpassshow"><?php echo L::t('show');?></label>
    <input id="button" type="button" value="<?php echo L::t('Change Database Password');?>" />
  </form>
  <hr/?>
  <form id="cafevdbevents">
    <fieldset id="calendars">
    <input type="text" id="user" name="calendaruser" placeholder="<?php echo L::t('username');?>" value="<?php echo $_['calendaruser']; ?>" />
    <label for="user"><?php echo L::t('Account for Calendars');?></label>
    <br/>
    <input type="text" id="concerts" name="concertscalendar" placeholder="<?php echo L::t('calendarname');?>" value="<?php echo $_['concertscalendar']; ?>" />
    <label for="concerts"><?php echo L::t('Calendar for Concerts');?></label>
    <br/>
    <input type="text" id="rehearsals" name="rehearsalscalendar" placeholder="<?php echo L::t('calendarname');?>" value="<?php echo $_['rehearsalscalendar']; ?>" />
    <label for="rehearsals"><?php echo L::t('Calendar for Rehearsals');?></label>
    <br/>
    <input type="text" id="other" name="othercalendar" placeholder="<?php echo L::t('calendarname');?>" value="<?php echo $_['othercalendar']; ?>" />
    <label for="other"><?php echo L::t('Calendar for other Events');?></label>
    </fieldset>
    <input type="text" id="duration" name="eventduration" placeholder="<?php echo L::t('#Minutes');?>" value="<?php echo $_['eventduration']; ?>" />
    <label for="duration"><?php echo L::t('Default Duration for Events');?></label>
    <br/>
    <span class="msg"></span>
  </form>
</div>
