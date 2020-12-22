<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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
/**@file
 * Error-status page for email-form validation
 *
 * @param $_['ProjectName'] optional
 *
 * @param $_['ProjectId'] optional
 *
 * @param $_['Diagnostics'] array, all fields are optional, recognized
 * records are:
 *
 * array(
 *   'Explanations' => TEXT,
 *   'AddressValidation' => array(
 *     'CC' => array(LIST_OF_BROKEN_EMAILS),
 *     'BCC' => array(LIST_OF_BROKEN_EMAILS),
 *     'Empty' => true (no recipients not allowed)
 *     ),
 *   'TemplateValidation' => array(
 *     'MemberErrors' => array(LIST_OF_FAILED_SUBSTITUTIONS),
 *     'GlobalErrors' => array(LIST_OF_FAILED_SUBSTITUTIONS),
 *     'SpuriousErrors' => array(LIST_OF_FAILED_SUBSTITUTIONS)
 *     )
 *   ),
 *   'SubjectValidation' => true/false (empty subject not allowed),
 *   'FromValidation' => true/false (empty name not allowed),
 *   'MailerException' => EXCEPTION_MESSAGE_FROM_PHP_MAILER,
 *   'Message' => FIRST_FEW_LINES_OF_SENT_MESSAGE
 */

namespace CAFEVDB {

  $admin = $roles->cloudAdminContact();

  $diagnostics = $_['Diagnostics'];
  $numTotal = $diagnostics['TotalCount'];
  $numFailed = $diagnostics['FailedCount'];

  $output = false; // set to true if anything has been printed

  /*****************************************************************************
   *
   * Overall status
   *
   */

  if ($numTotal > 0 && $numFailed == 0) {
    $output = true;
    if ($numTotal == 1) {
      echo '
<div class="emailform error group messagecount">
  <span class="error caption messagecount">
    '.$l->t('The mailing software did not signal an error.').
    ' '.
    $l->t('The message was propably sent out successfully.').'
  </span>
</div>';
    } else {
      echo '
<div class="emailform error group messagecount">
  <span class="error caption messagecount">
    '.$l->t('The mailing software did not signal an error. ').
        ' '.
        $l->t('%d messages were propably sent out successfully.',
             array($numTotal)).'
  </span>
</div>';
    }
  } else if ($numFailed > 0) {
    $output = true;
    echo '
<div class="emailform error group messagecount">
  <span class="error caption messagecount">
    '.$l->t('The mailing software encountered errors.');
    if ($numTotal > 1) {
      if ($numFailed == $numTotal) {
        echo '
    '.$l->t('Sending of all %d messages has failed, propably no message has been sent.',
           array($numTotal));
      } else if ($numFailed == 1) {
        echo '
    '.$l->t('One (out of %d) message has probably not been sent.',
           array($numTotal));
      } else {
        echo '
    '.$l->t('%d (out of %d) messages have probably not been sent.',
           array($numFailed, $numTotal));
      }
    } else {
      echo '
    '.$l->t('The message has probably not been sent.',
           array($numTotal));
    }
    echo '
  </span>
</div>';
  }

  /*****************************************************************************
   *
   * Failed template substitutions
   *
   */

  $templateDiag = $diagnostics['TemplateValidation'];
  if (!empty($templateDiag)) {
    $output = true;
    $leadIns = array('MemberErrors' => $l->t('Failed individual substitutions'),
                     'GlobalErrors' => $l->t('Failed global substitutions'),
                     'SpuriousErrors' => $l->t('Other failed substitutions'));
    echo '
<div class="emailform error group substitutions">
  <span class="error caption substitutions">
    '.$l->t('The operation failed due to template validation errors. '.
    'The following template substitutions could not be resolved:').'
  </span>';
    foreach($templateDiag as $key => $failed) {
      $cssTag = Navigation::camelCaseToDashes($key);
      echo '
  <div class="error contents substitutions '.$cssTag.'">
    <span class="error heading">'.$leadIns[$key].'</span>
    <ul>';
      foreach($failed as $failure) {
        echo '
      <li><span class="error item contents substitutions">'.$failure.'</span></li>';
      }
      echo '
    </ul>
  </div>';
    }
    $explanations =
    $l->t("Please understand that the software is really `picky'; ".
"names have to match exactly. ".
"Please use only capital letters for variable names. ".
"Please do not use spaces. Vaiable substitutions have to start with ".
"a dollar-sign `%s', be enclosed by curly braces `%s' and consist of a ".
"category-name (e.g. `%s') separated by double colons `%s' from ".
"the variable name itself (e.g. `%s'). An example is ".
"`%s'. ".
"Please have a look at the example template `%s' which contains ".
"a complete list of all known substitutions.",
         array('<span class="error code">$</span>',
               '<span class="error code">{...}</span>',
               '<span class="error code">GLOBAL</span>',
               '<span class="error code">::</span>',
               '<span class="error code">ORGANIZER</span>',
               '<span class="error code">${GLOBAL::ORGANIZER}</span>',
               '<span class="error code">All Variables</span>'));
    echo ' <div class="error contents explanations">
    '.$explanations.'
  </div>';
    echo '
