<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\IAppContainer;
use OCP\IRequest;
use OCP\ISession;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IURLGenerator;
use OCP\Files\FileInfo;
use OCP\IDateTimeFormatter;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\ContactsService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\EmailForm\RecipientsFilter;
use OCA\CAFEVDB\EmailForm\Composer;

use OCA\CAFEVDB\Common\Util;

class EmailFormController extends Controller {
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var ISession */
  private $session;

  /** @var ParameterService */
  private $parameterService;

  /** @var IURLGenerator */
  private $urlGenerator;

  /** @var ProjectService */
  private $projectService;

  /** @var PageNavigation */
  private $pageNavigation;

  /** @var PHPMyEdit */
  private $pme;

  /** @var IAppContainer */
  private $appContainer;

  public function __construct(
    $appName
    , IRequest $request
    , ISession $session
    , IAppContainer $appContainer
    , IURLGenerator $urlGenerator
    , RequestParameterService $parameterService
    , PageNavigation $pageNavigation
    , ConfigService $configService
    , ProjectService $projectService
    , PHPMyEdit $pme
  ) {
    parent::__construct($appName, $request);
    $this->session = $session;
    $this->appContainer = $appContainer;
    $this->urlGenerator = $urlGenerator;
    $this->parameterService = $parameterService;
    $this->pageNavigation = $pageNavigation;
    $this->configService = $configService;
    $this->projectService = $projectService;
    $this->pme = $pme;
    $this->l = $this->l10N();
  }

  private function getEmailDraftAutoSave()
  {
    return $this->getUserValue('email-draft-auto-save', 300);
  }

  /**
   * @NoAdminRequired
   * @UseSession
   */
  public function webForm($projectId = -1, $projectName = '', $bulkTransactionId = -1, $emailTemplate = null)
  {
    $composer = $this->appContainer->query(Composer::class);
    $recipientsFilter = $composer->getRecipientsFilter();

    $fileAttachments = $composer->fileAttachments();
    $eventAttachments = $composer->eventAttachments();

    $emailDraftAutoSave = $this->getEmailDraftAutoSave();

    $templateParameters = [
      'appName' => $this->appName(),
      'urlGenerator' => $this->urlGenerator,
      'dateTimeFormatter' => $this->appContainer->get(IDateTimeFormatter::class),
      'pageNavigation' => $this->pageNavigation,
      'toolTips' => $this->appContainer->get(ToolTipsService::class),
      'emailComposer' => $composer,
      'uploadMaxFilesize' => Util::maxUploadSize(),
      'uploadMaxHumanFilesize' => \OCP\Util::humanFileSize(Util::maxUploadSize()),
      'requesttoken' => \OCP\Util::callRegister(), // @todo: check
      'csrfToken' => \OCP\Util::callRegister(), // @todo: check
      'projectName' => $projectName,
      'projectId' => $projectId,
      'bulkTransactionId' => $bulkTransactionId,
      // Provide enough data s.t. a form-reload will bump the user to the
      // form the email-dialog was opened from. Ideally, we intercept the
      // form submit in javascript and simply close the dialog. Most of
      // the stuff below is a simple safe-guard.
      'formData' => [
        'projectName' => $projectName,
        'projectId' => $projectId,
        'template' => $this->parameterService['template'],
        // 'renderer' => ???? @todo check
        'bulkTransactionId' => $bulkTransactionId,
        'requesttoken' => \OCP\Util::callRegister(), // @todo: check
        'csrfToken' => \OCP\Util::callRegister(), // @todo: check
        $emailKey => $this->pme->cgiSysName('mrecs'),

      ],
      'emailDraftAutoSave' => $emailDraftAutoSave,
      // Needed for the editor
      'emailTemplateName' => $composer->currentEmailTemplate(),
      'storedEmails' => $composer->storedEmails(),
      'TO' => $composer->toString(),
      'BCC' => $composer->blindCarbonCopy(),
      'CC' => $composer->carbonCopy(),
      'mailTag' => $composer->subjectTag(),
      'subject' => $composer->subject(),
      'message' => $composer->messageText(),
      'sender' => $composer->fromName(),
      'catchAllEmail' => $composer->fromAddress(),
      'fileAttachmentOptions' => $composer->fileAttachmentOptions($fileAttachments),
      'fileAttachmentData' => json_encode($fileAttachments),
      'eventAttachmentOptions' => $composer->eventAttachmentOptions($projectId, $eventAttachments),
      'composerFormData' => $composer->formData(),
      // Needed for the recipient selection
      'recipientsFormData' => $recipientsFilter->formData(),
      'filterHistory' => $recipientsFilter->filterHistory(),
      'memberStatusFilter' => $recipientsFilter->memberStatusFilter(),
      'basicRecipientsSet' => $recipientsFilter->basicRecipientsSet(),
      'instrumentsFilter' => $recipientsFilter->instrumentsFilter(),
      'emailRecipientsChoices' => $recipientsFilter->emailRecipientsChoices(),
      'missingEmailAddresses' => $recipientsFilter->missingEmailAddresses(),
      'frozenRecipients' => $recipientsFilter->frozenRecipients(),
    ];

    // Close the session
    $this->session->close();

    $html = (new TemplateResponse(
      $this->appName,
      'emailform/form',
      $templateParameters,
      'blank'))->render();

    $responseData = [
      'contents' => $html,
      'projectName' => $projectName,
      'projectId' => $projectId,
      'filterHistory' => $templateParameters['filterHistory'],
    ];

    return self::dataResponse($responseData);
  }

