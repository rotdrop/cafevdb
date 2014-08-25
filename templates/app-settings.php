<?php

use CAFEVDB\L;
use CAFEVDB\Config;

$off = $_['orchestra'] == '' ? 'disabled="disabled"' : '';

?>
<div id="tabs-<?php echo $_['tabNr']; ?>" class="personalblock admin">
<!-- GENERAL CONFIGURATION STUFF -->
  <form id="admingeneral"><legend><?php echo L::t('General settings'); ?></legend>
    <fieldset>
    <input type="text"
           id="orchestra"
           name="orchestra"
           value="<?php echo $_['orchestra']; ?>"
           title="<?php echo L::t('name of orchestra'); ?>"
           placeholder="<?php echo L::t('name of orchestra'); ?>" />
    <span class="statusmessage" id="msg"></span>
    </fieldset>
  </form>
<!-- ENCRYPTION-KEY -->
  <form id="systemkey">
    <fieldset <?php echo $off; ?> ><legend><?php echo L::t('Encryption settings'); ?></legend>
      <input class="cafevdb-password" type="password" id="oldkey" name="oldkey" placeholder="<?php echo L::t('Current Key');?>" data-typetoggle="#oldkey-show" />
      <input class="cafevdb-password-show" type="checkbox" id="oldkey-show" name="show" />
      <label class="cafevdb-password-show" for="oldkey-show"><?php echo L::t('show');?></label>
      <input class="cafevdb-password" type="password" id="key" name="systemkey" placeholder="<?php echo L::t('New Key');?>" data-typetoggle="#systemkey-show" />
      <input class="cafevdb-password-show" type="checkbox" id="systemkey-show" name="show" />
      <label class="cafevdb-password-show" for="systemkey-show"><?php echo L::t('show');?></label>
      <input id="keychangebutton" type="button" value="<?php echo L::t('Change Encryption Key');?>" />
      <!-- <span><?php echo $_['encryptionkey']; ?></span> -->
      <div class="statusmessage" id="changed"><?php echo L::t('The encryption key was changed');?></div>
      <div class="statusmessage" id="error"><?php echo L::t('Unable to change the encryption key');?></div>
      <div class="statusmessage" id="insecure"><?php echo L::t('Data will be stored unencrypted');?></div>
      <div class="statusmessage" id="equal"><?php echo L::t('The keys are the same and remain unchanged.');?></div>
      <div class="statusmessage" id="standby"><?php echo L::t('Please standby, this action needs some seconds.');?></div>
    </fieldset>
<!-- DISTRIBUTE ENCRYPTION-KEY -->
    <fieldset id="keydistribute" <?php echo $off; ?> >
      <input id="keydistributebutton" type="button" name="keydistribute" value="<?php echo L::t('Distribute Encryption Key');?>" title="<?php echo L::t('Insert the data-base encryption key into the user preferences of all users belonging to the user group. The data-base key will be encrypted by the respective user\'s public key.') ?>" />
      <span class="statusmessage" id="msg"></span>
    </fieldset>
  </form>
<!-- GENERAL DATA-BASE STUFF -->
  <form id="dbsettings">
    <fieldset id="dbgeneral"  <?php echo $off; ?> ><legend><?php echo L::t('Database settings'); ?></legend>
      <input type="text" name="dbserver" id="dbserver" value="<?php echo $_['dbserver']; ?>" placeholder="<?php echo L::t('Server');?>" />
      <label for="dbserver"><?php echo L::t('Database Server');?></label>
      <br/>
      <input type="text" name="dbname" id="dbname" value="<?php echo $_['dbname']; ?>" placeholder="<?php echo L::t('Database');?>" />
      <label for="dbname"><?php echo L::t('Database Name');?></label>
      <br/>
      <input type="text" name="dbuser" id="dbuser" value="<?php echo $_['dbuser']; ?>" placeholder="<?php echo L::t('User');?>" />
      <label for="dbuser"><?php echo L::t('Database User');?></label>
      <div id="msgplaceholder"><div class="statusmessage" id="msg"></div></div>
    </fieldset>
<!-- DATA-BASE password -->
    <fieldset class="cafevdb_dbpassword">
      <input class="cafevdb-password" type="password" id="cafevdb_dbpassword" name="cafevdb_dbpassword" placeholder="<?php echo L::t('New Password');?>" data-typetoggle="#cafevdb_dbpassword-show" />
      <input class="cafevdb-password-show" type="checkbox" id="cafevdb_dbpassword-show" name="cafevdb_dbpassword-show" />
      <label class="cafevdb-password-show" for="cafevdb_dbpassword-show"><?php echo L::t('show');?></label>
      <input id="button" type="button" title="<?php echo Config::toolTips('test-cafevdb_dbpassword'); ?>" value="<?php echo L::t('Test Database Password');?>" />
      <div class="statusmessage" id="dbteststatus"></div>
    </fieldset>
    <fieldset id="dbtesting">
    </fieldset>
  </form>
</div>