</div>';
  }

  /*****************************************************************************
   *
   * Failed email validations
   *
   */

  $addressDiag = $diagnostics['AddressValidation'];
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
    foreach(array('CC', 'BCC') as $header) {
      $addresses = $addressDiag[$header];
      if (!empty($addresses)) {
        $lcHeader = strtolower($header);
        echo '
  <div class="error contents addresses '.$lcHeader.'">
    <span class="error heading">
      '.$l->t("Broken `%s' addresses", array(ucfirst($lcHeader).':')).'
    </span>
    <ul>';
        foreach($addresses as $address) {
          echo '
      <li><span class="error item contents adresses">'.$address.'</span></li>';
        }
        echo '
    </ul>
  </div>';
      }
    }
    $explanations =
    Util::htmlEncode(
      $l->t('No email will be sent out unless these errors are corrected. '.
'Please separate individual emails by commas. '.
'Please use standard-address notation '.
'(see RFC5322, if your want to know ...), '.
'remember to enclose the '.
'real-name in quotes if it contains a comma. Some valid examples are:')).
'
    <ul>
      <li><span class="error code">'.Util::htmlEncode('"Doe, John" <john@doe.org>').'</span></li>
      <li><span class="error code">'.Util::htmlEncode('John Doe <john@doe.org>').'</span></li>
      <li><span class="error code">'.Util::htmlEncode('john@doe.org (John Doe)').'</span></li>
      <li><span class="error code">'.Util::htmlEncode('john@doe.org').'</span></li>
    </ul>';
    echo '
  <div class="error contents explanations">
  <div class="error heading">'.$l->t('Explanations').'</div>
    '.$explanations.'
  </div>';
    echo '
</div>';
  }

  /*****************************************************************************
   *
   * More trivial stuff: empty fields which should not be empty
   *
   */

  if ($diagnostics['SubjectValidation'] !== true) {
    // empty subject
    $output = true;
    $subjectTag = $diagnostics['SubjectValidation'];
    echo '
<div class="emailform error group emptysubject">
  <div class="error contents emptysubject">
    <div class="error caption emptysubject">'.$l->t('Empty Subject').'</div>
    '.$l->t('The subject must not consist of `%s\' as only part. '.
    'Please correct that before trying send the message out, and also before trying to save the message as draft. Thanks.',
           array($subjectTag)).'
  </div>
</div>';
  }

  if ($diagnostics['FromValidation'] !== true) {
    // empty from name
    $output = true;
    $defaultSender = $diagnostics['FromValidation'];
    echo '
<div class="emailform error group emptyfrom">
  <div class="error contents emptyfrom">
    <div class="error caption emptyfrom">'.$l->t('Empty Sender Name').'</div>
    '.$l->t('The sender name should not be empty. '.
    'Originally, it used to be %s, but seemingly this did not suite your needs. '.
    'Please fill in a non-empty sender name before hitting the `Send\'-button again.',
           array($defaultSender)).'
  </div>