  private function storedEmailOptions($composer)
  {
    $stored = $composer->storedEmails();
    $options = '';
    $options .= '
            <optgroup label="'.$this->l->t('Drafts').'">
';
    foreach ($stored['drafts'] as $draft) {
      $options .= '
              <option value="__draft-'.$draft['id'].'">'.$draft['name'].'</option>
';
    }
    $options .= '
            </optgroup>';
    $options .= '<optgroup label="'.$this->l->t('Templates').'">
';
    foreach ($stored['templates'] as $template) {
      $options .= '
              <option value="'.$template['id'].'">'.$template['name'].'</option>
';
    }
    $options .= '
            </optgroup>';

    return $options;
  }

  /**
   * @NoAdminRequired
   * @UseSession
   *
   * @todo Close the PHP session if no longer needed.
   */
  public function composer($operation, $topic, $projectId, $projectName, $debitNodeId)
  {
    $caption = ''; ///< Optional status message caption.
    $messageText = ''; ///< Optional status message.
    $debugText = ''; ///< Diagnostic output, only enabled on request.

    $defaultData = [
      'operation' => 'update',
      'topic' => 'undefined',
      'projectId' => $projectId,
      'projectName' => $projectName,
      'bulkTransactionId' => $bulkTransactionId,
    ];
    $requestData = array_merge($defaultData, $this->parameterService->getParam('emailComposer', []));
    $projectId   = $requestData['projectId'];
    $projectName = $requestData['projectName'];
    $bulkTransactionId = $requestData['bulkTransactionId'];

    /** @var Composer */
    $composer = $this->appContainer->get(Composer::class);
    $recipientsFilter = $composer->getRecipientsFilter();

    if ($operation != 'load') {
      $this->session->close();
    }

    $recipients = $recipientsFilter->selectedRecipients();
    if (isset($requestData['singleItem'])) {
      $requestData['errorStatus'] = false;
      $requestData['diagnostics'] = '';
    } else {
      $requestData['errorStatus'] = $composer->errorStatus();
      $requestData['diagnostics'] = $composer->statusDiagnostics();
    }

    switch ($operation) {
    case 'send':
      $composer->sendMessages();
      if (!$composer->errorStatus()) {
        // Echo something back on success, error diagnostics are handled
        // in a unified way at the end of this script.
        $diagnostics = $composer->statusDiagnostics();
        $caption = $diagnostics['caption'];

        $roles = $this->appContainer->get(OrganizationalRolesService::class);
        $tmpl = new TemplateResponse(
          $this->appName,
          'emailform/part.emailform.statuspage',
          [
            'projectName' => $projectName,
            'projectId' => $projectId,
            'diagnostics' => $diagnostics,
            'cloudAdminContact' => $roles->cloudAdminContact(),
          ],
          'blank');
        $messageText = $tmpl->render();

        // Update list of drafts after sending the message (draft has
        // been deleted)
        $requestData['storedEmailOptions'] = $this->storedEmailOptions($composer);
      }
      break;
    case 'preview':
      $previewMessages = $composer->previewMessages();
      if ($composer->errorStatus()) {
        $requestData['errorStatus'] = $composer->errorStatus();
        $requestData['diagnostics'] = $composer->statusDiagnostics();
        break;
      }
      $templateParameters = [
        'appName' => $this->appName,
        'projectName' => $projectName,
        'projectId' => $projectId,
        'messages' => $previewMessages,
      ];
      $html = (new TemplateResponse(
        $this->appName,
        'emailform/part.emailform.preview',
        $templateParameters,
        'blank'))->render();
      return self::dataResponse([
        'message' => $this->l->t('Preview generation successful.'),
        'contents' => $html,
      ]);
    case 'cancel':
      $composer->cleanTemporaries();
      break;
    case 'update':
      switch ($topic) {
      case 'undefined':
        $fileAttachments = $composer->fileAttachments();
        $eventAttachments = $composer->eventAttachments();

        $emailDraftAutoSave = $this->getEmailDraftAutoSave();

        $templateParameters = [
          'projectName' => $projectName,
          'projectId' => $projectId,
          'emailTemplateName' => $composer->currentEmailTemplate(),
          'storedEmails' => $composer->storedEmails(),
          'TO' => $composer->toString(),
          'BCC' => $composer->blindCarbonCopy(),
          'CC' => $composer->carbonCopy(),
          'mailTag' => $composer->subjectTag(),
          'subject' => $composer->subject(),
          'message' => $composer->messageText(),
          'sender' => $composer->fromName(),
          'catchAllEmail' => $composer->fromAddress(),
          'fileAttachmentOptions' => $composer->fileAttachmentOptions($fileAttachments),
          'fileAttachmentData' => json_encode($fileAttachments),
          'eventAttachmentOptions' => $composer->eventAttachmentOptions($projectId, $eventAttachments),
          'dateTimeFormatter' => $this->appContainer->get(IDateTimeFormatter::class),
          'composerFormData' => $composer->formData(),
          'emailDraftAutoSave' => $emailDraftAutoSave,
        ];
        $elementData = (new TemplateResponse(
          $this->appName,
          'emailform/part.emailform.composer',
          $templateParameters,
          'blank'))->render();
        break;
      case 'element':
        $formElement = $requestData['formElement'];
        switch ($formElement) {
        case 'TO':
          $elementData = $composer->toString();
          break;
        case 'fileAttachments':
          $fileAttachments = $composer->fileAttachments();
          $elementData = [
            'options' => PageNavigation::selectOptions($composer->fileAttachmentOptions($fileAttachments)),
            'attachments' => $fileAttachments,
          ];
          break;
        case 'eventAttachments':
          $eventAttachments = $composer->eventAttachments();
          $elementData = [
            'options' => PageNavigation::selectOptions($composer->eventAttachmentOptions($projectId, $eventAttachments)),
            'attachments' => $eventAttachments,
          ];
          break;
        default:
          return self::grumble($this->l->t("Unknown form element: `%s'.", $formElement));
        }
        break;
      default:
        return self::grumble($this->l->t('Unknown request: "%s / %s".', [ $operation, $topic ]));
      }
      $requestData['formElement'] = $formElement;
      $requestData['elementData'] = $elementData;
      break;
    case 'load':
      $value = $requestData['storedMessagesSelector'];
      switch ($topic) {
      case 'template':
        if (!$composer->loadTemplate($value)) {
          return self::grumble($this->l->t('Unable to load template "%s".', $value));
        }
        $requestData['emailTemplateName'] = $composer->currentEmailTemplate();
        $requestData['message'] = $composer->messageText();
        $requestData['subject'] = $composer->subject();
        break;
      case 'draft':
        if (!preg_match('/__draft-(-?[0-9]+)/', $value, $matches)) {
          return self::grumble($this->l->t('Invalid draft name "%s".', $value));
        }
        $draftId = $matches[1];
        $draftParameters = $composer->loadDraft($draftId);
        if ($composer->errorStatus()) {
          $requestData['errorStatus'] = $composer->errorStatus();
          $requestData['diagnostics'] = $composer->statusDiagnostics();
          break;
        }
        $draftParameters[Composer::POST_TAG]['messageDraftId'] =
           $requestData['messageDraftId'] = $draftId;

        $requestParameters = $this->parameterService->getParams();
        $requestParameters = Util::arrayMergeRecursive($requestParameters, $draftParameters);

        // Update project name and id
        $projectId = $requestData['projectId'] = $requestParameters['projectId'];
        $projectName = $requestData['projectName'] = $requestParameters['projectName'];
        $bulkTransactionId = $requestData['bulkTransactionId'] = $requestParameters['bulkTransactionId'];

        // install new request parameters
        $this->parameterService->setParams($requestParameters);

        // "reload" the composer and recipients filter
        $composer->bind($this->parameterService);

        $this->session->close();

        $requestData['errorStatus'] = $composer->errorStatus();
        $requestData['diagnostics'] = $composer->statusDiagnostics();

        // Composer template
        $fileAttachments = $composer->fileAttachments();
        $eventAttachments = $composer->eventAttachments();

        $emailDraftAutoSave = $this->getEmailDraftAutoSave();

        $templateParameters = [
          'appName' =>  $this->appName(),
          'projectName' => $projectName,
          'projectId' => $projectId,
          'urlGenerator' => $this->urlGenerator,
          'dateTimeFormatter' => $this->appContainer->get(IDateTimeFormatter::class),
          'emailTemplateName' => $composer->currentEmailTemplate(),
          'storedEmails' => $composer->storedEmails(),
          'TO' => $composer->toString(),
          'BCC' => $composer->blindCarbonCopy(),
          'CC' => $composer->carbonCopy(),
          'mailTag' => $composer->subjectTag(),
          'subject' => $composer->subject(),
          'message' => $composer->messageText(),
          'sender' => $composer->fromName(),
          'catchAllEmail' => $composer->fromAddress(),
          'fileAttachmentOptions' => $composer->fileAttachmentOptions($fileAttachments),
          'fileAttachmentData' => json_encode($fileAttachments),
          'eventAttachmentOptions' => $composer->eventAttachmentOptions($projectId, $eventAttachments),
          'composerFormData' => $composer->formData(),
          'emailDraftAutoSave' => $emailDraftAutoSave,
        ];

        $msgData = (new TemplateResponse(
          $this->appName,
          'emailform/part.emailform.composer',
          $templateParameters,
          'blank'))->render();

        // Recipients template
        $filterHistory = $recipientsFilter->filterHistory();
        $templateParameters = [
          'appName' => $this->appName(),
          'projectName' => $projectName,
          'projectId' => $projectId,
          // Needed for the recipient selection
          'recipientsFormData' => $recipientsFilter->formData(),
          'filterHistory' => $filterHistory,
          'memberStatusFilter' => $recipientsFilter->memberStatusFilter(),
          'basicRecipientsSet' => $recipientsFilter->basicRecipientsSet(),
          'instrumentsFilter' => $recipientsFilter->instrumentsFilter(),
          'emailRecipientsChoices' => $recipientsFilter->emailRecipientsChoices(),
          'missingEmailAddresses' => $recipientsFilter->missingEmailAddresses(),
          'frozenRecipients' => $recipientsFilter->frozenRecipients(),
        ];

        $rcptData = (new TemplateResponse(
          $this->appName,
          'emailform/part.emailform.recipients',
          $templateParameters,
          'blank'))->render();

        $requestData['composerForm'] = $msgData;
        $requestData['recipientsForm'] = $rcptData;

        if (!$composer->errorStatus()) {
          $debugText .= $this->l->t("Loaded draft message with id %d", $requestData['messageDraftId']);
        }
        break;
      default:
        return self::grumble($this->l->t('Unknown request: "%s / %s".', [ $operation, $topic ]));
      }
      break; // load
    case 'save':
      switch ($topic) {
      case 'template':
        $emailTemplateName = Util::normalizeSpaces($requestData['emailTemplateName']);
        if (empty($emailTemplateName)) {
          return self::grumble($this->l->t('Email template name must not be empty'));
        }
        if ($composer->validateTemplate()) {
          $composer->storeTemplate($emailTemplateName);
        } else {
          $requestData['errorStatus'] = $composer->errorStatus();
          $requestData['diagnostics'] = $composer->statusDiagnostics();
        }
        break;
      case 'draft':
        if ($composer->storeDraft()) {
          $requestData['messageDraftId'] = $composer->messageDraftId();
        } else {
          $requestData['errorStatus'] = $composer->errorStatus();
          $requestData['diagnostics'] = $composer->statusDiagnostics();
        }
        break;
      default:
        return self::grumble($this->l->t('Unknown request: "%s / %s".', [ $operation, $topic ]));
      }
      if ($composer->errorStatus()) {
        $reqquestData['diagnostics']['caption'] =
                                                $this->l->t('%s could not be saved', ucfirst($topic));
      } else {
        $requestData['storedEmailOptions'] = $this->storedEmailOptions($composer);
      }
      break;
    case 'delete':
      switch ($topic) {
      case 'template':
        $composer->deleteTemplate($requestData['emailTemplateName']);
        $composer->setDefaultTemplate();
        $requestData['emailTemplateName'] = $composer->currentEmailTemplate();
        $requestData['message'] = $composer->messageText();
        $requestData['subject'] = $composer->subject();
        break;
      case 'draft':
        $composer->deleteDraft();
        $debugText .= $this->l->t("Deleted draft message with id %d", $requestData['messageDraftId']);
        $requestData['messageDraftId'] = 0;
        break;
      default:
        return self::grumble($this->l->t('Unknown request: "%s / %s".', [ $operation, $topic ]));
      }
      $requestData['storedEmailOptions'] = $this->storedEmailOptions($composer);
      break;
    case 'validateEmailRecipients':
      $composer->validateFreeFormAddresses($requestData['Header'],
                                           $requestData['Recipients']);
      $requestData['errorStatus'] = $composer->errorStatus();
      $requestData['diagnostics'] = $composer->statusDiagnostics();
      if ($requestData['errorStatus']) {
        $requestData['diagnostics']['caption'] =
                                               $this->l->t('Email Address Validation Failed');
      }
      break;
    default:
      return self::grumble($this->l->t("Unknown request: `%s'.", $request));
    }

    //$this->logInfo('REQUEST DATA POST '.print_r($requestData, true));

    if ($requestData['errorStatus']) {
      $caption = $requestData['diagnostics']['caption'];

      $roles = $this->appContainer->get(OrganizationalRolesService::class);
      $messageText = (new TemplateResponse(
        $this->appName,
        'emailform/part.emailform.statuspage',
        [
          'projectName' => $projectName,
          'projectId' => $projectId,
          'diagnostics' => $requestData['diagnostics'],
          'cloudAdminContact' => $roles->cloudAdminContact(),
        ],
        'blank'))->render();
      return self::grumble([
        'operation' => $operation,
        'topic' => $topic,
        'projectName' => $projectName,
        'projectId' => $projectId,
        'caption' => $caption,
        'message' => $messageText,
        'requestData' => $requestData,
        'debug' => htmlspecialchars($debugText),
      ]);
    } else {
      return self::dataResponse([
        'operation' => $operation,
        'topic' => $topic,
        'projectName' => $projectName,
        'projectId' => $projectId,
        'caption' => $caption,
        'message' => $messageText,
        'requestData' => $requestData,
        'debug' => htmlspecialchars($debugText),
      ]);
    }
  }

