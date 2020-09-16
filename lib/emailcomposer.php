<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/**CamerataDB namespace to prevent name-collisions.
 */
namespace CAFEVDB
{

  /**This is the mass-email composer class. We try to be somewhat
   * careful to have useful error reporting, and avoid sending garbled
   * messages or duplicates.
   */
  class EmailComposer {
    const DEFAULT_TEMPLATE = 'Liebe Musiker,
<p>
Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
<p>
Mit den besten Grüßen,
<p>
Euer Camerata Vorstand (${GLOBAL::ORGANIZER})
<p>
P.s.:
Sie erhalten diese Email, weil Sie schon einmal mit dem Orchester
Camerata Academica Freiburg musiziert haben. Wenn wir Sie aus unserer Datenbank
löschen sollen, teilen Sie uns das bitte kurz mit, indem Sie entsprechend
auf diese Email antworten. Wir entschuldigen uns in diesem Fall für die
Störung.';
    const MEMBERVARIABLES = '
VORNAME
NAME
EMAIL
MOBILFUNK
FESTNETZ
STRASSE
PLZ
STADT
LAND
GEBURTSTAG
UNKOSTENBEITRAG
ANZAHLUNG
GESAMTBEITRAG
ZUSATZKOSTEN
EXTRAS
VERSICHERUNGSBEITRAG
ZAHLUNGSEINGANG
FEHLBETRAG
SEPAMANDATSREFERENZ
SEPAMANDATSIBAN
SEPAMANDATSBIC
SEPAMANDATSINHABER
LASTSCHRIFTBETRAG
LASTSCHRIFTZWECK
';
    const MEMBERCOLUMNS = '
Vorname
Name
Email
MobilePhone
FixedLinePhone
Strasse
Postleitzahl
Stadt
Land
Geburtstag
Unkostenbeitrag
Anzahlung
TotalFees
SurchargeFees
Extras
InsuranceFee
AmountPaid
AmountMissing
MandateReference
MandateIBAN
MandateBIC
MandateAccountOwner
DebitNoteAmount
DebitNotePurpose
';
    const POST_TAG = 'emailComposer';

    private $opts; ///< For db connection and stuff.
    private $dbh;  ///< Data-base handle.
    private $user; ///< Owncloud user.

    private $recipients; ///< The list of recipients.
    private $onLookers;  ///< Cc: and Bcc: recipients.

    private $cgiData;
    private $submitted;

    private $projectId;
    private $projectName;
    private $debitNoteId;

    private $constructionMode;

    private $catchAllEmail; ///< The fixed From: email address.
    private $catchAllName;  ///< The default From: name.

    private $initialTemplate;
    private $templateName;

    private $draftId; ///< The ID of the current message draft, or -1

    private $messageTag;

    private $messageContents; // What we finally send out to the world

    private $executionStatus; // false on error
    private $diagnostics; // mixed, depends on operation

    private $memberVariables; // VARIABLENAME => column name

    /*
     * constructor
     */
    public function __construct($recipients = array(), $template = null)
    {
      Config::init();

      $this->opts = Config::$pmeopts;
      $this->dbh = false;

      $this->user = \OCP\USER::getUser();

      $this->constructionMode = Config::$opts['emailtestmode'] != 'off';
      $this->setCatchAll();

      $this->cgiData = Util::cgiValue(self::POST_TAG, array());

      if (!empty($template)) {
        $this->cgiData['StoredMessagesSelector'] = $template;
      }

      $this->recipients = $recipients;

      $this->projectId   = $this->cgiValue('ProjectId', Util::cgiValue('ProjectId', -1));
      $this->projectName = $this->cgiValue('ProjectName',
                                           Util::cgiValue('ProjectName',
                                                          Util::cgiValue('ProjectName', '')));
      $this->debitNoteId = $this->cgiValue('DebitNoteId', Util::cgiValue('DebitNoteId', -1));

      $this->setSubjectTag();

      // First initialize defaults, will be overriden based on
      // form-submit data in $this->execute()
      $this->setDefaultTemplate();

      $this->templateName = 'Default';

      $this->messageContents = $this->initialTemplate;

      // cache the list of per-recipient variables
      $this->memberVariables = $this->emailMemberVariables();

      $this->draftId = $this->cgiValue('MessageDraftId', -1);

      // Set to false on error
      $this->executionStatus = true;

      // Error diagnostics, can be retrieved by
      // $this->statusDiagnostics()
      $this->diagnostics = array(
        'Caption' => '',
        'AddressValidation' => array('CC' => array(),
                                     'BCC' => array(),
                                     'Empty' => false),
        'TemplateValidation' => array(),
        'SubjectValidation' => true,
        'FromValidation' => true,
        'AttachmentValidation' => array('Files' => array(),
                                        'Events' => array()),
        'MailerExceptions' => array(),
        'MailerErrors' => array(),
        'Duplicates' => array(),
        'CopyToSent' => array(), // IMAP stuff
        'Message' => array('Text' => '',
                           'Files' => array(),
                           'Events' => array()), // start of sent-messages for log window
        'TotalPayload' => 0,
        'TotalCount' => 0,
        'FailedCount' => 0
        );

      $this->execute();
    }

    /**Parse the submitted form data and act accordinly */
    private function execute()
    {
      // Maybe should also check something else. If submitted is true,
      // then we use the form data, otherwise the defaults.
      $this->submitted = $this->cgiValue('FormStatus', '') == 'submitted';

      if (!$this->submitted) {
        // Leave everything at default state, except for an optional
        // initial template and subject
        $initialTemplate = $this->cgiValue('StoredMessagesSelector', false);
        if ($initialTemplate !== false) {
          $this->templateName = $initialTemplate;
          $template = $this->fetchTemplate($initialTemplate);
          if ($template !== false) {
            $this->messageContents = $template;
          } else {
            // still leave the name as default in order to signal what was missing
            $this->cgiData['Subject'] =
              L::t('Unknown Template "%s"', array($initialTemplate));
            $template = $this->fetchTemplate(L::t('ExampleFormletter'));
            if ($template !== false) {
              $this->messageContents = $template;
            }
          }
        }
        return;
      }

      $this->messageContents = $this->cgiValue('MessageText', $this->initialTemplate);

      if (($value = $this->cgiValue('StoredMessagesSelector', false))) {
        if (preg_match('/__draft-(-?[0-9]+)/', $value, $matches)) {
          $this->draftId = $matches[1];
          // TODO: actually read the message in ...
        } else {
          $this->templateName = $value;
          $this->messageContents = $this->fetchTemplate($this->templateName);
          $this->draftId = -1; // avoid accidental overwriting
        }
      } else if (($value = $this->cgiValue('DeleteMessage', false))) {
        if (($value = $this->cgiValue('SaveAsTemplate', false))) {
          $this->deleteTemplate($this->cgiValue('TemplateName'));
          $this->setDefaultTemplate();
          $this->messageContents = $this->initialTemplate;
        } else {
          $this->deleteDraft();
        }
      } else if (($value = $this->cgiValue('SaveMessage', false))) {
        if (($value = $this->cgiValue('SaveAsTemplate', false))) {
          if ($this->validateTemplate($this->messageContents)) {
            $this->storeTemplate($this->cgiValue('TemplateName'),
                                 $this->subject(),
                                 $this->messageContents);
          } else {
            $this->executionStatus = false;
          }
        } else {
          $this->storeDraft();
        }
      } else if ($this->cgiValue('Cancel', false)) {
        // do some cleanup, i.e. remove temporay storage from file attachments
        $this->cleanTemporaries();
      } else if ($this->cgiValue('Send', false)) {
        if (!$this->preComposeValidation()) {
          return;
        }

        // Checks passed, let's see what happens. The mailer may throw
        // any kind of "nasty" exceptions.
        $this->sendMessages();
        if (!$this->errorStatus()) {
          // Hurray!!!
          $this->diagnostics['Caption'] = L::t('Message(s) sent out successfully!');

          // If sending out a draft, remove the draft.
          $this->deleteDraft();
        }
      } else if ($this->cgiValue('MessageExport', false)) {
        if (!$this->preComposeValidation()) {
          return;
        }

        // Checks passed, let's see what happens. The mailer may throw
        // any kind of "nasty" exceptions.
        $this->exportMessages();
        if (!$this->errorStatus()) {
          $this->diagnostics['Caption'] = L::t('Message(s) exported successfully!');
        }
      }
    }

    /**Fetch a CGI-variable out of the form-select name-space */
    private function cgiValue($key, $default = null)
    {
      if (isset($this->cgiData[$key])) {
        $value = $this->cgiData[$key];
        if (is_string($value)) {
          $value = trim($value);
        }
        return $value;
      } else {
        return $default;
      }
    }

    /**Return true if this email needs per-member substitutions. Up to
     * now validation is not handled here, but elsewhere. Still this
     * is not a static method (future ...)
     */
    private function isMemberTemplateEmail($message)
    {
      return preg_match('!([^$]|^)[$]{MEMBER::[^{]+}!', $message);
    }

