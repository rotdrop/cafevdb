<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB;

use OCA\CAFEVDB\Controller\PersonalSettingsController;

?>
<div id="tabs-<?php echo $_['tabNr']; ?>" class="personalblock admin email">
  <div id="email-settings">
    <h4><?php p($l->t('Server Settings')); ?></h4>
    <form class="serversettings">
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
  <input type="number" name="'.$proto.'port" id="'.$proto.'port" '
            .'value="'.$_[$proto.'port'].'" '
            .'placeholder="'.$upproto.' Port"
    min="1" max="65535"
    />
  <label for="'.$proto.'port">'.$upproto.' Port</label>
  <br/>
  <label for="'.$proto.'security" id="'.$proto.'securitylabel">
  <select name="'.$proto.'security" id="'.$proto.'security" '
            .'data-placeholder="'.$upproto.' '.$l->t('security').'" >
    <option value=""></option>';
        foreach (PersonalSettingsController::EMAIL_SECURITY as $value) {
          $upvalue = strtoupper($value);
          $sel = ($_[$proto.'security'] == $value) ? 'selected="selected"' : '';
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
      <!-- div class="statusmessage"></div -->
    </form><!-- server settings -->
    <!-- GENERAL EMAIL STUFF -->
    <h4><?php echo $l->t('Email Account'); ?></h4>
    <form id="emailaccount">
      <fieldset class="emailuser">
        <!-- EMAIL user / password -->
        <input type="text" name="emailuser" id="emailuser" value="<?php echo $_['emailuser']; ?>" placeholder="<?php echo $l->t('Email-User');?>" />
        <label for="emailuser"><?php echo $l->t('Login for email account.');?></label>
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
          <!-- div class="statusmessage"></div -->
        </fieldset>
      </fieldset>
      <fieldset id="emaildistribute">
        <input id="emaildistributebutton"
               type="button"
               name="emaildistribute"
               value="<?php echo $l->t('Distribute Email Account');?>"
               title="<?php echo  $toolTips['email-account-distribute'];?>"
        />
        <!-- span class="statusmessage" id="email-account-distribute-message"></span -->
      </fieldset>
    </form>
    <h4><?php echo $l->t('Bulk Sender Identity'); ?></h4>
    <form class="emailidentity">
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
    </form>
    <h4><?php echo $l->t('Test Settings'); ?></h4>
    <form class="emailtest">
      <input type="button"
             name="emailtest"
             id="emailtestbutton"
             value="<?php echo $l->t('Test Email Setup'); ?>"
             title="<?php echo $toolTips['emailtest']; ?>"
      />
      <input id="emailtestmode"
             type="checkbox"
             class="checkbox"
             name="emailtestmode" <?php echo $_['emailtestmode'] == 'on' ? 'checked="checked"' : ''; ?>
             id="emailtestmode"
      />
      <label for="emailtestmode"
             title="<?php echo $l->t('Email test-mode; send emails only to the email test-address.') ?>">
        <?php echo $l->t('Test-Mode'); ?>
      </label>
      <input type="text" name="emailtestaddress" id="emailtestaddress"
             <?php echo $_['emailtestmode'] == 'on' ? '' : 'disabled' ?>
             value="<?php echo $_['emailtestaddress']; ?>"
             placeholder="<?php echo $l->t('Test Email Adress');?>"
      />
      <label for="emailtestaddress"><?php echo $l->t('Test address');?></label>
    </form>
    <h4>Mailing List Service</h4>
    <form class="mailing-list">
      <input type="text"
             name="mailingListURL"
             id="mailingListServer"
             value="<?php echo $mailingListServer; ?>"
             placeholder="<?php p('http://localhost:8001');?>"
             required
      />
      <label for="mailing-list-server"><?php echo $l->t('Mailing-List REST URL');?></label>
      <br/>
      <input type="text"
             name="mailingListRestUser"
             id="mailingListRestUser"
             value="<?php echo $mailingListRestUser; ?>"
             placeholder="<?php echo $l->t('User');?>"
             required
      />
      <label for="mailingListRestUser"><?php echo $l->t('REST User');?></label>
      <br/>
      <input class="cafevdb-password"
             type="password"
             value="<?php echo $mailingListRestPassword; ?>"
             id="mailingListRestPassword"
             name="mailingListRestPassword"
             placeholder="<?php echo $l->t('Password');?>"
             data-typetoggle="#mailingListRestPassword-show"
             required
      />
      <input class="cafevdb-password-show"
             type="checkbox"
             id="mailingListRestPassword-show"
             name="mailingListRestPassword-show"
      />
      <label class="cafevdb-password-show"
             for="mailingListRestPassword-show">
        <?php echo $l->t('show');?>
      </label>
      <label for="mailingListRestPassword"><?php echo $l->t('REST Password');?></label>
    </form>
  </div>
</div>
