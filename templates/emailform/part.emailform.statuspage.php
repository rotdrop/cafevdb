<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2020, 2021, 2022, 2023, 2024 Claus-Justus Heine
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
/**
 * Error-status page for email-form validation
 *
 * @param $diagnostics array, all fields are optional, recognized
 * records are:
 *
 * [
 *   'Explanations' => TEXT,
 *   'AddressValidation' => [
 *     'CC' => array(LIST_OF_BROKEN_EMAILS),
 *     'BCC' => array(LIST_OF_BROKEN_EMAILS),
 *     'Empty' => true (no recipients not allowed)
 *     ],
 *   'TemplateValidation' => [
 *     'MemberErrors' => array(LIST_OF_FAILED_SUBSTITUTIONS),
 *     'GlobalErrors' => array(LIST_OF_FAILED_SUBSTITUTIONS),
 *     'SpuriousErrors' => array(LIST_OF_FAILED_SUBSTITUTIONS)
 *     ],
 *   ],
 *   'SubjectValidation' => true/false (empty subject not allowed),
 *   'FromValidation' => true/false (empty name not allowed),
 *   'MailerException' => EXCEPTION_MESSAGE_FROM_PHP_MAILER,
 *   'Message' => FIRST_FEW_LINES_OF_SENT_MESSAGE,
 * ]
 *
 * @todo this doc comment is incomplete
 */

namespace OCA\CAFEVDB;

use OCA\CAFEVDB\EmailForm\Composer;
use OCA\CAFEVDB\Common\Util;

$adminMailto = [];
$adminName = [];
foreach ($cloudAdminContact as $contact) {
  $adminMailto[] = $contact['email'];
  $adminName[] = $contact['name'];
}
$adminMailto = implode(',', $adminMailto);
$adminName = implode(', ', $adminName);

$numTotal = $diagnostics[Composer::DIAGNOSTICS_TOTAL_COUNT];
$numFailed = $diagnostics[Composer::DIAGNOSTICS_FAILED_COUNT];

$output = false; // set to true if anything has been printed

?>
<div class="emailform statuspage">
  <?php

  /*-***************************************************************************
   *
   * Overall status
   *
   */

  $stage = $diagnostics[Composer::DIAGNOSTICS_STAGE];
  ?>
  <div class="emailform error group messagecount">
    <span class="error caption messagecount">
      <?php
      if ($numTotal > 0 && $numFailed == 0) {
        $output = true;
        // if ($numTotal == 1) {
        p($l->t('The mailing software did not signal an error.'));
        p(' ');
        if ($stage == Composer::DIAGNOSTICS_STAGE_SEND) {
          p($l->n(
            'The message was propably sent out successfully.',
            '%n messages were propably sent out successfully.',
            $numTotal,
          ));
        } else {
          p($l->n(
            'The preview message was generated successfully.',
            '%n preview messages successfully generated.',
            $numTotal,
          ));
        }
      } elseif ($numFailed > 0) {
        $output = true;
        p($l->t('The mailing software encountered errors.'));
        p(' ');
        if ($stage == Composer::DIAGNOSTICS_STAGE_SEND) {
          if ($numTotal > 1) {
            if ($numFailed == $numTotal) {
              p($l->t('Sending of all %d messages has failed, propably no message has been sent.', $numTotal));
            } elseif ($numFailed == 1) {
              p($l->t('One (out of %d) message has probably not been sent.', $numTotal));
            } else {
              p($l->t(
                '%d (out of %d) messages have probably not been sent.',
                [ $numFailed, $numTotal, ]
              ));
            }
          } else {
            p($l->t('The message has probably not been sent.'));
          }
        } else {
          if ($numTotal > 1) {
            if ($numFailed == $numTotal) {
              p($l->t('Generating the previerw for all %d messages has failed.', $numTotal));
            } elseif ($numFailed == 1) {
              p($l->t('The preview for one (out of %d) message could not be generated.', $numTotal));
            } else {
              p($l->t(
                'The preview for %d (out of %d) messages could not be generated.',
                [ $numFailed, $numTotal, ]
              ));
            }
          } else {
            p($l->t('A preview for the message could not be generated.'));
          }
        }
      }
      p($l->t('The following lines may contain further diagnostic messages.'));
      ?>
    </span>
  </div>