    /**Substitute any per-recipient variables into $templateMessage
     *
     * @param $templateMessage The email message without substitutions.
     *
     * @param $dbdata The data from the musician data-base which
     * contains the substitutions.
     *
     * @return HTML message with variable substitutions.
     */
    private function replaceMemberVariables($templateMessage, $dbdata)
    {
      $enckey = Config::getEncryptionKey();      
      if ($dbdata['Geburtstag'] && $dbdata['Geburtstag'] != '') {
        $dbdata['Geburtstag'] = date('d.m.Y', strtotime($dbdata['Geburtstag']));
      }
      $strMessage = $templateMessage;
      foreach ($this->memberVariables as $placeholder => $column) {
        if ($placeholder === 'EXTRAS') {
          // EXTRAS is a multi-field stuff. In order to allow stuff
          // like forming tables we allow lead-in, lead-out and separator.
          if ($this->projectId <= 0) {
            $extras = [ [ 'label' => L::t("nothing"), 'surcharge' => 0 ] ];
          } else {
            $extras = $dbdata[$column];
          }
          $strMessage = preg_replace_callback(
            '/([^$]|^)[$]{MEMBER::EXTRAS(:([^!}]+)!([^!}]+)!([^}]+))?}/',
            function($matches) use ($extras) {
              if (count($matches) === 6) {
                $pre  = $matches[3];
                $sep  = $matches[4];
                $post = $matches[5];
              } else {
                $pre  = '';
                $sep  = ': ';
                $post = "\n";
              }
              $result = $matches[1];
              foreach($extras as $records) {
                $result .=
                  $pre.
                  $records['label'].
                  $sep.
                  $records['surcharge'].
                  $post;
              }
              return $result;
            },
            $strMessage);
          continue;
        } else if ($placeholder === 'SEPAMANDATSIBAN' ||
                   $placeholder === 'SEPAMANDATSBIC' ||
                   $placeholder === 'SEPAMANDATSINHABER') {
          $dbdata[$column] = Config::decrypt($dbdata[$column], $enckey);
          if ($placeholder === 'SEPAMANDATSIBAN') {
            $dbdata[$column] = substr($dbdata[$column], 0, 6).'...'.substr($dbdata[$column], -4, 4);
          }
        }
        // replace all remaining stuff
        $strMessage = preg_replace('/([^$]|^)[$]{MEMBER::'.$placeholder.'}/',
                                   '${1}'.htmlspecialchars($dbdata[$column]),
                                   $strMessage);
      }
      $strMessage = preg_replace('/[$]{2}{/', '${', $strMessage);
      return $strMessage;
    }

    /**Substitute any global variables into
     * $this->messageContents.
     *
     * @return HTML message with variable substitutions.
     */
    private function replaceGlobals()
    {
      $message = $this->messageContents;

      if (preg_match('!([^$]|^)[$]{GLOBAL::[^{]+}!', $message)) {
        $vars = $this->emailGlobalVariables();

        // TODO: one call to preg_replace would be enough, but does
        // not really matter as long as there is only one global
        // variable.
        foreach ($vars as $key => $value) {
          $message = preg_replace('/([^$]|^)[$]{GLOBAL::'.$key.'}/', '${1}'.$value, $message);
        }

        // Support date substitutions. Format is
        // ${GLOBAL::DATE:dateformat!datestring} where dateformat
        // defaults to d.m.Y. datestring is everything understood by
        // strtotime().
        $oldLocale = setlocale(LC_TIME, '0');
        setlocale(LC_TIME, Util::getLocale());
        $message = preg_replace_callback(
          '/([^$]|^)[$]{GLOBAL::DATE:([^!]*)!([^}]*)}/',
          function($matches) use ($vars) {
            $dateFormat = $matches[2];
            $timeString = $matches[3];
            // if one of the other global variables translates to a
            // date, then it is also allowed as date-string.
            if (array_key_exists($timeString, $vars)) {
              $timeString = $vars[$timeString];
            }
            return $matches[1].strftime($dateFormat, strtotime($timeString));
          },
          $message
          );
        setlocale(LC_TIME, $oldLocale);
      }

      return $message;
    }

    /**Finally, send the beast out to all recipients, either in
     * single-email mode or as one message.
     *
     * Template emails are emails with per-member variable
     * substitutions. This means that we cannot send one email to
     * all recipients, but have to send different emails one by
     * one. This has some implicatios:
     *
     * - extra recipients added through the Cc: and Bcc: fields
     *   and the catch-all address is not added to each
     *   email. Instead, we send out the template without
     *   substitutions.
     *
     * - still each single email is logged to the DB in order to
     *   catch duplicates.
     *
     * - each single email is copied to the Sent-folder; this is how it should be.
     *
     * - after variable substitution we need to reencode some
     *   special characters.
     */
    private function sendMessages()
    {
      // The following cannot fail, in principle. $message is then
      // the current template without any left-over globals.
      $message = $this->replaceGlobals();

      if ($this->isMemberTemplateEmail($message)) {

        $this->diagnostics['TotalPayload'] = count($this->recipients)+1;

        foreach ($this->recipients as $recipient) {
          $dbdata = $recipient['dbdata'];
          $strMessage = $this->replaceMemberVariables($message, $dbdata);
          ++$this->diagnostics['TotalCount'];
          $msg = $this->composeAndSend($strMessage, array($recipient), false);
	  if (!empty($msg['message'])) {
            $this->copyToSentFolder($msg['message']);
            // Don't remember the individual emails, but for
            // debit-mandates record the message id, ignore errors.
            if ($this->debitNoteId > 0 && $dbdata['PaymentId'] > 0) {
              $messageId = $msg['messageId'];
              $where =  '`Id` = '.$dbdata['PaymentId'].' AND `DebitNoteId` = '.$this->debitNoteId;
              mySQL::update('ProjectPayments', $where, array('DebitMessageId' => $messageId), $this->dbh);
            }
	  } else {
            ++$this->diagnostics['FailedCount'];
          }
        }

        // Finally send one message without template substitution (as
        // this makes no sense) to all Cc:, Bcc: recipients and the
        // catch-all. This Message also gets copied to the Sent-folder
        // on the imap server.
        ++$this->diagnostics['TotalCount'];
        $mimeMsg = $this->composeAndSend($message, array(), true);
        if (!empty($mimeMsg['message'])) {
          $this->copyToSentFolder($mimeMsg['message']);
          $this->recordMessageDiagnostics($mimeMsg['message']);
        } else {
          ++$this->diagnostics['FailedCount'];
        }
      } else {
        $this->diagnostics['TotalPayload'] = 1;
        ++$this->diagnostics['TotalCount']; // this is ONE then ...
        $mimeMsg = $this->composeAndSend($message, $this->recipients);
        if (!empty($mimeMsg['message'])) {
          $this->copyToSentFolder($mimeMsg['message']);
          $this->recordMessageDiagnostics($mimeMsg['message']);
        } else {
          ++$this->diagnostics['FailedCount'];
        }
      }
      return $this->executionStatus;
    }

    /**Extract the first few line of a text-buffer.
     *
     * @param $text The text to compute the "head" of.
     *
     * @param $lines The number of lines to return at most.
     *
     * @param $separators Regexp for preg_split. The default is just
     * "/\\n/". Note that this is enough for \\n and \\r\\n as the text is
     * afterwars imploded again with \n separator.
     */
    static private function head($text, $lines = 64, $separators = "/\n/")
    {
      $text = preg_split($separators, $text, $lines+1);
      if (isset($text[$lines])) {
        unset($text[$lines]);
      }
      return implode("\n", $text);
    }

