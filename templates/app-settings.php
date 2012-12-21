<div class="personalblock">
 <hr /><strong>Admin settings for Camerata DB</strong><br />
  <form id="cafevdbkey">
    <div id="changed"><?php echo $l->t('The encryption key was changed');?></div>
    <div id="error"><?php echo $l->t('Unable to change the encryption key');?></div>
    <div id="insecure"><?php echo $l->t('Data will be stored unencrypted');?></div>
    <input type="password" id="dbkey1" name="dbkey1" placeholder="<?php echo $l->t('Current Key');?>" />
    <input type="password" id="CAFEVDBkey" name="CAFEVDBkey" placeholder="<?php echo $l->t('New Key');?>" data-typetoggle="#cafevdbkeyshow" />
    <input type="checkbox" id="cafevdbkeyshow" name="show" /><label for="cafevdbkeyshow"><?php echo $l->t('show');?></label>
    <input id="button" type="button" value="<?php echo $l->t('Change Encryption Key');?>" />
  </form>
  <br/>
  <form id="cafevdbkeydistribute">
    <input type="hidden" name="CAFEVDBkeydistribute" value="clicked"/>
    <input id="button" type="button" name="CAFEVDBkeydistribute" value="<?php echo $l->t('Distribute Encryption Key');?>" title="<?php echo $l->t('Insert the data-base encryption key into the user preferences of all users belonging to the user group. The data-base key will be encrypted by the respective user\'s public key.') ?>" />
    <span id="msg">Hello</span>
  </form>
  <br/>
  <form id="cafevdbgeneral">
    <input type="text" name="CAFEVdbserver" id="CAFEVdbserver" value="<?php echo $_['dbserver']; ?>" placeholder="<?php echo $l->t('Server');?>" />
    <label for="CAFEVdbserver"><?php echo $l->t('Database Server');?></label>
    <br/>
    <input type="text" name="CAFEVdbname" id="CAFEVdbname" value="<?php echo $_['dbname']; ?>" placeholder="<?php echo $l->t('Database');?>" />
    <label for="CAFEVdbname"><?php echo $l->t('Database Name');?></label>
    <br/>
    <input type="text" name="CAFEVdbuser" id="CAFEVdbuser" value="<?php echo $_['dbuser']; ?>" placeholder="<?php echo $l->t('User');?>" />
    <label for="CAFEVdbuser"><?php echo $l->t('Database User');?></label>
    <br/>
    <span class="msg"></span>
  </form>
  <form id="cafevdbpass">
    <div id="changed"><?php echo $l->t('The database password was changed');?></div>
    <div id="error"><?php echo $l->t('Unable to change the database password');?></div>
    <!-- <input type="password" id="dbpass1" name="dbpass1" placeholder="<?php echo $l->t('Current Password');?>" /> -->
    <input type="password" id="CAFEVDBpass" name="CAFEVDBpass" placeholder="<?php echo $l->t('New Password');?>" data-typetoggle="#cafevdbpassshow" />
    <input type="checkbox" id="cafevdbpassshow" name="show" /><label for="cafevdbpassshow"><?php echo $l->t('show');?></label>
    <input id="button" type="button" value="<?php echo $l->t('Change Database Password');?>" />
  </form>
  <hr/?>
  <form id="cafevdbevents">
    <fieldset id="calendars">
    <input type="text" id="user" name="calendaruser" placeholder="<?php echo $l->t('username');?>" value="<?php echo $_['calendaruser']; ?>" />
    <label for="user"><?php echo $l->t('Account for Calendars');?></label>
    <br/>
    <input type="text" id="concerts" name="concertscalendar" placeholder="<?php echo $l->t('calendarname');?>" value="<?php echo $_['concertscalendar']; ?>" />
    <label for="concerts"><?php echo $l->t('Calendar for Concerts');?></label>
    <br/>
    <input type="text" id="rehearsals" name="rehearsalscalendar" placeholder="<?php echo $l->t('calendarname');?>" value="<?php echo $_['rehearsalscalendar']; ?>" />
    <label for="rehearsals"><?php echo $l->t('Calendar for Rehearsals');?></label>
    <br/>
    <input type="text" id="other" name="othercalendar" placeholder="<?php echo $l->t('calendarname');?>" value="<?php echo $_['othercalendar']; ?>" />
    <label for="other"><?php echo $l->t('Calendar for other Events');?></label>
    </fieldset>
    <input type="text" id="duration" name="eventduration" placeholder="<?php echo $l->t('#Minutes');?>" value="<?php echo $_['eventduration']; ?>" />
    <label for="duration"><?php echo $l->t('Default Duration for Events');?></label>
    <br/>
    <span class="msg"></span>
  </form>
</div>