<?php

  /*-****************************************************************************
   *
   * Failed template substitutions
   *
   */

$templateDiag = $diagnostics[Composer::DIAGNOSTICS_TEMPLATE_VALIDATION];
if (!empty($templateDiag)) {
  $output = true;
  $leadIns = [
    'MemberErrors' => $l->t('Failed individual substitutions'),
    'GlobalErrors' => $l->t('Failed global substitutions'),
    'SpuriousErrors' => $l->t('Other failed substitutions'),
    'PreconditionError' => $l->t('Precondition failed'),
  ];
  echo '
<div class="emailform error group substitutions">
  <span class="error caption substitutions">
  '.$l->t('The operation failed due to template validation errors.
Not all variable substitutions could be resolved:').'
  </span>';
  $needExplanations = false;
  foreach ($templateDiag as $key => $failed) {
    $needExplanations = $needExplanations || ($key != 'PreconditionError');
    $cssTag = Util::camelCaseToDashes($key);
    echo '
  <div class="error contents substitutions '.$cssTag.'">
    <span class="error heading">'.$leadIns[$key].'</span>
    <ul>';
    foreach ($failed as $failure) {
      echo '
      <li><span class="error item contents substitutions">'.print_r($failure, true).'</span></li>';
    }
    echo '
    </ul>
  </div>';
  }
  $explanations = !$needExplanations
    ? ''
    : $l->t(
      "Please understand that the software is really `picky'; ".
      "names have to match exactly. ".
      "Please use only capital letters for variable names. ".
      "Please do not use spaces. Vaiable substitutions have to start with ".
      "a dollar-sign `%s', be enclosed by curly braces `%s' and consist of a ".
      "category-name (e.g. `%s') separated by double colons `%s' from ".
      "the variable name itself (e.g. `%s'). An example is ".
      "`%s'. ".
      "Please have a look at the example template `%s' which contains ".
      "a complete list of all known substitutions.",
      [
        '<span class="error code">$</span>',
        '<span class="error code">{...}</span>',
        '<span class="error code">GLOBAL</span>',
        '<span class="error code">::</span>',
        '<span class="error code">ORGANIZER</span>',
        '<span class="error code">${GLOBAL::ORGANIZER}</span>',
        '<span class="error code">All Variables</span>',
      ]
    );
  echo ' <div class="error contents explanations">
  '.$explanations.'
  </div>';
  echo '
</div>';
}

/*-****************************************************************************
 *
 * Failed email validations
 *
 */

$addressDiag = $diagnostics[Composer::DIAGNOSTICS_ADDRESS_VALIDATION];
if (!empty($addressDiag['CC']) || !empty($addressDiag['BCC'])) {
  $output = true;
  echo '
<div class="emailform error group addresses">
  <div class="error contents addresses">
    <span class="error caption addresses">
  '.$l->t('The following email addresses appear to be syntactically incorrect, '.
          'meaning that they have not the form of an email address:').'
    </span>
  </div>';
  foreach ([ 'CC', 'BCC', ] as $header) {
    $addresses = $addressDiag[$header];
    if (!empty($addresses)) {
      $lcHeader = strtolower($header);
      echo '
  <div class="error contents addresses '.$lcHeader.'">
    <span class="error heading">
  '.$l->t("Broken `%s' addresses", ucfirst($lcHeader).':').'
    </span>
    <ul>';
      foreach ($addresses as $address) {
        echo '
      <li><span class="error item contents adresses">'.$address.'</span></li>';
      }
      echo '
    </ul>
  </div>';
    }
  }
  $explanations =
    htmlentities(
      $l->t('No email will be sent out unless these errors are corrected. '.
            'Please separate individual emails by commas. '.
            'Please use standard-address notation '.
            '(see RFC5322, if your want to know ...), '.
            'remember to enclose the '.
            'real-name in quotes if it contains a comma. Some valid examples are:')).
    '
    <ul>
      <li><span class="error code">'.htmlentities('"Doe, John" <john@doe.org>').'</span></li>
      <li><span class="error code">'.htmlentities('John Doe <john@doe.org>').'</span></li>
      <li><span class="error code">'.htmlentities('john@doe.org (John Doe)').'</span></li>
      <li><span class="error code">'.htmlentities('john@doe.org').'</span></li>
    </ul>';
  echo '
  <div class="error contents explanations">
  <div class="error heading">'.$l->t('Explanations').'</div>
  '.$explanations.'
  </div>';
  echo '