    /**Compose and send one message. If $EMails only contains one
     * address, then the emails goes out using To: and Cc: fields,
     * otherwise Bcc: is used, unless sending to the recipients of a
     * project. All emails are logged with an MD5-sum to the DB in order
     * to prevent duplicate mass-emails. If a duplicate is detected the
     * message is not sent out. A duplicate is something with the same
     * message text and the same recipient list.
     *
     * @param[in] $strMessage The message to send.
     *
     * @param[in] $EMails The recipient list
     *
     * @param[in] $addCC If @c false, then additional CC and BCC recipients will
     *                   not be added.
     *
     * @param[in] $allowDuplicates Whether n ot to check for
     * duplicates. This is currently only set to true when
     * sending a copy of a form-email with per-recipient substitutions
     * to the orchestra account.
     *
     * @return The sent Mime-message which then may be stored in the
     * Sent-Folder on the imap server (for example).
     */
    private function composeAndSend($strMessage, $EMails, $addCC = true, $allowDuplicates = false)
    {
      // If we are sending to a single address (i.e. if $strMessage has
      // been constructed with per-member variable substitution), then
      // we do not need to send via BCC.
      $singleAddress = count($EMails) == 1;

      // Construct an array for the data-base log
      $logMessage = new \stdClass;
      $logMessage->recipients = $EMails;
      $logMessage->message = $strMessage;

      // One big try-catch block. Using exceptions we do not need to
      // keep track of all return values, which is quite beneficial
      // here. Some of the stuff below clearly cannot throw, but then
      // it doesn't hurt to keep it in the try-block. All data is
      // added in the try block. There is another try-catch-construct
      // surrounding the actual sending of the message.
      try {

        $phpMailer = new \PHPMailer(true);
        $phpMailer->CharSet = 'utf-8';
        $phpMailer->SingleTo = false;

        $phpMailer->IsSMTP();

        // Provide some progress feed-back to amuse the user
        $progressProvider = new ProgressStatus();
        $diagnostics = $this->diagnostics;
        $phpMailer->ProgressCallback = function($currentLine, $totalLines) use ($diagnostics, $progressProvider) {
          if ($currentLine == 0) {
            $tag = array('proto' => 'smtp',
                         'total' =>  $diagnostics['TotalPayload'],
                         'active' => $diagnostics['TotalCount']);
            $tag = json_encode($tag);
            $progressProvider->save($currentLine, $totalLines, $tag);
          } else if ($currentLine % 1024 == 0 || $currentLine >= $totalLines) {
            $progressProvider->save($currentLine, $totalLines);
          }
        };

        $phpMailer->Host = Config::getValue('smtpserver');
        $phpMailer->Port = Config::getValue('smtpport');
        switch (Config::getValue('smtpsecure')) {
        case 'insecure': $phpMailer->SMTPSecure = ''; break;
        case 'starttls': $phpMailer->SMTPSecure = 'tls'; break;
        case 'ssl':      $phpMailer->SMTPSecure = 'ssl'; break;
        default:         $phpMailer->SMTPSecure = ''; break;
        }
        $phpMailer->SMTPAuth = true;
        $phpMailer->Username = Config::getValue('emailuser');
        $phpMailer->Password = Config::getValue('emailpassword');

        $phpMailer->Subject = $this->messageTag . ' ' . $this->subject();
        $logMessage->subject = $phpMailer->Subject;
        // pass the correct path in order for automatic image conversion
        $phpMailer->msgHTML($strMessage, __DIR__, true);

        $senderName = $this->fromName();
        $senderEmail = $this->fromAddress();
        $phpMailer->AddReplyTo($senderEmail, $senderName);
        $phpMailer->SetFrom($senderEmail, $senderName);

        if (!$this->constructionMode) {
          // Loop over all data-base records and add each recipient in turn
          foreach ($EMails as $recipient) {
            if ($singleAddress) {
              $phpMailer->AddAddress($recipient['email'], $recipient['name']);
            } else if ($recipient['project'] < 0) {
              // blind copy, don't expose the victim to the others.
              $phpMailer->AddBCC($recipient['email'], $recipient['name']);
            } else {
              // Well, people subscribing to one of our projects
              // simply must not complain, except soloist or
              // conductors which normally are not bothered with
              // mass-email at all, but if so, then they are added as Bcc
              if ($recipient['status'] == 'conductor' ||
                  $recipient['status'] == 'soloist') {
                $phpMailer->AddBCC($recipient['email'], $recipient['name']);
              } else {
                $phpMailer->AddAddress($recipient['email'], $recipient['name']);
              }
            }
          }
        } else {
          // Construction mode: per force only send to the developer
          $phpMailer->AddAddress($this->catchAllEmail, $this->catchAllName);
        }

        if ($addCC === true) {
          // Always drop a copy to the orchestra's email account for
          // archiving purposes and to catch illegal usage. It is legel
          // to modify $this->sender through the email-form.
          $phpMailer->AddCC($this->catchAllEmail, $senderName);
        }

        // If we have further Cc's, then add them also
        $stringCC = '';
        if ($addCC === true && !empty($this->onLookers['CC'])) {
          // Now comes some dirty work: we need to split the string in
          // names and email addresses. We re-construct $this->CC in this
          // context, to normalize it for storage in the email-log.

          foreach ($this->onLookers['CC'] as $value) {
            $stringCC .= $value['name'].' <'.$value['email'].'>, ';
            // PHP-Mailer adds " for itself as needed
            $value['name'] = trim($value['name'], '"');
            $phpMailer->AddCC($value['email'], $value['name']);
          }
          $stringCC = trim($stringCC, ', ');
        }
        $logMessage->CC = $stringCC;

        // Do the same for Bcc
        $stringBCC = '';
        if ($addCC === true && !empty($this->onLookers['BCC'])) {
          // Now comes some dirty work: we need to split the string in
          // names and email addresses.

          foreach ($this->onLookers['BCC'] as $value) {
            $stringBCC .= $value['name'].' <'.$value['email'].'>, ';
            // PHP-Mailer adds " for itself as needed
            $value['name'] = trim($value['name'], '"');
            $phpMailer->AddBCC($value['email'], $value['name']);
          }
          $stringBCC = trim($stringBCC, ', ');
        }
        $logMessage->BCC = $stringBCC;

        // Add all registered attachments.
        $attachments = $this->fileAttachments();
        $logMessage->fileAttach = $attachments;
        foreach ($attachments as $attachment) {
          if ($attachment['status'] != 'selected') {
            continue;
	  }
	  if ($attachment['type'] == 'message/rfc822') {
            $encoding = '8bit';
	  } else {
            $encoding = 'base64';
	  }
          $phpMailer->AddAttachment($attachment['tmp_name'],
                                    basename($attachment['name']),
                                    $encoding,
                                    $attachment['type']);
        }

        // Finally possibly to-be-attached events. This cannot throw,
        // but it does not hurt to keep it here. This way we are just
        // ready with adding data to the message inside the try-block.
        $events = $this->eventAttachments();
        $logMessage->events = $events;
        if ($this->projectId >= 0 && !empty($events)) {
          // Construct the calendar
          $calendar = Events::exportEvents($events, $this->projectName);

          // Encode it as attachment
          $phpMailer->AddStringEmbeddedImage($calendar,
                                             md5($this->projectName.'.ics'),
                                             $this->projectName.'.ics',
                                             'quoted-printable',
                                             'text/calendar');
        }

      } catch (\Exception $exception) {
        // popup an alert and abort the form-processing

        $this->executionStatus = false;
        $this->diagnostics['MailerExceptions'][] =
          $exception->getFile().
          '('.$exception->getLine().
          '): '.
          $exception->getMessage();

        return false;
      }

      $logQuery = $this->messageLogQuery($logMessage);
      if (!$logQuery) {
        return false;
      }

      // Finally the point of no return. Send it out!!!
      try {
        if (!$phpMailer->Send()) {
          // in principle this cannot happen as the mailer DOES use
          // exceptions ...
          $this->executionStatus = false;
          $this->diagnostics['MailerErrors'][] = $phpMailer->ErrorInfo;
          return false;
        } else {
          // success, log the message to our data-base
          $handle = $this->dataBaseConnect();
          mySQL::query($logQuery, $handle);
        }
      } catch (\Exception $exception) {
        $this->executionStatus = false;
        $this->diagnostics['MailerExceptions'][] =
          $exception->getFile().
          '('.$exception->getLine().
          '): '.
          $exception->getMessage();

        return false;
      }

      return array('messageId' => $phpMailer->getLastMessageID(),
                   'message' => $phpMailer->GetSentMIMEMessage());
    }

    /**Record diagnostic output from the actual message composition for the status page.
     */
    private function recordMessageDiagnostics($mimeMsg)
    {
      // Positive diagnostics
      $this->diagnostics['Message']['Text'] = self::head($mimeMsg, 40);

      $this->diagnostics['Message']['Files'] = array();
      $attachments = $this->fileAttachments();
      foreach ($attachments as $attachment) {
        if ($attachment['status'] != 'selected') {
          continue;
        }
        $size     = \OC_Helper::humanFileSize($attachment['size']);
        $name     = basename($attachment['name']).' ('.$size.')';
        $this->diagnostics['Message']['Files'][] = $name;
      }

      $this->diagnostics['Message']['Events'] = array();
      $events = $this->eventAttachments();
      $locale = Util::getLocale();
      $zone = Util::getTimezone();
      foreach($events as $eventId) {
        $event = Events::fetchEvent($eventId);
        $datestring = Events::briefEventDate($event, $zone, $locale);
        $name = stripslashes($event['summary']).', '.$datestring;
        $this->diagnostics['Message']['Events'][] = $name;
      }
    }

