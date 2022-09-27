<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\EmailForm\RecipientsFilter;
use OCA\CAFEVDB\EmailForm\Composer;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumAttachmentOrigin as AttachmentOrigin;

use OCA\CAFEVDB\Common\Util;

class EmailFormController extends Controller {
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  public const UPLOAD_KEY = 'files';

  const TOPIC_UNSPECIFIC = 'general';

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
  public function webForm(?int $projectId = null, ?string $projectName = '', ?int $bulkTransactionId = null, ?string $emailTemplate = null)
  {
    /** @var Composer $composer */
    $composer = $this->appContainer->query(Composer::class);
    $recipientsFilter = $composer->getRecipientsFilter();

    $fileAttachments = $composer->fileAttachments();
    $eventAttachments = $composer->eventAttachments();

    $emailDraftAutoSave = $this->getEmailDraftAutoSave();

    $templateParameters = [
      'appName' => $this->appName(),
      'urlGenerator' => $this->urlGenerator,
      'dateTimeFormatter' => $this->appContainer->get(IDateTimeFormatter::class),
      'dateTimeZone' => $this->getDateTimeZone(),
      'pageNavigation' => $this->pageNavigation,
      'emailComposer' => $composer,
      'uploadMaxFilesize' => Util::maxUploadSize(),
      'uploadMaxHumanFilesize' => \OCP\Util::humanFileSize(Util::maxUploadSize()),
      'requesttoken' => \OCP\Util::callRegister(), // @todo: check
      'projectName' => $projectName,
      'projectId' => $projectId,
      'wikinamespace' => $this->getAppValue('wikinamespace'),
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
        'requesttoken' => \OCP\Util::callRegister(),
        'emailKey' => $this->pme->cgiSysName('mrecs'),
      ],
      'emailDraftAutoSave' => $emailDraftAutoSave,
      // Needed for the editor
      'emailTemplateName' => $composer->currentEmailTemplate(),
      'storedEmails' => $composer->storedEmails(),
      'sentEmails' => $composer->sentEmails(),
      'disclosedRecipients' => $composer->discloseRecipients(),
      'TO' => $composer->toStringArray(),
      'BCC' => $composer->blindCarbonCopy(),
      'CC' => $composer->carbonCopy(),
      'mailTag' => $composer->subjectTag(),
      'subject' => $composer->subject(),
      'message' => $composer->messageText(),
      'sender' => $composer->fromName(),
      'catchAllEmail' => $composer->fromAddress(),
      'fileAttachmentOptions' => $composer->fileAttachmentOptions(),
      'fileAttachmentData' => json_encode($fileAttachments),
      'eventAttachmentOptions' => $composer->eventAttachmentOptions($projectId, $eventAttachments),
      'composerFormData' => $composer->formData(),
      // Needed for the recipient selection
      'recipientsFormData' => $recipientsFilter->formData(),
      'filterHistory' => $recipientsFilter->filterHistory(), // Session Usage!
      'memberStatusFilter' => $recipientsFilter->memberStatusFilter(),
      'basicRecipientsSet' => $recipientsFilter->basicRecipientsSet(),
      'instrumentsFilter' => $recipientsFilter->instrumentsFilter(),
      'emailRecipientsChoices' => $recipientsFilter->emailRecipientsChoices(),
      'missingEmailAddresses' => $recipientsFilter->missingEmailAddresses(),
      'frozenRecipients' => $recipientsFilter->frozenRecipients(),
      RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY => $recipientsFilter->getMailingListInfo(RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY),
      RecipientsFilter::PROJECT_MAILING_LIST_KEY => $recipientsFilter->getMailingListInfo(RecipientsFilter::PROJECT_MAILING_LIST_KEY),

      'toolTips' => $this->toolTipsService(),
    ];

    // Close the session ONLY AFTER fetching the filter history
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

  /**
   * Regenerate the stored-email options after updating drafts or
   * templates.
   */
  private function storedEmailOptions(Composer $composer)
  {
    $templateParamters = [
      'storedEmails' => $composer->storedEmails(),
      'dateTimeFormatter' => $this->dateTimeFormatter(),
      'dateTimeZone' => $this->getDateTimeZone(),
    ];

    $tmpl = new TemplateResponse(
      $this->appName,
      'emailform/part.stored-email-options',
      $templateParamters,
      'blank');
    return $tmpl->render();
  }

  /**
   * Regenerate the sent-email options after e.g. changing the project
   * context or having sent out an email.
   */
  private function sentEmailOptions(Composer $composer)
  {
    $templateParameters = [
      'sentEmails' => $composer->sentEmails(),
      'dateTimeFormatter' => $this->dateTimeFormatter(),
      'dateTimeZone' => $this->getDateTimeZone(),
    ];

    $tmpl = new TemplateResponse(
      $this->appName,
      'emailform/part.sent-email-options',
      $templateParameters,
      'blank');
    return $tmpl->render();
  }

