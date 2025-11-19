<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020-2025 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB;

// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect
// phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace.Indent

use OCA\CAFEVDB\Controller\PersonalSettingsController;
use OCA\CAFEVDB\Controller\EnumSimpleSettingsKey;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Settings\ConfigConstants;

if (!empty($_[ConfigConstants::EMAIL_TEST_NAME_KEY])) {
  $emailTestAddressValue = $_[ConfigConstants::EMAIL_TEST_NAME_KEY] . ' <' . $_[ConfigConstants::EMAIL_TEST_ADDRESS_KEY] . '>';
} else {
  $emailTestAddressValue = $_[ConfigConstants::EMAIL_TEST_ADDRESS_KEY];
}

if (!empty($_[ConfigConstants::EMAIL_FROM_NAME_KEY])) {
  $emailAddressValue = $_[ConfigConstants::EMAIL_FROM_NAME_KEY] . ' <' . $_[ConfigConstants::EMAIL_FROM_ADDRESS_KEY] . '>';
} else {
  $emailAddressValue = $_[ConfigConstants::EMAIL_FROM_ADDRESS_KEY];
}

$emailUserKey = ConfigConstants::EMAIL_USER;
$emailPasswordKey = ConfigConstants::EMAIL_PASSWORD;
$privacyNoticeKey = ConfigConstants::BULK_EMAIL_PRIVACY_NOTICE;
$mailingListConfig = ConfigConstants::MAILING_LIST_CONFIG;
$mailingListRestConfig = ConfigConstants::MAILING_LIST_REST_CONFIG;

?>
<div id="tabs-<?php echo $_['tabNr']; ?>" class="personalblock admin email">
  <div id="email-settings">
    <h4><?php p($l->t('Server Settings')); ?></h4>
    <form class="serversettings">
      <!-- SMTP and IMAP settings -->
