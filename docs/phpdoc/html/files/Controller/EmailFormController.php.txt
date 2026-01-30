<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Controller;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use Throwable;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute as CoreAttributes;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http;
use OCP\AppFramework\IAppContainer;
use OCP\Files\FileInfo;
use OCP\IDateTimeFormatter;
use OCP\IRequest;
use OCP\IURLGenerator;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Controller\DTO;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumAttachmentOrigin as AttachmentOrigin;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\EmailForm\Composer;
use OCA\CAFEVDB\EmailForm\ComposerCgiKeys;
use OCA\CAFEVDB\EmailForm\EnumFromTag;
use OCA\CAFEVDB\EmailForm\RecipientsFilter;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\PageRenderer\PersistentCGIKeys;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ContactsService;
use OCA\CAFEVDB\Service\EmailAddressService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Storage\UserStorage;

/** Controller class for the mass-email form */
class EmailFormController extends Controller
{
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  public const UPLOAD_KEY = 'files';

  public const EMAIL_TEMPLATE_NAME = 'emailTemplateName';
  public const TEMPLATE_EMAILS = 'templateEmails';
  public const DRAFT_EMAILS = 'draftEmails';
  public const SENT_EMAILS = 'sentEmails';

  /** {@inheritdoc} */
  public function __construct(
    string $appName,
    IRequest $request,
    private ContactsService $contactsService,
    private EmailAddressService $emailAddressService,
    private IURLGenerator $urlGenerator,
    private PHPMyEdit $pme,
    private PageNavigation $pageNavigation,
    protected ConfigService $configService,
    protected IAppContainer $appContainer,
  ) {
    parent::__construct($appName, $request);
    $this->l = $this->l10N();
  }

  /** @return int Email draft auto-save interval in seconds. */
  private function getEmailDraftAutoSave():int
  {
    return $this->getUserValue(EnumPersonalSettingsKey::EMAIL_DRAFT_AUTO_SAVE, ConfigConstants::DEFAULT_AUTOSAVE_INTERVAL);
  }

  /**
   * Return HTML for the web-form.
   *
   * @param null|int $projectId The project id.
   *
   * @param null|string $projectName The project name.
   *
   * @param null|int $bulkTransactionId The id of an associated bulk-transaction if any.
   *
   * @param null|string $emailTemplate Name of an email template.
   *
   * @return DataResponse
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(verb: 'POST', url: '/communication/email/outgoing/form')]
  public function webForm(
    int $projectId = 0,
    ?string $projectName = null,
    int $bulkTransactionId = 0,
    ?string $emailTemplate = null
  ): DataResponse|JSONResponse {

    // try to fetch filter information from the base-table if possible.
    $idx = $this->request->getParam(PersistentCGIKeys::PARTICIPATION_STATUS_FDD_INDEX);
    $participationStatusFilter = $this->request->getParam($this->pme->cgiSysName('qf' . $idx . '_idx'));

    $idx = $this->request->getParam(PersistentCGIKeys::INSTRUMENTS_FDD_INDEX);
    $instrumentsFilter = $this->request->getParam($this->pme->cgiSysName('qf' . $idx . '_idx'));

    $this->logInfo('MEMBER / INSTRUMENTS ' . print_r($participationStatusFilter, true) . ' / ' . print_r($instrumentsFilter, true));
    $recipientsFilterCGI = $this->request->getParam(RecipientsFilter::POST_TAG, []);
    if (!empty($instrumentsFilter)) {
      $recipientsFilterCGI['instrumentsFilter'] = $instrumentsFilter;
    }
    if (!empty($participationStatusFilter)) {
      $recipientsFilterCGI['participationStatusFilter'] = $participationStatusFilter;
    }

    $requestParameters = $this->request->getParams();
    $requestParameters[RecipientsFilter::POST_TAG] = $recipientsFilterCGI;

    /** @var Composer $composer */
    try {
      $composer = $this->appContainer->get(Composer::class);
    } catch (Throwable $t) {
      $this->logException($t);
    }
    $composer->bind($requestParameters);
    $recipientsFilter = $composer->getRecipientsFilter();

    $fileAttachments = $composer->fileAttachments();
    $eventAttachments = $composer->eventAttachments();

    $emailDraftAutoSave = $this->getEmailDraftAutoSave();

    $subjectTagPrefix = $this->getConfigValue(ConfigConstants::BULK_EMAIL_SUBJECT_TAG);
    $subjectTag = trim($composer->subjectTag(), '[]');
    if (!empty($subjectTagPrefix) && str_starts_with($subjectTag, $subjectTagPrefix)) {
      $subjectTag = substr($subjectTag, strlen($subjectTagPrefix) + 1);
    }

