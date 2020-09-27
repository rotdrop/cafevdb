<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use CAFEVDB\L;
use CAFEVDB\Config;
?>
<div id="tabs-<?php echo $_['tabNr']; ?>" class="personalblock admin email">
  <form id="emailsettings">
<!-- SMTP and IMAP settings -->
<?php
foreach (array('smtp', 'imap') as $proto) {
  $upproto = strtoupper($proto);
  echo ''
    .'<fieldset id="email'.$proto.'fields">'
    .'<legend>'.$upproto.' '.$l->t('Settings').'</legend>
  <input type="text" name="'.$proto.'server" id="'.$proto.'server" '
    .'value="'.$_[$proto.'server'].'" '
    .'placeholder="'.$upproto.' Server" />
  <label for="'.$proto.'server">'.$upproto.' Server</label>
  <br/>
  <input type="text" name="'.$proto.'port" id="'.$proto.'port" '
    .'value="'.$_[$proto.'port'].'" '
    .'placeholder="'.$upproto.' Port" />
  <label for="'.$proto.'port">'.$upproto.' Port</label>
  <br/>
  <label for="'.$proto.'secure" id="'.$proto.'securelabel">
  <select name="'.$proto.'secure" id="'.$proto.'secure" '
  .'data-placeholder="'.$upproto.' '.$l->t('security').'" >
    <option value=""></option>';
  foreach (array('insecure', 'starttls', 'ssl') as $value) {
    $upvalue = strtoupper($value);
    $sel = ($_[$proto.'secure'] == $value) ? 'selected="selected"' : '';
    echo '<option value="'.$value.'" '.$sel.'>'.$upvalue.'</option>'."\n";
  }
  echo '
  </select>'.$upproto.' '.$l->t('security').'</label>'."\n";
  echo '
</fieldset>';
  if ($proto == 'smtp') {
    echo '&nbsp;&nbsp;&nbsp;&nbsp;'."\n";
  }
}
?>
<!-- GENERAL EMAIL STUFF -->
  <br/>
    <fieldset id="emailaccount"><legend><?php echo $l->t('Email Account'); ?></legend>
      <input type="text" name="emailuser" id="emailuser" value="<?php echo $_['emailuser']; ?>" placeholder="<?php echo $l->t('Email-User');?>" />
      <label for="emailuser"><?php echo $l->t('Login for email account.');?></label>
      <br/>
<!-- EMAIL password -->
      <fieldset class="emailpassword">
        <input class="cafevdb-password"
               type="password"
               value="<?php echo $_['emailpassword']; ?>"
               id="emailpassword"
               name="emailpassword"
               placeholder="<?php echo $l->t('New Password');?>"
               data-typetoggle="#emailpassword-show"
               />
        <input class="cafevdb-password-show"
               type="checkbox"
               id="emailpassword-show"
               name="emailpassword-show"
               />
        <label class="cafevdb-password-show"
               for="emailpassword-show">
          <?php echo $l->t('show');?>
        </label>
        <input id="button"
               type="button"
               value="<?php echo $l->t('Change email password');?>"
               />
        <div class="statusmessage" id="changed">
          <?php echo $l->t('The email password was changed');?>
        </div>
        <div class="statusmessage" id="error">
          <?php echo $l->t('Unable to change the email password');?>
        </div>
      </fieldset>
      <fieldset id="emaildistribute">
        <input id="emaildistributebutton"
               type="button"
               name="emaildistribute"
               value="<?php echo $l->t('Distribute Email Account');?>"
               title="<?php echo  Config::toolTips('email-account-distribute');?>"
               />
        <span class="statusmessage" id="email-account-distribute-message"></span>
      </fieldset>
    </fieldset>
    <fieldset id="emailidentity">
      <legend><?php echo $l->t('Bulk Sender Identity'); ?></legend>
      <input type="text"
             name="emailfromname"
             id="emailfromname"
             value="<?php echo $_['emailfromname']; ?>"
             placeholder="<?php echo $l->t('Real Sender Name');?>"
             />
      <label for="emailfromname"><?php echo $l->t('From: name');?></label>
      <input type="text"
             name="emailfromaddress"
             id="emailfromaddress"
             value="<?php echo $_['emailfromaddress']; ?>"
             placeholder="<?php echo $l->t('Email From Adress');?>"
             />
      <label for="emailfromaddress"><?php echo $l->t('From: address');?></label>
    </fieldset>
    <fieldset id="emailtest">
      <legend><?php echo $l->t('Test Settings'); ?></legend>
      <input type="button"
             name="emailtest"
             id="emailtestbutton"
             value="<?php echo $l->t('Test Email Setup'); ?>"
             title="<?php echo Config::toolTips('emailtest'); ?>"
             />
      <input id="emailtestmode"
             type="checkbox"
             class="checkbox"
             name="emailtestmode" <?php echo $_['emailtestmode'] == 'on' ? 'checked="checked"' : ''; ?>
             id="emailtestmode"
             title="<?php echo $l->t('Email test-mode; send emails only to the email test-address.') ?>"
             />
      <label for="emailtestmode"
             title="<?php echo $l->t('Email test-mode; send emails only to the email test-address.') ?>">
        <?php echo $l->t('Test-Mode'); ?>
      </label>
      <input type="text" name="emailtestaddress" id="emailtestaddress"
      <?php echo $_['emailtestmode'] == 'on' ? '' : 'disabled="disabled"' ?>
             value="<?php echo $_['emailtestaddress']; ?>"
             placeholder="<?php echo $l->t('Test Email Adress');?>"
             />
      <label for="emailtestaddress"><?php echo $l->t('Test address');?></label>
    </fieldset>
    <span class="statusmessage" id="msg"></span>
  </form>
</div>