  /**
   * @NoAdminRequired
   * @UseSession
   *
   * @todo Close the PHP session if no longer needed.
   */
  public function recipientsFilter($projectId, $projectName, $bulkTransactionId)
  {
    $recipientsFilter = $this->appContainer->query(RecipientsFilter::class);

    $this->session->close();

    if ($recipientsFilter->reloadState()) {
      // Rebuild the entire page
      $recipientsOptions = [];
      $missingEmailAddresses = '';

      $filterHistory = $recipientsFilter->filterHistory();
      $templateParameters = [
        'appName' => $this->appName(),
        'projectName' => $projectName,
        'projectId' => $projectId,
        'bulkTransactionId' => $bulkTransactionId,
        // Needed for the recipient selection
        'recipientsFormData' => $recipientsFilter->formData(),
        'filterHistory' => $filterHistory,
        'memberStatusFilter' => $recipientsFilter->memberStatusFilter(),
        'basicRecipientsSet' => $recipientsFilter->basicRecipientsSet(),
        'instrumentsFilter' => $recipientsFilter->instrumentsFilter(),
        'emailRecipientsChoices' => $recipientsFilter->emailRecipientsChoices(),
        'missingEmailAddresses' => $recipientsFilter->missingEmailAddresses(),
        'frozenRecipients' => $recipientsFilter->frozenRecipients(),
      ];

      $contents = (new TemplateResponse(
        $this->appName,
        'emailform/part.emailform.recipients',
        $templateParameters,
        'blank'))->render();
    } else if ($recipientsFilter->snapshotState()) {
      // short-circuit
      $filterHistory = $recipientsFilter->filterHistory();
      return self::dataResponse([ 'filterHistory' => $filterHistory ]);
    } else {
      $recipientsChoices = $recipientsFilter->emailRecipientsChoices();
      $recipientsOptions = PageNavigation::selectOptions($recipientsChoices);
      $missingEmailAddresses = '';
      $separator = '';
      foreach ($recipientsFilter->missingEmailAddresses() as $id => $name) {
        $missingEmailAddresses .= $separator;
        $separator = ', ';
        $missingEmailAddresses .=
                               '<span class="missing-email-addresses personal-record" data-id="'.$id.'">'
                               .$name
                               .'</span>';
      }
      $filterHistory = $recipientsFilter->filterHistory();
      $contents = '';
    }

    return self::dataResponse([
      'projectName' => $projectName,
      'projectId' => $projectId,
      'contents' => $contents,
      'recipientsOptions' => $recipientsOptions,
      'missingEmailAddresses' => $missingEmailAddresses,
      'filterHistory' => $filterHistory,
    ]);
  }