    $templateParameters = [
      'appName' => $this->appName(),
      'appNameTag' => CssClasses::APP_NAME_TAG_PREFIX . $this->appName,
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
      ConfigConstants::WIKI_NAME_SPACE_KEY => $this->getAppValue(ConfigConstants::WIKI_NAME_SPACE_KEY),
      'bulkTransactionId' => $bulkTransactionId,
      // Provide enough data s.t. a form-reload will bump the user to the
      // form the email-dialog was opened from. Ideally, we intercept the
      // form submit in javascript and simply close the dialog. Most of
      // the stuff below is a simple safe-guard.
      'formData' => [
        'projectName' => $projectName,
        'projectId' => $projectId,
        'template' => $this->request->getParam('template'),
        // 'renderer' => ???? @todo check
        'bulkTransactionId' => $bulkTransactionId,
        'requesttoken' => \OCP\Util::callRegister(),
        'emailKey' => $this->pme->cgiSysName('mrecs'),
      ],
      // Needed for the editor
      self::EMAIL_TEMPLATE_NAME => $composer->currentEmailTemplate(),
      self::TEMPLATE_EMAILS => $composer->templateEmails(),
      self::DRAFT_EMAILS => $composer->draftEmails(),
      self::SENT_EMAILS => $composer->sentEmails(),
      'TO' => $composer->toStringArray(),
      Composer::POST_TAG => [
        ConfigConstants::BULK_EMAIL_SUBJECT_TAG => $subjectTagPrefix,
        ComposerCgiKeys::BCC => $composer->blindCarbonCopy(),
        ComposerCgiKeys::CC => $composer->carbonCopy(),
        ComposerCgiKeys::MESSAGE_TEXT => $composer->messageText(),
        ComposerCgiKeys::SUBJECT => $composer->subject(),
        ComposerCgiKeys::SUBJECT_TAG => $subjectTag,
        ComposerCgiKeys::FILE_ATTACHMENTS => json_encode($fileAttachments),
        ComposerCgiKeys::FROM_TAG => $composer->fromTag(),
        ComposerCgiKeys::DRAFT_AUTO_SAVE => $emailDraftAutoSave,
        ComposerCgiKeys::DISCLOSED_RECIPIENTS => $composer->discloseRecipients(),
      ],
      'sender' => $composer->fromName(),
      'catchAllEmail' => $composer->fromAddress(),
      'fromName' => [
        EnumFromTag::PERSONAL->value => $composer->fromName(EnumFromTag::PERSONAL),
        EnumFromTag::ORCHESTRA->value => $composer->fromName(EnumFromTag::ORCHESTRA),
      ],
      'fromAddress' => [
        EnumFromTag::PERSONAL->value => $composer->fromAddress(EnumFromTag::PERSONAL),
        EnumFromTag::ORCHESTRA->value => $composer->fromAddress(EnumFromTag::ORCHESTRA),
      ],
      'fileAttachmentOptions' => $composer->fileAttachmentOptions(),
      'eventAttachmentOptions' => $composer->eventAttachmentOptions($projectId, $eventAttachments),
      'composerFormData' => $composer->formData(),
      // Needed for the recipient selection
      'recipientsFormData' => $recipientsFilter->formData(),
      'filterHistory' => $recipientsFilter->filterHistory(),
      'participationStatusFilter' => $recipientsFilter->participationStatusFilter(),
      'basicRecipientsSet' => $recipientsFilter->basicRecipientsSet(),
      'instrumentsFilter' => $recipientsFilter->instrumentsFilter(),
      'emailRecipientsChoices' => $recipientsFilter->emailRecipientsChoices(),
      'missingEmailAddresses' => $recipientsFilter->missingEmailAddresses(),
      'frozenRecipients' => $recipientsFilter->frozenRecipients(),
      RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY => $recipientsFilter->getMailingListInfo(RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY),
      RecipientsFilter::PROJECT_MAILING_LIST_KEY => $recipientsFilter->getMailingListInfo(RecipientsFilter::PROJECT_MAILING_LIST_KEY),
      'toolTips' => $this->toolTipsService(),
    ];

    $html = $this->templateResponse(
      'emailform/form',
      $templateParameters,
    )->render();

