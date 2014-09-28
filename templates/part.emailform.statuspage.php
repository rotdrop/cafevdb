<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 *   'Explanations' => <text>
 *   'AddressValidation' => array(
 *     'CC' => array(<list of broken emails>),
 *     'BCC' => array(<list of broken emails>),
 *     'Empty' => true (no recipients not allowed)
 *     ),
 *   'TemplateValidation' => array(
 *     'MemberErrors' => array(<list of failed substitutions>),
 *     'GlobalErrors' => array(<list of failed substitutions>),
 *     'SpuriousErrors' => array(<list of failed substitutions>)
 *     )
 *   ),
 *   'SubjectValidation' => true/false (empty subject not allowed),
 *   'FromValidation' => true/false (empty name not allowed),
 *   'MailerException' => <exception message from PHPMailer>,
 */

use CAFEVDB\L;
use CAFEVDB\Util;
use CAFEVDB\Navigation;
use CAFEVDB\Config;

$admin = Config::adminContact();

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
  if ($numTotal > 1) {
    echo '
<div class="emailform error group messagecount">
  <span class="error caption messagecount">
    '.L::t('The mailing software did not signal an error.').
      L::t('The message was propably sent out successfully.').'
  </span>
</div>';
  } else {
    echo '
<div class="emailform error group messagecount">
  <span class="error caption messagecount">
    '.L::t('The mailing software did not signal an error.').
      L::t('%d messages were propably sent out successfully.').'
  </span>
</div>';
  }
} else if ($numFailed > 0) {
  $output = true;
  echo '
<div class="emailform error group messagecount">
  <span class="error caption messagecount">
    '.L::t('The mailing software encountered errors.');
  if ($numTotal > 1) {
    if ($numFailed == $numTotal) {
      echo '
    '.L::t('Sending of all %d messages has failed, propably no message has been sent.',
           array($numTotal));
    } else if ($numFailed == 1) {
      echo '
    '.L::t('One (out of %d) message has probably not been sent.',
           array($numTotal));
    } else {
      echo '
    '.L::t('%d (out of %d) messages have probably not been sent.',
           array($numTotal));
    }
  } else {
    echo '
    '.L::t('The message has probably not been sent.',
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
  $leadIns = array('MemberErrors' => L::t('Failed individual substitutions'),
                   'GlobalErrors' => L::t('Failed global substitutions'),
                   'SpuriousErrors' => L::t('Other failed substitutions'));
  echo '
<div class="emailform error group substitutions">
  <span class="error caption substitutions">
    '.L::t('The operation failed due to template validation errors. '.
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
    L::t("Please understand that the software is really `picky'; ".
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
      '.L::t('The following email addresses appear to be syntactically incorrect, '.
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
      '.L::t("Broken `%s' addresses", array(ucfirst($lcHeader).':')).'
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
    htmlspecialchars(
      L::t('No email will be sent out unless these errors are corrected. '.
           'Please separate individual emails by commas. '.
           'Please use standard-address notation '.
           '(see RFC5322, if your want to know ...), '.
           'remember to enclose the '.
           'real-name in quotes if it contains a comma. Some valid examples are:')).
    '
    <ul>
      <li><span class="error code">'.htmlspecialchars('"Doe, John" <john@doe.org>').'</span></li>
      <li><span class="error code">'.htmlspecialchars('John Doe <john@doe.org>').'</span></li>
      <li><span class="error code">'.htmlspecialchars('john@doe.org (John Doe)').'</span></li>
      <li><span class="error code">'.htmlspecialchars('john@doe.org').'</span></li>
    </ul>';
  echo '
  <div class="error contents explanations">
  <div class="error heading">'.L::t('Explanations').'</div>
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
    <div class="error caption emptysubject">'.L::t('Empty Subject').'</div>
    '.L::t('The subject must not consist of `%s\' as only part. '.
           'Please correct that before hitting the `Send\'-button again.',
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
    <div class="error caption emptyfrom">'.L::t('Empty Sender Name').'</div>
    '.L::t('The sender name should not be empty. '.
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
    <div class="error caption norecipients">'.L::t('No Recipients').'</div>
    '.L::t('You did not specify any recipients (Cc: and Bcc: does not count here!).'.
           'Please got to the `Em@il Recipients\' panel and select some before '.
           'hitting the `Send\'-button again. Please possibly take care of the list '.
           'of musicians without email address at the bottom of the `Em@il Recipients\' panel.').'
  </div>
</div>';
}

if (!empty($diagnostics['AttachmentValidation']['Files'])) {
} 

if (!empty($diagnostics['AttachmentValidation']['Events'])) {
  $output = true;

  $failedEvents = $diagnostics['AttachmentValidation']['Events'];
  echo '
<div class="emailform error group attachments events">
  <div class="error contents attachments events">
    <span class="error caption attachments events">
      '.L::t('The events could not be attached:').'
    </span>
    <ul>';
      foreach($failedEvents as $event) {
        echo '
      <li><span class="error item contents">'.$event.'</span></li>';
      }
      echo '
    </ul>
  </div>
</div>';  
}

if (!empty($diagnostics['MailerExceptions'])) {
  $output = true;

  $exceptions = $diagnostics['MailerExceptions'];
  $failedEvents = $diagnostics['AttachmentValidation']['Events'];
  echo '
<div class="emailform error group attachments events">
  <div class="error contents attachments events">
    <span class="error caption attachments events">
      '.L::t('While trying to send the message(s), the following exception(s) were caught:').'
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
  $explanations = L::t('This is an internal error. '.
                       'Please copy this page and send it via email to %s.',
                       array('<span class="error email">'.
                             '<a href="mailto:'.$mailto.'">'.
                             $admin['name'].
                             '</a>'.
                             '</span>'));
  echo '
  <div class="error contents explanations">
  <div class="error heading">'.L::t('Explanations').'</div>
    '.$explanations.'
  </div>';
  echo '
</div>';  
} 

if (!empty($diagnostics['AttachmentValidation']['MalerErrors'])) {
} 

if (!empty($diagnostics['AttachmentValidation']['Duplicates'])) {
} 

if (!empty($diagnostics['AttachmentValidation']['CopyToSent'])) {
} 

if (!empty($diagnostics['AttachmentValidation']['Messages'])) {
} 

if ($output) {
  echo '
<div class="spacer"><div class="ruler"></div></div>
<div class="error heading">'.L::t('The most recent status message is always saved to the status panel. It can be reviewed there even after closing this dialog window.').'</div> 
<div class="spacer"><div class="ruler"></div></div>';  
}

?>
