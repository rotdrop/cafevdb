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
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IURLGenerator;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\EmailForm\RecipientsFilter;
use OCA\CAFEVDB\EmailForm\Composer;

use OCA\CAFEVDB\Common\Util;

class EmailFormController extends Controller {
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var \OCA\CAFEVDB\Service\ParameterService */
  private $parameterService;

  /** @var \OCP\IURLGenerator */
  private $urlGenerator;

  /** @var \OCA\CAFEVDB\Service\ProjectService */
  private $projectService;

  /** @var OCA\CAFEVDB\PageRenderer\Util\Navigation */
  private $pageNavigation;

  /** @var PHPMyEdit */
  private $pme;

  /** @var IAppContainer */
  private $appContainer;

  public function __construct(
    $appName
    , IRequest $request
    , IAppContainer $appContainer
    , IURLGenerator $urlGenerator
    , RequestParameterService $parameterService
    , PageNavigation $pageNavigation
    , ConfigService $configService
    , ProjectService $projectService
    , PHPMyEdit $pme
  ) {
    parent::__construct($appName, $request);
    $this->appContainer = $appContainer;
    $this->urlGenerator = $urlGenerator;
    $this->parameterService = $parameterService;
    $this->pageNavigation = $pageNavigation;
    $this->configService = $configService;
    $this->projectService = $projectService;
    $this->pme = $pme;
    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   */
  public function webForm($projectId = -1, $projectName = '', $debitNoteId = -1, $emailTemplate = null)
  {
    $composer = $this->appContainer->query(Composer::class);
    $recipientsFilter = $composer->getRecipientsFilter();

    $templateParameters = [
      'appName' => $this->appName(),
      'appPrefix' => function($id, $join = '-') {
        return $this->appName() . $join . $id;
      },
      'urlGenerator' => $this->urlGenerator,
      'pageNavigation' => $this->pageNavigation,
      'emailComposer' => $composer,
      'uploadMaxFilesize' => Util::maxUploadSize(),
      'uploadMaxHumanFilesize' => \OCP\Util::humanFileSize(Util::maxUploadSize()),
      'requesttoken' => \OCP\Util::callRegister(), // @todo: check
      'csrfToken' => \OCP\Util::callRegister(), // @todo: check
      'projectName' => $projectName,
      'projectId' => $projectId,
      'debitNoteId' => $debitNoteId,
      // Provide enough data s.t. a form-reload will bump the user to the
      // form the email-dialog was opened from. Ideally, we intercept the
      // form submit in javascript and simply close the dialog. Most of
      // the stuff below is a simple safe-guard.
      'formData' => [
        'projectName' => $projectName,
        'projectId' => $projectId,
        'template' => $this->parameterService['template'],
        // 'renderer' => ???? @todo check
        'debitNoteId' => $debitNoteId,
        'requesttoken' => \OCP\Util::callRegister(), // @todo: check
        'csrfToken' => \OCP\Util::callRegister(), // @todo: check
        $emailKey => $this->pme->cgiSysName('mrecs'),

      ],
      // Needed for the editor
      'emailTemplateName' => $composer->currentEmailTemplate(),
      'storedEmails', $composer->storedEmails(),
      'TO' => $composer->toString(),
      'BCC' => $composer->blindCarbonCopy(),
      'CC' => $composer->carbonCopy(),
      'mailTag' => $composer->subjectTag(),
      'subject' => $composer->subject(),
      'message' => $composer->messageText(),
      'sender' => $composer->fromName(),
      'catchAllEmail' => $composer->fromAddress(),
      'fileAttachments' => $composer->fileAttachments(),
      'eventAttachments' => $composer->eventAttachments(),
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

    $html = (new TemplateResponse(
      $this->appName,
      'emailform/form',
      $templateParameters,
      'blank'))->render();

    /**
     * @todo supposedly this should call the destructor. This cannot
     * work well with php. Either register an exit hook or call the
     * things the DTOR is doing explicitly.
     */
    // unset($recipientsFilter);
    // unset($composer);

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
              <option value="__draft-'.$draft['value'].'">'.$draft['name'].'</option>
';
    }
    $options .= '
            </optgroup>';
    $options .= '<optgroup label="'.$this->l->t('Templates').'">
';
    foreach ($stored['templates'] as $template) {
      $options .= '
              <option value="'.$template.'">'.$template.'</option>
';
    }
    $options .= '
            </optgroup>';

    return $options;
  }

  /**
   * @NoAdminRequired
   */
  public function composer($projectId, $projectName, $debitNodeId)
  {
    // Need to unset to trigger destructers in the correct order
    $composer = false;
    $recipientsFilter = false;

    // Need to suspend the session for the progress bar (otherwise opening
    // the current session in the progress-callback will block until
    // send-script has finished)
    $sessionSuspended = false;

    $caption = ''; ///< Optional status message caption.
    $messageText = ''; ///< Optional status message.
    $debugText = ''; ///< Diagnostic output, only enabled on request.

    // Close this session in order to enable progress feed-back
    // @todo Check if really needed
    // session_write_close();
    // $sessionSuspended = true;

    $defaultData = [
      'request' => 'update',
      'formElement' => 'everything',
      'projectId' => $projectId,
      'projectName' => $projectName,
      'debitNoteId' => $debitNoteId,
    ];
    $requestData = array_merge($defaultData, $this->parameterService->getParam('emailComposer', []));
    $projectId   = $requestData['projectId'];
    $projectName = $requestData['projectName'];
    $debitNoteId = $requestData['debitNoteId'];

    $composer = null;
    if (isset($requestData['singleItem'])) {
      $requestData['errorStatus'] = false;
      $requestData['diagnostics'] = '';
    } else {
      $recipientsFilter = \OC::$server->query(RecipientsFilter::class);
      $recipients = $recipientsFilter->selectedRecipients();
      $composer = \OC::$server->query(Composer::class);
      $requestData['errorStatus'] = $composer->errorStatus();
      $requestData['diagnostics'] = $composer->statusDiagnostics();
    }

    $request = $requestData['request'];
    switch ($request) {
    case 'send':
      if (!$composer->errorStatus()) {
        // Echo something back on success, error diagnostics are handled
        // in a unified way at the end of this script.
        $diagnostics = $composer->statusDiagnostics();
        $caption = $diagnostics['caption'];

        $tmpl = new TemplateResponse(
          $this->appName,
          'emailform/part.emailform.statuspage',
          [
            'projectName' => $projectName,
            'projectId' => $projectId,
            'diagnostics' => $diagnostics,
          ],
          'blank');
        $messageText = $tmpl->render();

        // Update list of drafts after sending the message (draft has
        // been deleted)
        $requestData['storedEmailOptions'] = storedEmailOptions($composer);
      }
      break;
    case 'cancel':
      // simply let it do the cleanup
      $composer = \OC::$server->query(Composer::class);
      $blah = $composer->cleanTemporaries();
      $debugText .= "foo".print_r($blah, true);
      break;
    case 'update':
      $composer = \OC::$server->query(Composer::class);
      $formElement = $requestData['formElement'];
      if ($formElement == 'everything') {
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
          'fileAttachments' => $composer->fileAttachments(),
          'eventAttachments' => $composer->eventAttachments(),
          'composerFormData' => $composer->formData(),
        ];
        $elementData = (new TemplateResponse(
          $this->appName,
          'emailform/part.emailform.composer',
          $templateParameters,
          'blank'))->render();
      } else {
        switch ($formElement) {
        case 'TO':
          $elementData = $composer->toString();
          break;
        case 'fileAttachments':
          $composer = new EmailComposer();
          $fileAttach = $composer->fileAttachments();
          $elementData = [
            'options' => PageNavigation::selectOptions($composer->fileAttachmentOptions($fileAttach)),
            'fileAttach' => $fileAttach,
          ];
          break;
        case 'eventAttachments':
          $eventAttach = $composer->eventAttachments();
          $elementData = [
            'options' => PageNavigation::selectOptions($composer->eventAttachmentOptions($projectId, $eventAttach)),
            'eventAttach' => $eventAttach,
          ];
          break;
        default:
          return self::grumble($this->l->t("Unknown form element: `%s'.", $formElement));
        }
      }
      $requestData['formElement'] = $formElement;
      $requestData['elementData'] = $elementData;
      break;
    case 'deleteTemplate':
    case 'setTemplate':
      $requestData['templateName'] = $composer->currentEmailTemplate();
      $requestData['message'] = $composer->messageText();
      $requestData['subject'] = $composer->subject();
      if ($request == 'setTemplate') {
        break;
      }
    case 'saveTemplate':
      if (!$requestData['errorStatus'])  {
        $requestData['storedEmailOptions'] = storedEmailOptions($composer);
      } else {
        $requestData['diagnostics']['Caption'] =
          $this->l->t('Template could not be saved');
      }
      break;
    case 'saveDraft':
      if (!$requestData['errorStatus'])  {
        $requestData['storedEmailOptions'] = storedEmailOptions($composer);
        $requestData['messageDraftId'] = $composer->messageDraftId();
      } else {
        $requestData['diagnostics']['caption'] = $this->l->t('Draft could not be saved');
      }
      break;
    case 'deleteDraft':
      $debugText .= $this->l->t("Deleted draft message with id %d",
                         array($requestData['messageDraftId']));
      $requestData['storedEmailOptions'] = storedEmailOptions($composer);
      $requestData['messageDraftId'] = -1;
      break;
    case 'loadDraft':
      // This seems to be somewhat tricky. The procedure here is to
      // replace the $_POST array by the saved data, reconstruct the
      // composer and the recipient dialogs. Better way than that???

      $requestParameters = $this->parameterService->getParams();
      $requestParameters = Util::arrayMergeRecursive($requestParameters, $composer->loadDraft());
      $requestParameters['emailComposer']['messageDraftId'] = $composer->messageDraftId();

      // Update project name and id
      $projectId = $requestData['projectId'] = $requestParameters['projectId'];
      $projectName = $requestData['projectName'] = $requestParameters['projectName'];
      $debitNoteId = $requestData['debitNoteId'] = $requestParameters['debitNoteId'];
      $requestData['messageDraftId'] = $composer->messageDraftId();

      $this->parameterService->setParams($requestParameters);

      // "reload" the composer and recipients filter
      $composer->bind($this->parameterService);

      $requestData['errorStatus'] = $composer->errorStatus();
      $requestData['diagnostics'] = $composer->statusDiagnostics();

      // Composer template
      $templateParameters = [
        'projectName' => $projectName,
        'projectId' => $projectId,
        'templateName' => $composer->currentEmailTemplate(),
        'storedEmails' => $composer->storedEmails(),
        'TO' => $composer->toString(),
        'BCC' => $composer->blindCarbonCopy(),
        'CC' => $composer->carbonCopy(),
        'mailTag' => $composer->subjectTag(),
        'subject' => $composer->subject(),
        'message' => $composer->messageText(),
        'sender' => $composer->fromName(),
        'catchAllEmail' => $composer->fromAddress(),
        'fileAttachments' => $composer->fileAttachments(),
        'eventAttachments' => $composer->eventAttachments(),
        'composerFormData' => $composer->formData(),
      ];

      $msgData = (new TemplateResponse(
        $this->appName,
        'emailform/part.emailform.composer',
        $templateParameters,
        'blank'))->render();

      // Recipients template
      $filterHistory = $recipientsFilter->filterHistory();
      $templateParameters = [
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

      // $debugText .= print_r($_POST, true);
      $debugText .= $this->l->t("Loaded new draft message with id %d",
                                [ $requestData['messageDraftId'] ]);

      break;
    case 'validateEmailRecipients':
      $composer = new EmailComposer();
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

    // Restart sesssion when finished.
    // @todo needed?
    // session_start();
    // $sessionSuspended = false;

    if ($requestData['errorStatus']) {
      $caption = $requestData['diagnostics']['caption'];

      $messageText = (new TemplateResponse(
        $this->appName,
        'emailform/part.emailform.statuspage',
        [
          'projectName' => $projectName,
          'projectId' => $projectId,
          'diagnostics' => $requestData['diagnostics'],
        ],
        'blank'))->render();
      return self::grumble([
        'projectName' => $projectName,
        'projectId' => $projectId,
        'caption' => $caption,
        'message' => $messageText,
        'request' => $request,
        'requestData' => $requestData,
        'debug' => htmlspecialchars($debugText),
      ]);
    } else {
      return self::dataResponse([
        'projectName' => $projectName,
        'projectId' => $projectId,
        'caption' => $caption,
        'message' => $messageText,
        'request' => $request,
        'requestData' => $requestData,
        'debug' => htmlspecialchars($debugText),
      ]);
    }
  }

  /**
   * @NoAdminRequired
   */
  public function recipientsFilter($projectId, $projectName, $debitNoteId)
  {
    $recipientsfilter = $this->appContainer->query(RecipientsFilter::class);

    if ($recipientsFilter->reloadState()) {
      // Rebuild the entire page
      $recipientsOptions = [];
      $missingEmailAddresses = '';

      $filterHistory = $recipientsFilter->filterHistory();
      $templateData = [
        'projectName' => $projectName,
        'projectId' => $projectId,
        'debitNoteId' => $debitNoteId,
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
  public function upload($object)
  {
    switch ($object) {
    case 'attachment':
      $fileKey = 'files';
      if (!isset($_FILES[$fileKey])) {
        return self::grumble($this->l->t('No file was uploaded. Unknown error'));
      }

      foreach ($_FILES[$fileKey]['error'] as $error) {
        if ($error != 0) {
          $errors = [
            UPLOAD_ERR_OK => $this->l->t('There is no error, the file uploaded with success'),
            UPLOAD_ERR_INI_SIZE => $this->l->t('The uploaded file exceeds the upload_max_filesize directive in php.ini: %s',
                                               array(ini_get('upload_max_filesize'))),
            UPLOAD_ERR_FORM_SIZE => $this->l->t('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'),
            UPLOAD_ERR_PARTIAL => $this->l->t('The uploaded file was only partially uploaded'),
            UPLOAD_ERR_NO_FILE => $this->l->t('No file was uploaded'),
            UPLOAD_ERR_NO_TMP_DIR => $this->l->t('Missing a temporary folder'),
            UPLOAD_ERR_CANT_WRITE => $this->l->t('Failed to write to disk'),
          ];
          return self::grumble($errors[$error]);
        }
      }
      $files = $_FILES[$fileKey];

      $upload_max_filesize = \OCP\Util::computerFileSize(ini_get('upload_max_filesize'));
      $post_max_size = \OCP\Util::computerFileSize(ini_get('post_max_size'));
      $maxUploadFileSize = min($upload_max_filesize, $post_max_size);

      $maxHumanFileSize = \OCP\Util::humanFileSize($maxUploadFileSize);

      $totalSize = 0;
      foreach ($files['size'] as $size) {
        $totalSize += $size;
      }

      if ($maxUploadFileSize >= 0 and $totalSize > $maxUploadFileSize) {
        return self::grumble([
          'message' => $this->l->t('Not enough storage available'),
          'uploadMaxFilesize' => $maxUploadFileSize,
          'maxHumanFilesize' => $maxHumanFileSize,
        ]);
      }

      $result = [];
      $fileCount = count($files['name']);
      for ($i = 0; $i < $fileCount; $i++) {
        $fileRecord = [];
        foreach ($files as $key => $values) {
          $fileRecord[$key] = $values[$i];
        }
        // Move the temporary files to locations where we can find them later.
        $composer = $this->appContainer->query(Composer::class);
        $fileRecord = $composer->saveAttachment($fileRecord);

        // Submit the file-record back to the java-script in order to add the
        // data to the form.
        if ($fileRecord === false) {
          return self::grumble($this->l->t('Couldn\'t save temporary file for: %s', $files['name'][$i]));
        } else {
          $fileRecord['originalname']      = $fileRecord['name']; // clone
          $fileRecord['uploadMaxFilesize'] = $maxUploadFileSize;
          $fileRecord['maxHumanFilesize']  = $maxHumanFileSize;
          $result[] = $fileRecord;
        }
      }
      return self::dataResponse($result);
    }
    return self::grumble($this->l->t('UNIMPLEMENTED'));
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
