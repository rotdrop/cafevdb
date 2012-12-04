<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
"http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>CAFEV EMail Export and Send-Form</title>
    <style type="text/css">
      hr.pme-hr		     { border: 0px solid; padding: 0px; margin: 0px; border-top-width: 1px; height: 1px; }
      table.pme-main 	     { border: #004d9c 1px solid; border-collapse: collapse; border-spacing: 0px; width: 100%; }
      table.pme-navigation { border: #004d9c 0px solid; border-collapse: collapse; border-spacing: 0px; width: 100%; }
      td.pme-navigation-0, td.pme-navigation-1 { white-space: nowrap; }
      th.pme-header	     { border: #004d9c 1px solid; padding: 4px; background: #add8e6; }
      td.pme-key-0, td.pme-value-0, td.pme-help-0, td.pme-navigation-0, td.pme-cell-0,
      td.pme-key-1, td.pme-value-1, td.pme-help-0, td.pme-navigation-1, td.pme-cell-1,
      td.pme-sortinfo, td.pme-filter { border: #004d9c 1px solid; padding: 3px; }
      td.pme-buttons { text-align: left;   }
      td.pme-message { text-align: center; }
      td.pme-stats   { text-align: right;  }
    </style>
      <?php require_once("functions.php.inc"); ?>
      <?php require_once("Instruments.php"); ?>
      <?php disableEnterSubmit(); ?>
      <?php require ("pme/enablejscal.html"); ?>
      <?php require ("pme/enabletinymce.html"); ?>
      <?php require_once("class.html2text.inc"); ?>
      <?php require_once("PHPMailer/class.phpmailer.php"); ?>
  </head>
  <body>

<?php

global $debug_query;
//    $debug_query = true;

//  $ConstructionMode = true; // Only send email to ME

$CAFEVCatchAllEmail = 'orchestra@example.eu';
$CAFEVVorstandGroup = 'MailingList@groupserver.eu';

$MailTag = '[CAFEV-Musiker]';

//$debug_query = true;
/* We assume that we are using POST methods. The goal is to
 * export a list of Email addresses from a given table.
 *
 * Expected post-variables:
 *
 * Table       -- the table in the data-base
 *
 * The first goal is to generate a nice list of email adresses
 * from this:
 *
 * Vorname Name <foo@bar.com>
 *
 * with the option to either have a linear, comma separated list
 * or simply a list of addresses, line by line. Maybe add a
 * delimiter for that (a string, which may be anything)
 */
include('config.php.inc'); // data-base

if (CAFEVdebugMode()) {
  echo "<PRE>\n";
  print_r($_POST);
  print_r($_GET);
  print_r($_FILES);
  echo "</PRE>\n";
}

// Initialize some variables
$Separators = array('Komma' => array('pre' => '',
                                     'sep' => ',',
                                     'post' => ''),
                    'Leerzeichen' => array('pre' => '"',
                                           'sep' => '" "',
                                           'post' => '"'),
                    'NewLine' => array('pre' => '',
                                       'sep' => "\n",
                                       'post' => ''),
                    'CarriageReturn' => array('pre' => '',
                                              'sep' => "\r",
                                              'post' => ''),
                    'CR-NL' => array('pre' => '',
                                     'sep' => "\r\n",
                                     'post' => ''));

$ProjektId = CAFEVcgiValue('ProjektId',-1);
$Projekt   = CAFEVcgiValue('Projekt','');
$Table     = CAFEVcgiValue('Table','Musiker');
$Filter    = CAFEVcgiValue('Instrumente',array('*'));
$Separator = CAFEVcgiValue('Separator',"NewLine");
$Submit    = CAFEVcgiValue('Submit','Filter Anwenden');

    //include('NavigationButtonsEMail.php'); not too often ...
    
    echo '<H2>Email export and simple mass-mail web-form ';
    if ($Projekt != '') {
      echo 'for project '.$Projekt.'</H2>';
    } else {
      echo 'for all musicians</H2>';
    }

    $string =<<< __EOT__
<P>
<BLOCKQUOTE>
  <H4>Notes: please use this form carefully. Reloading the page
      after sending email will attempt to resend the message. This will fail,
      because all emails sent via this web-form are logged. The form will
      refuse to send an email with identical text and recipients twice.
      You can use the "Filter Anwenden" button to reload the page <B
      style="color:red">without</B> sending email. Unfortunately,
      attachments need to be specified again after reloading the page. The
      email-form performs some sanity checks before sending emails.
  </H4>
  <P>   
  The return path and sender emails are
  <TT>@OUREMAIL@</TT>. The addresses
  <TT>@OUREMAIL@</TT> and
  <TT>@OURGROUP@</TT> are included in the "Cc:"
  field for archiving purposes and to detect abuse of the mail-form.
<P>

  Emails are listed below the filter and email form, if the filter
  yields some musicians without emails, then those are listed,
  too. <EM>(Remember that your browser windows probably has a scroll-bar
  at the right or left window side ;)</EM>
<P>
</BLOCKQUOTE>
__EOT__;

    // replace some tokens.
    $string = str_replace('@OUREMAIL@', $CAFEVCatchAllEmail, $string);
    $string = str_replace('@OURGROUP@', $CAFEVVorstandGroup, $string);

    echo $string;

    /******************************************************************
     *
     * Remember the values from the Email-Form
     */

    $strSubject = isset($_POST['txtSubject']) ? $_POST['txtSubject'] : '';
    if (isset($_POST['txtDescription'])) {
      $strMsg = $_POST['txtDescription'];
    } else {
      $strMsg = 
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
Störung.';
    }
    $strSender = (isset($_POST['txtFormName']) && $_POST['txtFormName'] != '')
      ? $_POST['txtFormName']
      : 'Our Ensemble e.V..';
    $strSenderEmail = $CAFEVCatchAllEmail; // always
    $strFile1 = isset($_FILES['fileAttach1']['name']) ? $_FILES['fileAttach1']['name'] : '';
    $strFile2 = isset($_FILES['fileAttach2']['name']) ? $_FILES['fileAttach2']['name'] : '';
    $strFile3 = isset($_FILES['fileAttach3']['name']) ? $_FILES['fileAttach3']['name'] : '';
    $strFile4 = isset($_FILES['fileAttach4']['name']) ? $_FILES['fileAttach4']['name'] : '';

    $strCC = isset($_POST['txtCC']) ? $_POST['txtCC'] : '';
    $strBCC = isset($_POST['txtBCC']) ? $_POST['txtBCC'] : '';

    /*
     *
     ****************************************************************/

       // Now connect to the data-base
    $dbh = CAFEVmyconnect($opts);

    // Get the current list of instruments for the filter
    if ($ProjektId >= 0) {
      $Instruments = fetchProjectMusiciansInstruments($ProjektId, $dbh);
    } else {
      $Instruments = fetchInstruments($dbh);
    }

    if (CAFEVdebugMode()) {
      echo "<PRE>\n";
      print_r($Instruments);
      echo "</PRE>\n";
    }

    // Now build an SQL Query into $Table restricting to the instruments
    // in $Filter

    if ($Table == 'Musiker') {
      $Restrict = 'Instrumente';
    } else {
      $Restrict = 'Instrument';
    }

    $query = 'SELECT `Vorname`,`Name`,`Email` FROM '.$Table.' WHERE
( ';
    foreach ($Filter as $value) {
      if ($value == '*') {
        $query .= "1 OR\n";
      } else {
        $query .= "`".$Restrict."` LIKE '%".$value."%' OR\n";
      }
    }
    $query .= "0 ) AND NOT `".$Restrict."` LIKE '%Taktstock%'\n";
  

  // Fetch the result or die
  $result = CAFEVmyquery($query, $dbh);

  // Stuff all emails into one array for later usage.
  $NoMail = array();
  $EMails = array();
  while ($line = mysql_fetch_assoc($result)) {
    $name = $line['Vorname'].' '.$line['Name'];
    if ($line['Email'] != '') {
      // We allow comma separated multiple addresses
      $musmail = explode(',',$line['Email']);
      foreach ($musmail as $emailval) {
        array_push($EMails,
                   array('email' => $emailval,
                         'name' => $name));
      }
    } else {
      array_push($NoMail,$name);
    }
  }

  // Not needed anymore
  CAFEVmyclose($dbh);

    // Now define one huge form. This is really somewhat ugly.
    echo '
<FORM METHOD="post" ACTION="EMail.php" NAME="Instrumente" enctype="multipart/form-data">
  <TABLE BORDER="10">
    <TR>
      <TH ALIGN="CENTER">Filter</TH>
      <TH ALIGN="CENTER">Separator</TH>
      <TH ALIGN="CENTER">Mail Versenden (Spam-Gefahr!)</TH>
    </TR>
    <TR>
       <TD ALIGN="CENTER">
         <select name="Instrumente[]" multiple size="20" >
';
         echo '            <option value="*"';
         if (!(array_search("*", $Filter) === false)) {
           echo ' selected="selected"';
         }
         echo '>*</option>
';
         foreach ($Instruments as $value) {
           echo "            <option value=\"$value\"";
           if (!(array_search($value, $Filter) === false)) {
             echo ' selected="selected"';
           }
           echo ">$value</option>\n";
         }
  echo '         </select>
      </TD>
      <TD>
        <select name="Separator" size="20">
';
        foreach ($Separators as $key => $value) {
          echo "          <option value=".htmlspecialchars($key);
          if ($key === $Separator) {
            echo ' selected="selected"';
          }
          echo '>'.$key."</option>\n";
        }
        echo '       </select>
     </TD>
     <TD ALIGN="CENTER">
     <TABLE width="300" border="1">
     <tr>
        <td>Adressat</td>
        <td colspan="2">Determined automatically from data-base.</td>
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
        <td style="width:18ex">'.htmlspecialchars($MailTag).'&nbsp;</td>
        <td><input value="'.$strSubject.'" size="40" name="txtSubject" type="text" id="txtSubject"></td>
     </tr>
     <tr>
       <td>Nachricht</td>
       <td colspan="2"><textarea name="txtDescription" cols="20" rows="4" id="txtDescription">'.$strMsg.'</textarea></td>
     </tr>
     <tr>
       <td>Absende-Name</td>
       <td colspan="2"><input value="'.$strSender.'" size="40" value="CAFEV" name="txtFormName" type="text"></td>
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
     </tr>
     </table>
     </TD>
    </TR>
    <TR>
      <TD ALIGN="CENTER" COLSPAN="2">
        <input type="submit" name="Submit" value="Filter Anwenden" />
        <input type="hidden" name="Table" value="'.$Table.'" />
        <input type="hidden" name="Projekt" value="'.$Projekt.'" />
        <input type="hidden" name="ProjektId" value="'.$ProjektId.'" />
      </TD>
      <TD ALIGN="CENTER">
        <input type="submit" name="Submit" value="Email Versenden">
        <input type="hidden" name="Table" value="'.$Table.'" />
        <input type="hidden" name="Projekt" value="'.$Projekt.'" />
        <input type="hidden" name="ProjektId" value="'.$ProjektId.'" />
      </td>
    </TR>
  </TABLE>
</FORM>
';

  include('NavigationButtonsEMail.php');

  if (count($NoMail) > 0) {
    echo '<HR/><H4>Musiker ohne Email:</H4>
<PRE>
';
    foreach($NoMail as $value) {
      echo htmlspecialchars($value)."\n";
    }
    echo "</PRE><HR/>\n";
  }

  if (count($EMails) > 0) {
    echo '<HR/><H4>Email Liste</H4>
    <PRE>
';
    echo $Separators[$Separator]['pre'];
    foreach ($EMails as $value) {
      echo htmlspecialchars($value['name'])
        .' &lt;'
        .htmlspecialchars($value['email'])
        .'&gt;'.$Separators[$Separator]['sep'];
    }
    echo $Separators[$Separator]['post'];
    echo "</PRE><HR/>\n";
  }

  $MailAddrStr = '';
  foreach ($EMails as $value) {
    $MailAddrStr .=
      htmlspecialchars($value['name'])
      .'&lt;'
      .htmlspecialchars($value['email'])
      .'&gt;'
      .'<BR/>';
  }

  if (count($NoMail) > 0 || count($EMails) > 0) {
    include('NavigationButtonsEMail.php');
  }

  // Now: do we want to send an Email?
  if ($Submit == 'Email Versenden') {
    // So place the spam ...

    date_default_timezone_set(@date_default_timezone_get());

    // Perform sanity checks before spamming ...
    $DataValid = true;

    if ($strSubject == '') {
      echo '<HR/><H4>Need more than only the default subject "'.$MailTag.'".
<p>
Please correct that first and then click on the "Send"-button again.
<P>
Unfortunately, attachments (if any) have to be specified again.
</H4>';
      $DataValid = false;
    }
    if ($strSender == '') {
      echo '<HR/><H4>Need a non-empty name for the sender.
<p>
Please correct that first and then click on the "Send"-button again.
<P>
Unfortunately, attachments (if any) have to be specified again.
</H4>';
      $DataValid = false;
    }
    if ($strSenderEmail == '') {
      echo '<HR/><H4>Need the email address of the sender.
<p>
Please correct that first and then click on the "Send"-button again.
<P>
Unfortunately, attachments (if any) have to be specified again.
</H4>';
      $DataValid = false;
    }

    $strMessage = nl2br($strMsg);
    $h2t = new html2text($strMessage);
    $h2t->set_encoding('utf-8');
    $strTextMessage = $h2t->get_text();

    $mail = new PHPMailer();
    $mail->CharSet = 'utf-8';
    $mail->SingleTo = true;

    // Setup the mail server for testing
    // $mail->IsSMTP();
    //$mail->IsMail();
    $mail->IsSMTP();
    if (false) {
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
      $bulkRecipients .= $strCC.','.$strBCC;
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
      $logquery = "INSERT INTO `SentEmail`
(`BulkRecipients`,`MD5BulkRecipients`,`Cc`,`Bcc`,`Subject`,`HtmlBody`,`MD5Text`";
      $idx = 1;
      foreach ($attachLog as $pairs) {
        $logquery .= ",`Attachment".$idx."`,`MD5Attachment".$idx."`";
      }
      $logquery .= ') VALUES (';
      $logquery .= "'$bulkRecipients','$bulkMD5'";
      $logquery .= ",'$strCC'";
      $logquery .= ",'$strBCC'";
      $logquery .= ",'$strSubject'";
      $logquery .= ",'$strMessage'";
      $logquery .= ",'$textMD5'";
      foreach ($attachLog as $pairs) {
        $logquery .= ",'".$pairs['name']."','".$pairs['md5']."'";
      }
      $logquery .= ")";

      $handle = CAFEVmyconnect($opts);

      // Check for duplicates
      $loggedquery = "SELECT * FROM `SentEmail` WHERE";
      $loggedquery .= " `MD5Text` LIKE '$textMD5'";
      $loggedquery .= " AND `MD5BulkRecipients` LIKE '$bulkMD5'";
      $result = CAFEVmyquery($loggedquery, $handle);
      
      $cnt = 0;
      $loggedDates = '';
      if ($line = CAFEVmyfetch($result)) {
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
        if (!$mail->PreSend()) {
        echo <<<__EOT__
<p>Mail failed<p>
__EOT__;
        } else {
          // Log the message to our data-base

          CAFEVmyquery($logquery, $handle);

        }
      } catch (Exception $e) {
        echo '<HR/><H4>Fehler:</H4>';
        echo "<PRE>\n";
        echo htmlspecialchars($e->getMessage())."\n";
        echo "</PRE><HR/>\n";
      }

      CAFEVmyclose($handle);

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

      }


      if (true || $ProjektId < 0) {
	// Fuck our provider ...
        // Ideally, we would copy to the 'Sent' folder. Can't be done,
        // so send a forward. Mmmh. Why only for $ProjektId < 0???

	$DataValid = true;

	$sentmsg = $mail->GetSentMIMEMessage();

	$cpmail = new PHPMailer();
	$cpmail->CharSet = 'utf-8';
	$cpmail->SingleTo = true;

	// Setup the mail server for testing
	// $mail->IsSMTP();
	//$mail->IsMail();
	$cpmail->IsSMTP();

        if (false) {
          $cpmail->Host = 'server.example.eu';
          $cpmail->Port = 587;
          $cpmail->SMTPSecure = 'tls';
          $cpmail->SMTPAuth = true;
          $cpmail->Username = 'wp1173590-cafev';
          $cpmail->Password = 'XXXXXXXX';
        }
        
        $cpmail->IsHTML();

	$DataValid = EmailSetFrom($cpmail,$strSenderEmail,$strSender)
	  && $DataValid;
	$DataValid = EmailAddReplyTo($cpmail,$strSenderEmail,$strSender)
	  && $DataValid;
	$cpmail->Subject = 'FWD: '.$MailTag . ' ' . $strSubject;
	$cpmail->Body =
	  '<B>Mass-Mail Copy</B><BR/>'
	  .$MailAddrStr;

	$h2t = new html2text($cpmail->Body);
	$h2t->set_encoding('utf-8');
	$cpmail->AltBody = $h2t->get_text();

	// Send a copy, blame Host-Europe
	$DataValid = EmailAddAddress($cpmail, $CAFEVCatchAllEmail, $strSender)
	  && $DataValid;


	$cpmail->AddStringAttachment($sentmsg, 'Attached Message', '8bit', 'message/rfc822');

	if ($DataValid) {
	  // Ignore error, no point in retry anyway.
	  if (!$cpmail->PreSend()) {
            echo "Forward failed\n";
          }
	  echo '<HR/><H4>Gesendete Email</H4>';
	  echo "<PRE>\n";
	  echo htmlspecialchars($cpmail->GetSentMIMEMessage())."\n";
	  echo "</PRE><HR/>\n";
	} else {
	  echo "Why\n";
	}
      }

      if (true || CAFEVdebugMode()) {
        echo '<HR/><H4>Gesendete Email</H4>';
        echo "<PRE>\n";
        echo htmlspecialchars($mail->GetSentMIMEMessage())."\n";
        echo "</PRE><HR/>\n";
      }
    }
  }
?>
    
  </body>
</html>