  /**
   * @NoAdminRequired
   * @UseSession
   *
   * @todo Close the PHP session if no longer needed.
   */
  public function composer($operation, $topic, $projectId, $projectName)
  {
    $caption = ''; ///< Optional status message caption.
    $messageText = ''; ///< Optional status message.
    $debugText = ''; ///< Diagnostic output, only enabled on request.

    $defaultData = [
      'operation' => 'update',
      'topic' => self::TOPIC_UNSPECIFIC,
      'projectId' => $projectId,
      'projectName' => $projectName,
      'bulkTransactionId' => -1,
    ];
    $requestData = array_merge($defaultData, $this->parameterService->getParam(Composer::POST_TAG, []));
    $projectId   = $requestData['projectId'];
    $projectName = $requestData['projectName'];
    $bulkTransactionId = $requestData['bulkTransactionId'];

    /** @var Composer $composer */
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
      $diagnostics = $composer->statusDiagnostics();
      $requestData['errorStatus'] = $composer->errorStatus();
      $requestData['diagnostics'] = $composer->statusDiagnostics();
      if (!$composer->errorStatus()) {
        // Echo something back on success, error diagnostics are handled
        // in a unified way at the end of this script.
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
            'dateTimeFormatter' => $this->dateTimeFormatter(),
          ],
          'blank');
        $messageText = $tmpl->render();