    /**Take the supplied message and copy it to the "Sent" folder.
     */
    private function copyToSentFolder($mimeMessage)
    {
      // PEAR IMAP works without the c-client library
      ini_set('error_reporting', ini_get('error_reporting') & ~E_STRICT);

      $imaphost   = Config::getValue('imapserver');
      $imapport   = Config::getValue('imapport');
      $imapsecure = Config::getValue('imapsecure');

      $progressProvider = new ProgressStatus(0);
      $diagnostics = $this->diagnostics;
      $imap = new \Net_IMAP($imaphost,
                            $imapport,
                            $imapsecure == 'starttls' ? true : false, 'UTF-8',
                            function($pos, $total) use ($diagnostics, $progressProvider) {
                              if ($total < 128) {
                                return; // ignore non-data transfers
                              }
                              if ($pos == 0) {
                                $tag = array('proto' => 'imap',
                                             'total' =>  $diagnostics['TotalPayload'],
                                             'active' => $diagnostics['TotalCount']);
                                $tag = json_encode($tag);
                                $progressProvider->save($pos, $total, $tag);
                              } else {
                                $progressProvider->save($pos);
                              }
                            },
                            64*1024); // 64 kb chunk-size

      $user = Config::getValue('emailuser');
      $pass = Config::getValue('emailpassword');
      if (($ret = $imap->login($user, $pass)) !== true) {
        $this->executionStatus = false;
        $this->diagnostics['CopyToSent']['login'] = $ret->toString();
        $imap->disconnect();
        return false;
      }

      if (($ret1 = $imap->selectMailbox('Sent')) === true) {
        $ret1 = $imap->appendMessage($mimeMessage, 'Sent');
      } else if (($ret2 = $imap->selectMailbox('INBOX.Sent')) === true) {
        $ret2 = $imap->appendMessage($mimeMessage, 'INBOX.Sent');
      }
      if ($ret1 !== true && $ret2 !== true) {
        $this->executionStatus = false;
        $this->diagnostics['CopyToSent']['copy'] = array('Sent' => $ret1->toString(),
                                                         'INBOX.Sent' => $ret2->toString());
        $imap->disconnect();
        return false;
      }
      $imap->disconnect();

      return true;
    }

