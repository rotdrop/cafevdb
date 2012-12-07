<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
"http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
   <title>CAFEV EMail Export and Send-Form</title>
   <link rel="stylesheet" type="text/css" href="pme-blue.css" />
   <link rel="stylesheet" type="text/css" href="QuickForm2/data/quickform.css" />
<?php
require_once("favicon.php");
require_once("pme/enablejscal.html");
require_once("pme/enabletinymce.html");
require_once("class.html2text.inc");
require_once("PHPMailer/class.phpmailer.php");
set_include_path(dirname(dirname(dirname(__FILE__))).'/pear/php' . PATH_SEPARATOR . get_include_path());
require_once("Net/IMAP.php");
require_once('EMail.class.php');
?>
   <style type="text/css">
<?php echo CAFEVmailFilter::defaultStyle(); ?>
.cafev-email-form {
   font-size:100%;
 }
table.cafev-email-form {
    border:1px solid #808080;
}
.cafev-email-form tr.submit td {
    border: 1px solid #808080;
}
.cafev-email-form tr.submit td.send {
   width:auto;
   text-align:full;
   padding:0;
   margin:0;
}
.cafev-email-form td.subject {
   width:80px;
 }
.cafev-email-form tr.submit td.addresses input {
   width:auto;
 }
.cafev-email-form tr.submit td.addresses {
   width:auto;
   text-align:full;
   padding:0;
   margin:0;
}
.cafev-email-form tr.submit td.reset {
   width:auto;
   text-align:right;
   padding:0;
   margin:0;
}
.cafev-error h4 {
   color:red;
}
   </style>
   </head>
<body>

<?php

$footer =<<<__EOT__
</body> </html>
__EOT__;

disableEnterSubmit(); // ? needed ???

include('config.php.inc');

$cafevclass = 'cafev-nav-top';
include('NavigationButtonsEMail.php');
$cafevclass = 'cafev-email';
echo '<div class="'.$cafevclass.'">'."\n";

//$debug_query = true;
$ConstructionMode = false;

// Display a filter dialog
$filter = new CAFEVmailFilter($opts, 'EMail.php');

$filter->execute();

/********************************************************************************************
 *
 * Initialize some global stuff for the email form. Need to do this
 * before rendering the address selection stuff.
 *
 */

$ProjektId = CAFEVcgiValue('ProjektId',-1);
$Projekt   = CAFEVcgiValue('Projekt','');

if ($ConstructionMode) {
  $CAFEVCatchAllEmail = 'DEVELOPER@his.server.eu';
  $CAFEVVorstandGroup = 'DEVELOPER@his.server.eu';
} else {
  $CAFEVCatchAllEmail = 'orchestra@example.eu';
  $CAFEVVorstandGroup = 'MailingList@groupserver.eu';
}
if ($ProjektId < 0 || $Projekt == '') {
  $MailTag = '[CAF-Musiker]';
} else {
  $MailTag = '[CAF-'.$Projekt.']';
}