        // Update list of drafts after sending the message (draft has
        // been deleted)
        $requestData['storedEmailOptions'] = $this->storedEmailOptions($composer);
        $requestData['sendEmailOptions'] = $this->sentEmailOptions($composer);
      }
      break;
    case 'preview':
      switch ($topic) {
        case self::TOPIC_UNSPECIFIC:
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
            'urlGenerator' => $this->urlGenerator,
            'requesttoken' => \OCP\Util::callRegister(),
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
        case 'attachment':
          // @todo
          //
          // * identify attachment by file-name and musician, probably
          break;
      }
      break;
    case 'cancel':
      $composer->cleanDrafts();
      $composer->cleanTemporaries();
      $composer->cleanAttachmentDownloads();
      break;
    case 'update':
      switch ($topic) {
      case self::TOPIC_UNSPECIFIC:
        $fileAttachments = $composer->fileAttachments();
        $eventAttachments = $composer->eventAttachments();

        $emailDraftAutoSave = $this->getEmailDraftAutoSave();

        $templateParameters = [
          'projectName' => $projectName,
          'projectId' => $projectId,
          'emailTemplateName' => $composer->currentEmailTemplate(),
          'storedEmails' => $composer->storedEmails(),
          'sentEmails' => $composer->sentEmails(),
          'disclosedRecipients' => $composer->discloseRecipients(),
          'TO' => $composer->toStringArray(),
          'BCC' => $composer->blindCarbonCopy(),
          'CC' => $composer->carbonCopy(),
          'mailTag' => $composer->subjectTag(),
          'subject' => $composer->subject(),
          'message' => $composer->messageText(),
          'sender' => $composer->fromName(),
          'catchAllEmail' => $composer->fromAddress(),
          'fileAttachmentOptions' => $composer->fileAttachmentOptions(),
          'fileAttachmentData' => json_encode($fileAttachments),
          'eventAttachmentOptions' => $composer->eventAttachmentOptions($projectId, $eventAttachments),
          'dateTimeFormatter' => $this->appContainer->get(IDateTimeFormatter::class),
          'composerFormData' => $composer->formData(),
          'emailDraftAutoSave' => $emailDraftAutoSave,
          RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY => $recipientsFilter->getMailingListInfo(RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY),
          RecipientsFilter::PROJECT_MAILING_LIST_KEY => $recipientsFilter->getMailingListInfo(RecipientsFilter::PROJECT_MAILING_LIST_KEY),

          'toolTips' => $this->toolTipsService(),
        ];
        $elementData = (new TemplateResponse(
          $this->appName,
          'emailform/part.emailform.composer',
          $templateParameters,
          'blank'))->render();
        break;
      case 'element':
        $formElements = $requestData['formElement'];
        $formElements = is_array($formElements) ? $formElements : [ $formElements ];
        foreach ($formElements as $formElement) {
          switch (strtolower($formElement)) {
            case 'to':
              $elementData[$formElement] = $composer->toStringArray();
              break;
            case 'subjecttag':
              $elementData[$formElement] = $composer->subjectTag();
              break;
            case 'fileattachments':
              $fileAttachments = $composer->fileAttachments();
              $elementData[$formElement] = [
                'options' => PageNavigation::selectOptions($composer->fileAttachmentOptions()),
                'attachments' => $fileAttachments,
              ];
              break;
            case 'eventattachments':
              $eventAttachments = $composer->eventAttachments();
              $elementData[$formElement] = [
                'options' => PageNavigation::selectOptions($composer->eventAttachmentOptions($projectId, $eventAttachments)),
                'attachments' => $eventAttachments,
              ];
              break;
            default:
              return self::grumble($this->l->t("Unknown form element: `%s'.", $formElement));
          }
        }
        break;
      default:
        return self::grumble($this->l->t('Unknown request: "%s / %s".', [ $operation, $topic ]));
      }
      $requestData['formElement'] = $formElements ?? null;
      $requestData['elementData'] = $elementData;
      break;
    case 'load':
      switch ($topic) {
      case 'sent':
        $value = $requestData['sentMessagesSelector'];
        if (!$composer->loadSentEmail($value)) {
          return self::grumble($this->l->t('Unable to load sent email with message-id "%s".', $value));
        }
        $requestData['message'] = $composer->messageText();
        $requestData['subject'] = $composer->subject();

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
          'dateTimeZone' => $this->getDateTimeZone(),

          'emailTemplateName' => $composer->currentEmailTemplate(),
          'storedEmails' => $composer->storedEmails(),
          'sentEmails' => $composer->sentEmails(),
          'disclosedRecipients' => $composer->discloseRecipients(),
          'TO' => $composer->toStringArray(),
          'BCC' => $composer->blindCarbonCopy(),
          'CC' => $composer->carbonCopy(),
          'mailTag' => $composer->subjectTag(),
          'subject' => $composer->subject(),
          'message' => $composer->messageText(),
          'sender' => $composer->fromName(),
          'catchAllEmail' => $composer->fromAddress(),
          'fileAttachmentOptions' => $composer->fileAttachmentOptions(),
          'fileAttachmentData' => json_encode($fileAttachments),
          'eventAttachmentOptions' => $composer->eventAttachmentOptions($projectId, $eventAttachments),
          'composerFormData' => $composer->formData(),
          'emailDraftAutoSave' => $emailDraftAutoSave,

          'toolTips' => $this->toolTipsService(),
        ];

        $msgData = (new TemplateResponse(
          $this->appName,
          'emailform/part.emailform.composer',
          $templateParameters,
          'blank'))->render();

        $requestData['composerForm'] = $msgData;

        // We need to tweak the recipients template
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

          'toolTips' => $this->toolTipsService(),
        ];

        $rcptData = (new TemplateResponse(
          $this->appName,
          'emailform/part.emailform.recipients',
          $templateParameters,
          'blank'))->render();

        $requestData['recipientsForm'] = $rcptData;

        break;
      case 'template':
        $value = $requestData['storedMessagesSelector'];
        if (!$composer->loadTemplate($value)) {
          return self::grumble($this->l->t('Unable to load template "%s".', $value));
        }
        $requestData['emailTemplateName'] = $composer->currentEmailTemplate();
        $requestData['message'] = $composer->messageText();
        $requestData['subject'] = $composer->subject();
        break;
      case 'draft':
        $value = $requestData['storedMessagesSelector'];
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

        // Loading a draft message means that the project-relation of the
        // stored draft should be re-established. Unfortunately, it is stored
        // in two redundant positions ...
        foreach (['projectId', 'projectName', 'bulkTransactionId'] as $draftPriorityKey) {
          $requestParameters[$draftPriorityKey] = null;
          $requestParameters[Composer::POST_TAG][$draftPriorityKey] = null;
        }

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
          'dateTimeZone' => $this->getDateTimeZone(),

          'emailTemplateName' => $composer->currentEmailTemplate(),
          'storedEmails' => $composer->storedEmails(),
          'sentEmails' => $composer->sentEmails(),
          'disclosedRecipients' => $composer->discloseRecipients(),
          'TO' => $composer->toStringArray(),
          'BCC' => $composer->blindCarbonCopy(),
          'CC' => $composer->carbonCopy(),
          'mailTag' => $composer->subjectTag(),
          'subject' => $composer->subject(),
          'message' => $composer->messageText(),
          'sender' => $composer->fromName(),
          'catchAllEmail' => $composer->fromAddress(),
          'fileAttachmentOptions' => $composer->fileAttachmentOptions(),
          'fileAttachmentData' => json_encode($fileAttachments),
          'eventAttachmentOptions' => $composer->eventAttachmentOptions($projectId, $eventAttachments),
          'composerFormData' => $composer->formData(),
          'emailDraftAutoSave' => $emailDraftAutoSave,
          RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY => $recipientsFilter->getMailingListInfo(RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY),
          RecipientsFilter::PROJECT_MAILING_LIST_KEY => $recipientsFilter->getMailingListInfo(RecipientsFilter::PROJECT_MAILING_LIST_KEY),

          'toolTips' => $this->toolTipsService(),
        ];

        $msgData = (new TemplateResponse(
          $this->appName,
          'emailform/part.emailform.composer',
          $templateParameters,
          'blank'))->render();

        $requestData['composerForm'] = $msgData;

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
          RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY => $recipientsFilter->getMailingListInfo(RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY),
          RecipientsFilter::PROJECT_MAILING_LIST_KEY => $recipientsFilter->getMailingListInfo(RecipientsFilter::PROJECT_MAILING_LIST_KEY),

          'toolTips' => $this->toolTipsService(),
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
        if ($composer->deleteDraft()) {
          $debugText .= $this->l->t("Deleted draft message with id %d", $requestData['messageDraftId']);
          $requestData['messageDraftId'] = 0;
        } else {
          $requestData['errorStatus'] = $composer->errorStatus();
          $requestData['diagnostics'] = $composer->statusDiagnostics();
        }
        break;
      default:
        return self::grumble($this->l->t('Unknown request: "%s / %s".', [ $operation, $topic ]));
      }
      $requestData['storedEmailOptions'] = $this->storedEmailOptions($composer);
      break;
    case 'validateEmailRecipients':
      $composer->validateFreeFormAddresses($requestData['header'],
                                           $requestData['recipients']);
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
          'dateTimeFormatter' => $this->dateTimeFormatter(),
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

    $filterHistory = $recipientsFilter->filterHistory();

    if ($recipientsFilter->snapshotState()) {
      // short-circuit
      return self::dataResponse([ 'filterHistory' => $filterHistory ]);
    }

    if ($recipientsFilter->reloadState()) {
      // Rebuild the entire page

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
        RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY => $recipientsFilter->getMailingListInfo(RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY),
        RecipientsFilter::PROJECT_MAILING_LIST_KEY => $recipientsFilter->getMailingListInfo(RecipientsFilter::PROJECT_MAILING_LIST_KEY),

        'toolTips' => $this->toolTipsService(),
      ];

      $contents = (new TemplateResponse(
        $this->appName,
        'emailform/part.emailform.recipients',
        $templateParameters,
        'blank'))->render();

      return self::dataResponse([
        'projectName' => $projectName,
        'projectId' => $projectId,
        'contents' => $contents,
        // remaining parameter are expected by JS code and need to be there
        'instrumentsFilter' => '',
        'recipientsOptions' => '',
        'missingEmailAddresses' => '',
        'filterHistory' => '',
      ]);
    }

    $recipientsChoices = $recipientsFilter->emailRecipientsChoices();
    $recipientsOptions = PageNavigation::selectOptions($recipientsChoices);

    $missingEmailAddresses = (new TemplateResponse(
      $this->appName,
        'emailform/part.broken-email-addresses', [
          'missingEmailAddresses' => $recipientsFilter->missingEmailAddresses(),
        ],
      'blank'))->render();

    $instrumentsFilter = (new TemplateResponse(
      $this->appName,
        'emailform/part.instruments-filter', [
          'instrumentsFilter' => $recipientsFilter->instrumentsFilter(),
        ],
      'blank'))->render();

    return self::dataResponse([
      'projectName' => $projectName,
      'projectId' => $projectId,
      'recipientsOptions' => $recipientsOptions,
      'missingEmailAddresses' => $missingEmailAddresses,
      'filterHistory' => $filterHistory,
      'instrumentsFilter' => $instrumentsFilter,
      // remaining parameter is expected by JS code and needs to be there
      'contents' => '',
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
    case AttachmentOrigin::CLOUD:
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
          'node' => $node,
        ];

        if ($composer->saveAttachment($fileRecord) === false) {
          return self::grumble($this->l->t('Couldn\'t save temporary file for: %s', $fileRecord['name']));
        }

        $fileRecord['original_name']      = $fileRecord['name']; // clone
        $fileRecord['upload_max_file_size'] = $maxUploadFileSize;
        $fileRecord['max_human_file_size']  = $maxHumanFileSize;
        $files[] = $fileRecord;
      }
      return self::dataResponse($files);
    case AttachmentOrigin::UPLOAD:
      $files = $this->request->files[self::UPLOAD_KEY];
      if (empty($files)) {
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

      $files = Util::transposeArray($files);

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