</div>';
}

/*-****************************************************************************
 *
 * More trivial stuff: empty fields which should not be empty
 *
 */

if ($diagnostics[Composer::DIAGNOSTICS_SUBJECT_VALIDATION] !== true) {
  // empty subject
  $output = true;
  $subjectTag = $diagnostics[Composer::DIAGNOSTICS_SUBJECT_VALIDATION];
  echo '
<div class="emailform error group emptysubject">
  <div class="error contents emptysubject">
    <div class="error caption emptysubject">'.$l->t('Empty Subject').'</div>
    '.$l->t('The subject must not consist of "%s" as only part. '.
            'Please correct that before trying send the message out, and also before trying to save the message as draft. Thanks.', $subjectTag).'
  </div>
</div>';
}

if ($diagnostics[Composer::DIAGNOSTICS_FROM_VALIDATION] !== true) {
  // empty from name
  $output = true;
  $defaultSender = $diagnostics[Composer::DIAGNOSTICS_FROM_VALIDATION];
  echo '
<div class="emailform error group emptyfrom">
  <div class="error contents emptyfrom">
    <div class="error caption emptyfrom">'.$l->t('Empty Sender Name').'</div>
    '.$l->t(
      'The sender name should not be empty. '.
      'Originally, it used to be %s, but seemingly this did not suite your needs. '.
      'Please fill in a non-empty sender name before hitting the "Send"-button again.',
      $defaultSender
    ).'
  </div>
</div>';
}

if ($diagnostics[Composer::DIAGNOSTICS_ADDRESS_VALIDATION]['Empty']) {
  // no recipients
  $output = true;
  echo '
<div class="emailform error group norecipients">
  <div class="error contents norecipients">
    <div class="error caption norecipients">'.$l->t('No Recipients').'</div>
    '.$l->t('You did not specify any recipients (Cc: and Bcc: does not count here!).'.
            'Please got to the `Em@il Recipients\' panel and select some before '.
            'hitting the `Send\'-button again. Please possibly take care of the list '.
            'of musicians without email address at the bottom of the `Em@il Recipients\' panel.').'
  </div>
</div>';
}

/*-****************************************************************************
 *
 * External links which could not be followed
 *
 */
if (!$diagnostics[Composer::DIAGNOSTICS_EXTERNAL_LINK_VALIDATION]['Status']) {
  $goodUrls = $diagnostics[Composer::DIAGNOSTICS_EXTERNAL_LINK_VALIDATION]['Good'];
  $badUrls = $diagnostics[Composer::DIAGNOSTICS_EXTERNAL_LINK_VALIDATION]['Bad'];

  // no recipients
  $output = true;
  echo '
<div class="emailform error group broken-external-links">
  <div class="error contents broken-external-links">
    <div class="error caption broken-external-links">'
    . Util::htmlEscape($l->t('The message contains references to external links which could not be followed.'))
    . '</div>
    <div class="error hint broken-external-links">'
    . Util::htmlEscape($l->t(
      'Please understand that the relevant broken part is the link-target which is normally invisible.'
      . ' You can edit the link-target in the message editor by using the context menu (right-click) or the link-button.'))
    . '
    </div>
    <ul>';
  foreach ($diagnostics[Composer::DIAGNOSTICS_EXTERNAL_LINK_VALIDATION]['Bad'] as $info) {
    $url = $info['url'];
    $text = $info['text'];
    echo '
      <li>
        <dl>
           <dt>' . Util::htmlEscape($l->t('Link-Target')) . '</dt><dd>' . Util::htmlEscape($url) . '</dd>
           <dt>' . Util::htmlEscape($l->t('Link-Text')) . '</dt><dd>' . Util::htmlEscape($text) . '</dd>
        </dl>
      </li>';
  }
  echo '
    </ul>
  </div>
</div>';
}

