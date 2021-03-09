<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\EmailForm;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Service\ProgressStatusService;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;

/**
 * This is the mass-email composer class. We try to be somewhat
 * careful to have useful error reporting, and avoid sending garbled
 * messages or duplicates.
 *
 * @bug This is a mixture between a controller and service class and
 * needs to be cleaned up.
 */
class Composer
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Traits\SloppyTrait;

  const DEFAULT_TEMPLATE_NAME = 'Default';
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

  private const ATTACHMENT_ORIGIN_CLOUD = 'cloud';
  private const ATTACHMENT_ORIGIN_LOCAL = 'local';

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

  /** @var ConfigService */
  private $configSerivce;

  /** @var RequestParameterService */
  private $parameterService;

  /** @var RecipientsFilter */
  private  $recipientsFilter;

  /** @var EventsService */
  private $eventsService;

  /** @var ProgressStatusService */
  private $progressStatusService;

  /** @var int */
  private $progressToken;

  /*
   * constructor
   */
  public function __construct(
    ConfigService $configService
    , RequestParameterService $parameterService
    , EventsService $eventsService
    , RecipientsFilter $recipientsFilter
    , EntityManager $entityManager
    , ProgressStatusService $progressStatusService
  ) {
    $this->configService = $configService;
    $this->eventsService = $eventsService;
    $this->progressStatusService = $progressStatusService;
    $this->entityManager = $entityManager;
    $this->l = $this->l10N();

    $this->constructionMode = true; // Config::$opts['emailtestmode'] != 'off';
    $this->setCatchAll();

    $this->bind($parameterService, $recipientsFilter);
  }

  /**
   * @param RequestParameterService $parameterservice Control
   *   structure holding the request parameters to bind to.
   *
   * @param RecipientsFilter $recipientsFilter Already bound
   *   recipients filter.  If null self::$recipientFilter will be
   *   bound to $parameterservice.
   */
  public function bind(
    RequestParameterService $parameterService
    , RecipientsFilter $recipientsFilter = null
  ) {
    $this->parameterService = $parameterService;

    if (empty($recipientsFilter)) {
      $this->recipientsFilter->bind($parameterService);
    } else {
      $this->recipientsFilter = $recipientsFilter;
    }

    $this->recipients = $this->recipientsFilter->selectedRecipients();

    $template = $this->parameterService['emailTemplate'];

    $this->cgiData = $this->parameterService->getParam(self::POST_TAG, []);

    if (!empty($template)) {
      $this->cgiData['storedMessagesSelector'] = $template;
    }

    $this->projectId   = $this->cgiValue(
      'projectId', $this->parameterService->getParam('projectId', -1));
    $this->projectName = $this->cgiValue(
      'projectName', $this->parameterService->getParam('projectName', ''));
    $this->debitNoteId = $this->cgiValue(
      'debitNoteId', $this->parameterService->getParam('debitNoteId', -1));

    $this->setSubjectTag();

    // First initialize defaults, will be overriden based on
    // form-submit data in $this->execute()
    $this->setDefaultTemplate();

    // cache the list of per-recipient variables
    $this->memberVariables = $this->emailMemberVariables();

    $this->draftId = $this->cgiValue('messageDraftId', -1);

    // Set to false on error
    $this->executionStatus = true;

    // Error diagnostics, can be retrieved by
    // $this->statusDiagnostics()
    $this->diagnostics = [
      'caption' => '',
      'AddressValidation' => [
        'CC' => [],
        'BCC' => [],
        'Empty' => false,
      ],
      'TemplateValidation' => [],
      'SubjectValidation' => true,
      'FromValidation' => true,
      'AttachmentValidation' => [
        'Files' => [],
        'Events' => [],
      ],
      'MailerExceptions' => [],
      'MailerErrors' => [],
      'Duplicates' => [],
      'CopyToSent' => [], // IMAP stuff
      'Message' => [
        'Text' => '',
        'Files' => [],
        'Events' => [],
      ],
      // start of sent-messages for log window
      'TotalPayload' => 0,
      'TotalCount' => 0,
      'FailedCount' => 0
    ];

    // Maybe should also check something else. If submitted is true,
    // then we use the form data, otherwise the defaults.
    $this->submitted = $this->cgiValue('formStatus', '') == 'submitted';

    if (!$this->submitted) {
      // Leave everything at default state, except for an optional
      // initial template and subject
      $initialTemplate = $this->cgiValue('storedMessagesSelector');
      if (!empty($initialTemplate)) {
        $template = $this->fetchTemplate($initialTemplate);
        if (empty($template)) {
          $template = $this->fetchTemplate($this->l->t('ExampleFormletter'));
        }
        if (!empty($template)) {
          $this->messageContents = $template->getContents();
          $this->templateName = $initialTemplate;
        } else {
          $this->cgiData['subject'] = $this->l->t('Unknown Template');
        }
      }
      $this->logInfo('MESSAGE '.$this->messageContents);
    } else {
      $this->messageContents = $this->cgiValue('messageText', $this->initialTemplate);
    }
  }

  /**
   * The email composer never goes without its recipients filter.
   */
  public function getRecipientsFilter()
  {
    return $this->recipientsFilter;
  }

  /** Fetch a CGI-variable out of the form-select name-space */
  private function cgiValue($key, $default = null)
  {
    if (isset($this->cgiData[$key])) {
      $value = $this->cgiData[$key];
      if (is_string($value)) {
        $value = Util::normalizeSpaces($value);
      }
      return $value;
    } else {
      return $default;
    }
  }

  /**
   * Return true if this email needs per-member substitutions. Up to
   * now validation is not handled here, but elsewhere. Still this
   * is not a static method (future ...)
   */
  private function isMemberTemplateEmail($message)
  {
    return preg_match('!([^$]|^)[$]{MEMBER::[^{]+}!', $message);
  }

  /**
   * Substitute any per-recipient variables into $templateMessage
   *
   * @param $templateMessage The email message without substitutions.
   *
   * @param $dbdata The data from the musician data-base which
   * contains the substitutions.
   *
   * @return string HTML message with variable substitutions.
   *
   * @todo This needs to be reworked TOTALLY
   */
  private function replaceMemberVariables($templateMessage, $dbdata)
  {
    return $templateMessage;
    // if ($dbdata['Geburtstag'] && $dbdata['Geburtstag'] != '') {
    //   $dbdata['Geburtstag'] = date('d.m.Y', strtotime($dbdata['Geburtstag']));
    // }
    // $strMessage = $templateMessage;
    // foreach ($this->memberVariables as $placeholder => $column) {
    //   if ($placeholder === 'EXTRAS') {
    //     // EXTRAS is a multi-field stuff. In order to allow stuff
    //     // like forming tables we allow lead-in, lead-out and separator.
    //     if ($this->projectId <= 0) {
    //       $extras = [ [ 'label' => $this->l->t("nothing"), 'surcharge' => 0 ] ];
    //     } else {
    //       $extras = $dbdata[$column];
    //     }
    //     $strMessage = preg_replace_callback(
    //       '/([^$]|^)[$]{MEMBER::EXTRAS(:([^!}]+)!([^!}]+)!([^}]+))?}/',
    //       function($matches) use ($extras) {
    //         if (count($matches) === 6) {
    //           $pre  = $matches[3];
    //           $sep  = $matches[4];
    //           $post = $matches[5];
    //         } else {
    //           $pre  = '';
    //           $sep  = ': ';
    //           $post = "\n";
    //         }
    //         $result = $matches[1];
    //         foreach($extras as $records) {
    //           $result .=
    //                   $pre.
    //                   $records['label'].
    //                   $sep.
    //                   $records['surcharge'].
    //                   $post;
    //         }
    //         return $result;
    //       },
    //       $strMessage);
    //     continue;
    //   } else if ($placeholder === 'SEPAMANDATSIBAN' ||
    //              $placeholder === 'SEPAMANDATSBIC' ||
    //              $placeholder === 'SEPAMANDATSINHABER') {
    //     $dbdata[$column] = Config::decrypt($dbdata[$column], $enckey);
    //     if ($placeholder === 'SEPAMANDATSIBAN') {
    //       $dbdata[$column] = substr($dbdata[$column], 0, 6).'...'.substr($dbdata[$column], -4, 4);
    //     }
    //   }
    //   // replace all remaining stuff
    //   $strMessage = preg_replace('/([^$]|^)[$]{MEMBER::'.$placeholder.'}/',
    //                              '${1}'.htmlspecialchars($dbdata[$column]),
    //                              $strMessage);
    // }
    // $strMessage = preg_replace('/[$]{2}{/', '${', $strMessage);
    return $strMessage;
  }

  /**
   * Substitute any global variables into $this->messageContents.
   *
   * @return string HTML message with variable substitutions.
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
      setlocale(LC_TIME, $this->getLocale());
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

  /**
   * Send out the messages with self::doSendMessages(), after checking
   * them with self::preComposeValidation(). If successful a possibly
   * pending "draft" message is deleted.
   *
   * @return bool Success (true) or failure (false).
   */
  public function sendMessages()
  {
    if (!$this->preComposeValidation()) {
      return;
    }

    // Checks passed, let's see what happens. The mailer may throw
    // any kind of "nasty" exceptions.
    $this->doSendMessages();
    if (!$this->errorStatus()) {
      // Hurray!!!
      $this->diagnostics['caption'] = $this->l->t('Message(s) sent out successfully!');

      // If sending out a draft, remove the draft.
      $this->deleteDraft();
    }
    return $this->executionStatus;
  }

  /**
   * Finally, send the beast out to all recipients, either in
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
  private function doSendMessages()
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
        $msg = $this->composeAndSend($strMessage, [ $recipient ], false);
        if (!empty($msg['message'])) {
          $this->copyToSentFolder($msg['message']);
          // Don't remember the individual emails, but for
          // debit-mandates record the message id, ignore errors.
          if ($this->debitNoteId > 0 && $dbdata['PaymentId'] > 0) {
            $messageId = $msg['messageId'];
            $where =  '`Id` = '.$dbdata['PaymentId'].' AND `DebitNoteId` = '.$this->debitNoteId;
            mySQL::update('ProjectPayments', $where, [ 'DebitMessageId' => $messageId ], $this->dbh);
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
      $mimeMsg = $this->composeAndSend($message, [], true);
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

  /**
   * Extract the first few line of a text-buffer.
   *
   * @param $text The text to compute the "head" of.
   *
   * @param $lines The number of lines to return at most.
   *
   * @param $separators Regexp for preg_split. The default is just
   * "/\\n/". Note that this is enough for \\n and \\r\\n as the text is
   * afterwars imploded again with \n separator.
   */
  private function head($text, $lines = 64, $separators = "/\n/")
  {
    $text = preg_split($separators, $text, $lines+1);
    if (isset($text[$lines])) {
      unset($text[$lines]);
    }
    return implode("\n", $text);
  }

  /**
   * Compose and send one message. If $EMails only contains one
   * address, then the emails goes out using To: and Cc: fields,
   * otherwise Bcc: is used, unless sending to the recipients of a
   * project. All emails are logged with an MD5-sum to the DB in order
   * to prevent duplicate mass-emails. If a duplicate is detected the
   * message is not sent out. A duplicate is something with the same
   * message text and the same recipient list.
   *
   * @param $strMessage The message to send.
   *
   * @param $EMails The recipient list
   *
   * @param $addCC If @c false, then additional CC and BCC recipients will
   *                   not be added.
   *
   * @param $allowDuplicates Whether n ot to check for
   * duplicates. This is currently only set to true when
   * sending a copy of a form-email with per-recipient substitutions
   * to the orchestra account.
   *
   * @return string The sent Mime-message which then may be stored in the
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
      $progressStatus = $this->progressStatusService->get($this->progressToken);
      $diagnostics = $this->diagnostics;
      $phpMailer->ProgressCallback = function($currentLine, $totalLines) use ($progressStatus, $diagnostics) {
        $data = [
          'current' => $currentLine,
          'target' => $totalLines,
        ];
        if ($currentLine == 0) {
          $data['data'] =[
            'proto' => 'smtp',
            'total' =>  $diagnostics['TotalPayload'],
            'active' => $diagnostics['TotalCount'],
          ];
        }
        $progressStatus->merge($data);
      };

      $phpMailer->Host = $this->getConfigValue('smtpserver');
      $phpMailer->Port = $this->getConfigValue('smtpport');
      switch ($this->getConfigValue('smtpsecure')) {
      case 'insecure': $phpMailer->SMTPSecure = ''; break;
      case 'starttls': $phpMailer->SMTPSecure = 'tls'; break;
      case 'ssl':      $phpMailer->SMTPSecure = 'ssl'; break;
      default:         $phpMailer->SMTPSecure = ''; break;
      }
      $phpMailer->SMTPAuth = true;
      $phpMailer->Username = $this->getConfigValue('emailuser');
      $phpMailer->Password = $this->getConfigValue('emailpassword');

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
        $calendar = $this->eventsService->exportEvents($events, $this->projectName);

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

    return [
      'messageId' => $phpMailer->getLastMessageID(),
      'message' => $phpMailer->GetSentMIMEMessage(),
    ];
  }

  /**
   * Record diagnostic output from the actual message composition for
   * the status page.
   */
  private function recordMessageDiagnostics($mimeMsg)
  {
    // Positive diagnostics
    $this->diagnostics['Message']['Text'] = self::head($mimeMsg, 40);

    $this->diagnostics['Message']['Files'] = [];
    $attachments = $this->fileAttachments();
    foreach ($attachments as $attachment) {
      if ($attachment['status'] != 'selected') {
        continue;
      }
      $size     = \OCP\Util::humanFileSize($attachment['size']);
      $name     = basename($attachment['name']).' ('.$size.')';
      $this->diagnostics['Message']['Files'][] = $name;
    }

    $this->diagnostics['Message']['Events'] = [];
    $events = $this->eventAttachments();
    $locale = $this->getLocale();
    $timezone = $this->getTimezone();
    foreach($events as $eventUri) {
      $event = $this->eventsService->fetchEvent($this->projectId, $eventUri);
      $datestring = $this->eventsService->briefEventDate($event, $timezone, $locale);
      $name = stripslashes($event['summary']).', '.$datestring;
      $this->diagnostics['Message']['Events'][] = $name;
    }
  }

  /**
   * Take the supplied message and copy it to the "Sent" folder.
   */
  private function copyToSentFolder($mimeMessage)
  {
    // PEAR IMAP works without the c-client library
    ini_set('error_reporting', ini_get('error_reporting') & ~E_STRICT);

    $imaphost   = $this->getConfigValue('imapserver');
    $imapport   = $this->getConfigValue('imapport');
    $imapsecure = $this->getConfigValue('imapsecure');

    $progressStatus = $this->progressStatusService->get($this->progressToken);
    $diagnostics = $this->diagnostics;
    $imap = new \Net_IMAP($imaphost,
                          $imapport,
                          $imapsecure == 'starttls' ? true : false, 'UTF-8',
                          function($pos, $total) use ($progressStatus, $diagnostics) {
                            if ($total < 128) {
                              return; // ignore non-data transfers
                            }
                            $data = [
                              'current' => $pos,
                              'target' => $total,
                            ];
                            if ($pos == 0) {
                              $data['data'] = [
                                'proto' => 'imap',
                                'total' =>  $diagnostics['TotalPayload'],
                                'active' => $diagnostics['TotalCount'],
                              ];
                            }
                            $progressStatus->merge($data);
                          },
                          64*1024); // 64 kb chunk-size

    $user = $this->getConfigValue('emailuser');
    $pass = $this->getConfigValue('emailpassword');
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
      $this->diagnostics['CopyToSent']['copy'] = [
        'Sent' => $ret1->toString(),
        'INBOX.Sent' => $ret2->toString(),
      ];
      $imap->disconnect();
      return false;
    }
    $imap->disconnect();

    return true;
  }

  /**
   * Log the sent message to the data base if it is new. Return false
   * if this is a duplicate, return the data-base query string to be
   * executed after successful sending of the message in cas of
   * success.
   *
   * @param $logMessage The email-message to record in the DB.
   *
   * @param $allowDuplicates Whether n ot to check for
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
    $attachLog = [];
    foreach ($logMessage->fileAttach as $attachment) {
      if ($attachment['status'] != 'selected') {
        continue;
      }
      if ($attachment['name'] != "") {
        $md5val = md5_file($attachment['tmp_name']);
        $attachLog[] = [
          'name' => $attachment['name'],
          'md5' => $md5val,
        ];
      }
    }
    if (!empty($logMessage->events)) {
      sort($logMessage->events);
      $name = 'Events-'.implode('-', $logMessage->events);
      $md5 = md5($name);
      $attachLog[] = [ 'name' => $name, 'md5' => $md5, ];
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
        $shortRecipients = [];
        foreach($logMessage->recipients as $recipient) {
          $shortRecipients[] = $recipient['name'].' <'.$recipient['email'].'>';
        }
        $this->diagnostics['Duplicates'][] = [
          'dates' => $loggedDates,
          'recipients' => $shortRecipients,
          'text' => $logMessage->message,
          'textMD5' => $textMD5,
          'bulkMD5' => $bulkMD5,
          'bulkRecipients' => $bulkRecipients
        ];
        return false;
      }
    }

    return $logQuery;
  }

  /**
   * Compose and export one message to HTML.
   *
   * @param $strMessage The message to send.
   *
   * @param $EMails The recipient list
   *
   * @param $addCC If @c false, then additional CC and BCC recipients will
   *               not be added.
   *
   * @return bool true or false.
   */
  private function composeAndExport($strMessage, $eMails, $addCC = true)
  {
    // If we are sending to a single address (i.e. if $strMessage has
    // been constructed with per-member variable substitution), then
    // we do not need to send via BCC.
    $singleAddress = count($eMails) == 1;

    // Construct an array for the data-base log
    $logMessage = new \stdClass;
    $logMessage->recipients = $eMails;
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
      foreach ($eMails as $recipient) {
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
        $calendar = $this->eventsService->exportEvents($events, $this->projectName);

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

      return null;
    }

    // Finally the point of no return. Send it out!!!
    try {
      if (!$phpMailer->preSend()) {
        // in principle this cannot happen as the mailer DOES use
        // exceptions ...
        $this->executionStatus = false;
        $this->diagnostics['mailerErrors'][] = $phpMailer->ErrorInfo;
        return null;
      } else {
        // success, would log success if we really were sending
      }
    } catch (\Exception $exception) {
      $this->executionStatus = false;
      $this->diagnostics['mailerExceptions'][] =
        $exception->getFile()
       .'('.$exception->getLine()
       .'): '.
        $exception->getMessage();

      return null;
    }

    return [
      'headers' => $phpMailer->getMailHeaders(),
      'body' => $strMessage,
      // @todo perhaps also supply attachments as download links for easy checking.
    ];
  }

  /**
   * Generate a HTML message preview.
   *
   * @return array
   * ```
   * [
   *   [
   *     'headers' => HEADER_STRING,
   *     'body' => BODY_STRING,
   *   ],
   *   ...
   * ]
   * ```
   */
  public function previewMessages()
  {
    if (!$this->preComposeValidation()) {
      return null;
    }

    // Preliminary checks passed, let's see what happens. The mailer may throw
    // any kind of "nasty" exceptions.
    $preview = $this->exportMessages();

    if (!empty($preview)) {
      $this->diagnostics['caption'] = $this->l->t('Message(s) exported successfully!');
    }

    return $preview;
  }

  /**
   * Generate a HTML-export with all variables substituted. This is
   * primarily meant in order to debug actual variable substitutions,
   * or to have hardcopies from debit note notifications and other
   * important emails.
   */
  private function exportMessages()
  {
    // The following cannot fail, in principle. $message is then
    // the current template without any left-over globals.
    $messageTemplate = $this->replaceGlobals();

    if ($this->isMemberTemplateEmail($messageTemplate)) {

      $this->diagnostics['totalPayload'] = count($this->recipients)+1;

      foreach ($this->recipients as $recipient) {
        $strMessage = $this->replaceMemberVariables($messageTemplate, $recipient['dbdata']);
        ++$this->diagnostics['totalCount'];
        $message = $this->composeAndExport($strMessage, [ $recipient ], false);
        if (empty($message)) {
          ++$this->diagnostics['failedCount'];
          return;
        }
        yield $message;
      }

      // Finally send one message without template substitution (as
      // this makes no sense) to all Cc:, Bcc: recipients and the
      // catch-all. This Message also gets copied to the Sent-folder
      // on the imap server.
      ++$this->diagnostics['totalCount'];
      $message = $this->composeAndExport($messageTemplate, [], true);
      if (empty($message)) {
        ++$this->diagnostics['failedCount'];
        return;
      }
      yield $message;
    } else {
      $this->diagnostics['totalPayload'] = 1;
      ++$this->diagnostics['totalCount']; // this is ONE then ...
      $message = $this->composeAndExport($messageTemplate, $this->recipients);
      if (empty($message)) {
        ++$this->diagnostics['failedCount'];
        return;
      }
      yield $message;
    }
  }

  /**
   * Pre-message construction validation. Collect all data and perform
   * some checks on it.
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
    foreach ([ 'CC' => $this->carbonCopy(),
               'BCC' => $this->blindCarbonCopy(), ] as $key => $emails) {
      $this->onLookers[$key] = $this->validateFreeFormAddresses($key, $emails);
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
    foreach ($events as $eventUri) {
      if (!$this->eventsService->fetchEvent($this->projectId, $eventUri)) {
        $this->executionStatus = false;
        $this->diagnostics['AttachmentValidation']['Events'][] = $eventId;
      }
    }

    if (!$this->executionStatus) {
      $this->diagnostics['caption'] = $this->l->t('Pre-composition validation has failed!');
    }
    return $this->executionStatus;
  }

  /**
   * Compute the subject tag, depending on whether we are in project
   * mode or not.
   */
  private function setSubjectTag()
  {
    if ($this->projectId < 0 || $this->projectName == '') {
      $this->messageTag = '[CAF-'.ucfirst($this->l->t('musicians')).']';
    } else {
      $this->messageTag = '[CAF-'.$this->projectName.']';
    }
  }

  /**
   * Validate a comma separated list of email address from the Cc:
   * or Bcc: input.
   *
   * @param $header For error diagnostics, either CC or BCC.
   *
   * @param $freeForm the value from the input field.
   *
   * @return bool false in case of error, otherwise a borken down list of
   * recipients [ [ 'name' => '"Doe, John"', 'email' => 'john@doe.org', ], ... ]
   */
  public function validateFreeFormAddresses($header, $freeForm)
  {
    $phpMailer = new \PHPMailer(true);
    $parser = new \Mail_RFC822(null, null, null, false);

    $brokenRecipients = [];
    $parsedRecipients = $parser->parseAddressList($freeForm);
    $parseError = $parser->parseError();
    if ($parseError !== false) {
      $this->logDebug("Parse-error on email address list: ".
                      vsprintf($parseError['message'], $parseError['data']));
      // We report the entire string.
      $brokenRecipients[] = $this->l->t($parseError['message'], $parseError['data']);
    } else {
      $this->logDebug("Parsed address list: ". print_r($parsedRecipients, true));
      $recipients = [];
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
      $this->logDebug("Returned address list: ".print_r($recipients, true));
      return $recipients;
    }
  }

  /**
   * Validates the given template, i.e. searches for unknown
   * substitutions. This function is invoked right before sending
   * stuff out and before storing drafts. In order to do so we
   * substitute each known variable by a dummy value and then make
   * sure that no variable tag ${...} remains.
   */
  public function validateTemplate($template = null)
  {
    if (empty($template)) {
      $template = $this->messageText();
    }

    $templateError = [];

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
    $globalTemplateLeftOver = [];
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

    $spuriousTemplateLeftOver = [];
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

  public function setDefaultTemplate()
  {
    // Make sure that at least the default template exists and install
    // that as default text
    $this->initialTemplate = self::DEFAULT_TEMPLATE;

    $dbTemplate = $this->fetchTemplate(self::DEFAULT_TEMPLATE_NAME);
    if (empty($dbTemplate)) {
      $this->storeTemplate(self::DEFAULT_TEMPLATE_NAME, '', $this->initialTemplate);
    } else {
      $this->initialTemplate = $dbTemplate->getContents();
    }
    $this->messageContents = $this->initialTemplate;
    $this->templateName = self::DEFAULT_TEMPLATE_NAME;
  }

  private function setCatchAll()
  {
    if ($this->constructionMode) {
      $this->catchAllEmail = $this->getConfigValue('emailtestaddress');
      $this->catchAllName  = 'Bilbo Baggins';
    } else {
      $this->catchAllEmail = $this->getConfigValue('emailfromaddress');
      $this->catchAllName  = $this->getConfigValue('emailfromname');
    }
  }

  /**
   * Return an associative array with keys and column names for the
   * values (Name, Stadt etc.) for substituting per-member data.
   */
  private function emailMemberVariables()
  {
    $vars   = preg_split('/\s+/', trim(self::MEMBERVARIABLES));
    $values = preg_split('/\s+/', trim(self::MEMBERCOLUMNS));
    return array_combine($vars, $values);
  }

  /**
   * Compose an associative array with keys and values for global
   * variables which do not depend on the specific recipient.
   */
  private function emailGlobalVariables()
  {
    static $globalVars = false;
    if ($globalVars === false) {
      $globalVars = array(
        'ORGANIZER' => $this->fetchExecutiveBoard(),
        'CREDITORIDENTIFIER' => $this->getConfigValue('bankAccountCreditorIdentifier'),
        'ADDRESS' => $this->streetAddress(),
        'BANKACCOUNT' => $this->bankAccount(),
        'PROJECT' => $this->projectName != '' ? $this->projectName : $this->l->t('no project involved'),
        'DEBITNOTEDUEDATE' => '',
        'DEBITNOTEDUEDAYS' => '',
        'DEBITNOTESUBMITDATE' => '',
        'DEBITNOTESUBMITDAYS' => '',
        'DEBITNOTEJOB' => '',
      );

      if ($this->debitNoteId > 0) {
        $debitNote = DebitNotes::debitNote($this->debitNoteId, $this->dbh);

        $globalVars['DEBITNOTEJOB'] = $this->l->t($debitNote['Job']);

        $oldLocale = setlocale(LC_TIME, '0');
        setlocale(LC_TIME, $this->getLocale());

        $oldTZ = date_default_timezone_get();
        $tz = $this->getTimezone();
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
      $this->getConfigValue('streetAddressName01')."<br/>\n".
      $this->getConfigValue('streetAddressName02')."<br/>\n".
      $this->getConfigValue('streetAddressStreet')."&nbsp;".
      $this->getConfigValue('streetAddressHouseNumber')."<br/>\n".
      $this->getConfigValue('streetAddressZIP')."&nbsp;".
      $this->getConfigValue('streetAddressCity');
  }

  private function bankAccount()
  {
    $iban = new \PHP_IBAN\IBAN($this->getConfigValue('bankAccountIBAN'));
    return
      $this->getConfigValue('bankAccountOwner')."<br/>\n".
      "IBAN ".$iban->HumanFormat()." (".$iban->MachineFormat().")<br/>\n".
      "BIC ".$this->getConfigValue('bankAccountBIC');
  }

  /**
   * Fetch the pre-names of the members of the organizing committee in
   * order to construct an up-to-date greeting.
   */
  private function fetchExecutiveBoard()
  {
    $executiveBoardId = $this->getConfigValue('executiveBoardProjectId');

    $executiveBoardNames = $this
      ->getDatabaseRepository(Entities\ProjectParticipant::class)
      ->fetchParticipantNames($executiveBoardId, [ 'nickName' => 'ASC' ]);

    $executiveBoardNickNames = [];
    foreach ($executiveBoardNames as $names) {
      $executiveBoardNickNames[] = $names['nickName'];
    }

    return $this->implodeSloppy($executiveBoardNickNames);
  }

  /**
   * Take the text supplied by $contents and store it in the DB
   * EmailTemplates table with tag $templateName. An existing template
   * with the same tag will be replaced.
   */
  public function storeTemplate($templateName, $subject = null, $contents = null)
  {
    if (empty($subject)) {
      $subject = $this->subject();
    }
    if (empty($contents)) {
      $contents = $this->messageText();
    }

    $template = $this
      ->getDatabaseRepository(Entities\EmailTemplate::class)
      ->findOneBy([ 'tag' => $templateName ]);
    if (empty($template)) {
      $template = Entities\EmailTemplate::create();
    }
    $template->setTag($templateName)
      ->setSubject($subject)
      ->setContents($contents);
    $this->merge($template);
    $this->flush();
  }

  /** Delete the named email template. */
  public function deleteTemplate($templateName)
  {
    $template = $this
      ->getDatabaseRepository(Entities\EmailTemplate::class)
      ->findOneBy([ 'tag' => $templateName ]);
    if (!empty($template)) {
      $this->remove($template, true);
    }
  }

  public function loadTemplate($templateIdentifier)
  {
    $template = $this->fetchTemplate($templateIdentifier);
    if (empty($template)) {
      return $this->executionStatus = false;
    }
    $this->templateName = $template->getTag();
    $this->messageContents = $template->getContents();
    $this->draftId = -1; // avoid accidental overwriting
    return $this->executionStatus = true;
  }

  /**
   * Fetch a specific template from the DB. Return null if that
   * template is not found
   *
   * @param int|string|Entities\EmailTemplate $templateIdentifier
   *
   * @return null|Entities\EmailTemplate
   */
  private function fetchTemplate($templateIdentifier):?Entities\EmailTemplate
  {
    if (!($templateIdentifier instanceof Entities\EmailTemplate)) {
      if (filter_var($templateIdentifier, FILTER_VALIDATE_INT) !== false) {
        $template = $this
          ->getDatabaseRepository(Entities\EmailTemplate::class)
          ->find($templateIdentifier);
      } else {
        /** @var Entities\EmailTemplate */
        $template = $this
          ->getDatabaseRepository(Entities\EmailTemplate::class)
          ->findOneBy([ 'tag' => $templateName ]);
      }
    }

    if (empty($template)) {
      return null;
    }

    $templateName = $template->getTag();

    if ($templateName !== self::DEFAULT_TEMPLATE_NAME && !empty($template['subject'])) {
      $this->cgiData['subject'] = $template['subject'];
    }

    return $template;
  }

  /** Return a flat array with all known template names. */
  private function fetchTemplatesList()
  {
    return $this->getDatabaseRepository(Entities\EmailTemplate::class)->list();
  }

  /**
   * Return an associative matrix with all currently stored draft
   * messages. In order to load the draft we only need the id. The
   * list of drafts is used to generate a select menu where some fancy
   * title is displayed and the option value is the unique draft id.
   */
  private function fetchDraftsList()
  {
    return $this->getDatabaseRepository(Entities\EmailDraft::class)->list();
  }

  /**
   * Store a draft message. The only constraint on the "operator
   * behaviour" is that the subject must not be empty. Otherwise in
   * any way incomplete messages may be stored as drafts.
   */
  public function storeDraft()
  {
    if ($this->subject() == '') {
      $this->diagnostics['SubjectValidation'] = $this->messageTag;
      return $this->executionStatus = false;
    } else {
      $this->diagnostics['SubjectValidation'] = true;
    }

    $draftData = [
      'projectId' => $this->parameterService['projectId'],
      'projectName' => $this->parameterService['projectName'],
      'pebitNoteId' => $this->parameterService['debitNoteId'],
      self::POST_TAG => $this->parameterService[self::POST_TAG],
      RecipientsFilter::POST_TAG => $this->parameterService[RecipientsFilter::POST_TAG],
    ];

    unset($draftData[self::POST_TAG]['request']);
    unset($draftData[self::POST_TAG]['submitAll']);
    unset($draftData[self::POST_TAG]['saveMessage']);

    // $dataJSON = json_encode($draftData);
    $subject = $this->subjectTag() . ' ' . $this->subject();

    if ($this->draftId > 0) {
      $draft = $this->getDatabaseRepository(Entities\EmailDraft::class)
        ->find($this->draftId)
        ->setSubject($subject)
        ->setData($draftData);
    } else {
      $draft = Entities\EmailDraft::create()
        ->setSubject($subject)
        ->setData($draftData);
      $this->persist($draft);
    }
    $this->flush();
    $this->draftId = $draft->getId();

    // Update the list of attachments, if any
    foreach ($this->fileAttachments() as $attachment) {
      self::rememberTemporaryFile($attachment['tmp_name']);
    }

    return $this->executionStatus;
  }

  /** Preliminary draft read-back. */
  public function loadDraft(?int $draftId = null)
  {
    if ($draftId === null) {
      $draftId = $this->draftId;
    }
    if ($draftId <= 0) {
      $this->diagnostics['caption'] = $this->l->t('Unable to load draft without id');
      return $this->executionStatus = false;
    }

    $draft = $this->getDatabaseRepository(Entities\EmailDraft::class)
      ->find($draftId);
    if (empty($draft)) {
      $this->diagnostics['caption'] = $this->l->t('Draft %s could not be loaded', $draftId);
      return $this->executionStatus = false;
    }

    $draftData = $draft->getData();

    // undo request actions
    unset($draftData[self::POST_TAG]['request']);
    unset($draftData[self::POST_TAG]['submitAll']);
    unset($draftData[self::POST_TAG]['saveMessage']);

    if (empty($draftData['debitNoteId'])) {
      $draftData['debitNoteId'] = -1;
    }

    $this->draftId = $draftId;

    $this->executionStatus = true;

    return $draftData;
  }

  /** Delete the current message draft. */
  public function deleteDraft()
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

  // temporary file utilities

  /**
   * Delete all temorary files not found in $fileAttach. If the file
   * is successfully removed, then it is also removed from the
   * config-space.
   *
   * @param $fileAttach List of files @b not to be removed.
   *
   * @return bool $this->executionStatus
   */
  public function cleanTemporaries($fileAttach = [])
  {
    try {
      $tmpFiles = $this
        ->getDatabaseRepository(Entities\EmailAttachment::class)
        ->findBy([ 'user' => $this->userId(), 'draft' => null ]);
    } catch (\Throwable $t) {
      $this->diagnostics['caption'] = $this->l->t(
        'Cleaning temporary files failed: %s', $t->getMessage());
      return $this->executionStatus = false;
    }

    $toKeep = [];
    foreach ($fileAttach as $files) {
      $tmp = $files['tmp_name'];
      if (is_file($tmp)) {
        $toKeep[] = $tmp;
      }
    }

    foreach ($tmpFiles as $tmpFile) {
      $fileName = $tmpFile['fileName'];
      if (array_search($fileName, $toKeep) !== false) {
        continue;
      }
      @unlink($fileName);
      if (!@is_file($fileName)) {
        $this->forgetTemporaryFile($fileName);
      }
    }
    $this->diagnostics['caption'] = $this->l->t('Cleaning temporary files succeeded.');
    return $this->executionStatus = true;
  }

  /** Detach temporaries from a draft, i.e. after deleting the draft. */
  private function detachTemporaryFiles()
  {
    try {
      $this->queryBuilder()
           ->update(Entities\EmailAttachment::class, 'ea')
           ->set('ea.draft', null)
           ->set('ea.user', ':user')
           ->where($this->expr->eq('ea.draft', ':id'))
           ->setParameter('user', $this->userId())
           ->setParameter('id', $this->draftId)
           ->getQuery()
           ->execute();
      $this->flush();
    } catch (\Throwable $t) {
      return $this->executionStatus = false;
    }
    return $this->executionStatus = true;
  }

  /**
   * Remember a temporary file. Files attached to message drafts are
   * remembered across sessions, temporaries not attached to message
   * drafts are cleaned at logout and when closing the email form.
   */
  private function rememberTemporaryFile($tmpFile)
  {
    try {
      $attachment = $this
        ->getDatabaseRepository(Entities\EmailAttachment::class)
        ->findOneBy([
          'fileName' => $tmpFile,
          'user' => $this->userId(),
        ]);
      if (empty($attachment)) {
        $attachment = (new Entities\EmailAttachment())
          ->setFileName($tmpFile)
          ->setUser($this->userId());
      }
      if ($this->draftId > 0) {
        $attachment->setDraft($this->getReference(Entities\EmailDraft, $this->draftId));
      }
      $this->merge($attachment);
    } catch (\Throwable $t) {
      $this->logException($t);
      return $this->executionStatus = false;
    }
    return $this->executionStatus = true;
  }

  /** Forget a temporary file, i.e. purge it from the data-base. */
  private function forgetTemporaryFile($tmpFile)
  {
    try {
      if (is_string($tmpFile)) {
        $tmpFile = $this
          ->getDatabaseRepository(Entities\EmailAttachment::class)
          ->findOneBy([ 'fileName' => $tmpFile ]);
      }
      $this->remove($tmpFile, true);
    } catch (\Throwable $t) {
      $this->diagnostics['caption'] = $this->l->t(
        'Cleaning temporary files failed: %s', $t->getMessage());
      return $this->executionStatus = false;
    }
    return $this->executionStatus = true;
  }

  /**
   * Handle file uploads. In order for upload to survive we have to
   * move them to an alternate location. And clean up afterwards, of
   * course. We store the generated temporaries in the user
   * config-space in order to (latest) remove them on logout/login.
   *
   * @param $fileRecord Typically $_FILES['fileAttach'], but maybe
   * any file record.
   *
   * @param $local If @c true the underlying file will be renamed,
   * otherwise copied.
   *
   * @return array Copy of $fileRecord with changed temporary file which
   * survives script-reload, or @c false on error.
   *
   * @todo Use IAppData and use temporaries in the cloud storage.
   */
  public function saveAttachment(&$fileRecord, $local = false)
  {
    if (!empty($fileRecord['name'])) {
      $tmpdir = ini_get('upload_tmp_dir');
      if ($tmpdir == '') {
        $tmpdir = sys_get_temp_dir();
      }
      $tmpFile = tempnam($tmpdir, $this->appName());
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
        $this->logInfo("TRY COPY ".$fileRecord['tmp_name']." -> ".$tmpFile);
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

  // public methods exporting data needed by the web-page template

  /** General form data for hidden input elements.*/
  public function formData()
  {
    return array('formStatus' => 'submitted',
                 'messageDraftId' => $this->draftId);
  }

  /** Return the current catch-all email. */
  public function catchAllEmail()
  {
    return htmlspecialchars($this->catchAllName.' <'.$this->catchAllEmail.'>');
  }

  /**
   * Compose one "readable", comma separated list of recipients,
   * meant only for display. The real recipients list is composed
   * somewhere else.
   */
  public function toString()
  {
    $toString = [];
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

  /**
   * Export an option array suitable to load stored email messages,
   * currently templates and message drafts.
   */
  public function storedEmails()
  {
    $drafts = $this->fetchDraftsList();
    $templates = $this->fetchTemplatesList();

    //$this->logInfo('EMAILS '.print_r($drafts, true).' / '.print_r($templates, true));

    return [
      'drafts' => $drafts,
      'templates' => $templates,
    ];
  }

  /**Export the currently selected template name. */
  public function currentEmailTemplate()
  {
    return $this->templateName;
  }

  /** Export the currently selected draft id. */
  public function messageDraftId()
  {
    return $this->draftId;
  }

  /** Export the subject tag depending on whether we ar in "project-mode" or not. */
  public function subjectTag()
  {
    return $this->messageTag;
  }

  /** Export the From: name. This is modifiable. The From: email
   * address, however, is fixed in order to prevent abuse.
   */
  public function fromName()
  {
    return $this->cgiValue('fromName', $this->catchAllName);
  }

  /** Return the current From: addres. This is fixed and cannot be changed. */
  public function fromAddress()
  {
    return htmlspecialchars($this->catchAllEmail);
  }

  /** In principle the most important stuff: the message text. */
  public function messageText()
  {
    return $this->messageContents;
  }

  /** Export BCC. */
  public function blindCarbonCopy()
  {
    return $this->cgiValue('BCC', '');
  }

  /** Export CC. */
  public function carbonCopy()
  {
    return $this->cgiValue('CC', '');
  }

  /** Export Subject. */
  public function subject()
  {
    return $this->cgiValue('subject', '');
  }

  /** Return the file attachment data. */
  public function fileAttachments()
  {
    // JSON encoded array
    $fileAttachJSON = $this->cgiValue('fileAttachments', '{}');
    $fileAttach = json_decode($fileAttachJSON, true);
    $selectedAttachments = $this->cgiValue('attachedFiles', []);
    $selectedAttachments = array_flip($selectedAttachments);
    $localFileAttach = [];
    $cloudFileAttach = [];
    foreach($fileAttach as $attachment) {
      if ($attachment['status'] == 'new') {
        $attachment['status'] = 'selected';
      } else if (isset($selectedAttachments[$attachment['tmp_name']])) {
        $attachment['status'] = 'selected';
      } else {
        $attachment['status'] = 'inactive';
      }
      $attachment['name'] = basename($attachment['name']);
      if ($attachment['origin'] == self::ATTACHMENT_ORIGIN_CLOUD) {
        $cloudFileAttach[] = $attachment;
      } else {
        $localFileAttach[] = $attachment;
      }
    }

    usort($cloudFileAttach, function($a, $b) {
      return strcmp($a['name'], $b['name']);
    });
    usort($localFileAttach, function($a, $b) {
      return strcmp($a['name'], $b['name']);
    });

    return array_merge($localFileAttach, $cloudFileAttach);
  }

  /**
   * A helper function to generate suitable select options for
   * PageNavigation::selectOptions()
   */
  public function fileAttachmentOptions($fileAttach)
  {
    $selectOptions = [];
    foreach($fileAttach as $attachment) {
      $value    = $attachment['tmp_name'];
      $size     = \OC_Helper::humanFileSize($attachment['size']);
      $name     = $attachment['name'].' ('.$size.')';
      $group    = $attachment['origin'] == self::ATTACHMENT_ORIGIN_CLOUD ? $this->l->t('Cloud') : $this->l->t('Local Filesystem');
      $selected = $attachment['status'] == 'selected';
      $selectOptions[] = [
        'value' => $value,
        'name' => $name,
        'group' => $group,
        'flags' => $selected ? PageNavigation::SELECTED : 0,
      ];
    }
    return $selectOptions;
  }

  /**
   * Return the file attachment data. This function checks for the
   * cgi-values of EventSelect or the "local" cgi values
   * emailComposer[AttachedEvents]. The "legacy" values take
   * precedence.
   */
  public function eventAttachments()
  {
    $attachedEvents = $this->parameterService->getParam(
      'eventSelect', $this->cgiValue('attachedEvents', []));
    return $attachedEvents;
  }

  /**
   * A helper function to generate suitable select options for
   * PageNavigation::selectOptions().
   *
   * @param $projectId Id of the active project. If <= 0 an empty
   * array is returned.
   *
   * @param $attachedEvents Flat array of attached events.
   */
  public function eventAttachmentOptions($projectId, $attachedEvents)
  {
    if ($projectId <= 0) {
      return [];
    }

    // fetch all events for this project
    $events      = $this->eventsService->events($projectId);
    $dfltIds     = $this->eventsService->defaultCalendars();
    $eventMatrix = $this->eventsService->eventMatrix($events, $dfltIds);

    // timezone, locale
    $locale = $this->getLocale();
    $timezone = $this->getTimezone();

    // transpose for faster lookup
    $attachedEvents = array_flip($attachedEvents);

    // build the select option control array
    $selectOptions = [];
    foreach($eventMatrix as $eventGroup) {
      $group = $eventGroup['name'];
      foreach($eventGroup['events'] as $event) {
        $object = $event['object'];
        $datestring = $this->eventsService->briefEventDate($object, $timezone, $locale);
        $name = stripslashes($object['summary']).', '.$datestring;
        $value = $event['EventId'];
        $selectOptions[] = array('value' => $value,
                                 'name' => $name,
                                 'group' => $group,
                                 'flags' => isset($attachedEvents[$value]) ? PageNavigation::SELECTED : 0
        );
      }
    }
    return $selectOptions;
  }

  /** Return the dispatch status */
  public function executionStatus()
  {
    return $this->executionStatus;
  }

  /** Return the dispatch status. */
  public function errorStatus()
  {
    return !$this->executionStatus();
  }

  /** Return possible diagnostics or not. Depending on operation. */
  public function statusDiagnostics()
  {
    return $this->diagnostics;
  }
}