$emailPosts = array(
  'txtSubject' => '',
  'txtCC' => '',
  'txtBCC' => '',
  'txtFromName' => 'Our Ensemble e.V..',
  'txtDescription' =>
    'Liebe Musiker,
<p>
Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
<p>
Mit den besten Grüßen,
<p>
Euer Camerata Vorstand (Katha, Georg, Martina, Lea, Luise und Claus)
<p>
P.s.:
Sie erhalten diese Email, weil Sie schon einmal mit dem Orchester
Camerata Academica Freiburg musiziert haben. Wenn wir Sie aus unserer Datenbank
löschen sollen, teilen Sie uns das bitte kurz mit, indem Sie entsprechend
auf diese Email antworten. Wir entschuldigen uns in diesem Fall für die
Störung.');  

if (CAFEVcgiValue('eraseAll',false) !== false) {
  /* Take everything to its defaults */
  foreach ($emailPosts as $key => $value) {
    $_POST[$key] = $value; // cheat
  }
}

$strSubject = CAFEVcgiValue('txtSubject', $emailPosts['txtSubject']);
$strMsg     = CAFEVcgiValue('txtDescription', $emailPosts['txtDescription']);
$strSender  = CAFEVcgiValue('txtFromName', $emailPosts['txtFromName']);
$strCC      = CAFEVcgiValue('txtCC', $emailPosts['txtCC']);
$strBCC     = CAFEVcgiValue('txtBCC', $emailPosts['txtBCC']);
$strSenderEmail = $CAFEVCatchAllEmail; // always
$strFile1 = isset($_FILES['fileAttach1']['name']) ? $_FILES['fileAttach1']['name'] : '';
$strFile2 = isset($_FILES['fileAttach2']['name']) ? $_FILES['fileAttach2']['name'] : '';
$strFile3 = isset($_FILES['fileAttach3']['name']) ? $_FILES['fileAttach3']['name'] : '';
$strFile4 = isset($_FILES['fileAttach4']['name']) ? $_FILES['fileAttach4']['name'] : '';

/*
 *
 *
 *******************************************************************************************/

/******************************************************************************************
 *
 * Some blah-blah
 *
 */

if ($ConstructionMode) {
  echo '<H1>Testbetrieb. Email geht nur an mich.</H1>';
  echo '<H4>Ausgenommen Cc:. Bitte um Testmail von eurer Seite.</H4>';
}
echo '<H2>Email export and simple mass-mail web-form ';
if ($Projekt != '') {
  echo 'for project '.$Projekt.'</H2>';
} else {
  echo 'for all musicians</H2>';
}

$string =<<< __EOT__
<H4>
  Der Editor und die Adress-Auswahl sind wechselseitig ausgeschaltet.
  Um Adressen oder Text- nachzubearbeiten, auf den entsprechenden
  Button klicken; man kann mehrfach hin- und herwechseln, ohne dass
  die jeweiligen Eingaben verloren gehen. Der Instrumentenfilter ist
  "destruktiv": das Abwählen des Filters restauriert nicht die
  vorherige Adressenauswahl. Der "Abbrechen"-Button unter dem Editor
  setzt alles wieder auf die Default-Einstellungen.
  </H4>
  <P>
  ReturnTo: und Sender: sind
  <TT>@OUREMAIL@</TT>. Die Adressen
  <TT>@OUREMAIL@</TT>  und
  <TT>@OURGROUP@</TT> erhalten je eine Kopie um Missbrauch durch "Einbrecher" 
  abzufangen. Außerdem werden die Emails in der Datenbank gespeichert.
__EOT__;

    // replace some tokens.
    $string = str_replace('@OUREMAIL@', $CAFEVCatchAllEmail, $string);
    $string = str_replace('@OURGROUP@', $CAFEVVorstandGroup, $string);

    echo $string;

/*
 *
 *
 *******************************************************************************************/

/******************************************************************************************
 *
 * Display the address selection from if it is not frozen, other after the email editor.
 *
 */

if (!$filter->isFrozen()) {
  /* Add all of the above to the form, if it is active */

  $filter->addPersistentCGI('txtSubject', $strSubject);
  $filter->addPersistentCGI('txtDescription', $strMsg);
  $filter->addPersistentCGI('txtFromName', $strSender);
  $filter->addPersistentCGI('txtCC', $strCC);
  $filter->addPersistentCGI('txtBCC', $strBCC);
  $filter->addPersistentCGI('fileAttach1', $strFile1);
  $filter->addPersistentCGI('fileAttach2', $strFile2);
  $filter->addPersistentCGI('fileAttach3', $strFile3);
  $filter->addPersistentCGI('fileAttach4', $strFile4);

  $filter->render(); // else render below the Email editor
}

/*
 *
 *
 *******************************************************************************************/

/******************************************************************************************
 *
 * If sending requested and basic stuff is missing, display the
 * corresponding error messages here; because at the bottom of the
 * page they may be outside of the viewable region.
 *
 */

else if (CAFEVcgiValue('sendEmail',false) !== false) {

  if ($strSubject == '') {
    echo '<div class="cafev-error">
<HR/><H4>Fehler: Die Betreffzeile sollte nicht nur aus "'.$MailTag.'" bestehen.
<p>
Bitte korrigieren und dann den "Send"-Button noch einmal anklicken
<P>
Leider m&uuml;ssen etwaige Attachments jetzt noch einmal angegeben werden.
</H4><HR/>
</div>';
  }
  if ($strSender == '') {
    echo '<div class="cafev-error">
<HR/><H4>Fehler: Der Absender-Name sollte nicht leer sein.
<p>
Bitte korrigieren und dann den "Send"-Button noch einmal anklicken
<P>
Leider m&uuml;ssen etwaige Attachments jetzt noch einmal angegeben werden.
</H4><HR/>
</div>';
  }
  if ($strSenderEmail == '') {
    echo '<div class="cafev-error">
<HR/><H4>Fehler: Die Email-Adresse des Absenders sollte nicht leer sein.
<p>
Bitte korrigieren und dann den "Send"-Button noch einmal anklicken
<P>
Leider m&uuml;ssen etwaige Attachments jetzt noch einmal angegeben werden.
</H4><HR/>
</div>';
  }
}

/*
 *
 *
 *******************************************************************************************/

/******************************************************************************************
 *
 * Now define one huge form. This is really somewhat ugly.
 * Also: we cheat: pretend we are quick-form to simplify the look
 *
 */

/**** start quick-form cheat ****/
echo '<div class="quickform">';
/*******************************/
echo '
<FORM METHOD="post" ACTION="EMail.php" NAME="Email" enctype="multipart/form-data" class="cafev-mail-form">';
/**** start quick-form cheat ****/

/* Remember address filter for later */
if ($filter->isFrozen()) {
  echo $filter->getPersistent();
}

echo sprintf('
  <fieldset %s id="cafev-mail-form-0"><legend id="cafev-mail-form-0-legend">Em@il Verfassen</legend>',$filter->isFrozen() ? '' : 'disabled');
/*******************************/
   echo '
  <TABLE class="cafev-email-form">
  <tr>
     <td>Adressat</td>
     <td colspan="2">Determined automatically from data-base, see below the email form.</td>
  </tr>
  <tr>
     <td>Carbon Copy</td>
     <td colspan="2"><input size="40" value="'.htmlspecialchars($strCC).'" name="txtCC" type="text" id="txtCC"></td>
  </tr>
  <tr>
     <td>Blind CC</td>
     <td colspan="2"><input size="40" value="'.htmlspecialchars($strBCC).'" name="txtBCC" type="text" id="txtBCC"></td>
  </tr>
  <tr>
     <td>Betreff</td>
     <td class="subject">'.htmlspecialchars($MailTag).'&nbsp;</td>
     <td><input value="'.$strSubject.'" size="40" name="txtSubject" type="text" id="txtSubject"></td>
  </tr>
  <tr>
    <td>Nachricht</td>
    <td colspan="2"><textarea name="txtDescription" cols="20" rows="4" id="txtDescription">'.$strMsg.'</textarea></td>
  </tr>
  <tr>
    <td>Absende-Name</td>
    <td colspan="2"><input value="'.$strSender.'" size="40" value="CAFEV" name="txtFromName" type="text"></td>
  </tr>
  <tr>
  <tr>
    <td>Absende-Email</td>
    <td colspan="2">Tied to "'.$CAFEVCatchAllEmail.'"</td>
  </tr>
  <tr>
    <td>Attachment 1</td>
    <td colspan="2"><input name="fileAttach1" type="file"></td>
  </tr>
  <tr>
    <td>Attachment 2</td>
    <td colspan="2"><input name="fileAttach2" type="file"></td>
  </tr>
  <tr>
    <td>Attachment 3</td>
    <td colspan="2"><input name="fileAttach3" type="file"></td>
  </tr>
  <tr>
    <td>Attachment 4</td>
    <td colspan="2"><input name="fileAttach4" type="file"></td>
  </tr>';
$submitString = '
  <tr class="submit">
    <td class="send">
      <input %1$s title="Vorsicht!"
      type="submit" name="sendEmail" value="Em@il Verschicken"/></td>
    <td class="addresses">
      <input %1$s title="Der Nachrichten-Inhalt
bleibt erhalten." type="submit" name="modifyAddresses" value="Adressen Bearbeiten"/>
   </td>
   <td class="reset">
       <input %1$s title="Abbrechen und von
vorne Anfangen, bereits
veränderter Text geht
verloren." type="submit" name="eraseAll" value="Abbrechen" />
     </td>
  </tr>';
echo sprintf($submitString, $filter->isFrozen() ? '' : 'disabled');
echo '
  </table>';
echo '</fieldset></FORM></div>
';

/*
 *
 *
 *******************************************************************************************/

/******************************************************************************************
 *
 * If the filter is frozen, display it now, otherwise the editor
 * window would be at the bottom of some large address list.
 *
 */

if ($filter->isFrozen()) {
  $filter->render(); // else render below the Email editor
}

/*
 *
 *
 *******************************************************************************************/

/******************************************************************************************
 *
 * Now maybe send an email, if requested ...
 *
 */

echo '</div>
';
$cafevclass = 'cafev-nav-bottom';
include('NavigationButtonsEMail.php');

// Now: do we want to send an Email?
if (CAFEVcgiValue('sendEmail',false) === false) {
  // not yet.

  echo $footer;
  return true;
}

// So place the spam ...

date_default_timezone_set(@date_default_timezone_get());

// See what we finally have ...
$EMails = $filter->getEmails();

// For archieving
$MailAddrStr = '';
foreach ($EMails as $value) {
  $MailAddrStr .=
    htmlspecialchars($value['name'])
    .'&lt;'
    .htmlspecialchars($value['email'])
    .'&gt;'
    .'<BR/>';
}



// Perform sanity checks before spamming ...
$DataValid = true;

if ($strSubject == '') {
  echo '<HR/><H4>Die Betreffzeile sollte nicht nur aus "'.$MailTag.'" bestehen.
<p>
Bitte korrigieren und dann den "Send"-Button noch einmal anklicken
<P>
Leider m&uuml;ssen etwaige Attachments jetzt noch einmal angegeben werden.
</H4>';
  $DataValid = false;
}
if ($strSender == '') {
  echo '<HR/><H4>Der Absender-Name sollte nicht leer sein.
<p>
Bitte korrigieren und dann den "Send"-Button noch einmal anklicken
<P>
Leider m&uuml;ssen etwaige Attachments jetzt noch einmal angegeben werden.
</H4>';
  $DataValid = false;
}
if ($strSenderEmail == '') {
  echo '<HR/><H4>Die Email-Adresse des Absenders sollte nicht leer sein.
<p>
Bitte korrigieren und dann den "Send"-Button noch einmal anklicken
<P>
Leider m&uuml;ssen etwaige Attachments jetzt noch einmal angegeben werden.
</H4>';
  $DataValid = false;
}

$strMessage = nl2br($strMsg);
$h2t = new html2text($strMessage);
$h2t->set_encoding('utf-8');
$strTextMessage = $h2t->get_text();

$mail = new PHPMailer();
$mail->CharSet = 'utf-8';
$mail->SingleTo = false;

// Setup the mail server for testing
// $mail->IsSMTP();
//$mail->IsMail();
$mail->IsSMTP();
if (true) {
  $mail->Host = 'server.example.eu';
  $mail->Port = 587;
  $mail->SMTPSecure = 'tls';
  $mail->SMTPAuth = true;
  $mail->Username = 'wp1173590-cafev';
  $mail->Password = 'XXXXXXXX';
}

$mail->IsHTML();

$DataValid = EmailSetFrom($mail,$strSenderEmail,$strSender)
  && $DataValid;
$DataValid = EmailAddReplyTo($mail,$strSenderEmail,$strSender)
  && $DataValid;
$mail->Subject = $MailTag . ' ' . $strSubject;
$mail->Body = $strMessage;
$mail->AltBody = $strTextMessage;

if (!$ConstructionMode) {
  // Loop over all data-base records and add each recipient in turn
  foreach ($EMails as $pairs) {
    // Better not use AddAddress: we should not expose the email
    // addresses to everybody. TODO: instead place the entire
    // message, including the Bcc's, either in the "sent" folder,
    // or save it somewhere else.
    if ($ProjektId < 0) {
      $DataValid = EmailAddBCC($mail, $pairs['email'], $pairs['name'])
        && $DataValid;
    } else {
      // Well, people subscribing to one of our projects simply must not complain.
      $DataValid = EmailAddAddress($mail, $pairs['email'], $pairs['name'])
        && $DataValid;
    }
  }
} else {
  $DataValid = EmailAddAddress($mail, 'DEVELOPER@his.server.eu', 'Claus-Justus Heine')
    && $DataValid;
}

// Always drop a copy locally and to the mailing list, for
// archiving purposes and to catch illegal usage.
$DataValid = EmailAddCC($mail, $CAFEVCatchAllEmail, $strSender)
  && $DataValid;
$DataValid =
  EmailAddCC($mail, $CAFEVVorstandGroup, 'CAFEV Mail Form')
  && $DataValid;

// If we have further Cc's, then add them also
if ($strCC != '') {
  // Now comes some dirty work: we need to split the string in
  // names and email addresses.
  
  $arrayCC = parseEmailListToArray($strCC);
  if (CAFEVdebugMode()) {
    echo "<PRE>\n";
    print_r($arrayCC);
    echo "</PRE>\n";
  }
  
  foreach ($arrayCC as $value) {
    $strCC .= $value['name'].' <'.$value['email'].'>,';
    // PHP-Mailer adds " for itself as needed
    $value['name'] = trim($value['name'], '"');
    $DataValid = EmailAddCC($mail, $value['email'], $value['name'])
      && $DataValid;
  }
  $strCC = trim($strCC, ',');
}

// Do the same for Bcc
if ($strBCC != '') {
  // Now comes some dirty work: we need to split the string in
  // names and email addresses.
  
  $arrayBCC = parseEmailListToArray($strBCC);
  if (CAFEVdebugMode()) {
    echo "<PRE>\n";
    print_r($arrayBCC);
    echo "</PRE>\n";
  }
  
  $strBCC = '';
  foreach ($arrayBCC as $value) {
    $strBCC .= $value['name'].' <'.$value['email'].'>,';
    // PHP-Mailer adds " for itself as needed
    $value['name'] = trim($value['name'], '"');
    $DataValid = EmailAddBCC($mail, $value['email'], $value['name'])
      && $DataValid;
  }
  $strBCC = trim($strBCC, ',');
}

if ($DataValid) {
  
  foreach ($_FILES as $key => $value) {
    if (CAFEVdebugMode()) {
      echo "<PRE>\n";
      print_r($value);
      echo "</PRE>\n";
    }
    if($value['name'] != "") {
      if ($value['type'] == 'application/x-download' &&
          strrchr($value['name'], '.') == '.pdf') {
        $value['type'] = 'application/pdf';
      }
      if (!$mail->AddAttachment($value['tmp_name'],$value['name'],
                                'base64',$value['type'])) {
        $DataValid = false;
        echo '<HR/><H4>The attachment '.$value['name'].' seems to be invalid.
<p>
Please correct that first and then click on the "Send"-button again.
<P>
Unfortunately, attachments (if any) have to be specified again.
</H4>';
      }
    }
  }

  // Construct one MD5 for recipients subject and html-text
  $bulkRecipients = '';
  foreach ($EMails as $pairs) {
    $bulkRecipients .= $pairs['name'].' <'.$pairs['email'].'>,';
  }
  // add CC and BCC
  if ($strCC != '') {
    $bulkRecipients .= $strCC.',';
  }
  if ($strBCC != '') {
    $bulkRecipients .= $strBCC.',';
  }
  $bulkRecipients = trim($bulkRecipients,',');
  $bulkMD5 = md5($bulkRecipients);
  
  $textforMD5 = $strSubject . $strMessage;
  $textMD5 = md5($textforMD5);
  
  // compute the MD5 stuff for the attachments
  $attachLog = array();
  foreach ($_FILES as $key => $value) {
    if($value['name'] != "") {
      $md5val = md5_file($value['tmp_name']);
      $attachLog[] = array('name' => $value['name'],
                           'md5' => $md5val);
    }
  }
  
  // Now insert the stuff into the SentEmail table  
  $handle = CAFEVDB_mySQL::connect($opts);

  $logquery = "INSERT INTO `SentEmail`
(`user`,`host`,`BulkRecipients`,`MD5BulkRecipients`,`Cc`,`Bcc`,`Subject`,`HtmlBody`,`MD5Text`";
  $idx = 1;
  foreach ($attachLog as $pairs) {
    $logquery .= ",`Attachment".$idx."`,`MD5Attachment".$idx."`";
  }
  $logquery .= ') VALUES (';
  $logquery .= "'".$_SERVER['REMOTE_USER']."','".$_SERVER['REMOTE_ADDR']."'";
  $logquery .= sprintf(",'%s','%s'",
                       mysql_real_escape_string($bulkRecipients, $handle),
                       mysql_real_escape_string($bulkMD5, $handle),
                       mysql_real_escape_string($strCC, $handle),
                       mysql_real_escape_string($strBCC, $handle),
                       mysql_real_escape_string($strSubject, $handle),
                       mysql_real_escape_string($strMessage, $handle),
                       mysql_real_escape_string($textMD5, $handle));
  foreach ($attachLog as $pairs) {
    $logquery .=
      ",'".mysql_real_escape_string($pairs['name'], $handle)."'".
      ",'".mysql_real_escape_string($pairs['md5'], $handle)."'";
  }
  $logquery .= ")";
  
  // Check for duplicates
  $loggedquery = "SELECT * FROM `SentEmail` WHERE";
  $loggedquery .= " `MD5Text` LIKE '$textMD5'";
  $loggedquery .= " AND `MD5BulkRecipients` LIKE '$bulkMD5'";
  $result = CAFEVDB_mySQL::query($loggedquery, $handle);
  
  $cnt = 0;
  $loggedDates = '';
  if ($line = CAFEVDB_mySQL::fetch($result)) {
    $loggedDates .= ','.$line['Date'];
    ++$cnt;
  }
  $loggedDates = trim($loggedDates,',');
  
  if ($loggedDates != '') {
    echo '<HR/><H3>A message with exactly the same text to exactly the same
recipients has already been sent on the following date'.($cnt > 1 ? 's' : '').':
<p>
'.$loggedDates.'
<p>
Refusing to send duplicate bulk emails.
</H3><HR/>
';
    die();
  }

  try {
    if (!$mail->Send()) {
      echo <<<__EOT__
<p>Mail failed<p>
__EOT__;
    } else {
      // Log the message to our data-base
      
      CAFEVDB_mySQL::query($logquery, $handle);
      
    }
  } catch (Exception $e) {
    echo '<HR/><H4>Fehler:</H4>';
    echo "<PRE>\n";
    echo htmlspecialchars($e->getMessage())."\n";
    echo "</PRE><HR/>\n";
  }

  CAFEVDB_mySQL::close($handle);

  if (false) {
    // Now, this is really the fault of our provider. Sad
    // story. How to work-around? Well, we send the message as
    // Email-attachment to our own account. Whoops.
    
    // connect to your Inbox through port 143.  See imap_open()
    // function for more details
    $mbox = imap_open('{'.$mail->Host.':143/notls}INBOX',
                      $mail->Username,
                      $mail->Password);
    if ($mbox !== false) {
      // save the sent email to your Sent folder by just passing a
      // string composed of the entire message + headers.  See
      // imap_append() function for more details.  Notice the 'r'
      // format for the date function, which formats the date
      // correctly for messaging.

      imap_append($mbox, '{'.$mail->Host.':993/ssl}INBOX.Sent',
                  $mail->GetSentMIMEMessage());
      // close mail connection.
      imap_close($mbox);
    }
  } elseif (true) {
    // PEAR IMAP works without the c-client library

    ini_set('error_reporting',ini_get('error_reporting') & ~E_STRICT);

    $imap = new Net_IMAP($mail->Host, 993, false, 'UTF-8');
    if (($ret = $imap->login($mail->Username, $mail->Password)) !== true) {
      CAFEVerror($ret->toString(), false);
      $imap->disconnect();
      die();
    }
    if (($ret = $imap->appendMessage($mail->GetSentMIMEMessage(), 'Sent')) !== true) {
      CAFEVerror($ret->toString(), false);
      $imap->disconnect();
      die();
    }
    $imap->disconnect();
  }

  if (true || CAFEVdebugMode()) {
    echo '<HR/><H4>Gesendete Email</H4>';
    echo "<PRE>\n";
    echo htmlspecialchars($mail->GetSentMIMEMessage())."\n";
    echo "</PRE><HR/>\n";
  }
}

echo $footer;

?>