/*-****************************************************************************
 *
 * Public downloads folder which is somehow broken
 *
 */
if (!$diagnostics[Composer::DIAGNOSTICS_SHARE_LINK_VALIDATION]['status']) {

  $output = true;
  $folder = $diagnostics[Composer::DIAGNOSTICS_SHARE_LINK_VALIDATION]['folder'];
  $appLink = $diagnostics[Composer::DIAGNOSTICS_SHARE_LINK_VALIDATION]['appLink'];
  $httpCode = $diagnostics[Composer::DIAGNOSTICS_SHARE_LINK_VALIDATION]['httpCode'];
  ?>
  <div class="emailform error group broken-public-download">
    <div class="error contents broken-public-download">
      <div class="error caption broken-public-download">
        <?php p($l->t('There is something wrong with the pariticipants downloads folder.')); ?>
      </div>
      <dl>
        <?php if ($diagnostics[Composer::DIAGNOSTICS_SHARE_LINK_VALIDATION]['filesCount'] == 0) { ?>
          <dt><?php p($l->t('The folder contains no data.')); ?></dt>
          <dd>
            <p><?php p($l->t('Please visit the following link to examine the situation:')); ?></p>
            <a href="<?php echo $appLink; ?>" target="<?php p(md5($folder)); ?>">
              <?php p($folder); ?>
              <img src="<?php echo $urlGenerator->imagePath('core', 'filetypes/folder-external.svg'); ?>">
            </a>
          </dd>
        <?php } ?>
        <?php if ($httpCode < 200 || $httpCode >= 400) { ?>
          <dt></dt><dd></dd>
        <?php } ?>
      </dl>
    </div>
  </div>
  <?php
}

/*-****************************************************************************
 *
 * Obsolete privacy notice.
 *
 */
if (!$diagnostics[Composer::DIAGNOSTICS_PRIVACY_NOTICE_VALIDATION]['status']) {
  $forbidden = $diagnostics[Composer::DIAGNOSTICS_PRIVACY_NOTICE_VALIDATION]['forbiddenAddress'];
  ?>
  <div class="emailform error group broken-privacy-notice">
    <div class="error contents broken-privacy-notice">
      <div class="error caption broken-privacy-notice">
        <?php p($l->t('Please do not try to add custom privacy notices')); ?>
      </div>
      <div>
        <?php p($l->t('The message contains an explicit link to the opt-out email-address "%s". Do not do this! Please read on:', $forbidden)); ?>
        <ul>
          <li><?php p($l->t('Mailing list traffic does not need it, it has its own privacy notices. Also, people can unregister themselves from a mailing list.')); ?></li>
          <li><?php p($l->t('Project emails do not need it, because we have a justified reason to contact project-participants.')); ?></li>
          <li><?php p($l->t('@All email is routed automatically to mailing lists (see above).')); ?></li>
          <li><?php p($l->t('All remaining cases are handled by the cloud-software and a pre-configured privacy notice is attached automatically.')); ?>
        </ul>
      </div>
    </div>
  </div>
  <?php
}

/*-****************************************************************************
 *
 * File-attachments which misteriously have vanished
 *
 */
