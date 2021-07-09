<?php
/* Orchestra member, musician and project management application.
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

$off = $_['orchestra'] == '' ? 'disabled' : '';

?>
<div id="tabs-<?php echo $_['tabNr']; ?>" class="personalblock admin">
<!-- GENERAL CONFIGURATION STUFF -->
  <form id="admingeneral"><legend><?php echo $l->t('General settings'); ?></legend>
    <fieldset>
    <input type="text"
           id="orchestra"
           name="orchestra"
           value="<?php echo $_['orchestra']; ?>"
           required="required"
           title="<?php echo $l->t('name of orchestra'); ?>"
           placeholder="<?php echo $l->t('name of orchestra'); ?>" />
    <span class="statusmessage msg"></span>
    </fieldset>
  </form>
<!-- ENCRYPTION-KEY -->
  <form id="systemkey">
    <fieldset class="systemkey" <?php echo $off; ?> ><legend><?php echo $l->t('Encryption settings'); ?></legend>
      <input class="cafevdb-password"
             type="password"
             value="<?php p($encryptionkey); ?>"
             id="oldkey"
             name="oldkey"
             placeholder="<?php echo $l->t('Current Key');?>"
             data-typetoggle="#oldkey-show" />
      <input class="cafevdb-password-show" type="checkbox" id="oldkey-show" name="show" />
      <label class="cafevdb-password-show" for="oldkey-show"><?php echo $l->t('show');?></label>
      <input class="cafevdb-password randomkey" type="password" id="key" name="systemkey" placeholder="<?php echo $l->t('New Key');?>" data-typetoggle="#systemkey-show" />
      <input class="cafevdb-password-show" type="checkbox" id="systemkey-show" name="show" />
      <label class="cafevdb-password-show" for="systemkey-show"><?php echo $l->t('show');?></label>
      <input name="keygenerate" id="keygenerate" type="button" value="<?php echo $l->t('Generate'); ?>" title="<?php echo $l->t('Generate a random encryption key');?>" />
      <input id="keychangebutton" type="button" value="<?php echo $l->t('Change Encryption Key');?>" />
      <!-- <span><?php p($encryptionkey); ?></span> -->
      <div class="statusmessage changed"><?php echo $l->t('The encryption key was changed');?></div>
      <div class="statusmessage error"><?php echo $l->t('Unable to change the encryption key');?></div>
      <div class="statusmessage insecure"><?php echo $l->t('Data will be stored unencrypted');?></div>
      <div class="statusmessage equal"><?php echo $l->t('The keys are the same and remain unchanged.');?></div>
      <div class="statusmessage standby"><?php echo $l->t('Please standby, this action needs some seconds.');?></div>
      <div class="statusmessage general"></div>
    </fieldset>
    <!-- DISTRIBUTE ENCRYPTION-KEY -->
    <fieldset class="keydistribute" <?php echo $off; ?> >
      <input id="keydistributebutton" type="button" name="keydistribute" value="<?php echo $l->t('Distribute Encryption Key');?>" title="<?php echo $l->t('Insert the data-base encryption key into the user preferences of all users belonging to the user group. The data-base key will be encrypted by the respective user\'s public key.') ?>" />
      <span class="statusmessage"></span>
    </fieldset>
  </form>
  <!-- GENERAL DATA-BASE STUFF -->
  <form id="dbsettings">
    <fieldset id="dbgeneral"  <?php echo $off; ?> ><legend><?php echo $l->t('Database settings'); ?></legend>
      <input type="text"
             autocomplete="on"
             name="dbserver"
             id="dbserver"
             value="<?php echo $_['dbserver']; ?>"
             placeholder="<?php echo $l->t('Server');?>"
      />
      <label for="dbserver"><?php echo $l->t('Database Server');?></label>
      <br/>
      <input type="text"
             autocomplete="on"
             name="dbname"
             id="dbname"
             value="<?php echo $_['dbname']; ?>"
             placeholder="<?php echo $l->t('Database');?>"
      />
      <label for="dbname"><?php echo $l->t('Database Name');?></label>
      <br/>
      <input type="text"
             autocomplete="on"
             name="dbuser"
             id="dbuser"
             value="<?php echo $_['dbuser']; ?>"
             placeholder="<?php echo $l->t('User');?>"
      />
      <label for="dbuser"><?php echo $l->t('Database User');?></label>
      <div id="msgplaceholder"><div class="statusmessage" id="msg"></div></div>
    </fieldset>
<!-- DATA-BASE password -->
    <fieldset class="cafevdb_dbpassword">
      <input class="cafevdb-password" type="password" id="cafevdb-dbpassword" name="dbpassword" placeholder="<?php echo $l->t('New Password');?>" data-typetoggle="#cafevdb-dbpassword-show" />
      <input class="cafevdb-password-show" type="checkbox" id="cafevdb-dbpassword-show" name="dbpassword-show" />
      <label class="cafevdb-password-show" for="cafevdb-dbpassword-show"><?php echo $l->t('show');?></label>
      <input id="button" type="button" title="<?php echo $toolTips['test-dbpassword']; ?>" value="<?php echo $l->t('Test Database Password');?>" />
      <div class="statusmessage" id="dbteststatus"></div>
    </fieldset>
    <fieldset id="dbtesting">
    </fieldset>
  </form>
</div>