</div>';
  }

  if ($diagnostics['AddressValidation']['Empty']) {
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

  /*****************************************************************************
   *
   * File-attachments which misteriously have vanished
   *
   */
  if (!empty($diagnostics['AttachmentValidation']['Files'])) {
  }

  /*****************************************************************************
   *
   * Event-attachments which misteriously are no longer there
   *
   */
  if (!empty($diagnostics['AttachmentValidation']['Events'])) {
    $output = true;

    $failedEvents = $diagnostics['AttachmentValidation']['Events'];
    echo '
<div class="emailform error group attachments events">
  <div class="error contents attachments events">
    <span class="error caption attachments events">
      '.$l->t('The event(s) with the following id(s) could not be attached; '.
      'they do not seem to exists:').'
    </span>
    <ul>';
    foreach($failedEvents as $event) {
      echo '
      <li><span class="error item contents">'.$event.'</span></li>';
    }
    echo '
    </ul>
  </div>';
    $mailto = $admin['email'].
              '?subject='.rawurlencode('[CAFEVDB-InternalError] Event Attachments Do not Exist');
    $mailto = '<span class="error email"><a href="mailto:'.$mailto.'">'.$admin['name'].'</a></span>';
    $explanations = $l->t('This is probably an internal error. Please contact %s. '.
                    'It may be possible to simply click on the red, underlined text '.
                    'in order to compose a usefull message.',
                         array($mailto));
    echo '
  <div class="error contents explanations">
    <div class="error heading">'.$l->t('Explanations').'</div>
    '.$explanations.'
  </div>';
    echo '
</div>';
  }

  /*****************************************************************************
   *
   * This is really evil. Internal erros generated by PHPMailer.
   *
   */
  if (!empty($diagnostics['MailerExceptions'])) {

    $output = true;

    $exceptions = $diagnostics['MailerExceptions'];
    $failedEvents = $diagnostics['AttachmentValidation']['Events'];
    echo '
<div class="emailform error group attachments events">
  <div class="error contents attachments events">
    <span class="error caption attachments events">
      '.$l->t('While trying to send the message(s), the following exception(s) were caught:').'
    </span>
    <ul>';
    foreach($exceptions as $exception) {
      echo '
      <li><span class="error item contents exception name">'.$exception.'</span></li>';
    }
    echo '
    </ul>
  </div>';
    $mailto = $admin['email'].
              '?subject='.rawurlencode('[CAFEVDB-Exception] Exceptions from Email-Form').
              '&body='.rawurlencode(implode("\r\n", $exceptions));
    $explanations = $l->t('This is an internal error. '.
                    'Please copy this page and send it via email to %s.'.
                    'It may be possible to simply click on the red, underlined text '.
                    'in order to compose a usefull message.',
                         array('<span class="error email">'.
                    '<a href="mailto:'.$mailto.'">'.
                    $admin['name'].
                    '</a>'.
                    '</span>'));
    echo '
  <div class="error contents explanations">
  <div class="error heading">'.$l->t('Explanations').'</div>
    '.$explanations.'
  </div>';
    echo '
</div>';

  }

  /*****************************************************************************
   *
   * The following can -- in principle -- never be set, as we use
   * exceptions to catch the errors generated by the PHPMailer class.
   *
   */
  if (!empty($diagnostics['MailerErrors'])) {

    $output = true;

    $errors = $diagnostics['MailerErrors'];
    $failedEvents = $diagnostics['AttachmentValidation']['Events'];
    echo '
<div class="emailform error group attachments events">
  <div class="error contents attachments events">
    <span class="error caption attachments events">
      '.$l->t('While trying to send the message(s), the following error(s) have been encountered:').'
    </span>
    <ul>';
    foreach($errors as $error) {
      echo '
      <li><span class="error item contents exception name">'.$error.'</span></li>';
    }
    echo '
    </ul>
  </div>';
    $mailto = $admin['email'].
              '?subject='.rawurlencode('[CAFEVDB-ImpossibleMailerErrors] Errors from Email-Form').
              '&body='.rawurlencode(implode("\r\n", $errors));
    $explanations = $l->t('This is an internal error. '.
                    'Please copy this page and send it via email to %s. '.
                    'It may be possible to simply click on the red, underlined text '.
                    'in order to compose a usefull message.',
                         array('<span class="error email">'.
                    '<a href="mailto:'.$mailto.'">'.
                    $admin['name'].
                    '</a>'.
                    '</span>'));
    echo '
  <div class="error contents explanations">
  <div class="error heading">'.$l->t('Explanations').'</div>
    '.$explanations.'
  </div>';
    echo '
</div>';

  }

  /*****************************************************************************
   *
   * Detected message duplicates.
   *
   */
  if (!empty($diagnostics['Duplicates'])) {
    $output = true;

    $duplicates = $diagnostics['Duplicates'];
    echo '
<div class="emailform error group duplicates">
  <div class="error contents duplicates">
    <span class="error caption duplicates">
      '.$l->t('Message Duplicates Detected!').'
    </span>
  </div>';
    foreach($duplicates as $duplicate) {
      if (empty($duplicate['recipients'])) {
        // This is very likely just the Cc: to the shared email account.
        continue;
      }
      $dates = $duplicate['dates'];
      echo '
  <div class="error contents duplicates>
    <span class="error heading">
      '.$l->t('Message already sent at time %s to the following recipient(s):',
             array($dates)).'
    </span>
    <ul>';
      foreach($duplicate['recipients'] as $address) {
        echo '
      <li><span class="error item contents adresses">'.Util::htmlEncode($address).'</span></li>';
      }
      echo '
    </ul>
  </div>';
    }
    $errorBody = '';
    foreach ($duplicates as $duplicate) {
      $errorBody .= "\n".
                    "Date of Duplicate:\n".$duplicate['dates']."\n".
                    "Failed Recipients:\n".implode(', ', $duplicate['recipients'])."\n".
                    "All Recipients MD5:\n".$duplicate['bulkMD5']."\n".
                    "All Recipients:\n".$duplicate['bulkRecipients']."\n".
                    "Text-MD5:\n".$duplicate['textMD5']."\n".
                    "Text:\n".$duplicate['text'];
    }
    $mailto = $admin['email'].
              '?subject='.rawurlencode('[CAFEVDB-EmailDuplicate] Probably False Positive').
              '&body='.rawurlencode($errorBody);
    $mailto = '<span class="error email"><a href="mailto:'.$mailto.'">'.$admin['name'].'</a></span>';
    $explanations =
    $l->t('The email-form refuses to send email twice to the same recipients. '.
'In order to send out your email you have either to change the subject '.
'or the message body. If your message has been constructed from a pre-defined '.
'message-template (like the one for the yearly adress-validation) then '.
'please add a self-explaining subject. Otherwise the error is probably '.
'really on your side. Mass-emails should never be submitted twice. If in doubt '.
'contact %s. Please add a detailed description.',
         array($mailto));
    echo '
  <div class="error contents explanations">
    <div class="error heading">'.$l->t('Explanations').'</div>
    '.$explanations.'
  </div>';
    echo '