if (!empty($diagnostics[Composer::DIAGNOSTICS_ATTACHMENT_VALIDATION]['Files'])) {
  $output = true;

  $failedFiles = $diagnostics[Composer::DIAGNOSTICS_ATTACHMENT_VALIDATION]['Files'];
  echo '
<div class="emailform error group attachments files">
  <div class="error contents attachments files">
    <span class="error caption attachments files">
  '.$l->t('The files(s) with the following name(s) could not be attached; '.
          'they do not seem to exists:').'
    </span>
    <ul>';
  foreach ($failedFiles as $file) {
    echo '
      <li><span class="error item contents">
        <span class="file original-name">'.$file['original_name'].'</span>
        <span class="file tmp-name">('.$file['tmp_name'].')</span>
      </span></li>';
  }
  echo '
    </ul>
  </div>';
  $explanations = $l->t(
    'This is probably an internal error. '
    . 'It may be possible to simply click on the red, underlined text '
    . 'in order to compose a useful message.'
  );
  echo '
  <div class="error contents explanations">
    <div class="error heading">'.$l->t('Explanations').'</div>
    '.$explanations.'
  </div>';
  echo '
</div>';
}

/*-****************************************************************************
 *
 * Event-attachments which misteriously are no longer there
 *
 */
if (!empty($diagnostics[Composer::DIAGNOSTICS_ATTACHMENT_VALIDATION]['Events'])) {
  $output = true;

  $failedEvents = $diagnostics[Composer::DIAGNOSTICS_ATTACHMENT_VALIDATION]['Events'];
  echo '
<div class="emailform error group attachments events">
  <div class="error contents attachments events">
    <span class="error caption attachments events">
  '.$l->t('The event(s) with the following id(s) could not be attached; '.
          'they do not seem to exists:').'
    </span>
    <ul>';
  foreach ($failedEvents as $event) {
    echo '
      <li><span class="error item contents">'.$event.'</span></li>';
  }
  echo '
    </ul>
  </div>';
  $explanations = $l->t(
    'This is probably an internal error. '
  . 'It may be possible to simply click on the red, underlined text '
  . 'in order to compose a useful message.'
  );
  echo '
  <div class="error contents explanations">
    <div class="error heading">'.$l->t('Explanations').'</div>
    '.$explanations.'
  </div>';
  echo '
</div>';
}

/*-****************************************************************************
 *
 * Failed recipients determined during the SMTP communication (i.e. not
 * messages
 *
 */
if (!empty($diagnostics[Composer::DIAGNOSTICS_FAILED_RECIPIENTS])) {

  $output = true;

  $failedRecipients = $diagnostics[Composer::DIAGNOSTICS_FAILED_RECIPIENTS];
  echo '
<div class="emailform error group failed-recipients">
  <div class="error contents failed-recipients">
    <span class="error caption failed-recipients">'
    . $l->t(
      'While sending the message the following recipients failed.'
      . ' All other recipients were successfully sumitted to the email-server,'
      . ' but you should still monitor the email-inbox for messages returned later.'
    )
    . '</span>
    <dl>';
  foreach ($failedRecipients as $failedRecipipient => $errorMessage) {
    echo '
      <dt>' . Util::htmlEscape($failedRecipipient). '</dt>';
    echo '
      <dd>' . Util::htmlEscape($errorMessage). '</dd>';
  }
  echo '
    </dl>
  </div>';
  echo '
</div>';

}

/*-****************************************************************************
 *
 * This is really evil. Internal erros generated by PHPMailer.
 *
 */
if (!empty($diagnostics[Composer::DIAGNOSTICS_MAILER_EXCEPTIONS])) {

  $output = true;

  $exceptions = $diagnostics[Composer::DIAGNOSTICS_MAILER_EXCEPTIONS];
  echo '
<div class="emailform error group exceptions">
  <div class="error contents exceptions">
    <span class="error caption exceptions">
  '.$l->t('While trying to send the message(s), the following exception(s) were caught:').'
    </span>
    <ul>';
  foreach ($exceptions as $exception) {
    echo '
      <li><span class="error item contents exception name">'.htmlspecialchars($exception).'</span></li>';
  }
  echo '
    </ul>
  </div>';
  $mailto = $adminMailto
    . '?subject='.rawurlencode('[CAFEVDB-Exception] Exceptions from Email-Form')
    . '&body='.rawurlencode(implode("\r\n", $exceptions));
  $explanations = $l->t(
    'This is an internal error. '.
    'Please copy this page and send it via email to %s.'.
    'It may be possible to simply click on the red, underlined text '.
    'in order to compose a useful message.',
    [ '<span class="error <?php p($appNameTag) ?> email">'
      . '<a href="mailto:' . $mailto . '">'
      . $adminName
      . '</a>'
      . '</span>' ]
  );
  echo '
  <div class="error contents explanations">
  <div class="error heading">'.$l->t('Explanations').'</div>
  '.$explanations.'
  </div>
</div>';

}