    /**Log the sent message to the data base if it is new. Return
     * false if this is a duplicate, return the data-base query string
     * to be executed after successful sending of the message in cas
     * of success.
     *
     * @param[in] $logMessage The email-message to record in the DB.
     *
     * @param[in] $allowDuplicates Whether n ot to check for
     * duplicates. This is currently only set to true when
     * sending a copy of a form-email with per-recipient substitutions
     * to the orchestra account.
     *
     */
    private function messageLogQuery($logMessage, $allowDuplicates = false)
    {
      // Construct the query to store the email in the data-base
      // log-table.

      // Construct one MD5 for recipients subject and html-text
      $bulkRecipients = '';
      foreach ($logMessage->recipients as $pairs) {
        $bulkRecipients .= $pairs['name'].' <'.$pairs['email'].'>,';
      }

      $bulkRecipients = trim($bulkRecipients,',');
      $bulkMD5 = md5($bulkRecipients);

      $textforMD5 = $logMessage->subject . $logMessage->message;
      $textMD5 = md5($textforMD5);

      // compute the MD5 stuff for the attachments
      $attachLog = array();
      foreach ($logMessage->fileAttach as $attachment) {
        if ($attachment['status'] != 'selected') {
          continue;
        }
        if ($attachment['name'] != "") {
          $md5val = md5_file($attachment['tmp_name']);
          $attachLog[] = array('name' => $attachment['name'],
                               'md5' => $md5val);
        }
      }
      if (!empty($logMessage->events)) {
        sort($logMessage->events);
        $name = 'Events-'.implode('-', $logMessage->events);
        $md5 = md5($name);
        $attachLog[] = array('name' => $name, 'md5' => $md5);
      }

      // Now insert the stuff into the SentEmail table
      $handle = $this->dataBaseConnect();

      // First make sure that we have enough columns to store the
      // attachments (better: only their checksums)

      foreach ($attachLog as $key => $value) {
        $query = sprintf(
          'ALTER TABLE `SentEmail`
  ADD `Attachment%02d` TEXT
  CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  ADD `MD5Attachment%02d` TEXT
  CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL',
          $key, $key);

        // And execute. Just to make that all needed columns exist.

        $result = mySQL::query($query, $handle, false, true);
      }

      // Now construct the real query, but do not execute it until the
      // message has succesfully been sent.

      $logQuery = "INSERT INTO `SentEmail`
(`user`,`host`,`BulkRecipients`,`MD5BulkRecipients`,`Cc`,`Bcc`,`Subject`,`HtmlBody`,`MD5Text`";
      $idx = 0;
      foreach ($attachLog as $pairs) {
        $logQuery .=
          sprintf(",`Attachment%02d`,`MD5Attachment%02d`", $idx, $idx);
        $idx++;
      }

      $logQuery .= ') VALUES (';
      $logQuery .= "'".$this->user."','".$_SERVER['REMOTE_ADDR']."'";
      $logQuery .= ",'".mySQL::escape($bulkRecipients, $handle)."'";
      $logQuery .= ",'".mySQL::escape($bulkMD5, $handle)."'";
      $logQuery .= ",'".mySQL::escape($logMessage->CC, $handle)."'";
      $logQuery .= ",'".mySQL::escape($logMessage->BCC, $handle)."'";
      $logQuery .= ",'".mySQL::escape($logMessage->subject, $handle)."'";
      $logQuery .= ",'".mySQL::escape($logMessage->message, $handle)."'";
      $logQuery .= ",'".mySQL::escape($textMD5, $handle)."'";
      foreach ($attachLog as $pairs) {
        $logQuery .=
          ",'".mySQL::escape($pairs['name'], $handle)."'".
          ",'".mySQL::escape($pairs['md5'], $handle)."'";
      }
      $logQuery .= ")";

      // Now logging is ready to execute. But first check for
      // duplicate sending attempts. This takes only the recipients,
      // the subject and the message body into account. Rationale: if
      // you want to send an updated attachment, then you really
      // should write a comment on that. Still the test is flaky
      // enough.

      if ($allowDuplicates !== true) {
        // Check for duplicates
        $loggedQuery = "SELECT * FROM `SentEmail` WHERE";
        $loggedQuery .= " `MD5Text` LIKE '$textMD5'";
        $loggedQuery .= " AND `MD5BulkRecipients` LIKE '$bulkMD5'";
        $result = mySQL::query($loggedQuery, $handle);

        $cnt = 0;
        $loggedDates = '';
        if ($line = mySQL::fetch($result)) {
          $loggedDates .= ', '.$line['Date'];
          ++$cnt;
        }
        $loggedDates = trim($loggedDates,', ');

        if ($loggedDates != '') {
          $this->executionStatus = false;
          $shortRecipients = array();
          foreach($logMessage->recipients as $recipient) {
            $shortRecipients[] = $recipient['name'].' <'.$recipient['email'].'>';
          }
          $this->diagnostics['Duplicates'][] = array(
            'dates' => $loggedDates,
            'recipients' => $shortRecipients,
            'text' => $logMessage->message,
            'textMD5' => $textMD5,
            'bulkMD5' => $bulkMD5,
            'bulkRecipients' => $bulkRecipients
            );
          return false;
        }
      }

      return $logQuery;
    }

    /**Compose and export one message to HTML.
     *
     * @param[in] $strMessage The message to send.
     *
     * @param[in] $EMails The recipient list
     *
     * @param[in] $addCC If @c false, then additional CC and BCC recipients will
     *                   not be added.
     *
     * @return true or false.
     */
    private function composeAndExport($strMessage, $EMails, $addCC = true)
    {
      // If we are sending to a single address (i.e. if $strMessage has
      // been constructed with per-member variable substitution), then
      // we do not need to send via BCC.
      $singleAddress = count($EMails) == 1;

      // Construct an array for the data-base log
      $logMessage = new \stdClass;
      $logMessage->recipients = $EMails;
      $logMessage->message = $strMessage;

      // First part: go through the composition part of PHPMailer in
      // order to have some consistency checks. If this works, we
      // export the message text, with a short header.
      try {

        $phpMailer = new \PHPMailer(true);
        $phpMailer->CharSet = 'utf-8';
        $phpMailer->SingleTo = false;

        $phpMailer->Subject = $this->messageTag . ' ' . $this->subject();
        $logMessage->subject = $phpMailer->Subject;
        // pass the correct path in order for automatic image conversion
        $phpMailer->msgHTML($strMessage, __DIR__.'/../', true);

        $senderName = $this->fromName();
        $senderEmail = $this->fromAddress();
        $phpMailer->AddReplyTo($senderEmail, $senderName);
        $phpMailer->SetFrom($senderEmail, $senderName);

        // Loop over all data-base records and add each recipient in turn
        foreach ($EMails as $recipient) {
          if ($singleAddress) {
            $phpMailer->AddAddress($recipient['email'], $recipient['name']);
          } else if ($recipient['project'] < 0) {
            // blind copy, don't expose the victim to the others.
            $phpMailer->AddBCC($recipient['email'], $recipient['name']);
          } else {
            // Well, people subscribing to one of our projects
            // simply must not complain, except soloist or
            // conductors which normally are not bothered with
            // mass-email at all, but if so, then they are added as Bcc
            if ($recipient['status'] == 'conductor' ||
                $recipient['status'] == 'soloist') {
              $phpMailer->AddBCC($recipient['email'], $recipient['name']);
            } else {
              $phpMailer->AddAddress($recipient['email'], $recipient['name']);
            }
          }
        }

        if ($addCC === true) {
          // Always drop a copy to the orchestra's email account for
          // archiving purposes and to catch illegal usage. It is legel
          // to modify $this->sender through the email-form.
          $phpMailer->AddCC($this->catchAllEmail, $senderName);
        }

        // If we have further Cc's, then add them also
        $stringCC = '';
        if ($addCC === true && !empty($this->onLookers['CC'])) {
          // Now comes some dirty work: we need to split the string in
          // names and email addresses. We re-construct $this->CC in this
          // context, to normalize it for storage in the email-log.

          foreach ($this->onLookers['CC'] as $value) {
            $stringCC .= $value['name'].' <'.$value['email'].'>, ';
            // PHP-Mailer adds " for itself as needed
            $value['name'] = trim($value['name'], '"');
            $phpMailer->AddCC($value['email'], $value['name']);
          }
          $stringCC = trim($stringCC, ', ');
        }
        $logMessage->CC = $stringCC;

        // Do the same for Bcc
        $stringBCC = '';
        if ($addCC === true && !empty($this->onLookers['BCC'])) {
          // Now comes some dirty work: we need to split the string in
          // names and email addresses.

          foreach ($this->onLookers['BCC'] as $value) {
            $stringBCC .= $value['name'].' <'.$value['email'].'>, ';
            // PHP-Mailer adds " for itself as needed
            $value['name'] = trim($value['name'], '"');
            $phpMailer->AddBCC($value['email'], $value['name']);
          }
          $stringBCC = trim($stringBCC, ', ');
        }
        $logMessage->BCC = $stringBCC;

        // Add all registered attachments.
        $attachments = $this->fileAttachments();
        $logMessage->fileAttach = $attachments;
        foreach ($attachments as $attachment) {
          if ($attachment['status'] != 'selected') {
            continue;
          }
          $phpMailer->AddAttachment($attachment['tmp_name'],
                                    basename($attachment['name']),
                                    'base64',
                                    $attachment['type']);
        }

        // Finally possibly to-be-attached events. This cannot throw,
        // but it does not hurt to keep it here. This way we are just
        // ready with adding data to the message inside the try-block.
        $events = $this->eventAttachments();
        $logMessage->events = $events;
        if ($this->projectId >= 0 && !empty($events)) {
          // Construct the calendar
          $calendar = Events::exportEvents($events, $this->projectName);

          // Encode it as attachment
          $phpMailer->AddStringEmbeddedImage($calendar,
                                             md5($this->projectName.'.ics'),
                                             $this->projectName.'.ics',
                                             'quoted-printable',
                                             'text/calendar');
        }

      } catch (\Exception $exception) {
        // popup an alert and abort the form-processing

        $this->executionStatus = false;
        $this->diagnostics['MailerExceptions'][] =
          $exception->getFile().
          '('.$exception->getLine().
          '): '.
          $exception->getMessage();

        return false;
      }

      // Finally the point of no return. Send it out!!!
      try {
        if (!$phpMailer->preSend()) {
          // in principle this cannot happen as the mailer DOES use
          // exceptions ...
          $this->executionStatus = false;
          $this->diagnostics['MailerErrors'][] = $phpMailer->ErrorInfo;
          return false;
        } else {
          // success, would log success if we really were sending
        }
      } catch (\Exception $exception) {
        $this->executionStatus = false;
        $this->diagnostics['MailerExceptions'][] =
          $exception->getFile().
          '('.$exception->getLine().
          '): '.
          $exception->getMessage();

        return false;
      }

      echo '<div class="email-header"><pre>'.htmlspecialchars($phpMailer->getMailHeaders()).'</pre></div>';
      echo '<div class="email-body">';
      echo $strMessage;
      echo '</div>';
      echo '<hr style="page-break-after:always;"/>';

      return true;
    }

    /**Generate a HTML-export with all variables substituted. This is
     *primarily meant in order to debug actual variable substitutions,
     *or to have hardcopies from debit note notifications and other
     *important emails.
     */
    private function exportMessages()
    {
      // The following cannot fail, in principle. $message is then
      // the current template without any left-over globals.
      $message = $this->replaceGlobals();

      if ($this->isMemberTemplateEmail($message)) {

        $this->diagnostics['TotalPayload'] = count($this->recipients)+1;

        foreach ($this->recipients as $recipient) {
          $strMessage = $this->replaceMemberVariables($message, $recipient['dbdata']);
          ++$this->diagnostics['TotalCount'];
          if (!$this->composeAndExport($strMessage, array($recipient), false)) {
            ++$this->diagnostics['FailedCount'];
          }
        }

        // Finally send one message without template substitution (as
        // this makes no sense) to all Cc:, Bcc: recipients and the
        // catch-all. This Message also gets copied to the Sent-folder
        // on the imap server.
        ++$this->diagnostics['TotalCount'];
        if (!$this->composeAndExport($message, array(), true)) {
          ++$this->diagnostics['FailedCount'];
        }
      } else {
        $this->diagnostics['TotalPayload'] = 1;
        ++$this->diagnostics['TotalCount']; // this is ONE then ...
        if (!$this->composeAndExport($message, $this->recipients)) {
          ++$this->diagnostics['FailedCount'];
        }
      }

      return $this->executionStatus;
    }

    /**Pre-message construction validation. Collect all data and
     *perform some checks on it.
     *
     * - Cc, valid email addresses
     * - Bcc, valid email addresses
     * - subject, must not be empty
     * - message-text, variable substitutions
     * - sender name, must not be empty
     * - file attchments, temporary local copy must exist
     * - events, must exist
     */
    private function preComposeValidation()
    {
      // Basic boolean stuff
      if ($this->subject() == '') {
        $this->diagnostics['SubjectValidation'] = $this->messageTag;
        $this->executionStatus = false;
      } else {
        $this->diagnostics['SubjectValidation'] = true;
      }
      if ($this->fromName() == '') {
        $this->diagnostics['FromValidation'] = $this->catchAllName;
        $this->executionStatus = false;
      } else {
        $this->diagnostics['FromValidation'] = true;
      }
      if (empty($this->recipients)) {
        $this->diagnostics['AddressValidation']['Empty'] = true;
        $this->executionStatus = false;
      }

      // Template validation (i.e. variable substituions)
      $this->validateTemplate($this->messageContents);

      // Cc: and Bcc: validation
      foreach(array('CC' => $this->carbonCopy(),
                    'BCC' => $this->blindCarbonCopy()) as $key => $emails) {
        $this->onLookers[$key] =
          $this->validateFreeFormAddresses($key, $emails);
      }

      // file attachments, check the selected ones for readability
      $attachments = $this->fileAttachments();
      foreach($attachments as $attachment) {
        if ($attachment['status'] != 'selected') {
          continue; // don't bother
        }
        if (!is_readable($attachment['tmp_name'])) {
          $this->executionStatus = false;
          $attachment->status = 'unreadable';
          $this->diagnostics['AttchmentValidation']['Files'][] = $attachment;
        }
      }

      // event attachment
      $events = $this->eventAttachments();
      foreach($events as $eventId) {
        if (!Events::fetchEvent($eventId)) {
          $this->executionStatus = false;
          $this->diagnostics['AttachmentValidation']['Events'][] = $eventId;
        }
      }

      if (!$this->executionStatus) {
        $this->diagnostics['Caption'] = L::t('Pre-composition validation has failed!');
      }
      return $this->executionStatus;
    }

    /**Compute the subject tag, depending on whether we are in project
     * mode or not.
     */
    private function setSubjectTag()
    {
      if ($this->projectId < 0 || $this->projectName == '') {
        $this->messageTag = '[CAF-Musiker]';
      } else {
        $this->messageTag = '[CAF-'.$this->projectName.']';
      }
    }

    /**Validate a comma separated list of email address from the Cc:
     * or Bcc: input.
     *
     * @param $header For error diagnostics, either CC or BCC.
     *
     * @param $freeForm the value from the input field.
     *
     * @return false in case of error, otherwise a borken down list of
     * recipients array(array('name' => '"Doe, John"', 'email' => 'john@doe.org'),...)
     */
    public function validateFreeFormAddresses($header, $freeForm)
    {
      $phpMailer = new \PHPMailer(true);
      $parser = new \Mail_RFC822(null, null, null, false);

      $brokenRecipients = array();
      $parsedRecipients = $parser->parseAddressList($freeForm);
      $parseError = $parser->parseError();
      if ($parseError !== false) {
        \OCP\Util::writeLog(Config::APP_NAME,
                            "Parse-error on email address list: ".
                            vsprintf($parseError['message'], $parseError['data']),
                            \OCP\Util::DEBUG);
        // We report the entire string.
        $brokenRecipients[] = L::t($parseError['message'], $parseError['data']);
      } else {
        \OCP\Util::writeLog(Config::APP_NAME,
                            "Parsed address list: ".
                            print_r($parsedRecipients, true),
                            \OCP\Util::DEBUG);
        $recipients = array();
        foreach ($parsedRecipients as $emailRecord) {
          $email = $emailRecord->mailbox.'@'.$emailRecord->host;
          $name  = $emailRecord->personal;
          if ($name === '') {
            $recipient = $email;
          } else {
            $recipient = $name.' <'.$email.'>';
          }
          if ($emailRecord->host === 'localhost') {
            $brokenRecipients[] = htmlspecialchars($recipient);
          } else if (!$phpMailer->validateAddress($email)) {
            $brokenRecipients[] = htmlspecialchars($recipient);
          } else {
            $recipients[] = array('email' => $email,
                                  'name' => $name);
          }
        }
      }
      if (!empty($brokenRecipients)) {
        $this->diagnostics['AddressValidation'][$header] = $brokenRecipients;
        $this->executionStatus = false;
        return false;
      } else {
        \OCP\Util::writeLog(Config::APP_NAME,
                            "Returned address list: ".
                            print_r($recipients, true),
                            \OCP\Util::DEBUG);
        return $recipients;
      }
    }

    /**Validates the given template, i.e. searches for unknown
     * substitutions. This function is invoked right before sending
     * stuff out and before storing drafts. In order to do so we
     * substitute each known variable by a dummy value and then make
     * sure that no variable tag ${...} remains.
     */
    public function validateTemplate($template)
    {
      $templateError = array();

      // Check for per-member stubstitutions

      $dummy = $template;

      if (preg_match('!([^$]|^)[$]{MEMBER::[^}]+}!', $dummy)) {
        // Fine, we have substitutions. We should now verify that we
        // only have _legal_ substitutions. There are probably more
        // clever ways to do this, but at this point we simply
        // substitute any legal variable by DUMMY and check that no
        // unknown ${...} substitution tag remains. Mmmh.

        $variables = $this->memberVariables;
        foreach ($variables as $placeholder => $column) {
          if ($placeholder === 'EXTRAS') {
            $dummy = preg_replace(
              '/([^$]|^)[$]{MEMBER::EXTRAS(:([^!}]+)!([^!}]+)!([^}]+))?}/',
              '${1}'.$column, $dummy);
            continue;
          }
          $dummy = preg_replace('/([^$]|^)[$]{MEMBER::'.$placeholder.'[^}]*}/', '${1}'.$column, $dummy);
        }

        if (preg_match('!([^$]|^)[$]{MEMBER::[^}]+}?!', $dummy, $leftOver)) {
          $templateError[] = 'member';
          $this->diagnostics['TemplateValidation']['MemberErrors'] = $leftOver;
        }

        // Now remove all member variables, known or not
        $dummy = preg_replace('/[$]{MEMBER::[^}]*}/', '', $dummy);
      }

      // Now check for global substitutions
      $globalTemplateLeftOver = array();
      if (preg_match('!([^$]|^)[$]{GLOBAL::[^}]+}!', $dummy)) {
        $variables = $this->emailGlobalVariables();

        // dummy replace all "ordinary" global variables
        foreach ($variables as $key => $value) {
          $dummy = preg_replace('/([^$]|^)[$]{GLOBAL::'.$key.'}/', '${1}'.$value, $dummy);
        }

        // replace all date-strings, but give a damn on valid results. Grin 8-)
        $dummy = preg_replace_callback(
          '/([^$]|^)[$]{GLOBAL::DATE:([^!]*)!([^}]*)}/',
          function($matches) {
            $dateFormat = $matches[2];
            $timeString = $matches[3];
            return $matches[1].strftime($dateFormat, strtotime($timeString));
          },
          $dummy
          );

        if (preg_match('!([^$]|^)[$]{GLOBAL::[^}]+}?!', $dummy, $leftOver)) {
          $templateError[] = 'global';
          $this->diagnostics['TemplateValidation']['GlobalErrors'] = $leftOver;
        }

        // Now remove all global variables, known or not
        $dummy = preg_replace('/([^$]|^)[$]{GLOBAL::[^}]*}/', '', $dummy);
      }

      $spuriousTemplateLeftOver = array();
      // No substitutions should remain. Check for that.
      if (preg_match('!([^$]|^)[$]{[^}]+}?!', $dummy, $leftOver)) {
        $templateError[] = 'spurious';
        $this->diagnostics['TemplateValidation']['SpuriousErrors'] = $leftOver;
      }

      if (empty($templateError)) {
        return true;
      }

      $this->executionStatus = false;

      return false;
    }

    private function setDefaultTemplate()
    {
      // Make sure that at least the default template exists and install
      // that as default text
      $this->initialTemplate = self::DEFAULT_TEMPLATE;

      $dbTemplate = $this->fetchTemplate('Default');
      if ($dbTemplate === false) {
        $this->storeTemplate('Default', '', $this->initialTemplate);
      } else {
        $this->initialTemplate = $dbTemplate;
      }
    }

    private function setCatchAll()
    {
      if ($this->constructionMode) {
        $this->catchAllEmail = Config::getValue('emailtestaddress');
        $this->catchAllName  = 'Bilbo Baggins';
      } else {
        $this->catchAllEmail = Config::getValue('emailfromaddress');
        $this->catchAllName  = Config::getValue('emailfromname');
      }
    }

    /**Return an associative array with keys and column names for the
     * values (Name, Stadt etc.) for substituting per-member data.
     */
    private function emailMemberVariables()
    {
      $vars   = preg_split('/\s+/', trim(self::MEMBERVARIABLES));
      $values = preg_split('/\s+/', trim(self::MEMBERCOLUMNS));
      return array_combine($vars, $values);
    }

    /**Compose an associative array with keys and values for global
     * variables which do not depend on the specific recipient.
     */
    private function emailGlobalVariables()
    {
      static $globalVars = false;
      if ($globalVars === false) {
        $globalVars = array(
          'ORGANIZER' => $this->fetchExecutiveBoard(),
          'CREDITORIDENTIFIER' => Config::getValue('bankAccountCreditorIdentifier'),
          'ADDRESS' => $this->streetAddress(),
          'BANKACCOUNT' => $this->bankAccount(),
          'PROJECT' => $this->projectName != '' ? $this->projectName : L::t('no project involved'),
          'DEBITNOTEDUEDATE' => '',
          'DEBITNOTEDUEDAYS' => '',
          'DEBITNOTESUBMITDATE' => '',
          'DEBITNOTESUBMITDAYS' => '',
          'DEBITNOTEJOB' => '',
          );

        if ($this->debitNoteId > 0) {
          $debitNote = DebitNotes::debitNote($this->debitNoteId, $this->dbh);

          $globalVars['DEBITNOTEJOB'] = L::t($debitNote['Job']);

          $oldLocale = setlocale(LC_TIME, '0');
          setlocale(LC_TIME, Util::getLocale());

          $oldTZ = date_default_timezone_get();
          $tz = Util::getTimezone();
          date_default_timezone_set($tz);

          $nowDate = new \DateTime(strftime('%Y-%m-%d'));
          $dueDate = new \DateTime($debitNote['DueDate']);
          $subDate = new \DateTime($debitNote['SubmissionDeadline']);

          $globalVars['DEBITNOTEDUEDAYS'] = $nowDate->diff($dueDate)->format('%r%a');
          $globalVars['DEBITNOTESUBMITDAYS'] =  $nowDate->diff($subDate)->format('%r%a');

          $globalVars['DEBITNOTEDUEDATE'] = strftime('%x', strtotime($debitNote['DueDate']));
          $globalVars['DEBITNOTESUBMITDATE'] = strftime('%x', strtotime($debitNote['SubmissionDeadline']));

          date_default_timezone_set($oldTZ);

          setlocale(LC_TIME, $oldLocale);
        }
      }
      return $globalVars;
    }

    private function streetAddress()
    {
      return
        Config::getValue('streetAddressName01')."<br/>\n".
        Config::getValue('streetAddressName02')."<br/>\n".
        Config::getValue('streetAddressStreet')."&nbsp;".
        Config::getValue('streetAddressHouseNumber')."<br/>\n".
        Config::getValue('streetAddressZIP')."&nbsp;".
        Config::getValue('streetAddressCity');
    }

    private function bankAccount()
    {
      $iban = new \IBAN(Config::getValue('bankAccountIBAN'));
      return
        Config::getValue('bankAccountOwner')."<br/>\n".
        "IBAN ".$iban->HumanFormat()." (".$iban->MachineFormat().")<br/>\n".
        "BIC ".Config::getValue('bankAccountBIC');
    }

    /**Fetch the pre-names of the members of the organizing committee in
     * order to construct an up-to-date greeting.
     */
    private function fetchExecutiveBoard()
    {
      $executiveBoard = Config::getValue('executiveBoardTable');

      $handle = $this->dataBaseConnect();

      $query = "SELECT `Vorname` FROM `".$executiveBoard."View` ORDER BY `Sortierung`,`Voice`,`SectionLeader` DESC,`Vorname`";

      $result = mySQL::query($query, $handle);

      if ($result === false) {
        throw new \RuntimeException("\n".L::t('Unable to fetch executive board contents from data-base.'.$query));
      }

      $vorstand = array();
      while ($line = mySQL::fetch($result)) {
        $vorstand[] = $line['Vorname'];
      }

      $cnt = count($vorstand);
      $text = $vorstand[0];
      for ($i = 1; $i < $cnt-1; $i++) {
        $text .= ', '.$vorstand[$i];
      }
      $text .= ' '.L::t('and').' '.$vorstand[$cnt-1];

      return $text;
    }

    public function dataBaseConnect()
    {
      if ($this->dbh === false) {
        $this->dbh = mySQL::connect($this->opts);
      }
      return $this->dbh;
    }

    private function dataBaseDisconnect()
    {
      if ($this->dbh !== false) {
        mySQL::close($this->dbh);
        $this->dbh = false;
      }
    }

    /**Take the text supplied by $contents and store it in the DB
     * EmailTemplates table with tag $templateName. An existing template with the
     * same tag will be replaced.
     */
    private function storeTemplate($templateName, $subject, $contents)
    {
      $handle = $this->dataBaseConnect();

      $contents = mySQL::escape($contents, $handle);

      $query = "REPLACE INTO `EmailTemplates` (`Tag`,`Subject`,`Contents`)
  VALUES ('".$templateName."','".$subject."','".$contents."')";

      // Ignore the result at this point.
      mySQL::query($query, $handle);
    }

    /**Delete the named email template.
     */
    private function deleteTemplate($templateName)
    {
      $handle = $this->dataBaseConnect();

      $query = "DELETE FROM `EmailTemplates` WHERE `Tag` LIKE '".$templateName."'";

      // Ignore the result at this point.
      mySQL::query($query, $handle);
    }

    /**Fetch a specific template from the DB. Return false if that template is not found
     */
    private function fetchTemplate($templateName)
    {
      $handle = $this->dataBaseConnect();

      $query   = "SELECT * FROM `EmailTemplates` WHERE `Tag` LIKE '".$templateName."'";
      $result  = mySQL::query($query, $handle);
      $line    = mySQL::fetch($result);
      $numrows = mySQL::numRows($result);

      if ($numrows !== 1) {
        return false;
      }

      if ($templateName !== 'Default' && !empty($line['Subject'])) {
        $this->cgiData['Subject'] = $line['Subject'];
      }

      return $line['Contents'];
    }

    /**Return a flat array with all known template names.
     */
    private function fetchTemplateNames()
    {
      $handle = $this->dataBaseConnect();

      $query  = "SELECT `Tag` FROM `EmailTemplates` WHERE 1";
      $result = mySQL::query($query, $handle);
      $names  = array();
      while ($line = mySQL::fetch($result)) {
        $names[] = $line['Tag'];
      }

      return $names;
    }

    /**Return an associative matrix with all currently stored draft
     * messages.  In order to load the draft we only need the id. The
     * list of drafts is used to generate a select menu where some
     * fancy title is displayed and the option value is the unique
     * draft id.
     */
    private function fetchDraftsList()
    {
      $handle = $this->dataBaseConnect();

      $query = "SELECT Id, Subject, Created, Updated FROM EmailDrafts WHERE 1";
      $result = mySQL::query($query, $handle);
      $drafts = array();
      while ($line = mySQL::fetch($result)) {
        $stamp = max(strtotime($line['Updated']),
                     strtotime($line['Created']));
        $stamp = date('Y-m-d', $stamp);
        $drafts[] = array('value' => $line['Id'],
                          'name' => $stamp . ": " . $line['Subject']);
      }
      if (count($drafts) == 0) {
        $drafts = array(array('value' => -1,
                              'name' => L::t('empty')));
      }
      return $drafts;
    }

    /**Store a draft message. The only constraint on the "operator
     * behaviour" is that the subject must not be empty. Otherwise in
     * any way incomplete messages may be stored as drafts.
     */
    private function storeDraft()
    {
      if ($this->subject() == '') {
        $this->diagnostics['SubjectValidation'] = $this->messageTag;
        $this->executionStatus = false;
        return;
      } else {
        $this->diagnostics['SubjectValidation'] = true;
      }

      $draftData = array('ProjectId' => $_POST['ProjectId'],
                         'ProjectName' => $_POST['ProjectName'],
                         'DebitNoteId' => $_POST['DebitNoteId'],
                         self::POST_TAG => $_POST[self::POST_TAG],
                         EmailRecipientsFilter::POST_TAG => $_POST[EmailRecipientsFilter::POST_TAG]);

      unset($draftData[self::POST_TAG]['Request']);
      unset($draftData[self::POST_TAG]['SubmitAll']);
      unset($draftData[self::POST_TAG]['SaveMessage']);

      $dataJSON = json_encode($draftData);
      $subject = $this->subjectTag() . ' ' . $this->subject();

      $handle = $this->dataBaseConnect();
      $dataJSON = mySQL::escape($dataJSON, $handle);
      $subject = mySQL::escape($subject, $handle);

      if ($this->draftId >= 0) {
        $query = "INSERT INTO `EmailDrafts` (Id,Subject,Data,Created)
  VALUES (".$this->draftId.",'".$subject."','".$dataJSON."',NOW())
  ON DUPLICATE KEY UPDATE
  Subject = '".$subject."', Data = '".$dataJSON."'";
        if (mySQL::query($query, $handle, false, true) === false) {
          $this->executionStatus = false;
        }
      } else {
        $query = "INSERT INTO `EmailDrafts` (Subject,Data,Created)
  VALUES ('".$subject."','".$dataJSON."',NOW())";
        if (mySQL::query($query, $handle) === false) {
          $this->executionStatus = false;
        } else {
          $newId = MySQL::newestIndex($handle);
          if (!$newId) {
            $this->executionStatus = false;
          } else {
            $this->draftId = $newId;
          }
        }
      }

      // Update the list of attachments, if any
      foreach ($this->fileAttachments() as $attachment) {
        self::rememberTemporaryFile($attachment['tmp_name']);
      }
    }

    /**Preliminary draft read-back. */
    public function loadDraft()
    {
      if ($this->draftId >= 0) {
        $handle = $this->dataBaseConnect();

        $data = mySQL::fetchRows('EmailDrafts', "`Id` = ".$this->draftId, null, $handle, null, null);

        if (count($data) == 1) {
          $draftData = json_decode($data[0]['Data'], true, 512, JSON_BIGINT_AS_STRING);

          // undo request actions
          unset($draftData[self::POST_TAG]['Request']);
          unset($draftData[self::POST_TAG]['SubmitAll']);
          unset($draftData[self::POST_TAG]['SaveMessage']);

          if (empty($draftData['DebitNoteId'])) {
            $draftData['DebitNoteId'] = -1;
          }

          return $draftData;
        }
      }
      return false;
    }

    /**Delete the current message draft. */
    private function deleteDraft()
    {
      if ($this->draftId >= 0 )  {
        $handle = $this->dataBaseConnect();

        $query = "DELETE FROM `EmailDrafts` WHERE `Id` = ".$this->draftId;

        // Ignore the result at this point.
        mySQL::query($query, $handle);

        // detach any attachnments for later clean-up
        $this->detachTemporaryFiles();

        // Mark as gone
        $this->draftId = -1;
      }
    }

    /**** file temporary utilities ***/

    /**Delete all temorary files not found in $fileAttach. If the file
     * is successfully removed, then it is also removed from the
     * config-space.
     *
     * @param[in] $fileAttach List of files @b not to be removed.
     *
     * @return Undefined.
     */
    public function cleanTemporaries($fileAttach = array())
    {
      $handle = $this->dataBaseConnect();

      $tmpFiles = mySQL::fetchRows("EmailAttachments",
                                   "`User` LIKE '".$this->user."' AND `MessageId` = -1",
                                   null,
                                   $handle, false, true);

      if ($tmpFiles === false) {
        $this->executionStatus = false;
        return;
      }

      $toKeep = array();
      foreach ($fileAttach as $files) {
        $tmp = $files['tmp_name'];
        if (is_file($tmp)) {
          $toKeep[] = $tmp;
        }
      }

      foreach ($tmpFiles as $tmpFile) {
        $fileName = $tmpFile['FileName'];
        if (array_search($fileName, $toKeep) !== false) {
          continue;
        }
        @unlink($fileName);
        if (!@is_file($fileName)) {
          $this->forgetTemporaryFile($fileName);
        }
      }
    }

    /**Detach temporaries from a draft, i.e. after deleting the draft. */
    private function detachTemporaryFiles()
    {
      $handle = $this->dataBaseConnect();

      $query = "UPDATE `EmailAttachments` SET
  `MessageId` = -1, `User` = '".$this->user."'
  WHERE `MessageId` = ".$this->draftId;
      if (mySQL::query($query, $handle, false, true) === false) {
        $this->executionStatus = false;
      }
    }

    /**Remember a temporary file. Files attached to message drafts are
     * remembered across sessions, temporaries not attached to message
     * drafts are cleaned at logout and when closing the email form.
     */
    private function rememberTemporaryFile($tmpFile)
    {
      $handle = $this->dataBaseConnect();

      $tmpFile = mySQL::escape($tmpFile, $handle);
      $query = "INSERT IGNORE INTO `EmailAttachments` (`MessageId`,`User`,`FileName`)
  VALUES (".$this->draftId.",'".$this->user."','".$tmpFile."')
  ON DUPLICATE KEY UPDATE
    `MessageId` = ".$this->draftId.",
    `User` = '".$this->user."'";
      if (mySQL::query($query, $handle, false, true) === false) {
        $this->executionStatus = false;
      }
    }

    /**Forget a temporary file, i.e. purge it from the data-base. */
    private function forgetTemporaryFile($tmpFile)
    {
      $handle = $this->dataBaseConnect();

      $tmpFile = mySQL::escape($tmpFile, $handle);
      $query = "DELETE FROM `EmailAttachments` WHERE `FileName` LIKE '".$tmpFile."'";
      if (mySQL::query($query, $handle, false, true) === false) {
        $this->executionStatus = false;
      }
    }

    /**Handle file uploads. In order for upload to survive we have to
     * move them to an alternate location. And clean up afterwards, of
     * course. We store the generated temporaries in the user
     * config-space in order to (latest) remove them on logout/login.
     *
     * @param[in,out] $fileRecord Typically $_FILES['fileAttach'], but maybe
     * any file record.
     *
     * @param $local If @c true the underlying file will be renamed,
     * otherwise copied.
     *
     * @return Copy of $fileRecord with changed temporary file which
     * survives script-reload, or @c false on error.
     */
    public function saveAttachment($fileRecord, $local = false)
    {
      if ($fileRecord['name'] != '') {
        $tmpdir = ini_get('upload_tmp_dir');
        if ($tmpdir == '') {
          $tmpdir = sys_get_temp_dir();
        }
        $tmpFile = tempnam($tmpdir, Config::APP_NAME);
        if ($tmpFile === false) {
          return false;
        }

        // Remember the file in the data-base for cleaning up later
        $this->rememberTemporaryFile($tmpFile);

        if ($local) {
          // Move the uploaded file
          if (move_uploaded_file($fileRecord['tmp_name'], $tmpFile)) {
            // Sanitize permissions
            chmod($tmpFile, 0600);

            // Remember the uploaded file.
            $fileRecord['tmp_name'] = $tmpFile;

            return $fileRecord;
          }
        } else {
          // Make a copy
          if (copy($fileRecord['tmp_name'], $tmpFile)) {
            // Sanitize permissions
            chmod($tmpFile, 0600);

            // Remember the uploaded file.
            $fileRecord['tmp_name'] = $tmpFile;

            return $fileRecord;
          }
        }

        // Clean up after error
        unlink($tmpFile);
        $this->forgetTemporaryFile($tmpFile);

        return false;
      }
      return false;
    }

    /**** public methods exporting data needed by the web-page template ***/

    /**General form data for hidden input elements.*/
    public function formData()
    {
      return array('FormStatus' => 'submitted',
                   'MessageDraftId' => $this->draftId);
    }

    /**Return the current catch-all email. */
    public function catchAllEmail()
    {
      return htmlspecialchars($this->catchAllName.' <'.$this->catchAllEmail.'>');
    }

    /**Compose one "readable", comma separated list of recipients,
     * meant only for display. The real recipients list is composed
     * somewhere else.
     */
    public function toString()
    {
      $toString = array();
      foreach($this->recipients as $recipient) {
        $name = trim($recipient['name']);
        $email = trim($recipient['email']);
        if ($name == '') {
          $toString[] = $email;
        } else {
          $toString[] = $name.' <'.$email.'>';
        }
      }
      return htmlspecialchars(implode(', ', $toString));
    }

    /***Export an option array suitable to load stored email messages,
     * currently templates and message drafts. */
    public function storedEmails()
    {
      $drafts = $this->fetchDraftsList();
      $templates = $this->fetchTemplateNames();

      return array('drafts' => $drafts,
                   'templates' => $templates);
    }

    /**Export the currently selected template name. */
    public function currentEmailTemplate()
    {
      return $this->templateName;
    }

    /**Export the currently selected draft id. */
    public function messageDraftId()
    {
      return $this->draftId;
    }

    /**Export the subject tag depending on whether we ar in "project-mode" or not. */
    public function subjectTag()
    {
      return $this->messageTag;
    }

    /**Export the From: name. This is modifiable. The From: email
     * address, however, is fixed in order to prevent abuse.
     */
    public function fromName()
    {
      return $this->cgiValue('FromName', $this->catchAllName);
    }

    /**Return the current From: addres. This is fixed and cannot be changed. */
    public function fromAddress()
    {
      return htmlspecialchars($this->catchAllEmail);
    }

    /**In principle the most important stuff: the message text. */
    public function messageText()
    {
      return $this->messageContents;
    }

    /**Export BCC. */
    public function blindCarbonCopy()
    {
      return $this->cgiValue('BCC', '');
    }

    /**Export CC. */
    public function carbonCopy()
    {
      return $this->cgiValue('CC', '');
    }

    /**Export Subject. */
    public function subject()
    {
      return $this->cgiValue('Subject', '');
    }

    /**Return the file attachment data. */
    public function fileAttachments()
    {
      // JSON encoded array
      $fileAttachJSON = $this->cgiValue('FileAttach', '{}');
      $fileAttach = json_decode($fileAttachJSON, true);
      $selectedAttachments = $this->cgiValue('AttachedFiles', array());
      $selectedAttachments = array_flip($selectedAttachments);
      $localFileAttach = array();
      $ocFileAttach = array();
      foreach($fileAttach as $attachment) {
        if ($attachment['status'] == 'new') {
          $attachment['status'] = 'selected';
        } else if (isset($selectedAttachments[$attachment['tmp_name']])) {
          $attachment['status'] = 'selected';
        } else {
          $attachment['status'] = 'inactive';
        }
        $attachment['name'] = basename($attachment['name']);
        if($attachment['origin'] == 'owncloud') {
          $ocFileAttach[] = $attachment;
        } else {
          $localFileAttach[] = $attachment;
        }
      }

      usort($ocFileAttach, function($a, $b) {
          return strcmp($a['name'], $b['name']);
        });
      usort($localFileAttach, function($a, $b) {
          return strcmp($a['name'], $b['name']);
        });

      return array_merge($localFileAttach, $ocFileAttach);
    }

    /**A helper function to generate suitable select options for
     * Navigation::selectOptions()
     */
    static public function fileAttachmentOptions($fileAttach)
    {
      $selectOptions = array();
      foreach($fileAttach as $attachment) {
        $value    = $attachment['tmp_name'];
        $size     = \OC_Helper::humanFileSize($attachment['size']);
        $name     = $attachment['name'].' ('.$size.')';
        $group    = $attachment['origin'] == 'owncloud' ? 'Owncloud' : L::t('Local Filesystem');
        $selected = $attachment['status'] == 'selected';
        $selectOptions[] = array('value' => $value,
                                 'name' => $name,
                                 'group' => $group,
                                 'flags' => $selected ? Navigation::SELECTED : 0);
      }
      return $selectOptions;
    }

    /**Return the file attachment data. This function checks for the
     * cgi-values of EventSelect or the "local" cgi values
     * emailComposer[AttachedEvents]. The "legacy" values take
     * precedence.
     */
    public function eventAttachments()
    {
      $attachedEvents = Util::cgiValue('EventSelect',
                                       $this->cgiValue('AttachedEvents', array()));
      return $attachedEvents;
    }

    /**A helper function to generate suitable select options for
     * Navigation::selectOptions().
     *
     * @param $projectId Id of the active project. If <= 0 an empty
     * array is returned.
     *
     * @param $attachedEvents Flat array of attached events.
     */
    static public function eventAttachmentOptions($projectId, $attachedEvents)
    {
      if ($projectId <= 0) {
        return array();
      }

      // fetch all events for this project
      $events      = Events::events($projectId);
      $dfltIds     = Events::defaultCalendars();
      $eventMatrix = Events::eventMatrix($events, $dfltIds);

      // timezone, locale
      $locale = Util::getLocale();
      $zone = Util::getTimezone();

      // transpose for faster lookup
      $attachedEvents = array_flip($attachedEvents);

      // build the select option control array
      $selectOptions = array();
      foreach($eventMatrix as $eventGroup) {
        $group = $eventGroup['name'];
        foreach($eventGroup['events'] as $event) {
          $object = $event['object'];
          $datestring = Events::briefEventDate($object, $zone, $locale);
          $name = stripslashes($object['summary']).', '.$datestring;
          $value = $event['EventId'];
          $selectOptions[] = array('value' => $value,
                                   'name' => $name,
                                   'group' => $group,
                                   'flags' => isset($attachedEvents[$value]) ? Navigation::SELECTED : 0
            );
        }
      }
      return $selectOptions;
    }

    /**If a complete reload has to be done ... for now */
    public function reloadState()
    {
      return true;
    }

    /**Return the dispatch status. */
    public function errorStatus()
    {
      return $this->executionStatus !== true;
    }

    /**Return possible diagnostics or not. Depending on operation. */
    public function statusDiagnostics()
    {
      return $this->diagnostics;
    }

  };

} // CAFEVDB

?>