  /**
   * @NoAdminRequired
   */
  public function contacts($operation)
  {
    /** @var ContactsService */
    $contactsService = $this->appContainer->query(ContactsService::class);
    switch ($operation) {
    case 'list':
      // Free-form recipients from Cc: or Bcc:
      $freeForm  = $this->parameterService->getParam('freeFormRecipients', '');

      // Convert the free-form input to an array (possibly)
      $parser = new \Mail_RFC822(null, null, null, false);
      $recipients = $parser->parseAddressList($freeForm);
      $parseError = $parser->parseError();
      if ($parseError !== false) {
        return self::grumble(
          $this->l->t('Unable to parse email-recipients "%s".',
                      vsprintf($parseError['message'], $parseError['data'])));
      }
      $freeForm = [];
      foreach ($recipients as $emailRecord) {
        $email = $emailRecord->mailbox.'@'.$emailRecord->host;
        $name  = $emailRecord->personal;
        $freeForm[$email] = $name;
      }

      // Fetch all known address-book contacts with email
      $bookContacts = $contactsService->emailContacts();

      $addressBookEmails = [];
      foreach ($bookContacts as $entry) {
        $addressBookEmails[$entry['email']] = $entry['name'];
      }

      // Convert the free-form input in "book-format", but exclude those
      // contacts already present in the address-book in order not to list
      // contacts twice.
      $formContacts = [];
      foreach ($freeForm as $email => $name) {
        if (isset($addressBookEmails[$email]) /* && $addressBookEmails[$email] == $name*/) {
          // skip free-form if already listed in address-book
          continue;
        }
        $formContacts[] = [
          'email' => $email,
          'name' => $name,
          'addressBook' => $this->l->t('Form Input'),
          'class' => 'free-form'
        ];
      }

      // The total options list is the union of the (remaining) free-form
      // addresses and the address-book entries
      $emailOptions = array_merge($formContacts, $bookContacts);

      // Now convert it into a form Navigation::selectOptions()
      // understands
      $selectOptions = [];
      foreach ($emailOptions as $entry) {
        $email = $entry['email'];
        if ($entry['name'] == '') {
          $displayName = $email;
        } else {
          $displayName = $entry['name'].' <'.$email.'>';
        }

        $option = [
          'value' => $email,
          'name' => $displayName,
          'flags' => isset($freeForm[$email]) ? PageNavigation::SELECTED : 0,
          'group' => $entry['addressBook'],
        ];
        if (isset($entry['class'])) {
          $option['groupClass'] = $entry['class'];
        }
        $selectOptions[] = $option;
      }

      // $phpMailer = new \OCA\CAFEVDB\CommonPHPMailer(true); could validate addresses here

      $html = (new TemplateResponse(
        $this->appName,
        'emailform/addressbook',
        [ 'emailOptions' => $selectOptions ],
        'blank'))->render();

      return self::dataResponse([ 'contents' => $html ]);

    case 'save':
      // Get some common post data, rest has to be handled by the
      // recipients and the sender class.
      $addressBookCandidates = $this->parameterService->getParam('addressBookCandidates', []);

      $formContacts = [];
      foreach ($addressBookCandidates as $record) {
        // This is already pre-parsed. If there is a natural name for the
        // person, then it is the thing until the first occurence of '<'.
        $text = $record['text']; // use html?
        $name = strchr($text, '<', true);
        if ($name !== false) {
          $name = Util::normalizeSpaces($name);
        } else {
          $name = '';
        }
        $email = $record['value'];
        $formContacts[] = [
          'email' => $email,
          'name' => $name,
          'display' => htmlspecialchars($name.' <'.$email.'>')
        ];
      }
      $failedContacts = [];
      foreach($formContacts as $contact) {
        if ($contactsService->addEmailContact($contact) === false) {
          $failedContacts[] = $contact['display'];
        }
      }

      if (count($failedContacts) > 0) {
        return self::grumble(
          $this->l->t(
            'The following contacts could not be stored: %s',
            implode(', ', $failedContacts)));
      }

      return self::response('');
    }
    return self::grumble($this->l->t('UNIMPLEMENTED'));
  }

