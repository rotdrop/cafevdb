<?php use CAFEVDB\L; ?>
<div id="tabs-2" class="personalblock admin">
<!-- GENERAL CONFIGURATION STUFF -->
   <form id="admingeneral">
     <input type="text" id="orchestra" name=" orchestra" value="<?php echo $_['orchestra']; ?>" placeholder="<?php echo L::t('name of orchestra'); ?>" />
     <span class="statusmessage" id="msg"></span>
   </form>
<!-- ENCRYPTION-KEY -->
  <form id="systemkey">
    <input type="password" id="oldkey" name="oldkey" placeholder="<?php echo L::t('Current Key');?>" />
    <input type="password" id="key" name="systemkey" placeholder="<?php echo L::t('New Key');?>" data-typetoggle="#systemkey-show" />
    <input type="checkbox" id="systemkey-show" name="show" /><label for="systemkey-show"><?php echo L::t('show');?></label>
    <input id="button" type="button" value="<?php echo L::t('Change Encryption Key');?>" />
    <div class="statusmessage" id="changed"><?php echo L::t('The encryption key was changed');?></div>
    <div class="statusmessage" id="error"><?php echo L::t('Unable to change the encryption key');?></div>
    <div class="statusmessage" id="insecure"><?php echo L::t('Data will be stored unencrypted');?></div>
    <div class="statusmessage" id="equal"><?php echo L::t('The keys are the same and remain unchanged.');?></div>
  </form>
<!-- DISTRIBUTE ENCRYPTION-KEY -->
  <form id="keydistribute">
    <input id="button" type="button" name="keydistribute" value="<?php echo L::t('Distribute Encryption Key');?>" title="<?php echo L::t('Insert the data-base encryption key into the user preferences of all users belonging to the user group. The data-base key will be encrypted by the respective user\'s public key.') ?>" />
    <span class="statusmessage" id="msg"></span>
  </form>
<!-- GENERAL DATA-BASE STUFF -->
  <form id="dbsettings">
    <fieldset id="dbgeneral">
      <input type="text" name="dbserver" id="dbserver" value="<?php echo $_['dbserver']; ?>" placeholder="<?php echo L::t('Server');?>" />
      <label for="dbserver"><?php echo L::t('Database Server');?></label>
      <br/>
      <input type="text" name="dbname" id="dbname" value="<?php echo $_['dbname']; ?>" placeholder="<?php echo L::t('Database');?>" />
      <label for="dbname"><?php echo L::t('Database Name');?></label>
      <br/>
      <input type="text" name="dbuser" id="dbuser" value="<?php echo $_['dbuser']; ?>" placeholder="<?php echo L::t('User');?>" />
      <label for="dbuser"><?php echo L::t('Database User');?></label>
      <br/>
      <span class="statusmessage" id="msg"></span>
    </fieldset>
<!-- DATA-BASE password -->
    <fieldset id="dbpassword">
      <!-- <input type="password" id="pass1" name="pass1" placeholder="<?php echo L::t('Current Password');?>" /> -->
      <input type="password" id="dbpassword" name="dbpassword" placeholder="<?php echo L::t('New Password');?>" data-typetoggle="#dbpassword-show" />
      <input type="checkbox" id="dbpassword-show" name="dbpassword-show" /><label for="dbpassword-show"><?php echo L::t('show');?></label>
      <input id="button" type="button" value="<?php echo L::t('Change Database Password');?>" />
      <div class="statusmessage" id="changed"><?php echo L::t('The database password was changed');?></div>
      <div class="statusmessage" id="error"><?php echo L::t('Unable to change the database password');?></div>
    </fieldset>
  </form>
</div>