<?php
foreach (PersonalSettingsController::EMAIL_PROTO as $proto) {
  $upproto = strtoupper($proto);
  // enforce consistency with controllers and TS frontend code.
  $serverKey = EnumSimpleSettingsKey::get($proto . 'server')->value;
  $portKey = EnumSimpleSettingsKey::get($proto . 'port')->value;
  $securityKey = EnumSimpleSettingsKey::get($proto . 'security')->value;
?>
      <fieldset id="email<?= $proto ?>fields">
        <legend><?= $upproto ?> <?php p($l->t('Settings')) ?></legend>
        <input type="text" name="<?= $serverKey ?>" id="<?= $serverKey ?>"
               value="<?= $_[$serverKey] ?>"
               placeholder="<?= $upproto ?> Server"
        />
        <label for="<?= $serverKey ?>"><?= $upproto ?> Server</label>
        <br/>
        <input type="number" name="<?= $portKey ?>" id="<?= $portKey ?>"
               value="<?= $_[$portKey] ?>"
               placeholder="'<?= $upproto ?> Port"
               min="1" max="65535"
        />
        <label for="<?= $portKey ?>"><?= $upproto ?> Port</label>
        <br/>
        <label for="<?= $proto ?>security" id="<?= $proto ?>securitylabel">
        <select name="<?= $securityKey ?>" id="<?= $securityKey ?>"
                data-placeholder="<?= $upproto ?> <?php p($l->t('security')) ?>"
        >
        <!-- <option value=""></option> -->
<?php
  foreach (PersonalSettingsController::EMAIL_SECURITY as $value) {
    $upvalue = strtoupper($value);
    $sel = ($_[$securityKey] == $value) ? ' selected="selected"' : '';
?>
          <option value="<?= $value ?>"<?= $sel ?>><?= $upvalue ?></option>
<?php } ?>
        </select><?= $upproto ?> <?php p($l->t('security')) ?></label>
      </fieldset>
<?php if ($proto == 'smtp') { ?>
  &nbsp;&nbsp;&nbsp;&nbsp
<?php
  }
}
?>
      <!-- div class="statusmessage"></div -->
    </form><!-- server settings -->
    <!-- GENERAL EMAIL STUFF -->
    <h4><?php echo $l->t('Email Account'); ?></h4>
    <form id="emailaccount">
      <fieldset class="<?= $emailUserKey ?> flex-container flex-center">
        <!-- EMAIL user / password -->
        <input type="text"
               name="<?= $emailUserKey ?>"
               id="<?= $emailUserKey ?>"
               value="<?= $_[$emailUserKey]; ?>"
               placeholder="<?php echo $l->t('Email-User');?>"
               autocomplete="username"
        />
        <label for="<?= $emailUserKey ?>"><?php echo $l->t('Login for email account.');?></label>
      </fieldset>
      <fieldset class="<?= $emailPasswordKey ?> flex-container flex-center">
        <div class="password-container inline-block">
          <input class="cafevdb-password"
                 type="password"
                 value="<?= $_[$emailPasswordKey]; ?>"
                 id="<?= $emailPasswordKey ?>"
                 name="<?= $emailPasswordKey ?>"
                 placeholder="<?php echo $l->t('New Password');?>"
                 data-typetoggle="#<?= $emailPasswordKey ?>-show"
                 autocomplete="current-password"
          />
          <input class="cafevdb-password-show"
                 type="checkbox"
                 id="<?= $emailPasswordKey ?>-show"
                 name="<?= $emailPasswordKey ?>-show"
          />
          <label class="cafevdb-password-show"
                 for="<?= $emailPasswordKey ?>-show">
            <?php echo $l->t('show');?>
          </label>
        </div>
        <input id="email-password-button"
               class="button"
               type="button"
               value="<?php echo $l->t('Change email password');?>"
        />
        <!-- div class="statusmessage"></div -->
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
      <fieldset class="emailtransport flex-container flex-column flex-grow">
        <legend><?php p($l->t('Bulk Email Transport')); ?></legend>
        <label for="<?= ConfigConstants::ANNOUNCEMENTS_MAILING_LIST_KEY ?>"
               title="<?php echo $toolTips['emailform:transport:announcements:mailing-list']; ?>"
               class="settings-input-label"
        >
          <span><?php p($l->t('Announcements Mailing List')); ?></span>
        </label>
        <input type="text"
               name="<?= ConfigConstants::ANNOUNCEMENTS_MAILING_LIST_KEY ?>"
               id="<?= ConfigConstants::ANNOUNCEMENTS_MAILING_LIST_KEY ?>"
               value="<?php p($_[ConfigConstants::ANNOUNCEMENTS_MAILING_LIST_KEY]); ?>"
               placeholder="<?php p($l->t('e.g. Playground <playground@lists.domain.tld>')); ?>"
               title="<?php echo $toolTips['emailform:transport:announcements:mailing-list']; ?>"
        />
        <br class="input-field-separater"/>
        <label for ="<?= ConfigConstants::ANNOUNCEMENTS_MAILING_LIST_KEY ?>Autoconf"
               title="<?php echo $toolTips['mailing-list:announcements:autoconf']; ?>"
        >
          <input type="button"
                 name="<?= ConfigConstants::ANNOUNCEMENTS_MAILING_LIST_KEY ?>Autoconf"
                 id="<?= ConfigConstants::ANNOUNCEMENTS_MAILING_LIST_KEY ?>Autoconf"
                 value="<?php echo $l->t('configure'); ?>"
          />
          <?php p($l->t('auto-configure announcements mailing-list')); ?>
        </label>
      </fieldset>
      <fieldset class="emailidentity flex-container">
        <legend><?php p($l->t('Bulk Email Identity')); ?></legend>
       <div class="flex-container flex-column flex-grow">
          <label for="<?= ConfigConstants::EMAIL_FROM_ADDRESS_KEY ?>"
                 class="settings-input-label"
                 title="<?= $toolTips['emailform:sender:address']; ?>"
          >
            <span><?php echo $l->t('"From:" name and address');?></span>
          </label>
          <input type="text"
                 name="<?= ConfigConstants::EMAIL_FROM_ADDRESS_KEY ?>"
                 id="<?= ConfigConstants::EMAIL_FROM_ADDRESS_KEY ?>"
                 value="<?= $emailAddressValue ?>"
                 placeholder="<?= $l->t('e.g. Orchestra <name@domain.tld>');?>"
                 title="<?= $toolTips['emailform:sender:address']; ?>"
          />
        </div>
        <div class="flex-container flex-column flex-grow">
          <label for="<?= ConfigConstants::EMAIL_FROM_DOMAIN_KEY ?>"
                 class="settings-input-label"
                 title="<?= $toolTips['emailform:sender:domain']; ?>"
          >
            <span><?php echo $l->t('"From:" domain for individual senders');?></span>
          </label>
          <input type="text"
                 name="<?= ConfigConstants::EMAIL_FROM_DOMAIN_KEY ?>"
                 id="<?= ConfigConstants::EMAIL_FROM_DOMAIN_KEY ?>"
                 value="<?= $_[ConfigConstants::EMAIL_FROM_DOMAIN_KEY] ?>"
                 placeholder="<?= $l->t('e.g. domain.tld');?>"
                 title="<?= $toolTips['emailform:sender:domain']; ?>"
          />
        </div>
      </fieldset>
      <fieldset class="bulk-email-subject">
        <legend><?php p($l->t('Bulk Email Subject')); ?></legend>
        <div class="bulk-email-subject container">
          <span class="bulk-email-subject tag">[</span>
          <input type="text"
                 name="<?= ConfigConstants::BULK_EMAIL_SUBJECT_TAG ?>"
                 id="<?= ConfigConstants::BULK_EMAIL_SUBJECT_TAG ?>"
                 class="tooltip-auto"
                 value="<?php p(${ConfigConstants::BULK_EMAIL_SUBJECT_TAG}); ?>"
                 title="<?php echo $toolTips['emailform:composer:subject:tag']; ?>"
                 size="5"
          />
          <span class="bulk-email-subject tag"><?php p('-' . $l->t('ProjectNameYYYY') . ']'); ?></span>
          <span class="bulk-email-subject"><?php p($l->t('Example Subject')); ?></span>
        </div>
      </fieldset>
      <fieldset class="email-attachments">
        <legend><?php p($l->t('Attachment Policy')); ?></legend>
        <label for="<?= ConfigConstants::ATTACHMENT_LINK_SIZE_LIMIT ?>"
               class="tooltip-auto"
               title="<?php echo $toolTips['emailform:composer:attachments:link:size-limit']; ?>"
        >
          <input type="text"
                 id="<?= ConfigConstants::ATTACHMENT_LINK_SIZE_LIMIT ?>"
                 name="<?= ConfigConstants::ATTACHMENT_LINK_SIZE_LIMIT ?>"
                 class="<?= ConfigConstants::ATTACHMENT_LINK_SIZE_LIMIT ?> tooltip-auto"
                 value="<?php p(${ConfigConstants::ATTACHMENT_LINK_SIZE_LIMIT}); ?>"
                 placeholder="<?php p($l->t('e.g. 4.7 GB')); ?>"
          />
          <?php p($l->t('Attachment Link Size Limit')); ?>
        </label>
        <br class="input-field-separater"/>
        <label for="<?= ConfigConstants::ATTACHMENT_LINK_EXPIRATION_LIMIT ?>"
               class="tooltip-auto"
               title="<?php echo Util::htmlEscape($toolTips['emailform:composer:attachments:link:expiration-limit']); ?>"
        >
          <input type="text"
                 id="<?= ConfigConstants::ATTACHMENT_LINK_EXPIRATION_LIMIT ?>"
                 class="<?= ConfigConstants::ATTACHMENT_LINK_EXPIRATION_LIMIT ?> tooltip-auto"
                 name="<?= ConfigConstants::ATTACHMENT_LINK_EXPIRATION_LIMIT ?>"
                 value="<?php p(${ConfigConstants::ATTACHMENT_LINK_EXPIRATION_LIMIT}); ?>"
                 placeholder="<?php p($l->t('e.g. 7 days')); ?>"
          />
          <?php p($l->t('Attachment Link Expiration Limit')); ?>
        </label>
        <br class="input-field-separater"/>
        <label for="<?= ConfigConstants::CLOUD_ATTACHMENT_ALWAYS_LINK ?>"
               class="tooltip-auto hidden"
               title="<?= $toolTips['emailform:composer:attachments:link:cloud-always'] ?>"
               disabled
        >
          <input type="checkbox"
                 id="<?= ConfigConstants::CLOUD_ATTACHMENT_ALWAYS_LINK ?>"
                 class="<?= ConfigConstants::CLOUD_ATTACHMENT_ALWAYS_LINK ?> checkbox tooltip-auto hidden"
                 name="<?= ConfigConstants::CLOUD_ATTACHMENT_ALWAYS_LINK ?>"
                 type="checkbox"
                 disabled
          />
          <?php p($l->t('Always Link Cloud Files')); ?>
        </label>
      </fieldset>
      <fieldset class="pre-send-validation">
        <legend><?php p($l->t('Pre-Send Validation')); ?></legend>
        <input id="<?= ConfigConstants::PRE_SEND_VALIDATION_EXTERNAL_LINKS_SSL_VERIFY ?>"
               type="checkbox"
               class="checkbox"
               name="<?= ConfigConstants::PRE_SEND_VALIDATION_EXTERNAL_LINKS_SSL_VERIFY ?>"
               <?= (${ConfigConstants::PRE_SEND_VALIDATION_EXTERNAL_LINKS_SSL_VERIFY} ?? '') == 'off' ? '' : 'checked="checked"'; ?>
        />
        <label for="<?= ConfigConstants::PRE_SEND_VALIDATION_EXTERNAL_LINKS_SSL_VERIFY ?>"
               title="<?= $toolTips['emailform:composer:validation:external-links:ssl'] ?>"
        >
          <?php p($l->t('Validate SSL certificates of external links')); ?>
        </label>
        <br class="input-field-separater"/>
        <input id="<?= ConfigConstants::PRE_SEND_VALIDATION_EXTERNAL_LINKS_ENFORCE_HTTPS ?>"
               type="checkbox"
               class="checkbox"
               name="<?= ConfigConstants::PRE_SEND_VALIDATION_EXTERNAL_LINKS_ENFORCE_HTTPS ?>"
               <?= (${ConfigConstants::PRE_SEND_VALIDATION_EXTERNAL_LINKS_ENFORCE_HTTPS} ?? '') == 'off' ? '' : 'checked="checked"'; ?>
        />
        <label for="<?= ConfigConstants::PRE_SEND_VALIDATION_EXTERNAL_LINKS_ENFORCE_HTTPS ?>"
               title="<?= $toolTips['emailform:composer:validation:external-links:enforce-ssl'] ?>"
        >
          <?php p($l->t('Enforce https:// for external links')); ?>
        </label>
      </fieldset>
    </form>
    <h4><?php echo $l->t('Bulk Email Privacy Notice'); ?></h4>
    <form class="<?= $privacyNoticeKey ?> bulk-email-privacy-notice">
      <fieldset class="<?= $privacyNoticeKey ?> bulk-email-privacy-notice">
        <legend><?php p($l->t(
                'This is the notice attached to all bulk-emails which are not directed to a mailing list or project-participants.'
                . ' The idea is to give the recipients information why they receive the message'
                . ' and how they can prevent further messages if they consider such messages as spam.')); ?></legend>
        <div class="bulk-email-privacy-notice container">
          <textarea class="<?= $privacyNoticeKey ?> bulk-email-privacy-notice wysiwyg"
                    name="<?= $privacyNoticeKey ?>"
                    id="<?= $privacyNoticeKey ?>"
                    cols="60"
                    rows="10"><?php p($_[$privacyNoticeKey]); ?></textarea>
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
        <label for="<?= $mailingListConfig['domain'] ?>"
               title="<?php echo $toolTips['mailing-list:domain:config']; ?>"
        >
          <input type="text"
                 name="<?= $mailingListConfig['domain'] ?>"
                 id="<?= $mailingListConfig['domain'] ?>"
                 value="<?php p($_[$mailingListConfig['domain']]) ?>"
                 placeholder="<?php p('e.g. lists.tld');?>"
                 required
          />
          <?php echo $l->t('Mailing-List Domain');?>
        </label>
        <br/>
        <label for="<?= $mailingListConfig['web'] ?>"
               title="<?php echo $toolTips['mailing-list:domain:config']; ?>"
        >
          <input type="text"
                 name="<?= $mailingListConfig['web'] ?>"
                 id="<?= $mailingListConfig['web'] ?>"
                 value="<?php p($_[$mailingListConfig['web']]) ?>"
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
        <label for="<?= $mailingListRestConfig['url'] ?>"
               title="<?php echo $toolTips['mailing-list:restapi:url']; ?>"
        >
          <input type="text"
                 name="<?= $mailingListRestConfig['url'] ?>"
                 id="<?= $mailingListRestConfig['url'] ?>"
                 value="<?php p($_[$mailingListRestConfig['url']]); ?>"
                 placeholder="<?php p('http://localhost:8001');?>"
                 required
          />
          <?php echo $l->t('Mailing-List REST URL');?>
        </label>
        <br/>
        <label for="<?= $mailingListRestConfig['user'] ?>"
               title="<?php echo $toolTips['mailing-list:restapi:user']; ?>"
        >
          <input type="text"
                 name="<?= $mailingListRestConfig['user'] ?>"
                 id="<?= $mailingListRestConfig['user'] ?>"
                 autocomplete="username"
                 value="<?php p($_[$mailingListRestConfig['user']]); ?>"
                 placeholder="<?php echo $l->t('User');?>"
                 required
                 autocomplete="username"
          />
          <?php echo $l->t('REST User');?>
        </label>
        <br/>
        <label for="<?= $mailingListRestConfig['password'] ?>"
               title="<?php echo $toolTips['mailing-list:restapi:password']; ?>"
        >
          <div class="password-container inline-block">
            <input class="cafevdb-password"
                   name="<?= $mailingListRestConfig['password'] ?>"
                   type="password"
                   autocomplete="current-password"
                   value="<?php p($_[$mailingListRestConfig['password']]); ?>"
                   id="<?= $mailingListRestConfig['password'] ?>"
                   placeholder="<?php echo $l->t('Password');?>"
                   data-typetoggle="#<?= $mailingListRestConfig['password'] ?>-show"
                   required
            />
            <input class="cafevdb-password-show"
                   type="checkbox"
                   id="<?= $mailingListRestConfig['password'] ?>-show"
                   name="<?= $mailingListRestConfig['password'] ?>-show"
            />
            <label class="cafevdb-password-show"
                   for="<?= $mailingListRestConfig['password'] ?>-show">
              <?php echo $l->t('show');?>
            </label>
          </div>
          <?php echo $l->t('REST Password');?>
        </label>
      </fieldset>
      <fieldset class="auto-generated-list">
        <legend class="tooltip-auto"
                title="<?php echo $toolTips['mailing-list:generated:defaults']; ?>"
        >
          <?php p($l->t('Defaults for Generated Lists')); ?>
        </legend>
        <label for="<?= $mailingListConfig['owner'] ?>"
               title="<?php echo $toolTips['mailing-list:generated:defaults:owner']; ?>"
        >
          <input type="text"
                 name="<?= $mailingListConfig['owner'] ?>"
                 id="<?= $mailingListConfig['owner'] ?>"
                 value="<?php p($_[$mailingListConfig['owner']]); ?>"
                 placeholder="<?php p($l->t('someone@somewhere.tld')); ?>"
          />
          <?php p($l->t('Default List-Owner'));?>
        </label>
        <br/>
        <label for="<?= $mailingListConfig['moderator'] ?>"
               title="<?php echo $toolTips['mailing-list:generated:defaults:moderator']; ?>"
        >
          <input type="text"
                 name="<?= $mailingListConfig['moderator'] ?>"
                 id="<?= $mailingListConfig['moderator'] ?>"
                 value="<?php p($_[$mailingListConfig['moderator']]); ?>"
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
      <input id="<?= ConfigConstants::EMAIL_TEST_MODE ?>"
             type="checkbox"
             class="checkbox"
             name="<?= ConfigConstants::EMAIL_TEST_MODE ?>"
             <?= $_[ConfigConstants::EMAIL_TEST_MODE] == 'on' ? 'checked="checked"' : ''; ?>
      />
      <label for="<?= ConfigConstants::EMAIL_TEST_MODE ?>"
             title="<?php echo $l->t('Email test-mode; send emails only to the email test-address.') ?>">
        <?php echo $l->t('Test-Mode'); ?>
      </label>
      <input type="text" name="<?= ConfigConstants::EMAIL_TEST_ADDRESS_KEY ?>" id="<?= ConfigConstants::EMAIL_TEST_ADDRESS_KEY?>"
             value="<?= $emailTestAddressValue ?>"
             placeholder="<?php echo $l->t('e.g. John Doe <john@doe.org>');?>"
      />
      <label for="<?= ConfigConstants::EMAIL_TEST_ADDRESS_KEY ?>"><?php echo $l->t('Test address');?></label>
    </form>
  </div>
</div>