  /**
   * @NoAdminRequired
   * @UseSession
   *
   * @todo Use IAppData for storage.
   */
  public function attachment($source)
  {
    $composer = $this->appContainer->query(Composer::class);
    $upload_max_filesize = \OCP\Util::computerFileSize(ini_get('upload_max_filesize'));
    $post_max_size = \OCP\Util::computerFileSize(ini_get('post_max_size'));
    $maxUploadFileSize = min($upload_max_filesize, $post_max_size);
    $maxHumanFileSize = \OCP\Util::humanFileSize($maxUploadFileSize);

    $this->session->close();

    switch ($source) {
    case Composer::ATTACHMENT_ORIGIN_CLOUD:
      $paths = $this->parameterService['paths'];
      if (empty($paths)) {
        return self::grumble($this->l->t('Attachment file-names were not submitted'));
      }

      // @todo find file in cloud
      $storage = $this->appContainer->query(UserStorage::class);
      $files = [];
      foreach ($paths as $path) {
        $node = $storage->get($path);
        if (empty($node)) {
          return self::grumble($this->l->t('File "%s" could not be found in cloud storage.', $path));
        }
        if ($node->getType() != FileInfo::TYPE_FILE) {
          return self::grumble($this->l->t('File "%s" is not a plain file, this is not yet implemented.'));
        }

        // We emulate an uploaded file here:
        $fileRecord = [
          'name' => $path,
          'error' => 0,
          'tmp_name' => $node->getStorage()->getLocalFile($node->getInternalPath()),
          'type' => $node->getMimetype(),
          'size' => $node->getSize(),
        ];

        if ($composer->saveAttachment($fileRecord, false) === false) {
          return self::grumble($this->l->t('Couldn\'t save temporary file for: %s', $fileRecord['name']));
        }

        $fileRecord['original_name']      = $fileRecord['name']; // clone
        $fileRecord['upload_max_file_size'] = $maxUploadFileSize;
        $fileRecord['max_human_file_size']  = $maxHumanFileSize;
        $files[] = $fileRecord;
      }
      return self::dataResponse($files);
    case Composer::ATTACHMENT_ORIGIN_UPLOAD:
      $fileKey = 'files';
      if (empty($_FILES[$fileKey])) {
        // may be caused by PHP restrictions which are not caught by
        // error handlers.
        $contentLength = $this->request->server['CONTENT_LENGTH'];
        $limit = \OCP\Util::uploadLimit();
        if ($contentLength > $limit) {
          return self::grumble(
            $this->l->t('Upload size %s exceeds limit %s, contact your server administrator.', [
              \OCP\Util::humanFileSize($contentLength),
              \OCP\Util::humanFileSize($limit),
            ]));
        }
        $error = error_get_last();
        if (!empty($error)) {
          return self::grumble(
            $this->l->t('No file was uploaded, error message was "%s".', $error['message']));
        }
        return self::grumble($this->l->t('No file was uploaded. Unknown error'));
      }

      $files = Util::transposeArray($_FILES[$fileKey]);

      $totalSize = 0;
      foreach ($files as &$file) {

        $totalSize += $file['size'];

        if ($maxUploadFileSize >= 0 and $totalSize > $maxUploadFileSize) {
          return self::grumble([
            'message' => $this->l->t('Not enough storage available'),
            'upload_max_file_size' => $maxUploadFileSize,
            'max_human_file_size' => $maxHumanFileSize,
          ]);
        }

        $file['upload_max_file_size'] = $maxUploadFileSize;
        $file['max_human_file_size']  = $maxHumanFileSize;
        $file['original_name'] = $file['name']; // clone

        $file['str_error'] = Util::fileUploadError($file['error'], $this->l);
        if ($file['error'] != UPLOAD_ERR_OK) {
          continue;
        }

        // Move the temporary files to locations where we can find them later.
        if ($composer->saveAttachment($file) === false) {
          $file['error'] = 99;
          $file['str_error'] = $this->l->t('Couldn\'t save temporary file for: %s', $file['name']);
          continue;
        }
      }
      return self::dataResponse($files);
    }
    return self::grumble($this->l->t('Unknown attachment source: "%s".', $source));
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