    return new DTO\EmailWebFormResponse(
      contents: $html,
      projectName: $projectName,
      projectId: $projectId,
      filterHistory: $templateParameters['filterHistory'],
    )->response();
  }

  /**
   * Regenerate the template emails selector.
   *
   * @param Composer $composer The email composer class.
   *
   * @param ?string $currentTemplate The selected template.
   *
   * @return string Rendered HTML template.
   *
   * @SuppressWarnings(PHPMD.UnusedPrivateMethod) (accesed indirectly throw method variable)
   */
  private function templateEmailOptions(Composer $composer, ?string $currentTemplate = null):string
  {
    $templateParameters = [
      self::TEMPLATE_EMAILS => $composer->templateEmails(),
      self::EMAIL_TEMPLATE_NAME => $currentTemplate,
      'dateTimeFormatter' => $this->dateTimeFormatter(),
      'dateTimeZone' => $this->getDateTimeZone(),
    ];

    $tmpl = $this->templateResponse(
      'emailform/part.template-email-options',
      $templateParameters,
    );
    return $tmpl->render();
  }

  /**
   * Regenerate the draft emails selector.
   *
   * @param Composer $composer The email composer class.
   *
   * @return string Rendered HTML template.
   */
  private function draftEmailOptions(Composer $composer):string
  {
    $templateParameters = [
      self::DRAFT_EMAILS => $composer->draftEmails(),
      'dateTimeFormatter' => $this->dateTimeFormatter(),
      'dateTimeZone' => $this->getDateTimeZone(),
    ];

    $tmpl = $this->templateResponse(
      'emailform/part.draft-email-options',
      $templateParameters,
    );
    return $tmpl->render();
  }

  /**
   * Regenerate the sent-email options after e.g. changing the project
   * context or having sent out an email.
   *
   * @param Composer $composer The email composer service class.
   *
   * @return string The rendered email select options.
   */
  private function sentEmailOptions(Composer $composer):string
  {
    $templateParameters = [
      self::SENT_EMAILS => $composer->sentEmails(),
      'dateTimeFormatter' => $this->dateTimeFormatter(),
      'dateTimeZone' => $this->getDateTimeZone(),
    ];

    $tmpl = $this->templateResponse(
      'emailform/part.sent-email-options',
      $templateParameters,
    );
    return $tmpl->render();
  }

  /**
   * Email composer related controller function.
   *
   * @param string $operation Operation to perform.
   *
   * @param string $topic Sub-topic of the operation.
   *
   * @param null|int $projectId Optional id of a linked project.
   *
   * @param null|string $projectName Name of a linked project, if any.
   *
   * @return DataResponse
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(
    verb: 'POST',
    url: '/communication/email/outgoing/composer/{operation}/{topic}',
    defaults: [
      'operation' => EnumEmailFormComposerOperation::UPDATE->value,
      'topic' => EnumEmailFormComposerTopic::UNSPECIFIC->value,
    ],
  )]
  public function composer(
    string $operation,
    string $topic,
    ?int $projectId,
    ?string $projectName
  ): DataResponse|JSONResponse {
    $caption = ''; ///< Optional status message caption.
    $statusMessageText = ''; ///< Optional status message.
    $debugText = ''; ///< Diagnostic output, only enabled on request.

    $defaultData = [
      ComposerCgiKeys::OPERATION => EnumEmailFormComposerOperation::UPDATE,
      ComposerCgiKeys::TOPIC => EnumEmailFormComposerTopic::UNSPECIFIC,
      ComposerCgiKeys::PROJECT_ID => $projectId,
      ComposerCgiKeys::PROJECT_NAME => $projectName,
      ComposerCgiKeys::BULK_TRANSACTION_ID => -1,
    ];
    $requestData = DTO\EmailFormComposerRequestData::fromArray(
      array_merge($defaultData, $this->request->getParam(Composer::POST_TAG, [])),
    )->toArray();
    $projectId   = $requestData['projectId'];
    $projectName = $requestData['projectName'];

    /** @var Composer $composer */
    $composer = $this->appContainer->get(Composer::class);
    if (!$composer->bound()) {
      $composer->bind($this->request->getParams());
    }
    $recipientsFilter = $composer->getRecipientsFilter();

    if (isset($requestData['singleItem'])) {
      $requestData['errorStatus'] = false;
      $requestData['diagnostics'] = [];
    } else {
      $requestData['errorStatus'] = $composer->errorStatus();
      $requestData['diagnostics'] = $composer->statusDiagnostics();
    }

    $operation = EnumEmailFormComposerOperation::get($operation);
    $topic = EnumEmailFormComposerTopic::get($topic);
    switch ($operation) {
      case EnumEmailFormComposerOperation::SEND:
        $composer->sendMessages();
        $diagnostics = $composer->statusDiagnostics();
        $requestData['errorStatus'] = $composer->errorStatus();
        $requestData['diagnostics'] = $composer->statusDiagnostics();
        if (!$composer->errorStatus()) {
          // Echo something back on success, error diagnostics are handled
          // in a unified way at the end of this script.
          $caption = $diagnostics['caption'];

          $roles = $this->appContainer->get(OrganizationalRolesService::class);
          $tmpl = $this->templateResponse(
            'emailform/part.emailform.statuspage',
            [
              'projectName' => $projectName,
              'projectId' => $projectId,
              'diagnostics' => $diagnostics,
              'cloudAdminContact' => $roles->cloudAdminContact(),
              'dateTimeFormatter' => $this->dateTimeFormatter(),
              'urlGenerator' => $this->urlGenerator,
            ],
          );
          $statusMessageText = $tmpl->render();

          // Update list of drafts after sending the message (draft has
          // been deleted)
          $requestData['draftEmailOptions'] = $this->draftEmailOptions($composer);
          $requestData['sentEmailOptions'] = $this->sentEmailOptions($composer);
        }
        break;
      case EnumEmailFormComposerOperation::PREVIEW:
        switch ($topic) {
          case EnumEmailFormComposerTopic::UNSPECIFIC:
            $previewMessages = $composer->previewMessages();
            $diagnostics = $composer->statusDiagnostics();
            $replacements = [];
            $explanations = [];
            $iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($diagnostics), mode: RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $key => $value) {
              if ($key === 'explanations' && is_string($value) && $value != '') {
                $explanations[] = $value;
              }
              if ($key === 'replacements') {
                $replacements = [...$replacements, ...$value];
              }
            }
            $explanations = array_values(array_unique($explanations));
            if (count($replacements) > 0) {
              $explanations[] = $this->l->t(
                'Please note that the email preview displays the messages with all substitions already applied.'
                . ' Please revisit the message composition window to actually review the changes.'
                . ' You can use the "undo"-functionality of the editor to revert the substitutions.');
            }
            $templateParameters = [
              'appName' => $this->appName,
              'appNameTag' => CssClasses::APP_NAME_TAG_PREFIX . $this->appName,
              'projectName' => $projectName,
              'projectId' => $projectId,
              'messages' => $previewMessages,
              'explanations' => $explanations,
              'urlGenerator' => $this->urlGenerator,
              'requesttoken' => \OCP\Util::callRegister(),
            ];
            $html = $this->templateResponse(
              'emailform/part.emailform.preview',
              $templateParameters,
            )->render();
            $requestData['errorStatus'] = $composer->errorStatus();
            $requestData['diagnostics'] = $diagnostics;
            $requestData['previewData'] = $html;
            if (!empty($replacements)) {
              $requestData[ComposerCgiKeys::MESSAGE_TEXT_REPLACEMENTS] = $replacements;
              $requestData[ComposerCgiKeys::MESSAGE_TEXT] = $composer->messageText();
              $requestData[ComposerCgiKeys::SUBJECT] = $composer->subject();
            }
        }
        break;
      case EnumEmailFormComposerOperation::CANCEL:
        $composer->cleanDrafts();
        $composer->cleanTemporaries();
        $composer->cleanAttachmentDownloads();
        break;
      case EnumEmailFormComposerOperation::UPDATE:
        switch ($topic) {
          case EnumEmailFormComposerTopic::UNSPECIFIC:
            $fileAttachments = $composer->fileAttachments();
            $eventAttachments = $composer->eventAttachments();

            $emailDraftAutoSave = $this->getEmailDraftAutoSave();

            $subjectTagPrefix = $this->getConfigValue('bulkEmailSubjectTag');
            $subjectTag = trim($composer->subjectTag(), '[]');
            if (!empty($subjectTagPrefix) && str_starts_with($subjectTag, $subjectTagPrefix)) {
              $subjectTag = substr($subjectTag, strlen($subjectTagPrefix) + 1);
            }

            $templateParameters = [
              'projectName' => $projectName,
              'projectId' => $projectId,
              self::EMAIL_TEMPLATE_NAME => $composer->currentEmailTemplate(),
              self::TEMPLATE_EMAILS => $composer->templateEmails(),
              self::DRAFT_EMAILS => $composer->draftEmails(),
              self::SENT_EMAILS => $composer->sentEmails(),
              'TO' => $composer->toStringArray(),
              Composer::POST_TAG => [
                ConfigConstants::BULK_EMAIL_SUBJECT_TAG => $subjectTagPrefix,
                ComposerCgiKeys::BCC => $composer->blindCarbonCopy(),
                ComposerCgiKeys::CC => $composer->carbonCopy(),
                ComposerCgiKeys::SUBJECT => $composer->subject(),
                ComposerCgiKeys::MESSAGE_TEXT => $composer->messageText(),
                ComposerCgiKeys::SUBJECT_TAG => $subjectTag,
                ComposerCgiKeys::FILE_ATTACHMENTS => json_encode($fileAttachments),
                ComposerCgiKeys::FROM_TAG => $composer->fromTag(),
                ComposerCgiKeys::DRAFT_AUTO_SAVE => $emailDraftAutoSave,
                ComposerCgiKeys::DISCLOSED_RECIPIENTS => $composer->discloseRecipients(),
              ],
              'sender' => $composer->fromName(),
              'catchAllEmail' => $composer->fromAddress(),
              'fromName' => [
                EnumFromTag::PERSONAL->value => $composer->fromName(EnumFromTag::PERSONAL),
                EnumFromTag::ORCHESTRA->value => $composer->fromName(EnumFromTag::ORCHESTRA)
              ],
              'fromAddress' => [
                EnumFromTag::PERSONAL->value => $composer->fromAddress(EnumFromTag::PERSONAL),
                EnumFromTag::ORCHESTRA->value => $composer->fromAddress(EnumFromTag::ORCHESTRA),
              ],
              'fileAttachmentOptions' => $composer->fileAttachmentOptions(),
              'eventAttachmentOptions' => $composer->eventAttachmentOptions($projectId, $eventAttachments),
              'dateTimeFormatter' => $this->appContainer->get(IDateTimeFormatter::class),
              'composerFormData' => $composer->formData(),
              RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY => $recipientsFilter->getMailingListInfo(RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY),
              RecipientsFilter::PROJECT_MAILING_LIST_KEY => $recipientsFilter->getMailingListInfo(RecipientsFilter::PROJECT_MAILING_LIST_KEY),

              'toolTips' => $this->toolTipsService(),
            ];
            $elementData = $this->templateResponse(
              'emailform/part.emailform.composer',
              $templateParameters,
            )->render();
            break;
          case EnumEmailFormComposerTopic::ELEMENT:
            $formElements = $requestData[ComposerCgiKeys::FORM_ELEMENTS];
            $this->logInfo('#FORM ELEMENTS ' . count($formElements));
            foreach ($formElements as $formElement) {
              switch ($formElement) {
                case EnumEmailFormComposerElement::TO:
                  $elementData[$formElement->value] = $composer->toStringArray();
                  break;
                case EnumEmailFormComposerElement::SUBJECT_TAG:
                  $subjectTagPrefix = $this->getConfigValue(ConfigConstants::BULK_EMAIL_SUBJECT_TAG);
                  $subjectTag = trim($composer->subjectTag(), '[]');
                  if (!empty($subjectTagPrefix) && str_starts_with($subjectTag, $subjectTagPrefix)) {
                    $subjectTag = substr($subjectTag, strlen($subjectTagPrefix) + 1);
                  }
                  $elementData[$formElement->value] = $subjectTag;
                  break;
                case EnumEmailFormComposerElement::FILE_ATTACHMENTS:
                  $fileAttachments = $composer->fileAttachments();
                  $elementData[$formElement->value] = [
                    'options' => PageNavigation::selectOptions($composer->fileAttachmentOptions()),
                    'attachments' => $fileAttachments,
                  ];
                  break;
                case EnumEmailFormComposerElement::EVENT_ATTACHMENTS:
                  $eventAttachments = $composer->eventAttachments();
                  $elementData[$formElement->value] = [
                    'options' => PageNavigation::selectOptions($composer->eventAttachmentOptions($projectId, $eventAttachments)),
                    'attachments' => $eventAttachments,
                  ];
                  break;
                default:
                  return self::grumble($this->l->t("Unknown form element: `%s'.", $formElement->value));
              }
            }
            break;
          default:
            return self::grumble($this->l->t('Unknown request: "%s / %s".', [ $operation, $topic ]));
        }
        $requestData[ComposerCgiKeys::FORM_ELEMENTS] = $formElements ?? null;
        $requestData[ComposerCgiKeys::ELEMENT_DATA] = $elementData;
        break;
      case EnumEmailFormComposerOperation::LOAD:
        switch ($topic) {
          case EnumEmailFormComposerTopic::SENT:
            $value = $requestData['sentMessagesSelector'];
            if (!$composer->loadSentEmail($value)) {
              return self::grumble($this->l->t('Unable to load sent email with message-id "%s".', $value));
            }
            $requestData[ComposerCgiKeys::MESSAGE_TEXT] = $composer->messageText();
            $requestData[ComposerCgiKeys::SUBJECT] = $composer->subject();

            // Composer template
            $fileAttachments = $composer->fileAttachments();
            $eventAttachments = $composer->eventAttachments();

            $emailDraftAutoSave = $this->getEmailDraftAutoSave();

            $subjectTagPrefix = $this->getConfigValue('bulkEmailSubjectTag');
            $subjectTag = trim($composer->subjectTag(), '[]');
            if (!empty($subjectTagPrefix) && str_starts_with($subjectTag, $subjectTagPrefix)) {
              $subjectTag = substr($subjectTag, strlen($subjectTagPrefix) + 1);
            }

            $templateParameters = [
              'appName' =>  $this->appName(),
              'appNameTag' => CssClasses::APP_NAME_TAG_PREFIX . $this->appName,
              'projectName' => $projectName,
              'projectId' => $projectId,
              'urlGenerator' => $this->urlGenerator,
              'dateTimeFormatter' => $this->appContainer->get(IDateTimeFormatter::class),
              'dateTimeZone' => $this->getDateTimeZone(),

              self::EMAIL_TEMPLATE_NAME => $composer->currentEmailTemplate(),
              self::TEMPLATE_EMAILS => $composer->templateEmails(),
              self::DRAFT_EMAILS => $composer->draftEmails(),
              self::SENT_EMAILS => $composer->sentEmails(),
              'TO' => $composer->toStringArray(),
              Composer::POST_TAG => [
                ConfigConstants::BULK_EMAIL_SUBJECT_TAG => $subjectTagPrefix,
                ComposerCgiKeys::BCC => $composer->blindCarbonCopy(),
                ComposerCgiKeys::CC => $composer->carbonCopy(),
                ComposerCgiKeys::SUBJECT_TAG => $subjectTag,
                ComposerCgiKeys::SUBJECT => $composer->subject(),
                ComposerCgiKeys::MESSAGE_TEXT => $composer->messageText(),
                ComposerCgiKeys::FILE_ATTACHMENTS => json_encode($fileAttachments),
                ComposerCgiKeys::FROM_TAG => $composer->fromTag(),
                ComposerCgiKeys::DRAFT_AUTO_SAVE => $emailDraftAutoSave,
                ComposerCgiKeys::DISCLOSED_RECIPIENTS => $composer->discloseRecipients(),
              ],
              'sender' => $composer->fromName(),
              'catchAllEmail' => $composer->fromAddress(),
              'fromName' => [
                EnumFromTag::PERSONAL->value => $composer->fromName(EnumFromTag::PERSONAL),
                EnumFromTag::ORCHESTRA->value => $composer->fromName(EnumFromTag::ORCHESTRA)
              ],
              'fromAddress' => [
                EnumFromTag::PERSONAL->value => $composer->fromAddress(EnumFromTag::PERSONAL),
                EnumFromTag::ORCHESTRA->value => $composer->fromAddress(EnumFromTag::ORCHESTRA),
              ],
              'fileAttachmentOptions' => $composer->fileAttachmentOptions(),
              'eventAttachmentOptions' => $composer->eventAttachmentOptions($projectId, $eventAttachments),
              'composerFormData' => $composer->formData(),
              'toolTips' => $this->toolTipsService(),
            ];

            $msgData = $this->templateResponse(
              'emailform/part.emailform.composer',
              $templateParameters,
            )->render();

            $requestData['composerForm'] = $msgData;

            // We need to tweak the recipients template
            $filterHistory = $recipientsFilter->filterHistory();
            $templateParameters = [
              'appName' => $this->appName(),
              'appNameTag' => CssClasses::APP_NAME_TAG_PREFIX . $this->appName,
              'projectName' => $projectName,
              'projectId' => $projectId,
              // Needed for the recipient selection
              'recipientsFormData' => $recipientsFilter->formData(),
              'filterHistory' => $filterHistory,
              'participationStatusFilter' => $recipientsFilter->participationStatusFilter(),
              'basicRecipientsSet' => $recipientsFilter->basicRecipientsSet(),
              'instrumentsFilter' => $recipientsFilter->instrumentsFilter(),
              'emailRecipientsChoices' => $recipientsFilter->emailRecipientsChoices(),
              'missingEmailAddresses' => $recipientsFilter->missingEmailAddresses(),
              'frozenRecipients' => $recipientsFilter->frozenRecipients(),

              'toolTips' => $this->toolTipsService(),
            ];

            $rcptData = $this->templateResponse(
              'emailform/part.emailform.recipients',
              $templateParameters,
            )->render();

            $requestData['recipientsForm'] = $rcptData;

            break;
          case EnumEmailFormComposerTopic::TEMPLATE:
            $value = $requestData[ComposerCgiKeys::TEMPLATE_MESSAGES_SELECTOR];
            if (!$composer->loadTemplate($value)) {
              throw new Exceptions\EnduserNotificationException(
                message: $this->l->t('Unable to load template "%s".', $value),
                httpStatusCode: Http::STATUS_NOT_FOUND,
              );
            }
            $requestData[self::EMAIL_TEMPLATE_NAME] = $composer->currentEmailTemplate();
            $requestData[ComposerCgiKeys::MESSAGE_TEXT] = $composer->messageText();
            $requestData[ComposerCgiKeys::SUBJECT] = $composer->subject();
            break;
          case EnumEmailFormComposerTopic::DRAFT:
            $draftId = $requestData[ComposerCgiKeys::DRAFT_MESSAGES_SELECTOR];
            $this->logInfo('DRAFT IF ' . $draftId);
            $draftParameters = $composer->loadDraft($draftId);
            if ($composer->errorStatus()) {
              $requestData['errorStatus'] = $composer->errorStatus();
              $requestData['diagnostics'] = $composer->statusDiagnostics();
              break;
            }
            $draftParameters[Composer::POST_TAG][ComposerCgiKeys::MESSAGE_DRAFT_ID] =
              $requestData[ComposerCgiKeys::MESSAGE_DRAFT_ID] = $draftId;

            $requestParameters = $this->request->getParams();

            // Loading a draft message means that the project-relation of the
            // stored draft should be re-established. Unfortunately, it is stored
            // in two redundant positions ...
            foreach (['projectId', 'projectName', 'bulkTransactionId'] as $draftPriorityKey) {
              $requestParameters[$draftPriorityKey] = null;
              $requestParameters[Composer::POST_TAG][$draftPriorityKey] = null;
            }

            $requestParameters = Util::arrayMergeRecursive($requestParameters, $draftParameters);

            // "reload" the composer and recipients filter
            $composer->bind($requestParameters);

            // Update project name and id
            $projectId = $requestData['projectId'] = $requestParameters['projectId'];
            $projectName = $requestData['projectName'] = $requestParameters['projectName'];

            $requestData['bulkTransactionId'] = $requestParameters['bulkTransactionId'];


            $requestData['errorStatus'] = $composer->errorStatus();
            $requestData['diagnostics'] = $composer->statusDiagnostics();

            // Composer template
            $fileAttachments = $composer->fileAttachments();
            $eventAttachments = $composer->eventAttachments();

            $emailDraftAutoSave = $this->getEmailDraftAutoSave();

            $subjectTagPrefix = $this->getConfigValue('bulkEmailSubjectTag');
            $subjectTag = trim($composer->subjectTag(), '[]');
            if (!empty($subjectTagPrefix) && str_starts_with($subjectTag, $subjectTagPrefix)) {
              $subjectTag = substr($subjectTag, strlen($subjectTagPrefix) + 1);
            }

            $templateParameters = [
              'appName' =>  $this->appName(),
              'appNameTag' => CssClasses::APP_NAME_TAG_PREFIX . $this->appName,
              'projectName' => $projectName,
              'projectId' => $projectId,
              'urlGenerator' => $this->urlGenerator,
              'dateTimeFormatter' => $this->appContainer->get(IDateTimeFormatter::class),
              'dateTimeZone' => $this->getDateTimeZone(),

              self::EMAIL_TEMPLATE_NAME => $composer->currentEmailTemplate(),
              self::TEMPLATE_EMAILS => $composer->templateEmails(),
              self::DRAFT_EMAILS => $composer->draftEmails(),
              self::SENT_EMAILS => $composer->sentEmails(),

              'TO' => $composer->toStringArray(),
              Composer::POST_TAG => [
                ConfigConstants::BULK_EMAIL_SUBJECT_TAG => $subjectTagPrefix,
                ComposerCgiKeys::BCC => $composer->blindCarbonCopy(),
                ComposerCgiKeys::CC => $composer->carbonCopy(),
                ComposerCgiKeys::SUBJECT_TAG => $subjectTag,
                ComposerCgiKeys::SUBJECT => $composer->subject(),
                ComposerCgiKeys::MESSAGE_TEXT => $composer->messageText(),
                ComposerCgiKeys::FILE_ATTACHMENTS => json_encode($fileAttachments),
                ComposerCgiKeys::FROM_TAG => $composer->fromTag(),
                ComposerCgiKeys::DRAFT_AUTO_SAVE => $emailDraftAutoSave,
                ComposerCgiKeys::DISCLOSED_RECIPIENTS => $composer->discloseRecipients(),
              ],
              'sender' => $composer->fromName(),
              'catchAllEmail' => $composer->fromAddress(),
              'fromName' => [
                EnumFromTag::PERSONAL->value => $composer->fromName(EnumFromTag::PERSONAL),
                EnumFromTag::ORCHESTRA->value => $composer->fromName(EnumFromTag::ORCHESTRA)
              ],
              'fromAddress' => [
                EnumFromTag::PERSONAL->value => $composer->fromAddress(EnumFromTag::PERSONAL),
                EnumFromTag::ORCHESTRA->value => $composer->fromAddress(EnumFromTag::ORCHESTRA),
              ],
              'fileAttachmentOptions' => $composer->fileAttachmentOptions(),
              'eventAttachmentOptions' => $composer->eventAttachmentOptions($projectId, $eventAttachments),
              'composerFormData' => $composer->formData(),
              RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY => $recipientsFilter->getMailingListInfo(RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY),
              RecipientsFilter::PROJECT_MAILING_LIST_KEY => $recipientsFilter->getMailingListInfo(RecipientsFilter::PROJECT_MAILING_LIST_KEY),

              'toolTips' => $this->toolTipsService(),
            ];

            $msgData = $this->templateResponse(
              'emailform/part.emailform.composer',
              $templateParameters,
            )->render();

            $requestData['composerForm'] = $msgData;

            // Recipients template
            $filterHistory = $recipientsFilter->filterHistory();
            $templateParameters = [
              'appName' => $this->appName(),
              'appNameTag' => CssClasses::APP_NAME_TAG_PREFIX . $this->appName,
              'projectName' => $projectName,
              'projectId' => $projectId,
              // Needed for the recipient selection
              'recipientsFormData' => $recipientsFilter->formData(),
              'filterHistory' => $filterHistory,
              'participationStatusFilter' => $recipientsFilter->participationStatusFilter(),
              'basicRecipientsSet' => $recipientsFilter->basicRecipientsSet(),
              'instrumentsFilter' => $recipientsFilter->instrumentsFilter(),
              'emailRecipientsChoices' => $recipientsFilter->emailRecipientsChoices(),
              'missingEmailAddresses' => $recipientsFilter->missingEmailAddresses(),
              'frozenRecipients' => $recipientsFilter->frozenRecipients(),
              RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY => $recipientsFilter->getMailingListInfo(RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY),
              RecipientsFilter::PROJECT_MAILING_LIST_KEY => $recipientsFilter->getMailingListInfo(RecipientsFilter::PROJECT_MAILING_LIST_KEY),

              'toolTips' => $this->toolTipsService(),
            ];

            $rcptData = $this->templateResponse(
              'emailform/part.emailform.recipients',
              $templateParameters,
            )->render();

            $requestData['recipientsForm'] = $rcptData;

            if (!$composer->errorStatus()) {
              $debugText .= $this->l->t("Loaded draft message with id %d", $requestData[ComposerCgiKeys::MESSAGE_DRAFT_ID]);
            }
            break;
          default:
            return self::grumble($this->l->t('Unknown request: "%s / %s".', [ $operation, $topic ]));
        }
        break; // load
      case EnumEmailFormComposerOperation::SAVE:
        $selected = null;
        switch ($topic) {
          case EnumEmailFormComposerTopic::TEMPLATE:
            $selected =
              $emailTemplateName = Util::normalizeSpaces($requestData[ComposerCgiKeys::TEMPLATE_MESSAGES_SELECTOR]);
            if (empty($emailTemplateName)) {
              throw new Exceptions\EnduserNotificationException(
                $this->l->t('Email template name must not be empty'),
              );
            }
            if ($composer->validateTemplate()) {
              $composer->storeTemplate($emailTemplateName);
            } else {
              $requestData['errorStatus'] = $composer->errorStatus();
              $requestData['diagnostics'] = $composer->statusDiagnostics();
            }
            break;
          case EnumEmailFormComposerTopic::DRAFT:
            if ($composer->storeDraft()) {
              $requestData[ComposerCgiKeys::MESSAGE_DRAFT_ID] = $composer->messageDraftId();
            } else {
              $requestData['errorStatus'] = $composer->errorStatus();
              $requestData['diagnostics'] = $composer->statusDiagnostics();
            }
            break;
          default:
            return self::grumble($this->l->t('Unknown request: "%s / %s".', [ $operation, $topic ]));
        }
        if ($composer->errorStatus()) {
          $requestData['diagnostics']['caption'] =
            $this->l->t('%s could not be saved', ucfirst($topic->value));
        } else {
          $emailOptions = $topic->value . 'EmailOptions';
          $requestData[$emailOptions] = $this->$emailOptions($composer, $selected);
        }
        break;
      case EnumEmailFormComposerOperation::DELETE:
        switch ($topic) {
          case EnumEmailFormComposerTopic::TEMPLATE:
            $composer->deleteTemplate($requestData[ComposerCgiKeys::TEMPLATE_MESSAGES_SELECTOR]);
            $composer->setDefaultTemplate();
            $requestData[self::EMAIL_TEMPLATE_NAME] = $composer->currentEmailTemplate();
            $requestData[ComposerCgiKeys::MESSAGE_TEXT] = $composer->messageText();
            $requestData[ComposerCgiKeys::SUBJECT] = $composer->subject();
            break;
          case EnumEmailFormComposerTopic::DRAFT:
            if ($composer->deleteDraft()) {
              $debugText .= $this->l->t("Deleted draft message with id %d", $requestData[ComposerCgiKeys::MESSAGE_DRAFT_ID]);
              $requestData[ComposerCgiKeys::MESSAGE_DRAFT_ID] = 0;
            } else {
              $requestData['errorStatus'] = $composer->errorStatus();
              $requestData['diagnostics'] = $composer->statusDiagnostics();
            }
            break;
          default:
            return self::grumble($this->l->t('Unknown request: "%s / %s".', [ $operation, $topic ]));
        }
        $emailOptions = $topic->value . 'EmailOptions';
        $requestData[$emailOptions] = $this->$emailOptions($composer);
        break;
      case EnumEmailFormComposerOperation::VALIDATE_EMAIL_RECIPIENTS:
        $composer->validateFreeFormAddresses(
          $requestData['header'],
          $requestData['recipients']
        );
        $requestData['errorStatus'] = $composer->errorStatus();
        $requestData['diagnostics'] = $composer->statusDiagnostics();
        if ($requestData['errorStatus']) {
          $requestData['diagnostics']['caption'] =
            $this->l->t('Email Address Validation Failed');
        }
        break;
      default:
        return self::grumble($this->l->t("Unknown request: `%s'.", $operation));
    }

    $requestData = DTO\EmailFormComposerRequestData::fromArray($requestData);
    if ($requestData->errorStatus) {
      $caption = $requestData->diagnostics['caption'];

      $roles = $this->appContainer->get(OrganizationalRolesService::class);
      $statusMessageText = $this->templateResponse(
        'emailform/part.emailform.statuspage',
        [
          'projectName' => $projectName,
          'projectId' => $projectId,
          'diagnostics' => $requestData->diagnostics,
          'cloudAdminContact' => $roles->cloudAdminContact(),
          'dateTimeFormatter' => $this->dateTimeFormatter(),
          'urlGenerator' => $this->urlGenerator,
        ],
      )->render();

      return new DTO\EmailFormComposerResponse(
        operation: $operation,
        topic: $topic,
        projectName: $projectName,
        projectId: $projectId,
        caption: $caption,
        messages: [$statusMessageText],
        requestData: $requestData,
        debug: htmlspecialchars($debugText),
      )->response(Http::STATUS_BAD_REQUEST);
    } else {
      return new DTO\EmailFormComposerResponse(
        operation: $operation,
        topic: $topic,
        projectName: $projectName,
        projectId: (int)$projectId,
        caption: $caption,
        messages: is_array($statusMessageText) ? $statusMessageText : [$statusMessageText],
        requestData: $requestData,
        debug: htmlspecialchars($debugText),
      )->response();
    }
  }

  /**
   * @param null|int $projectId Project id of the linked project if any.
   *
   * @param null|string $projectName Project name of the linked project if any.
   *
   * @param null|int $bulkTransactionId Bulk-transaction id of the linke bank
   * transaction if any.
   *
   * @return DataResponse|JSONResponse
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(verb: 'POST', url: '/communication/email/outgoing/recipients-filter')]
  public function recipientsFilter(
    ?int $projectId,
    ?string $projectName,
    ?int $bulkTransactionId,
  ): DataResponse|JSONResponse {
    $recipientsFilter = $this->appContainer->get(RecipientsFilter::class);
    if (!$recipientsFilter->bound()) {
      $recipientsFilter->bind($this->request->getParams());
    }

    $filterHistory = $recipientsFilter->filterHistory();

    if ($recipientsFilter->snapshotState()) {
      // short-circuit
      return new DTO\EmailFormRecipientsFilterSnapshotResponse($filterHistory)->response();
    }

    if ($recipientsFilter->reloadState()) {
      // Rebuild the entire page

      $templateParameters = [
        'appName' => $this->appName(),
        'appNameTag' => CssClasses::APP_NAME_TAG_PREFIX . $this->appName,
        'projectName' => $projectName,
        'projectId' => $projectId,
        'bulkTransactionId' => $bulkTransactionId,
        // Needed for the recipient selection
        'recipientsFormData' => $recipientsFilter->formData(),
        'filterHistory' => $filterHistory,
        'participationStatusFilter' => $recipientsFilter->participationStatusFilter(),
        'basicRecipientsSet' => $recipientsFilter->basicRecipientsSet(),
        'instrumentsFilter' => $recipientsFilter->instrumentsFilter(),
        'emailRecipientsChoices' => $recipientsFilter->emailRecipientsChoices(),
        'missingEmailAddresses' => $recipientsFilter->missingEmailAddresses(),
        'frozenRecipients' => $recipientsFilter->frozenRecipients(),
        RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY => $recipientsFilter->getMailingListInfo(RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY),
        RecipientsFilter::PROJECT_MAILING_LIST_KEY => $recipientsFilter->getMailingListInfo(RecipientsFilter::PROJECT_MAILING_LIST_KEY),

        'toolTips' => $this->toolTipsService(),
      ];

      $contents = $this->templateResponse(
        'emailform/part.emailform.recipients',
        $templateParameters,
      )->render();

      return new DTO\EmailFormRecipientsFilterReloadResponse(
        contents: $contents,
        projectId: $projectId,
        projectName: $projectName,
        filterHistory: $filterHistory,
      )->response();
    }

    $recipientsChoices = $recipientsFilter->emailRecipientsChoices();
    $recipientsOptions = PageNavigation::selectOptions($recipientsChoices);

    $missingEmailAddresses = $this->templateResponse(
      'emailform/part.broken-email-addresses', [
        'missingEmailAddresses' => $recipientsFilter->missingEmailAddresses(),
      ],
    )->render();

    $instrumentsFilter = $this->templateResponse(
      'emailform/part.instruments-filter', [
        'instrumentsFilter' => $recipientsFilter->instrumentsFilter(),
      ],
    )->render();

    $participationStatusFilter = $this->templateResponse(
      'emailform/part.participation-status-filter', [
        'participationStatusFilter' => $recipientsFilter->participationStatusFilter(),
      ],
    )->render();

    return new DTO\EmailFormRecipientsFilterResponse(
      filterHistory: $filterHistory,
      instrumentsFilter: $instrumentsFilter,
      missingEmailAddresses: $missingEmailAddresses,
      participationStatusFilter: $participationStatusFilter,
      projectId: $projectId,
      projectName: $projectName,
      recipientsOptions: $recipientsOptions,
    )->response();
  }

  /**
   * Interact with the cloud contacts.
   *
   * @param string $operation Operation to perform.
   *
   * @return Response
   *
   * @throws Exceptions\EnduserNotificationException
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(verb: 'POST', url: '/communication/email/outgoing/contacts/{operation}')]
  public function contacts(string $operation): Response
  {
    $operation = EnumEmailFormContactsOperation::get($operation);
    switch ($operation) {
      case EnumEmailFormContactsOperation::LIST:
        // Free-form recipients from Cc: or Bcc:
        $freeForm  = $this->request->getParam(EnumEmailFormContactsPostParams::FREE_FORM_RECIPIENTS->value, '');

        $freeForm = $this->emailAddressService->parseAddressString($freeForm);

        // Fetch all known address-book contacts with email
        $bookContacts = $this->contactsService->emailContacts();

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

        $html = $this->templateResponse(
          'emailform/addressbook',
          [ 'emailOptions' => $selectOptions ],
        )->render();

        return new DTO\EmailFormListContactsResponse(
          contents: $html,
        )->response();

      case EnumEmailFormContactsOperation::SAVE:
        // Get some common post data, rest has to be handled by the
        // recipients and the sender class.
        $addressBookCandidates = $this->request->getParam(EnumEmailFormContactsPostParams::ADDRESS_BOOK_CANDIDATES->value);

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
            'display' => $name ? htmlspecialchars($name . ' <' . $email . '>') : $email,
          ];
        }
        $failedContacts = [];
        foreach ($formContacts as $contact) {
          if ($this->contactsService->addEmailContact($contact) === null) {
            $failedContacts[] = $contact['display'];
          }
        }

        if (count($failedContacts) > 0) {
          throw Exeptions\EnduserNotificationException(
            message: $this->l->t(
              'The following contacts could not be stored: %s',
              implode(', ', $failedContacts),
            ),
          );
        }

        return new Http\Response;
    }
  }

  /**
   * Fetch uploaded attachments, or faked "uploads" from the cloud FS.
   *
   * @param string $source Attachment origin.
   *
   * @return DataResponse
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(
    verb: 'POST',
    url: '/communication/email/outgoing/attachment/{source}',
    defaults: [ 'source' => AttachmentOrigin::UPLOAD->value ]
  )]
  public function attachment(string $source):DataResponse
  {
    $source = AttachmentOrigin::get($source);
    $composer = $this->appContainer->get(Composer::class);
    if (!$composer->bound()) {
      $composer->bind($this->request->getParams());
    }
    $uploadMaxFileSize = \OCP\Util::computerFileSize(ini_get('upload_max_filesize'));
    $postMaxSize = \OCP\Util::computerFileSize(ini_get('post_max_size'));
    $maxUploadFileSize = min($uploadMaxFileSize, $postMaxSize);
    $maxHumanFileSize = \OCP\Util::humanFileSize($maxUploadFileSize);

    switch ($source) {
      case AttachmentOrigin::CLOUD:
        $paths = $this->request->getParam('paths');
        if (empty($paths)) {
          return self::grumble($this->l->t('Attachment file-names were not submitted'));
        }

        // @todo find file in cloud
        $storage = $this->appContainer->get(UserStorage::class);
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
            'origin' => EnumFileUploadOrigin::CLOUD,
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
        break;
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
              'messages' => [$this->l->t('Not enough storage available')],
              'upload_max_file_size' => $maxUploadFileSize,
              'max_human_file_size' => $maxHumanFileSize,
            ]);
          }

          $file['origin'] = EnumFileUploadOrigin::UPLOAD;
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
        break;
      defaults:
        $files = null;
        break;
    }
    if ($files !== null) {
      return new DataResponse(
        array_map(fn(array $file) => DTO\UploadFileData::fromArray($file), $files),
      );
    }
    return self::grumble($this->l->t('Unknown attachment source: "%s".', $source));
  }
}