/*-****************************************************************************
 *
 * Detected message duplicates.
 *
 */
if (!empty($diagnostics[Composer::DIAGNOSTICS_DUPLICATES])) {
  $output = true;

  $duplicates = $diagnostics[Composer::DIAGNOSTICS_DUPLICATES];
  echo '
<div class="emailform error group duplicates">
  <div class="error contents duplicates">
    <span class="error caption duplicates">
  '.$l->t('Message Duplicates Detected!').'
    </span>
  </div>';
  foreach ($duplicates as $duplicate) {
    if (empty($duplicate['recipients'])) {
      // This is very likely just the Cc: to the shared email account.
      continue;
    }
    $dates = implode('; ', array_map(function($date) use ($dateTimeFormatter) {
      return $dateTimeFormatter->formatDateTime($date, 'medium');
    }, $duplicate['dates']));
    echo '
  <div class="error contents duplicates>
    <span class="error heading">
    ' . $l->t('Message already sent at time %s to the following recipient(s):', $dates) . '
    </span>
    <ul>';
    foreach ($duplicate['recipients'] as $address) {
      echo '
      <li><span class="error item contents adresses">'.htmlentities($address).'</span></li>';
    }
    echo '
    </ul>
  </div>';
  }
  $errorBody = '';
  foreach ($duplicates as $duplicate) {
    $errorBody .= "\n".
                  "Date of Duplicate:\n".$dates."\n".
                  "Author of Duplicate:\n".implode('; ', $duplicate['authors'])."\n".
                  "Recipients:\n".implode('; ', $duplicate['recipients'])."\n".
                  "Text:\n".$duplicate['text'];
  }
  $mailto = $adminMailto
    . '?subject='.rawurlencode('[CAFEVDB-EmailDuplicate] Probably False Positive')
    . '&body='.rawurlencode($errorBody);
  $mailto = '<span class="error ' . $appNameTag . ' email"><a href="mailto:'.$mailto.'">'.$adminName.'</a></span>';
  $explanations =
    $l->t(
      'The email-form refuses to send email twice to the same recipients. '.
      'In order to send out your email you have either to change the subject '.
      'or the message body. If your message has been constructed from a pre-defined '.
      'message-template (like the one for the yearly adress-validation) then '.
      'please add a self-explaining subject. Otherwise the error is probably '.
      'really on your side. Mass-emails should never be submitted twice. If in doubt '.
      'contact %s. Please add a detailed description.',
      [ $mailto ]
    );
  echo '
  <div class="error contents explanations">
    <div class="error heading">'.$l->t('Explanations').'</div>
    '.$explanations.'
  </div>';
  echo '
</div>';

}

/*-****************************************************************************
 *
 * Copy-to-Sent failures. These are not fatal (messages have probably
 * been sent), but still: we want to have our copy in the Sent-folder.
 *
 */