</div>';

  }

  /*****************************************************************************
   *
   * Copy-to-Sent failures. These are not fatal (messages have probably
   * been sent), but still: we want to have our copy in the Sent-folder.
   *
   */
  if (!empty($diagnostics['CopyToSent'])) {
    $output = true;

    $copyErrors = $diagnostics['CopyToSent'];
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
           $l->t('Could not authenticate with IMAP-server: %s',
                array($loginError)).'
  </div>';
    }
    if (isset($copyErrors['copy'])) {
      $folderErrors = $copyErrors['copy'];
      foreach($folderErrors as $folder => $error) {
        $errorBody .= "Folder Error:\n".$folder." -- ".$error."\n";
        echo '
  <div class="error contents copytosent">'.
             $l->t('Copy to folder %s has failed: %s',
                  array($folder, $error)).'
  </div>';
      }
    }
    $mailto = $admin['email'].
              '?subject='.rawurlencode('[CAFEVDB-CopyToSent] IMAP Error').
              '&body='.rawurlencode($errorBody);
    $mailto = '<span class="error email"><a href="mailto:'.$mailto.'">'.$admin['name'].'</a></span>';
    $explanations =
    $l->t('If no other error messages are echoed on this page, then '.
'the emails have probably been sent successfully. However, copying '.
'the sent-out message to the sent-folder on the email-server has failed. '.
'This is nothing you can solve on your own, please contact %s. '.
'It may be possible to simply click on the red, underlined text '.
'in order to compose a usefull message.',
         array($mailto));
    echo '
  <div class="error contents explanations">
    <div class="error heading">'.$l->t('Explanations').'</div>
    '.$explanations.'
  </div>';
    echo '
</div>';

  }

  /*****************************************************************************
   *
   * Notify once more about the attached event after successful sending.
   *
   */
  if (!empty($diagnostics['Message']['Events'])) {
    $output = true;

    $events = $diagnostics['Message']['Events'];

    echo '
<div class="emailform error group message events">
  <span class="error caption message events">
    '.$l->t('The Following Events have been attached to the Message:').'
  </span>
  <div class="error contents message events">
    <ul>';
    foreach($events as $event) {
      echo '
      <li><span class="error item contents">'.$event.'</span></li>';
    }
    echo '
    </ul>
  </div>
</div>';

  }

  /*****************************************************************************
   *
   * Notify once more about the attached files after successful sending.
   *
   */
  if (!empty($diagnostics['Message']['Files'])) {
    $output = true;

    $files = $diagnostics['Message']['Files'];

    echo '
<div class="emailform error group message files">
  <span class="error caption message files">
    '.$l->t('The following files have been attached to the message:').'
  </span>
  <div class="error contents message files">
    <ul>';
    foreach($files as $file) {
      echo '
      <li><span class="error item contents">'.$file.'</span></li>';
    }
    echo '
    </ul>
  </div>
</div>';

  }

  /*****************************************************************************
   *
   * Notify about the start of the raw message after successful
   * sending. This is primarily valuable for me :)
   *
   */
  if ($diagnostics['Message']['Text'] != '') {
    // no recipients
    $output = true;

    $text = $diagnostics['Message']['Text'];
    echo '
<div class="emailform error group message text">
  <div class="error caption message text">'.$l->t('First Few Lines of Sent Message').'</div>
  <div class="error contents message text">
    <pre>'.Util::htmlEncode($text).'</pre>
  </div>
</div>';
  }

  /*****************************************************************************
   *
   * Final notes. Also show up in the status log. But so what ;)
   *
   */
  if ($output) {
    echo '
<div class="spacer"><div class="ruler"></div></div>
<div class="emailform error group">
  <div class="emailform error heading">'.$l->t('The most recent status messages are always saved to the status panel. Please see there for detailed diagnostics.').'</div>
</div>
<div class="spacer"><div class="ruler"></div></div>';
  }

} // namespace CAFEVDB

?>
