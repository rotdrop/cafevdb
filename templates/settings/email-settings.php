<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCA\CAFEVDB\Common\Util;

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
    <h4><?php echo $l->t('Bulk Email Composition'); ?></h4>
    <form class="bulk-email-settings">
      <fieldset class="emailtransport">
        <legend><?php p($l->t('Bulk Email Transport')); ?></legend>
        <label for="announcements-mailing-list"
               title="<?php echo $toolTips['emailform:transport:announcements:mailing-list']; ?>"
        >
          <input type="text"
                 name="announcementsMailingList"
                 id="announcements-mailing-list"
                 value="<?php p($announcementsMailingList); ?>"
                 placeholder="<?php p($l->t('Mailing List')); ?>"
          />
          <?php p($l->t('Announcements Mailing List')); ?>
        </label>
        <br class="input-field-separater"/>
        <label for ="announcements-mailing-list-autoconf"
               title="<?php echo $toolTips['mailing-list:announcements:autoconf']; ?>"
        >
          <input type="button"
                 name="announcementsMailingListAutoconf"
                 id="announcements-mailing-list-autoconf"
                 value="<?php echo $l->t('configure'); ?>"
          />
          <?php p($l->t('auto-configure announcements mailing-list')); ?>
        </label>
      </fieldset>
      <fieldset class="emailidentity">
        <legend><?php p($l->t('Bulk Email Identity')); ?></legend>
        <label for="emailfromname"
               title="<?php echo $toolTips['emailform:sender:address']; ?>"
        >
          <input type="text"
                 name="emailfromname"
                 id="emailfromname"
                 value="<?php echo $_['emailfromname']; ?>"
                 placeholder="<?php echo $l->t('Real Sender Name');?>"
          />
          <?php echo $l->t('From: name');?>
        </label>
        <label for="emailfromaddress"
               title="<?php echo $toolTips['emailform:sender:address']; ?>"
        >
          <input type="text"
                 name="emailfromaddress"
                 id="emailfromaddress"
                 value="<?php echo $_['emailfromaddress']; ?>"
                 placeholder="<?php echo $l->t('Email From Adress');?>"
          />
          <?php echo $l->t('From: address');?>
        </label>
      </fieldset>
      <fieldset class="bulk-email-subject">
        <legend><?php p($l->t('Bulk Email Subject')); ?></legend>
        <div class="bulk-email-subject container">
          <span class="bulk-email-subject tag">[</span>
          <input type="text"
                 name="bulkEmailSubjectTag"
                 id="bulk-email-subject-tag"
                 class="tooltip-auto"
                 value="<?php p($bulkEmailSubjectTag); ?>"
                 title="<?php echo $toolTips['emailform:composer:subject:tag']; ?>"
                 size="5"
          />
          <span class="bulk-email-subject tag"><?php p('-' . $l->t('ProjectNameYYYY') . ']'); ?></span>
          <span class="bulk-email-subject"><?php p($l->t('Example Subject')); ?></span>
        </div>
      </fieldset>
      <fieldset class="email-attachments">
        <legend><?php p($l->t('Attachment Policy')); ?></legend>
        <label for="attachmentLinkSizeLimit"
               class="tooltip-auto"
               title="<?php echo $toolTips['emailform:composer:attachments:link:size-limit']; ?>"
        >
          <input type="text"
                 id="attachmentLinkSizeLimit"
                 name="attachmentLinkSizeLimit"
                 class="attachmentLinkSizeLimit tooltip-auto"
                 value="<?php p($attachmentLinkSizeLimit); ?>"
                 placeholder="<?php p($l->t('e.g. 4.7 GB')); ?>"
          />
          <?php p($l->t('Attachment Link Size Limit')); ?>
        </label>
        <br class="input-field-separater"/>
        <label for="attachmentLinkExpirationLimit"
               class="tooltip-auto"
               title="<?php echo Util::htmlEscape($toolTips['emailform:composer:attachments:link:expiration-limit']); ?>"
        >
          <input type="text"
                 id="attachmentLinkExpirationLimit"
                 class="attachmentLinkExpirationLimit tooltip-auto"
                 name="attachmentLinkExpirationLimit"
                 value="<?php p($attachmentLinkExpirationLimit); ?>"
                 placeholder="<?php p($l->t('e.g. 7 days')); ?>"
          />
          <?php p($l->t('Attachment Link Expiration Limit')); ?>
        </label>
        <br class="input-field-separater"/>
        <label for="cloudAttachmentAlwaysLink"
               class="tooltip-auto hidden"
               title="<?php echo Util::htmlEscape($toolTips['emailform:composer:attachments:link:cloud-always']); ?>"
               disabled
        >
          <input type="checkbox"
                 id="cloudAttachmentAlwaysLink"
                 class="cloudAttachmentAlwaysLink checkbox tooltip-auto hidden"
                 name="cloudAttachmentAlwaysLink"
                 type="checkbox"
                 disabled
          />
          <?php p($l->t('Always Link Cloud Files')); ?>
        </label>
      </fieldset>
    </form>
    <h4><?php echo $l->t('Bulk Email Privacy Notice'); ?></h4>
    <form class="bulk-email-privacy-notice">
      <fieldset class="bulk-email-privacy-notice">
        <legend><?php p($l->t('This is the notice attached to all bulk-emails which are not directed to a mailing list or project-participants. The idea is to give the recipients information why they receive the message and how they can prevent further messages if they consider such messages as spam.')); ?></legend>
        <div class="bulk-email-privacy-notice container">
          <textarea class="bulk-email-privacy-notice wysiwyg"
                    name="bulkEmailPrivacyNotice"
                    cols="60"
                    rows="10">
            <?php p($bulkEmailPrivacyNotice); ?>
          </textarea>
        </div>
      </fieldset>
    </form>
    <h4><?php p($l->t('Mailing List Service')); ?></h4>
    <form class="mailing-list">
      <fieldset class="web-interface">
        <legend class="tooltip-auto"
                title="<?php echo $toolTips['mailing-list:domain']; ?>">
          <?php p($l->t('Mailing Lists Domain')); ?>
        </legend>
        <label for="mailingListEmailDomain"
               title="<?php echo $toolTips['mailing-list:domain:config']; ?>"
        >
          <input type="text"
                 name="mailingListEmailDomain"
                 id="mailingListEmailDomain"
                 value="<?php echo $mailingListEmailDomain; ?>"
                 placeholder="<?php p('e.g. lists.tld');?>"
                 required
          />
          <?php echo $l->t('Mailing-List Domain');?>
        </label>
        <br/>
        <label for="mailingListWebPages"
                 title="<?php echo $toolTips['mailing-list:domain:config']; ?>"
        >
          <input type="text"
                 name="mailingListWebPages"
                 id="mailingListWebPages"
                 value="<?php echo $mailingListWebPages; ?>"
                 placeholder="<?php p('e.g. https://lists.tld/mailman');?>"
                 required
          />
          <?php echo $l->t('Mailing-List Configuration Pages');?>
        </label>
      </fieldset>
      <fieldset class="rest-account">
        <legend class="tooltip-auto"
                title="<?php echo $toolTips['mailing-list:restapi']; ?>"
        >
          <?php p($l->t('REST API Account')); ?>
        </legend>
        <label for="mailingListRestUrl"
               title="<?php echo $toolTips['mailing-list:restapi:url']; ?>"
        >
          <input type="text"
                 name="mailingListRestUrl"
                 id="mailingListRestUrl"
                 value="<?php echo $mailingListRestUrl; ?>"
                 placeholder="<?php p('http://localhost:8001');?>"
                 required
          />
          <?php echo $l->t('Mailing-List REST URL');?>
        </label>
        <br/>
        <label for="mailingListRestUser"
               title="<?php echo $toolTips['mailing-list:restapi:user']; ?>"
        >
          <input type="text"
                 name="mailingListRestUser"
                 id="mailingListRestUser"
                 value="<?php echo $mailingListRestUser; ?>"
                 placeholder="<?php echo $l->t('User');?>"
                 required
          />
          <?php echo $l->t('REST User');?>
        </label>
        <br/>
        <label for="mailingListRestPassword"
               title="<?php echo $toolTips['mailing-list:restapi:password']; ?>"
        >
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
          <?php echo $l->t('REST Password');?>
        </label>
      </fieldset>
      <fieldset class="auto-generated-list">
        <legend class="tooltip-auto"
                title="<?php echo $toolTips['mailing-list:generated:defaults']; ?>"
        >
          <?php p($l->t('Defaults for Generated Lists')); ?>
        </legend>
        <label for="mailingListDefaultOwner"
               title="<?php echo $toolTips['mailing-list:generated:defaults:owner']; ?>"
        >
          <input type="text"
                 name="mailingListDefaultOwner"
                 id="mailingListDefaultOwner"
                 value="<?php p($mailingListDefaultOwner); ?>"
                 placeholder="<?php p($l->t('someone@somewhere.tld')); ?>"
          />
          <?php p($l->t('Default List-Owner'));?>
        </label>
        <br/>
        <label for="mailingListDefaultModerator"
               title="<?php echo $toolTips['mailing-list:generated:defaults:moderator']; ?>"
        >
          <input type="text"
                 name="mailingListDefaultModerator"
                 id="mailingListDefaultModerator"
                 value="<?php p($mailingListDefaultModerator); ?>"
                 placeholder="<?php p($l->t('someone@somewhere.tld')); ?>"
          />
          <?php p($l->t('Default List-Moderator'));?>
        </label>
      </fieldset>
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
  </div>
</div>