if (!empty($diagnostics[Composer::DIAGNOSTICS_COPY_TO_SENT])) {
  $output = true;

  $copyErrors = $diagnostics[Composer::DIAGNOSTICS_COPY_TO_SENT];
  echo '
<div class="emailform error group copytosent">
  <span class="error caption copytosent">
  '.$l->t('Copy to Sent-Folder has Failed').'
  </span>';
  $errorBody = "\n";
  $loginError = '';
  if (isset($copyErrors['login'])) {
    $loginError = $copyErrors['login'];
    $errorBody .= "Authentication Error:\n".$loginError."\n";
    echo '
  <div class="error contents copytosent">'.
         $l->t('Could not authenticate with IMAP-server: %s', $loginError).'
  </div>';
  }
  if (isset($copyErrors['copy'])) {
    $folderErrors = $copyErrors['copy'];
    foreach ($folderErrors as $folder => $error) {
      $errorBody .= "Folder Error:\n".$folder." -- ".$error."\n";
      echo '
  <div class="error contents copytosent">' .
           $l->t('Copy to folder %s has failed: %s', [ $folder, $error, ]) . '
  </div>';
    }
  }

  $mailto = $cloudAdminContact['email'].
            '?subject='.rawurlencode('[CAFEVDB-CopyToSent] IMAP Error').
            '&body='.rawurlencode($errorBody);
  $mailto = '<span class="error ' . $appNameTag . ' email"><a href="mailto:'.$mailto.'">'.$cloudAdminContact['name'].'</a></span>';
  $explanations = $l->t(
    'If no other error messages are echoed on this page, then'
    . ' the emails have probably been sent successfully. However, copying'
    . ' the sent-out message to the sent-folder on the email-server has failed.'
    . ' This is nothing you can solve on your own, please contact %s.'
    . ' It may be possible to simply click on the red, underlined text'
    . ' in order to compose a useful message.',
    $mailto
  );
  echo '
  <div class="error contents explanations">
    <div class="error heading">'.$l->t('Explanations').'</div>
    '.$explanations.'
  </div>';
  echo '
</div>';

}

/*-****************************************************************************
 *
 * Notify once more about the attached event after successful sending.
 *
 */
if (!empty($diagnostics[Composer::DIAGNOSTICS_MESSAGE]['Events'])) {
  $output = true;

  $events = $diagnostics[Composer::DIAGNOSTICS_MESSAGE]['Events'];

  echo '
<div class="emailform error group message events">
  <span class="error caption message events">
  '.$l->t('The Following Events have been attached to the Message:').'
  </span>
  <div class="error contents message events">
    <ul>';
  foreach ($events as $event) {
    echo '
      <li><span class="error item contents">'.$event.'</span></li>';
  }
  echo '
    </ul>
  </div>
</div>';

}

/*-****************************************************************************
 *
 * Notify once more about the attached files after successful sending.
 *
 */
if (!empty($diagnostics[Composer::DIAGNOSTICS_MESSAGE]['Files'])) {
  $output = true;

  $files = $diagnostics[Composer::DIAGNOSTICS_MESSAGE]['Files'];

  echo '
<div class="emailform error group message files">
  <span class="error caption message files">
  '.$l->t('The following files have been attached to the message:').'
  </span>
  <div class="error contents message files">
    <ul>';
  foreach ($files as $file) {
    echo '
      <li><span class="error item contents">'.$file.'</span></li>';
  }
  echo '
    </ul>
  </div>
</div>';

}

/*-****************************************************************************
 *
 * Notify about the start of the raw message after successful
 * sending. This is primarily valuable for me :)
 *
 */
if ($diagnostics[Composer::DIAGNOSTICS_MESSAGE]['Text'] != '') {
  // no recipients
  $output = true;

  $text = $diagnostics[Composer::DIAGNOSTICS_MESSAGE]['Text'];
  echo '
<div class="emailform error group message text">
  <div class="error caption message text">'.$l->t('First Few Lines of Sent Message').'</div>
  <div class="error contents message text">
    <pre>'.htmlentities($text).'</pre>
  </div>
</div>';
}

/*-****************************************************************************
 *
 * Final notes. Also show up in the status log. But so what ;)
 *
 */
?>
<?php if ($output) { ?>

  <div class="spacer for-dialog"><div class="ruler"></div></div>
  <div class="emailform error group for-dialog">
    <div class="emailform error heading"><?php p($l->t('The most recent status messages are always saved to the status panel. Please see there for detailed diagnostics.')); ?></div>
  </div>
  <div class="spacer"><div class="ruler"></div></div>
<?php } ?>
</div> <!-- Endo Status Page -->
