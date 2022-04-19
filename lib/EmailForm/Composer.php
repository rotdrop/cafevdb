<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use ZipStream\ZipStream;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;

use OCP\IDateTimeFormatter;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Common\PHPMailer;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumAttachmentOrigin as AttachmentOrigin;
use OCA\CAFEVDB\Documents\OpenDocumentFiller;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\InstrumentationService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Service\ProgressStatusService;
use OCA\CAFEVDB\Service\ConfigCheckService;
use OCA\CAFEVDB\Service\SimpleSharingService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\Service\Finance\SepaBulkTransactionService;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Service\Finance\InstrumentInsuranceService;
use OCA\CAFEVDB\Service\Finance\ReceivablesGeneratorFactory;
use OCA\CAFEVDB\Service\Finance\IRecurringReceivablesGenerator;
use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Storage\DatabaseStorageUtil;

use OCA\CAFEVDB\BackgroundJob\CleanupExpiredDownloads;

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

  const MSG_ID_AT = '_at_';

  const PROGRESS_CHUNK_SIZE = 4096;
  const PROGRESS_THROTTLE_SECONDS = 2;

  const POST_TAG = 'emailComposer';

  private const PERSONAL_ATTACHMENT_PARENTS_STRIP = 5;
  private const ATTACHMENT_PREVIEW_CACHE_TTL = 4 * 60 * 60;

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
  const GLOBAL_NAMESPACE = 'GLOBAL';
  const MEMBER_NAMESPACE = 'MEMBER';
  const MEMBER_VARIABLES = [
    'FIRST_NAME',
    'SUR_NAME',
    'NICK_NAME',
    'DISPLAY_NAME',
    'EMAIL',
    'MOBILE_PHONE',
    'FIXED_LINE_PHONE',
    'STREET',
    'POSTAL_CODE',
    'CITY',
    'COUNTRY',
    'LANGUAGE',
    'BIRTHDAY',
    'TOTAL_FEES',
    'AMOUNT_PAID',
    'MISSING_AMOUNT',
    'PROJECT_DATA',
    'SEPA_MANDATE_REFERENCE',
    'BANK_ACCOUNT_IBAN',
    'BANK_ACCOUNT_BIC',
    'BANK_ACCOUNT_OWNER',
    'BANK_TRANSACTION_AMOUNT',
    'BANK_TRANSACTION_PURPOSE',
    'BANK_TRANSACTION_PARTS',
    'DATE',
  ];
  /**
   * @var string
   * @todo Make this configurable
   */
  const DEFAULT_HTML_TEMPLATES = [
    'transaction-parts' => [
      'header' => '<table class="transaction-parts"><thead><tr>
  <th>[PURPOSE]</th>
  <th>[INVOICED]</th>
  <th>[TOTALS]</th>
  <th>[RECEIVED]</th>
  <th>[REMAINING]</th>
</tr></thead><tbody>',
      'row' => '<tr>
  <td>[PURPOSE]</td>
  <td class="money">[INVOICED]</td>
  <td class="money">[TOTALS]</td>
  <td class="money">[RECEIVED]</td>
  <td class="money">[REMAINING]</td>
</tr>',
      'footer' => '</tbody><tbody class="footer">
  <tr class="totalsum">
    <td>[PURPOSE]</td>
    <td class="money">[INVOICED]</td>
    <td class="money">[TOTALS]</td>
    <td class="money">[RECEIVED]</td>
    <td class="money">[REMAINING]</td>
  </tr>
</tbody></table>'
    ],
    'monetary-fields' => [
      'header' => '<table class="[CSSCLASS]"><thead><tr>
  <th>[OPTION]</th>
  <th>[TOTALS]</th>
  <th>[RECEIVED]</th>
  <th>[REMAINING]</th>
  <th>[DUEDATE]</th>
</tr></thead><tbody>',
      'fieldHeader' => '</tbody><tbody class="[CSSCLASS]">
  <tr class="[CSSCLASS]">
    <td colspan="4">[FIELDNAME]</td>
    <td>[DUEDATE]</td>
  </tr>
</tbody><tbody class="[CSSROWCLASS]">',
      'row' => '<tr class="[CSSROWCLASS]">
  <td class="row-label">[OPTION]</td>
  <td class="money">[TOTALS]</td>
  <td class="money">[RECEIVED]</td>
  <td class="money">[REMAINING]</td>
  <td class="date">[DUEDATE]</td>
</tr><tr class="row-data">
  <td class="row-data-label">[ROWDATALABEL]</td>
  <td class="row-data-contents" colspan="4">[ROWDATACONTENTS]</td>
</tr>',
      'footer' => '</tbody><tbody class="[CSSCLASS]">
  <tr class="[CSSCLASS]">
    <td class="row-label">[LABEl]</td>
    <td class="money">[TOTALS]</td>
    <td class="money">[RECEIVED]</td>
    <td class="money">[REMAINING]</td>
    <td class="date">[DUEDATE]</td>
  </tr>
</tbody></table>',
    ],
  ];
  const EMAIL_PREVIEW_SELECTOR = 'div#cafevdb-email-preview .email-body';
  const DEFAULT_HTML_STYLES = [
    'transaction-parts' => '<style>
[CSSPREFIX]table.transaction-parts,
[CSSPREFIX]table.transaction-parts tr,
[CSSPREFIX]table.transaction-parts th,
[CSSPREFIX]table.transaction-parts td {
  border-collapse:collapse;
}
[CSSPREFIX]table.transaction-parts th,
[CSSPREFIX]table.transaction-parts td {
  border: 1px solid black;
  padding: 0 2pt;
}
[CSSPREFIX]table.transaction-parts th {
  text-align:center;
  font-weight:bold;
  border-bottom:2px solid black;
}
[CSSPREFIX]table.transaction-parts td { text-align:left; }
[CSSPREFIX]table.transaction-parts tr.totalsum {
  border-top:double;
}
[CSSPREFIX]table.transaction-parts tr.totalsum td {
  text-align:right;
  font-weight:bold;
}
[CSSPREFIX]table.transaction-parts td.money {
  text-align:right;
  padding-left: 1em;
 }
</style>',
    'monetary-fields' => '<style>
[CSSPREFIX]table.monetary-fields,
[CSSPREFIX]table.monetary-fields tr,
[CSSPREFIX]table.monetary-fields th,
[CSSPREFIX]table.monetary-fields td {
  border-collapse:collapse;
}
[CSSPREFIX]table.monetary-fields th,
[CSSPREFIX]table.monetary-fields td {
  border: 1px solid black;
  padding: 0 2pt;
}
[CSSPREFIX]table.monetary-fields tr.field-header {
  border-top:double;
  border-bottom:2px solid black;
}
[CSSPREFIX]table.monetary-fields tr.field-header td {
  font-style:italic;
  text-align:center;
}
[CSSPREFIX]table.monetary-fields th {
  font-weight:bold;
  text-align:center;
  border-bottom:2px solid black;
}
[CSSPREFIX]table.monetary-fields tbody.field-option.number-of-options-1 td.row-label {
  font-style:italic;
}
[CSSPREFIX]table.monetary-fields tbody.footer td.date,
[CSSPREFIX]table.monetary-fields tbody.number-of-options-1 td.date {
  opacity:inherit;
}
[CSSPREFIX]table.monetary-fields td.money {
  text-align:right;
  padding-left: 1em;
}
[CSSPREFIX]table.monetary-fields tr.totalsum {
  border-top:3px double black;
  border-bottom:3px double black;
}
[CSSPREFIX]table.monetary-fields tr.totalsum td {
  font-weight:bold;
}
[CSSPREFIX]table.monetary-fields tr.totalsum td.row-label {
  text-align:right;
}
[CSSPREFIX]table.monetary-fields tr.totalsum.total-number-of-options-0,
[CSSPREFIX]table.monetary-fields tr.totalsum.total-number-of-options-1,
[CSSPREFIX]table.monetary-fields tbody.field-header.number-of-options-0,
[CSSPREFIX]table.monetary-fields tbody.field-header.number-of-options-1,
[CSSPREFIX]table.monetary-fields tr.field-header.number-of-options-0,
[CSSPREFIX]table.monetary-fields tr.field-header.number-of-options-1,
[CSSPREFIX]table.monetary-fields tbody.field-option tr.row-data,
[CSSPREFIX]table.monetary-fields tbody:empty {
  display:none;
}
[CSSPREFIX]table.monetary-fields tbody.field-option tr.has-data + tr.row-data {
  display:table-row;
}
[CSSPREFIX]table.monetary-fields tr.row-data td {
  opacity:0.6;
}
[CSSPREFIX]table.monetary-fields tr.row-data td.row-data-label {
  text-align:right;
}
[CSSPREFIX]table.monetary-fields tr.row-data td.row-data-contents {
  white-space:nowrap;
  overflow:hidden;
  max-width:80%;
  text-overflow:ellipsis;
  font-style:italic;
}
</style>',
  ];
  const PARTICIPANT_MONETARY_FIELDS_CSS_CLASS = [
    'header' => 'monetary-fields',
    'fieldHeader' => 'field-header',
    'row' => 'field-option',
    'footer' => 'footer totalsum',
  ];

  private $recipients; ///< The list of recipients.
  private $onLookers;  ///< Cc: and Bcc: recipients.

  /** @var array */
  private $cgiData;

  /** @var bool */
  private $submitted;

  /** @var int */
  private $projectId;

  /** @var string */
  private $projectName;

  /** @var Entities\Project */
  private $project;

  /** @var Entities\SepaBulkTransaction */
  private $bulkTransaction;

  /** @var int */
  private $bulkTransactionId;

  /** @var bool */
  private $constructionMode;

  private $catchAllEmail; ///< The fixed From: email address.
  private $catchAllName;  ///< The default From: name.

  private $initialTemplate;
  private $templateName;

  private $inReplyToId; ///< Message id of a to-be-replied message
  private $referencing; ///< Further related message ids

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

  /** @var ProjectParticipantFieldsService */
  private $participantFieldsService;

  /** @var ProgressStatusService */
  private $progressStatusService;

  /** @var SimpleSharingService */
  private $simpleSharingService;

  /** @var OrganizationalRolesService */
  private $organizationalRolesService;

  /** @var AppStorage */
  private $appStorage;

  /** @var UserStorage */
  private $userStorage;

  /** @var int */
  private $progressToken;

  /** @var array */
  private $substitutions;

  /**
   * @var array
   *
   * Template file-attachment without personalization, i.e. blank PDF forms.
   */
  private $templateFileAttachments = null;

  /**
   * @var array
   *
   * Personal file-attachments stemming form ProjectParticipantField
   * entities.
   */
  private $personalFileAttachments = null;

  /**
   * @var array
   *
   * "Global" file attachments from upload or the cloud file-system.
   */
  private $globalFileAttachments = null;

  /**
   * @var array
   *
   * Potential personal file-attachments deduced from parsing the personal
   * substitutions.
   */
  private $implicitFileAttachments = null;

  /*
   * constructor
   */
  public function __construct(
    ConfigService $configService
    , RequestParameterService $parameterService
    , EventsService $eventsService
    , RecipientsFilter $recipientsFilter
    , EntityManager $entityManager
    , ProjectParticipantFieldsService $participantFieldsService
    , ProgressStatusService $progressStatusService
    , SimpleSharingService $simpleSharingService
    , OrganizationalRolesService $organizationRolesService
    , AppStorage $appStorage
    , UserStorage $userStorage
  ) {
    $this->configService = $configService;
    $this->eventsService = $eventsService;
    $this->progressStatusService = $progressStatusService;
    $this->simpleSharingService = $simpleSharingService;
    $this->organizationalRolesService = $organizationRolesService;
    $this->appStorage = $appStorage;
    $this->userStorage = $userStorage;
    $this->entityManager = $entityManager;
    $this->participantFieldsService = $participantFieldsService;
    $this->l = $this->l10N();

    $this->constructionMode = $this->getConfigValue('emailtestmode') !== 'off';
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

    $this->projectId   = $this->cgiValue(
      'projectId', $this->parameterService->getParam('projectId', 0));
    $this->projectName = $this->cgiValue(
      'projectName', $this->parameterService->getParam('projectName', ''));
    if ($this->projectId > 0) {
      $this->project = $this->getDatabaseRepository(Entities\Project::class)
                            ->find($this->projectId);
    }

    $this->bulkTransactionId = $this->cgiValue(
      'bulkTransactionId', $this->parameterService->getParam('bulkTransactionId', 0));
    if ($this->bulkTransactionId > 0) {
      $this->bulkTransaction = $this->getDatabaseRepository(Entities\SepaBulkTransaction::class)
                                    ->find($this->bulkTransactionId);
      if (!empty($this->bulkTransaction) && empty($template)) {
        $bulkTransactionService = $this->di(SepaBulkTransactionService::class);
        $template = $bulkTransactionService->getBulkTransactionSlug($this->bulkTransaction);
        $template = $template . '-' . 'announcement';
        list($template,) = $this->normalizeTemplateName($template);
      }
    }

    if (!empty($template)) {
      $this->cgiData['storedMessagesSelector'] = $template;
    }

    $this->setSubjectTag();

    // First initialize defaults, will be overriden based on
    // form-submit data in $this->execute()
    $this->setDefaultTemplate();

    $this->draftId = $this->cgiValue('messageDraftId', 0);

    $this->inReplyToId = $this->cgiValue('inReplyTo', '');
    $this->referencing = $this->cgiValue('referencing', []);

    $this->progressToken = $this->cgiValue('progressToken');

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
      'FailedRecipients' => [],
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
        $this->templateName = $initialTemplate;
        if (!empty($template)) {
          $this->messageContents = $template->getContents();
        } else {
          $this->cgiData['subject'] = $this->l->t('Unknown Template');
        }
      }
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
    return preg_match('/([^$]|^)[$]{MEMBER::[^}]+}/', $message);
  }

  /**
   * Fill the $this->substitutions array.
   */
  private function generateSubstitutionHandlers()
  {
    $this->generateGlobalSubstitutionHandlers();

    foreach (self::MEMBER_VARIABLES as $key) {
      $this->substitutions[self::MEMBER_NAMESPACE][$key] = function(array $keyArg, ?Entities\Musician $musician) use ($key) {
        $field = Util::dashesToCamelCase(strtolower($key), false, '_');
        if (empty($musician) || !isset($musician[$field])) {
          return $keyArg[0];
        }
        return $musician[$field];
      };
    }

    $this->substitutions[self::MEMBER_NAMESPACE]['NICK_NAME'] = function(array $keyArg, ?Entities\Musician $musician) {
      if (empty($musician)) {
        return $keyArg[0];
      }
      return $musician->getNickName()?:$musician->getFirstName();
    };

    $this->substitutions[self::MEMBER_NAMESPACE]['DISPLAY_NAME'] = function(array $keyArg, ?Entities\Musician $musician) {
      if (empty($musician)) {
        return $keyArg[0];
      }
      return $musician->getPublicName(true); // rather firstName lastName than last, first
    };

    $this->substitutions[self::MEMBER_NAMESPACE]['COUNTRY'] = function(array $keyArg, ?Entities\Musician $musician) {
      if (empty($musician)) {
        return $keyArg[0];
      }
      $country = $musician->getCountry();
      if (empty($country)) {
        return '';
      }
      $language = substr($this->l->getLanguageCode(), 0, 2);
      $locale = $language.'_'.$country;
      return locale_get_display_region($locale, $language);
    };

    $languageNames = $this->localeLanguageNames();
    $this->substitutions[self::MEMBER_NAMESPACE]['LANGUAGE'] = function(array $keyArg, ?Entities\Musician $musician) use ($languageNames) {
      if (empty($musician)) {
        return $keyArg[0];
      }
      $language = $musician['language'];
      if (empty($language)) {
        return '';
      }
      return $languageNames[$language] ?? $language;
    };

    $this->substitutions[self::MEMBER_NAMESPACE]['BIRTHDAY'] = function(array $keyArg, ?Entities\Musician $musician) {
      if (empty($musician)) {
        return $keyArg[0];
      }
      $date = $musician->getBirthday();
      if (empty($date)) {
        return '';
      }
      $result = $this->formatDate($date, $keyArg[1]??'full');

      return $result;
    };

    $this->substitutions[self::MEMBER_NAMESPACE]['DATE'] =  function(array $keyArg, ?Entities\Musician $musician) {
      if (empty($musician)) {
        return $keyArg[0];
      }
      return $this->dateSubstitution($keyArg, self::MEMBER_NAMESPACE, $musician);
    };

    if (!empty($this->project)) {

      $this->substitutions[self::MEMBER_NAMESPACE]['TOTAL_FEES'] =  function(array $keyArg, ?Entities\Musician $musician) {
        if (empty($musician)) {
          return $keyArg[0];
        }
        $obligations = ProjectParticipantFieldsService::participantMonetaryObligations($musician, $this->project);
        return $this->moneyValue($obligations['sum']);
      };

      $this->substitutions[self::MEMBER_NAMESPACE]['AMOUNT_PAID'] =  function(array $keyArg, ?Entities\Musician $musician) {
        if (empty($musician)) {
          return $keyArg[0];
        }
        $obligations = ProjectParticipantFieldsService::participantMonetaryObligations($musician, $this->project);
        return $this->moneyValue($obligations['received']);
      };

      $this->substitutions[self::MEMBER_NAMESPACE]['MISSING_AMOUNT'] =  function(array $keyArg, ?Entities\Musician $musician) {
        if (empty($musician)) {
          return $keyArg[0];
        }
        $obligations = ProjectParticipantFieldsService::participantMonetaryObligations($musician, $this->project);
        return $this->moneyValue($obligations['sum'] - $obligations['received']);
      };

      // per-participant project-data
      $this->substitutions[self::MEMBER_NAMESPACE]['PROJECT_DATA'] =  function(array $keyArg, ?Entities\Musician $musician) {

        if (empty($musician) || count($keyArg) > 2) {
          return implode(':', $keyArg);
	}

        /** @var Entities\ProjectParticipant $projectParticipant */
        $projectParticipant = $musician->getProjectParticipantOf($this->project);

        $participantFields = $this->project->getParticipantFields();
        $fieldsByType = [
          'monetary' => $participantFields->filter(function($field) {
            /** @var Entities\ProjectParticipantField $field */
            return $field->getDataType() == FieldType::SERVICE_FEE;
          }),
          'deposit' => $participantFields->filter(function($field) {
            /** @var Entities\ProjectParticipantField $field */
            return $field->getDataType() == FieldType::SERVICE_FEE && !empty($field->getDepositDueDate());
          }),
          'files' => $participantFields->filter(function($field) {
            /** @var Entities\ProjectParticipantField $field */
            return ($field->getDataType() == FieldType::CLOUD_FILE
                    || $field->getDataType() == FieldType::DB_FILE
                    || $field->getDataType() == FieldType::CLOUD_FOLDER);
          }),
          'other' => $participantFields->filter(function($field) {
            /** @var Entities\ProjectParticipantField $field */
            return ($field->getDataType() != FieldType::SERVICE_FEE
                    && $field->getDataType() != FieldType::CLOUD_FILE
                    && $field->getDataType() != FieldType::DB_FILE
                    && $field->getDataType() != FieldType::CLOUD_FOLDER);
          }),
        ];

        $doSpecificField = false;
        $doSpecificType = false;

        if (count($keyArg) == 2) {
          $found = false;
          $selector = strtolower($keyArg[1]);

          $specificField = $participantFields->filter(function($field) use ($selector) {
            /** @var Entities\ProjectParticipantField $field */
            return strtolower($field->getName()) == $selector;
          });
          if ($specificField->count() == 1) {
            $doSpecificField = true;
            $found = true;
            switch ($specificField->first()->getDataType()) {
            case FieldType::SERVICE_FEE:
              $fieldsByType = ['monetary' => $specificField ];
              if (!empty($specificField->first()->getDepositDueDate())) {
                $fieldsByType['deposit'] = $specificField;
              }
              break;
            case FieldType::CLOUD_FILE:
            case FieldType::DB_FILE:
              $fieldsByType = [ 'file' => $specificField ];
              break;
            default:
              $fieldsByType = [ 'other' => $specificField ];
              break;
            }
          } else {
            foreach (array_keys($fieldsByType) as $type) {
              $variants = $this->translationVariants($type);
              if (array_search($selector, $variants) !== false) {
                $fieldsByType = [ $type => $fieldsByType[$type] ];
                $doSpecificType = true;
                $found = true;
                break;
              }
            }
          }
          if (!$found) {
            return $keyArg[0] . ':' . $keyArg[1];
          }
        }

        $html = '';
        foreach ($fieldsByType as $type => $fields) {

          // add implicit file attachments
          foreach ($fields as $field) {
            switch ($field->getDataType()) {
              case FieldType::SERVICE_FEE:
              case FieldType::CLOUD_FILE:
              case FieldType::DB_FILE:
              case FieldType::CLOUD_FOLDER:
                $this->implicitFileAttachments[] = 'participant-field' . ':' . $field->getId();
                break;
            }
          }

          $numberOfFields = $fields->count();

          // First output the simple options, then the ones with multiple
          // options. Also sort by name.
          $fieldsByMultiplicity = [
            // only one possible option
            'single' => (function($fields) {
              $fieldArray = $fields->filter(function($field) {
                /** @var Entities\ProjectParticipantField $field */
                return ($field->getMultiplicity() == FieldMultiplicity::SIMPLE
                        || $field->getMultiplicity() == FieldMultiplicity::SINGLE
                        || $field->getMultiplicity() == FieldMultiplicity::GROUPOFPEOPLE);
              })->toArray();
              usort($fieldArray, function($a, $b) {
                return strcmp($a->getName(), $b->getName());
              });
              return $fieldArray;
            })($fields),
            // possibly multiple options
            'multiple' => (function($fields) {
              $fieldArray = $fields->filter(function($field) {
                /** @var Entities\ProjectParticipantField $field */
                return ($field->getMultiplicity() == FieldMultiplicity::MULTIPLE
                        || $field->getMultiplicity() == FieldMultiplicity::PARALLEL
                        || $field->getMultiplicity() == FieldMultiplicity::RECURRING
                        || $field->getMultiplicity() == FieldMultiplicity::GROUPSOFPEOPLE);
              })->toArray();
              usort($fieldArray, function($a, $b) {
                return strcmp($a->getName(), $b->getName());
              });
              return $fieldArray;
            })($fields),
          ];

          // PLAN: table for monetary fields, perhaps file-downloads for file fields.

          /** @var IDateTimeFormatter $formatter */
          $formatter = $this->appContainer()->get(IDateTimeFormatter::class);

          if ($type == 'files' || $type == 'other') {
            $html .= '<ul class="participant-fields">';

            /** @var Entities\ProjectParticipantField $field */
            foreach ($fieldsByMultiplicity as $multiplicity => $fields) {
              foreach ($fields as $field) {
                /** @var Entities\ProjectParticipantFieldDataOption $fieldOption */
                $fieldOptions = $field->getSelectableOptions();
                if ($multiplicity == 'single') {
                  $fieldOption = $fieldOptions->first();
                  $fieldValue = '';
                  if (!empty($fieldOption)) {
                    $fieldDatum = $projectParticipant->getParticipantFieldsDatum($fieldOption->getKey());
                    $fieldValue = $this->participantFieldsService->printEffectiveFieldDatum($fieldDatum);
                  }
                  $html .= '<li>'
                    . $field->getName()
                    . ': '
                    . $fieldValue
                    . '</li>';
                } else {
                  $html .= '<li>' . $field->getName() . '<ul>';
                  foreach ($fieldOptions as $fieldOption) {
                    $fieldValue = '';
                    if (!empty($fieldOption)) {
                      $fieldDatum = $projectParticipant->getParticipantFieldsDatum($fieldOption->getKey());
                      $fieldValue = $this->participantFieldsService->printEffectiveFieldDatum($fieldDatum);
                    }
                    if (empty($fieldValue)) {
                      continue;
                    }
                    $html .= '<li>'
                      . $fieldValue
                      . '</li>';
                  }
                  $html .= '</ul></li>';
                }
              }
            }
            $html .= '</ul>';
          } else if ($type == 'monetary' || $type == 'deposit') {

            $headerReplacements = [
              'option' => $this->l->t('Option'),
              'totals' => $type == 'monetary' ? $this->l->t('Total Amount') : $this->l->t('Deposit'),
              'received' => $this->l->t('Received'),
              'remaining' => $this->l->t('Remaining'),
              'dueDate' => $this->l->t('Due Date'),
            ];
            $replacementKeys = array_keys($headerReplacements);
            $header = self::DEFAULT_HTML_TEMPLATES['monetary-fields']['header'];
            foreach ($headerReplacements as $key => $replacement) {
              $keyVariants = array_map(
                function($key) { return '['.$key.']'; },
                $this->translationVariants($key)
              );
              $header = str_ireplace($keyVariants, $replacement, $header);
            }
            $cssClass = implode(' ', [
              self::PARTICIPANT_MONETARY_FIELDS_CSS_CLASS['header'],
              'number-of-fields-'.$numberOfFields,
            ]);
            $header = str_replace('[CSSCLASS]', $cssClass, $header);
            $html .= /* self::DEFAULT_PARTICIPANT_MONETARY_FIELDS_STYLE . */ $header;

            $totalSum = array_fill_keys($replacementKeys, 0.0);
            $totalSum['label'] = $this->l->t('Total Amount');
            $totalSum['dueDate'] = null;

            $totalNumberOfOptions = 0;
            foreach ($fieldsByMultiplicity as $multiplicity => $fields) {
              /** @var Entities\ProjectParticipantField $field */
              foreach ($fields as $field) {

                if ($field->getMultiplicity() == FieldMultiplicity::RECURRING) {
                  /** @var IRecurringReceivablesGenerator $receivablesGenerator  */
                  $receivablesGenerator = $this->di(ReceivablesGeneratorFactory::class)->getGenerator($field);
                  $dueDate = $receivablesGenerator->dueDate();
                } else {
                  $dueDate = $type == 'monetary' ? $field->getDueDate() : $field->getDepositDueDate();
                }
                if (!empty($dueDate)) {
                  if (empty($totalSum['dueDate'])) {
                    $totalSum['dueDate']['min'] = $totalSum['dueDate']['max'] = $dueDate;
                  } else {
                    $totalSum['dueDate']['min'] = min($totalSum['dueDate']['min'], $dueDate);
                    $totalSum['dueDate']['max'] = max($totalSum['dueDate']['max'], $dueDate);
                  }

                  $dueDate = $dueDate
                           ? $formatter->formatDate($dueDate, 'medium')
                           : '';
                }

                $numberOfOptions = $field->getSelectableOptions()->count();
                $totalNumberOfOptions += $numberOfOptions;

                // generate a field-header for multiple options
                $replacements = [
                  'field-name' => $field->getName(),
                  'dueDate' => $dueDate,
                ];

                $fieldHeader = self::DEFAULT_HTML_TEMPLATES['monetary-fields']['fieldHeader'];
                foreach ($replacements as $key => $replacement) {
                  $keyVariants = array_map(
                    function($key) { return '['.$key.']'; },
                    $this->translationVariants($key)
                  );
                  $fieldHeader = str_ireplace($keyVariants, $replacement, $fieldHeader);
                }
                $cssClass = implode(' ', [
                  self::PARTICIPANT_MONETARY_FIELDS_CSS_CLASS['fieldHeader'],
                  'number-of-options-' . $numberOfOptions,
                ]);
                $cssRowClass = implode(' ', [
                  self::PARTICIPANT_MONETARY_FIELDS_CSS_CLASS['row'],
                  'number-of-options-'.$numberOfOptions,
                ]);
                $fieldHeader = str_replace(
                  [ '[CSSCLASS]', '[CSSROWCLASS]' ], [ $cssClass, $cssRowClass ], $fieldHeader);
                $html .= $fieldHeader;

                /** @var Entities\ProjectParticipantFieldDataOption $fieldOption */
                foreach ($field->getSelectableOptions() as $fieldOption) {

                  if ($field->getMultiplicity() == FieldMultiplicity::RECURRING) {
                    $receivableDueDate = $receivablesGenerator->dueDate($fieldOption) ?? '';
                    if (!empty($receivableDueDate)) {
                      $receivableDueDate = $formatter->formatDate($receivableDueDate, 'medium');
                    }
                  } else {
                    $receivableDueDate = '';
                  }

                  $option = $fieldOption->getLabel() ?: $field->getName();

                  $fieldData = $projectParticipant->getParticipantFieldsDatum($fieldOption->getKey());

                  $totals = '--';
                  $received = '--';
                  $remaining = '--';
                  $memberNames = [];

                  if (!empty($fieldData)) {
                    switch ($type) {
                    case 'monetary':
                      $totals = $fieldData->amountPayable();
                      $received = $fieldData->amountPaid();
                      break;
                    case 'deposit':
                      $totals = $fieldData->depositAmount();
                      $received = min($totals, $fieldData->amountPaid());
                      break;
                    }
                    $remaining = $totals - $received;

                    if ($field->getMultiplicity() == FieldMultiplicity::GROUPOFPEOPLE
                        || $field->getMultiplicity() == FieldMultiplicity::GROUPSOFPEOPLE) {
                      $groupMembers = $this->participantFieldsService->findGroupMembersOf($fieldData);
                      /** @var Entities\ProjectParticipantFieldDatum $groupMember */
                      foreach ($groupMembers as $groupMember) {
                        $memberNames[] = $groupMember->getMusician()->getPublicName(true);
                      }
                    }
                  } else if ($field->getMultiplicity() == FieldMultiplicity::GROUPOFPEOPLE) {
                    if ($fieldOption != $field->getSelectableOptions()->last()) {
                      // make sure we only include the option the participant has booked.
                      continue;
                    }
                  }

                  // compute substitution values
                  $replacements = [];
		  $nonZeroData = false;
                  foreach ($replacementKeys as $key) {
                    if ($key == 'option') {
                      $replacements[$key] = ${$key};
                      continue;
                    }
                    if ($key == 'dueDate' ) {
                      $replacements[$key] = $receivableDueDate ?? '';
                      continue;
                    }
                    if (${$key} != '--') {
		      $nonZeroData = $nonZeroData || !empty(${$key});
                      $totalSum[$key] += ${$key};
                      $replacements[$key] = $this->moneyValue(${$key});
                    } else {
                      $replacements[$key] = ${$key};
                    }
                  }

		  if (!$nonZeroData) {
		    continue;
		  }

                  // inject into template
                  $row = self::DEFAULT_HTML_TEMPLATES['monetary-fields']['row'];
                  foreach ($replacementKeys as $key) {
                    $keyVariants = array_map(
                      function($key) { return '['.$key.']'; },
                      $this->translationVariants($key)
                    );
                    $row = str_ireplace($keyVariants, $replacements[$key], $row);
                  }
                  $cssClass = $cssRowClass;
                  if (!empty($memberNames)) {
                    $cssClass .= ' has-data';
                    $rowData = [ $cssClass, $this->l->t('members'), implode(', ', $memberNames), ];
                  } else {
                    $rowData = [ $cssClass, '', '', ];
                  }
                  $rowKeys = [ '[CSSROWCLASS]', '[ROWDATALABEL]', '[ROWDATACONTENTS]',  ];
                  $row = str_replace($rowKeys, $rowData, $row);
                  $html .= $row;
                }
              }
            }

            $footer = self::DEFAULT_HTML_TEMPLATES['monetary-fields']['footer'];
            foreach ($replacementKeys as $key) {
              switch ($key) {
              case 'option':
                $key = 'label';
                break;
              case 'dueDate':
                if (!empty($totalSum['dueDate'])) {
                  $minDate = $formatter->formatDate($totalSum['dueDate']['min'], 'short');
                  $maxDate = $formatter->formatDate($totalSum['dueDate']['max'], 'short');
                  if (true || $minDate == $maxDate) {
                    $totalSum['dueDate'] = $formatter->formatDate($totalSum['dueDate']['max'], 'medium');
                  } else {
                    $totalSum['dueDate'] = $minDate . ' - ' . $maxDate;
                  }
                }
                break;
              default:
                $totalSum[$key] = $this->moneyValue($totalSum[$key]);
                break;
              }
              $keyVariants = array_map(
                function($key) { return '['.$key.']'; },
                $this->translationVariants($key)
              );
              $footer = str_ireplace($keyVariants, $totalSum[$key], $footer);
            }
            $cssClass = implode(' ', [
              self::PARTICIPANT_MONETARY_FIELDS_CSS_CLASS['footer'],
              'total-number-of-options-' . $totalNumberOfOptions,
            ]);
            $footer = str_replace('[CSSCLASS]', $cssClass, $footer);
            $html .= $footer;
          }
          if ($numberOfFields > 0 && !$doSpecificType && !$doSpecificField) {
            $html .= '<p>';
          }
        }

        return $html;
      };

    }

    if (!empty($this->bulkTransaction)) {

      $this->substitutions[self::MEMBER_NAMESPACE]['BANK_TRANSACTION_AMOUNT'] = function(array $keyArg, ?Entities\Musician $musician) {
        if (empty($musician)) {
          return $keyArg[0];
        }

        /** @var Entities\CompositePayment $compositePayment */
        $compositePayment = $this->bulkTransaction->getPayments()->get($musician->getId());
        if (!empty($compositePayment)) {
          $amount = $compositePayment->getAmount();
          return $this->moneyValue($amount);
        }

        return $keyArg[0];
      };

      $this->substitutions[self::MEMBER_NAMESPACE]['BANK_TRANSACTION_PURPOSE'] = function(array $keyArg, ?Entities\Musician $musician) {
        if (empty($musician)) {
          return $keyArg[0];
        }

        /** @var Entities\CompositePayment $compositePayment */
        $compositePayment = $this->bulkTransaction->getPayments()->get($musician->getId());
        if (!empty($compositePayment)) {
          return $compositePayment->getSubject();
        }

        return $keyArg[0];
      };

      $this->substitutions[self::MEMBER_NAMESPACE]['SEPA_MANDATE_REFERENCE'] = function(array $keyArg, ?Entities\Musician $musician) {
        if (empty($musician)) {
          return $keyArg[0];
        }

        /** @var Entities\CompositePayment $compositePayment */
        $compositePayment = $this->bulkTransaction->getPayments()->get($musician->getId());
        if (!empty($compositePayment)) {
          /** @var Entities\SepaDebitMandate $debitMandate */
          $debitMandate = $compositePayment->getSepaDebitMandate();
          if (!empty($debitMandate)) {
            return $debitMandate->getMandateReference();
          }
        }

        return $keyArg[0];
      };

      $this->substitutions[self::MEMBER_NAMESPACE]['BANK_ACCOUNT_IBAN'] = function(array $keyArg, ?Entities\Musician $musician) {
        if (empty($musician)) {
          return $keyArg[0];
        }

        /** @var Entities\CompositePayment $compositePayment */
        $compositePayment = $this->bulkTransaction->getPayments()->get($musician->getId());
        if (!empty($compositePayment)) {
          /** @var Entities\SepaBankAccount $bankAccount */
          $bankAccount = $compositePayment->getSepaBankAccount();
          if (!empty($bankAccount)) {
            return $bankAccount->getIban();
          }
        }

        return $keyArg[0];
      };

      $this->substitutions[self::MEMBER_NAMESPACE]['BANK_ACCOUNT_BIC'] = function(array $keyArg, ?Entities\Musician $musician) {
        if (empty($musician)) {
          return $keyArg[0];
        }

        /** @var Entities\CompositePayment $compositePayment */
        $compositePayment = $this->bulkTransaction->getPayments()->get($musician->getId());
        if (!empty($compositePayment)) {
          /** @var Entities\SepaBankAccount $bankAccount */
          $bankAccount = $compositePayment->getSepaBankAccount();
          if (!empty($bankAccount)) {
            return $bankAccount->getBic();
          }
        }

        return $keyArg[0];
      };

      $this->substitutions[self::MEMBER_NAMESPACE]['BANK_ACCOUNT_OWNER'] = function(array $keyArg, ?Entities\Musician $musician) {
        if (empty($musician)) {
          return $keyArg[0];
        }

        /** @var Entities\CompositePayment $compositePayment */
        $compositePayment = $this->bulkTransaction->getPayments()->get($musician->getId());
        if (!empty($compositePayment)) {
          /** @var Entities\SepaBankAccount $bankAccount */
          $bankAccount = $compositePayment->getSepaBankAccount();
          if (!empty($bankAccount)) {
            return $bankAccount->getBankAccountOwner();
          }
        }

        return $keyArg[0];
      };

      $this->substitutions[self::MEMBER_NAMESPACE]['BANK_TRANSACTION_PARTS'] = function(array $keyArg, ?Entities\Musician $musician) {
        if (empty($musician)) {
          return $keyArg[0];
        }

        /** @var Entities\CompositePayment $compositePayment */
        $compositePayment = $this->bulkTransaction->getPayments()->get($musician->getId());
        if (!empty($compositePayment)) {

          $keyArg = array_map(
            function($value) {
              return html_entity_decode($value, ENT_HTML5, 'UTF-8');
            },
            $keyArg);

          $tableTemplate = [
            'header' => $keyArg[1]?:self::DEFAULT_HTML_TEMPLATES['transaction-parts']['header'],
            'row' => $keyArg[2]?:self::DEFAULT_HTML_TEMPLATES['transaction-parts']['row'],
            'footer' => $keyArg[3]?:self::DEFAULT_HTML_TEMPLATES['transaction-parts']['footer'],
          ];

          $replacementKeys = [ 'purpose', 'invoiced', 'totals', 'received', 'remaining' ];
          $totalSum = array_fill_keys($replacementKeys, 0.0);
          $totalSum['purpose'] = $this->l->t('Total Amount');

          $html = ''; /* self::DEFAULT_TRANSACTION_PARTS_STYLE; */

          $headerReplacements = [
            'purpose' => $this->l->t('Purpose'),
            'invoiced' => $this->l->t('Invoice Amount'),
            'totals' => $this->l->t('Total Amount'),
            'received' => $this->l->t('Received'),
            'remaining' => $this->l->t('Remaining'),
          ];
          $header = $tableTemplate['header'];
          foreach ($replacementKeys as $key) {
            $keyVariants = array_map(
              function($key) { return '['.$key.']'; },
              $this->translationVariants($key)
            );
            $header = str_ireplace($keyVariants, $headerReplacements[$key], $header);
          }
          $html .= $header;

          $rowTemplate = $tableTemplate['row'];

          $payments = $compositePayment->getProjectPayments();
          /** @var Entities\ProjectPayment $payment */
          foreach ($payments as $payment) {
            $invoiced = $payment->getAmount();

            $totals = $payment->getReceivable()->amountPayable();
            $received = $payment->getReceivable()->amountPaid();

            // otherwise one would have to account for the dueDate,
            // so keep it simple and just remove the current payment.
            $received -= $invoiced;

            $remaining = $totals - $received;

            $purpose = $payment->getSubject();

            $replacements = [];
            foreach ($replacementKeys as $key) {
              if ($key == 'purpose') {
                $replacements[$key] = ${$key};
                continue;
              }
              $totalSum[$key] += ${$key};
              $replacements[$key] = $this->moneyValue(${$key});
            }

            $row = $rowTemplate;
            foreach ($replacementKeys as $key) {
              $keyVariants = array_map(
                function($key) { return '['.$key.']'; },
                $this->translationVariants($key)
              );
              $row = str_ireplace($keyVariants, $replacements[$key], $row);
            }
            $html .= $row;
          }

          $footer = $tableTemplate['footer'];
          foreach ($replacementKeys as $key) {
            if ($key != 'purpose') {
              $totalSum[$key] = $this->moneyValue($totalSum[$key]);
            }
            $keyVariants = array_map(
              function($key) { return '['.$key.']'; },
              $this->translationVariants($key)
            );
            $footer = str_ireplace($keyVariants, $totalSum[$key], $footer);
          }
          $html .= $footer;

          return $html;
        }

        return $keyArg[0];
      };

    } // bulk-transaction fields

    // Generate localized variable names
    foreach ($this->substitutions as $nameSpace => $replacements) {
      foreach ($replacements as $key => $handler) {
        $this->substitutions[$nameSpace][$this->l->t($key)] = function(array $keyArg, ?Entities\Musician $musician) use ($handler, $key) { $keyArg[0] = $key; return $handler($keyArg, $musician); };
      }
    }
  }

  /**
   * Return true if this email needs per-namespace
   * substitutions. Substitutions have the form
   * ```
   * ${NAMESPACE::VARIABLE}
   * ```
   * For example ${MEMBER::FIRST_NAME}.
   *
   */
  private function hasSubstitutionNamespace($nameSpace, $message = null)
  {
    if (empty($message)) {
      $message = $this->messageContents;
    }

    return preg_match('/([^$]|^)[$]{('.$nameSpace.'|'.$this->l->t($nameSpace).')(.)\3(.*?)(?<!\\\)}/u', $message);
  }

  /**
   * Replace all variables of the given namespace in
   * $this->messageContents.
   *
   * @param string $nameSpace The variable prefix, e.g. MEMBER or one
   * of its translations.
   *
   * @param mixed $data Context dependent data, likely Entities\Musician.
   *
   * @param null|string $message
   *
   * @param null|array $failures Optional failure array. If null then
   * the method will throw an exception on errror.
   *
   * @return string Substituted message
   * @throw Exceptions\SubstitutionException
   */
  private function replaceFormVariables(string $nameSpace, $data = null, ?string $message = null, ?array &$failures = null):string
  {
    if (empty($message)) {
      $message = $this->messageContents;
    }

    if (empty($this->substitutions)) {
      $this->generateSubstitutionHandlers();
    }

    $regexp = '/([^$]|^)[$]{('.$nameSpace.'|'.$this->l->t($nameSpace).')(.)\3(.*?)(?<!\\\\)}/u';
    return preg_replace_callback(
      $regexp,
      function($matches) use ($data, &$failures) {
        $prefix = $matches[1]; // in order not to substitute $$
        $nameSpace = html_entity_decode($matches[2], ENT_HTML5, 'UTF-8');
        $separator = $matches[3];
        $variable  = array_map(function($value) {
          return preg_replace('/\\\\(.)/u', '$1', html_entity_decode($value, ENT_HTML5, 'UTF-8'));
        }, explode($separator, $matches[4]));
        $handler = $this->substitutions[$nameSpace][$variable[0]]??null;
        if (empty($handler) || !is_callable($handler)) {
          if (!is_array($failures)) {
            throw new Exceptions\SubstitutionException($this->l->t('No substitution handler found for "%s%s%s".', [ $nameSpace, $separator.$separator, $variable[0] ]));
          } else {
            $failures[] = [
              'namespace' => $nameSpace,
              'variable' => $variable,
              'error' => 'unknown',
            ];
          }
          return '';
        }
        try {
          return $prefix.call_user_func($handler, $variable, $data);
        } catch (\Throwable $t) {
          if (!is_array($failures)) {
            throw $t;
          }
          $this->logException($t);
          $failures[] = [
            'namespace' => $nameSpace,
            'variable' => $variable,
            'error' => 'substitution',
            'exception' => $t->getMessage(),
          ];
        }
        return ''; // replace with empty string in case of error
      },
      $message);
  }

  /**
   * Cleanup edge-cases. ATM this only replaces left-over '$$'
   * occurences with a single $.
   *
   * @return string
   */
  private function finalizeSubstitutions($message = null)
  {
    if (empty($message)) {
      $message = $this->messageContents;
    }

    return str_replace('$$', '$', $message);
  }

  public function discloseRecipients()
  {
    return $this->projectId >= 0
      && (
        filter_var(
          $this->cgiValue('disclosedRecipients', 'off'),
          FILTER_VALIDATE_BOOLEAN,
          FILTER_NULL_ON_FAILURE)
        ??false);
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

  static private function emitHtmlBodyStyle($style, $cssPrefix = null)
  {
    return str_replace('[CSSPREFIX]', empty($cssPrefix) ? '' : ($cssPrefix . ' '), $style);
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
    $messageTemplate = implode("\n", array_map([ $this, 'emitHtmlBodyStyle' ], self::DEFAULT_HTML_STYLES))
                     . $this->replaceFormVariables(self::GLOBAL_NAMESPACE);

    $hasPersonalSubstitutions = $this->hasSubstitutionNamespace(self::MEMBER_NAMESPACE, $messageTemplate);
    $hasPersonalAttachments = $this->activePersonalAttachments() > 0;

    $references = [];
    $templateMessageId = $this->getOutboundService()->generateMessageId();

    if ($hasPersonalSubstitutions || $hasPersonalAttachments) {

      $this->logInfo(
        'Sending separately because of personal substitutions / attachments '
        . (int)$hasPersonalSubstitutions
        . ' / '
        . (int)$hasPersonalAttachments);

      $this->diagnostics['TotalPayload'] = count($this->recipients)+1;

      foreach ($this->recipients as $recipient) {
        ++$this->diagnostics['TotalCount'];

        /** @var Entities\Musician $musician */
        $musician = $recipient['dbdata'];

        $this->implicitFileAttachments = [];
        $strMessage = $this->replaceFormVariables(self::MEMBER_NAMESPACE, $musician, $messageTemplate);
        $strMessage = $this->finalizeSubstitutions($strMessage);

        $this->implicitFileAttachments = array_values(array_unique($this->implicitFileAttachments));
        if (!empty($this->implicitFileAttachments)) {
          $this->personalFileAttachments = null; // have to void it ...
          $this->cgiData['attachedFiles'] = array_values(
            array_unique(
              array_merge(
                $this->cgiData['attachedFiles']??[],
                $this->implicitFileAttachments??[]
              )));
        }
        $personalAttachments = $this->composePersonalAttachments($musician);

        // we should not send the message out if the generation of the
        // personal attachment has failed.
        if (!empty($this->diagnostics['AttachmentValidation']['Personal'][$musician->getId()])) {
          ++$this->diagnostics['FailedCount'];
          continue;
        }

        $msg = $this->composeAndSend(
          $strMessage, [ $recipient ], $personalAttachments,
          addCC: false,
          references: $templateMessageId
        );
        if (!empty($msg['message'])) {
          $this->copyToSentFolder($msg['message']);
          $references[] = $msg['messageId'];

          // Don't remember the individual emails, but for
          // debit-mandates record the message id, ignore errors.

          // BIG FAT TODO
          // if ($this->bulkTransactionId > 0 && $dbdata['PaymentId'] > 0) {
          //   $messageId = $msg['messageId'];
          //   $where =  '`Id` = '.$dbdata['PaymentId'].' AND `BulkTransactionId` = '.$this->bulkTransactionId;
          //   mySQL::update('ProjectPayments', $where, [ 'DebitMessageId' => $messageId ], $this->dbh);
          // }

        } else {
          ++$this->diagnostics['FailedCount'];
        }
      }

      // Finally send one message without template substitution (as
      // this makes no sense) to all Cc:, Bcc: recipients and the
      // catch-all. This Message also gets copied to the Sent-folder
      // on the imap server.
      ++$this->diagnostics['TotalCount'];
      $mimeMsg = $this->composeAndSend(
        $messageTemplate, [],
        addCC: true,
        messageId: $templateMessageId,
        references: $references
      );
      if (!empty($mimeMsg['message'])) {
        $this->copyToSentFolder($mimeMsg['message']);
        $this->recordMessageDiagnostics($mimeMsg['message']);
      } else {
        ++$this->diagnostics['FailedCount'];
      }
    } else {
      $this->diagnostics['TotalPayload'] = 1;
      ++$this->diagnostics['TotalCount']; // this is ONE then ...
      $mimeMsg = $this->composeAndSend($messageTemplate, $this->recipients);
      if (!empty($mimeMsg['message'])) {
        $this->copyToSentFolder($mimeMsg['message']);
        $this->recordMessageDiagnostics($mimeMsg['message']);
      } else {
        ++$this->diagnostics['FailedCount'];
      }
    }

    try {
      $this->flush();
    } catch (\Throwable $t) {
      $this->logException($t);
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
   * Compose all personal attachments for the given musician.
   */
  private function composePersonalAttachments(Entities\Musician $musician):array
  {
    // in order to form file-names withtout dots
    $userIdSlug = $musician->getUserIdSlug();
    $camelCaseSlug = Util::dashesToCamelCase($userIdSlug, true, '_-.');

    $personalAttachments = [];

    $this->diagnostics['AttachmentValidation']['Personal'][$musician->getId()] = [];

    // Find payments and potential attachments. Always attached a pre-filled
    // member-data update-form
    if (!empty($this->bulkTransaction)) {
      /** @var Entities\CompositePayment $compositePayment */
      $compositePayment = $this->bulkTransaction->getPayments()->get($musician->getId());
      if (!empty($compositePayment)) {
        $supportingDocument = $compositePayment->getSupportingDocument();
        if (!empty($supportingDocument)) {
          $personalAttachments[] = function() use ($supportingDocument) {
            return [
              'data' => $supportingDocument->getFileData()->getData(),
              'fileName' => $supportingDocument->getFileName(),
              'encoding' => 'base64',
              'mimeType' => $supportingDocument->getMimeType(),
            ];
          };
        }
        /** @var Entities\ProjectPayment $projectPayment */
        foreach ($compositePayment->getProjectPayments() as $projectPayment) {
          $supportingDocument = $projectPayment->getReceivable()->getSupportingDocument();
          if (!empty($supportingDocument)) {
            $personalAttachments[] = function() use ($supportingDocument) {
              return [
                'data' => $supportingDocument->getFileData()->getData(),
                'fileName' => $supportingDocument->getFileName(),
                'encoding' => 'base64',
                'mimeType' => $supportingDocument->getMimeType(),
              ];
            };
          }
        }
      }
    }

    foreach ($this->personalAttachments() as $attachment) {
      if ($attachment['status'] != 'selected' && !$attachment['sub_selection']) {
        continue;
      }

      if (isset($attachment['template_id'])) {
        $templateId = $attachment['template_id'];
        if (!empty($this->project)
            && $this->project->getId() == $this->getClubMembersProjectId()
            && $templateId == ConfigService::DOCUMENT_TEMPLATE_PROJECT_DEBIT_NOTE_MANDATE) {
          $templateId = ConfigService::DOCUMENT_TEMPLATE_GENERAL_DEBIT_NOTE_MANDATE;
        }

        /** @var FinanceService $financeService */
        $financeService = $this->di(FinanceService::class);
        switch ($templateId) {
          case ConfigService::DOCUMENT_TEMPLATE_GENERAL_DEBIT_NOTE_MANDATE:
            $membersProject = $this->entityManager->find(
              Entities\Project::class,
              $this->getClubMembersProjectId()
            );
            if (empty($membersProject)) {
              continue 2;
            }
            /**@var Entities\ProjectParticipant $participant */
            $participant = $this->entityManager->find(Entities\ProjectParticipant::class, [
              'musician' => $musician,
              'project' => $membersProject,
            ]);
            if (!empty($participant)) {
              $bankAccount = $participant->getSepaBankAccount();
            }
            if (empty($bankAccount)) {
              $bankAccount = $musician->getSepaBankAccounts()->first();
            }

            $personalAttachments[] = function() use ($financeService, $bankAccount, $membersProject, $musician, $templateId) {
              list($fileData, $mimeType, $fileName) =
                $financeService->preFilledDebitMandateForm(
                  $bankAccount,
                  $membersProject,
                  $musician,
                  formName: $templateId
                );
              return [
                'data' => $fileData,
                'fileName' => $fileName,
                'encoding' => 'base64',
                'mimeType' => $mimeType,
              ];
            };
            break;
          case ConfigService::DOCUMENT_TEMPLATE_MEMBER_DATA_UPDATE:
            $membersProject = $this->entityManager->find(
              Entities\Project::class,
              $this->getClubMembersProjectId()
            );
            if (empty($membersProject)) {
              continue 2;
            }
            /**@var Entities\ProjectParticipant $participant */
            $participant = $this->entityManager->find(Entities\ProjectParticipant::class, [
              'musician' => $musician,
              'project' => $membersProject,
            ]);
            if (!empty($participant)) {
              $bankAccount = $participant->getSepaBankAccount();
            }
            if (empty($bankAccount)) {
              $bankAccount = $musician->getSepaBankAccounts()->first();
            }

            $personalAttachments[] = function() use ($financeService, $bankAccount, $membersProject, $musician, $templateId) {
              list($fileData, $mimeType, $fileName) =
                $financeService->preFilledDebitMandateForm(
                  $bankAccount,
                  $membersProject,
                  $musician,
                  formName: $templateId
                );
              return [
                'data' => $fileData,
                'fileName' => $fileName,
                'encoding' => 'base64',
                'mimeType' => $mimeType,
              ];
            };
            break;
          case ConfigService::DOCUMENT_TEMPLATE_PROJECT_DEBIT_NOTE_MANDATE:
            if (empty($this->project)) {
              continue 2;
            }
            /**@var Entities\ProjectParticipant $participant */
            $participant = $this->entityManager->find(Entities\ProjectParticipant::class, [
              'musician' => $musician,
              'project' => $this->project,
            ]);
            if (!empty($participant)) {
              $bankAccount = $participant->getSepaBankAccount();
            }
            if (empty($bankAccount)) {
              $bankAccount = $musician->getSepaBankAccounts()->first();
            }

            $personalAttachments[] = function() use ($financeService, $bankAccount, $musician, $templateId) {
              list($fileData, $mimeType, $fileName) =
                $financeService->preFilledDebitMandateForm(
                  $bankAccount,
                  $this->project,
                  $musician,
                  formName: $templateId
                );
              return [
                'data' => $fileData,
                'fileName' => $fileName,
                'encoding' => 'base64',
                'mimeType' => $mimeType,
              ];
            };
            break;
          case ConfigService::DOCUMENT_TEMPLATE_INSTRUMENT_INSURANCE_RECORD:
            /** @var InstrumentInsuranceService $insuranceService */
            $insuranceService = $this->di(InstrumentInsuranceService::class);
            $insuranceOverview = $insuranceService->musicianOverview($musician);

            if (empty($insuranceOverview['musicians'])) {
              // just skip
              continue 2;
            }

            $personalAttachments[] = function() use ($insuranceService, $insuranceOverview) {
              try {
                $fileData = $insuranceService->musicianOverviewLetter($insuranceOverview);
                $mimeType = 'application/pdf';
              } catch (\Throwable $t) {
                $this->logException($t);
                $fileData = $t->getMessage() . ' / ' . $t->getTraceAsString();
                $mimeType = 'text/plain';
              }
              $fileName = $insuranceService->musicianOverviewFileName($insuranceOverview);
              return [
                'data' => $fileData,
                'fileName' => $fileName,
                'encoding' => 'base64',
                'mimeType' => $mimeType,
              ];
            };
            break;
        }
        continue;
      }

      /** @var Entities\ProjectParticipantField $field */
      $field = $this->entityManager->find(Entities\ProjectParticipantField::class, $attachment['field_id']);
      if (empty($field)) {
        $this->logError('Unable to find attachment field "%s".', $fieldId);
        $this->diagnostics['AttachmentValidation']['Personal'][$musician->getId()]['Fields'][] = $attachment;
        $attachmentFailure = true;
        continue;
      }

      $fieldName = $field->getName();
      $fieldType = $field->getDataType();
      $fieldMultiplicity = $field->getMultiplicity();

      switch ($fieldType) {
        case FieldType::CLOUD_FILE:
        case FieldType::CLOUD_FOLDER:
        case FieldType::DB_FILE:
        case FieldType::SERVICE_FEE:
          break;
        default:
          $this->logError(sprintf('Cannot attach field "%s" of type "%s".', $fieldName, $fieldType));
          $this->diagnostics['AttachmentValidation']['Personal'][$musician->getId()]['Fields'][] = $attachment;
          $attachmentFailure = true;
          continue 2;
      }

      /** @var Collection $fieldData */
      $fieldData = $musician->getProjectParticipantFieldsData()
        ->matching(DBUtil::criteriaWhere([ 'field' => $field, 'deleted' => null ]));

      if ($fieldData->isEmpty()) {
        // Fields are optional, perhaps one could add a "required" field qualifier ...
        continue;
      }

      if ($fieldData->count() > 1 && $fieldMultiplicity == FieldMultiplicity::SIMPLE) {
        $this->logError(sprintf('More than one data-item for field "%s" of type "%s" with multiplicity "%s".', $fieldName, $fieldType, $fieldMultiplicity));
        $this->diagnostics['AttachmentValidation']['Personal'][$musician->getId()]['Fields'][] = $attachment;
        $attachmentFailure = true;
        continue;
      }

      switch ($fieldType) {
        case FieldType::SERVICE_FEE:
          // ATM we support sub-selection item only for FieldMultiplicity::RECURRING
          if ($attachment['status'] != 'selected') {
            $selectedKeys = [];
            foreach ($attachment['sub_options'] as $subOption) {
              if ($subOption['status'] == 'selected') {
                $selectedKeys[] = $subOption['option_key'];
              }
            }
            $fieldData = $fieldData->filter(function(Entities\ProjectParticipantFieldDatum $fieldDatum) use ($selectedKeys) {
              return in_array((string)$fieldDatum->getOptionKey(), $selectedKeys);
            });
          }
          if ($fieldData->isEmpty()) {
            // ok, field-data is optional
            continue 2;
          }
          /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
          if ($fieldData->count() == 1) {
            // attach file
            $fieldDatum = $fieldData->first();
            $supportingDocument = $fieldDatum->getSupportingDocument();
            if (!empty($supportingDocument)) {
              $personalAttachments[] = function() use ($supportingDocument) {
                return [
                  'data' => $supportingDocument->getFileData()->getData(),
                  'fileName' => $supportingDocument->getFileName(),
                  'encoding' => 'base64',
                  'mimeType' => $supportingDocument->getMimeType(),
                ];
              };
            }
          } else {
            // attach zip-archive
            $items = [];
            foreach ($fieldData as $fieldDatum) {
              /** @var Entities\File $file */
              $items[] = $fieldDatum->getSupportingDocument();
            }
            $folderName = $fieldName . '-' . $camelCaseSlug;

            $personalAttachments[] = function() use ($folderName, $items) {
              return [
                'data' => $this->di(DatabaseStorageUtil::class)->getCollectionArchive($items, $folderName),
                'fileName' => $folderName . '.zip',
                'encoding' => 'base64',
                'mimeType' => 'application/zip',
              ];
            };
          }
          break;
        case FieldType::CLOUD_FILE:
          // can be a single file or multiple files with FieldMultiplicity::PARALLEL
          if ($fieldData->count() == 1) {
            $fieldDatum = $fieldData->first();
            /** @var \OCP\Files\File $file */
            $file = $this->participantFieldsService->getEffectiveFieldDatum($fieldDatum);

            $fileName = str_replace(
              $userIdSlug, $camelCaseSlug,
              str_replace(
                UserStorage::PATH_SEP, '_',
                  substr(UserStorage::stripParents($file->getPath(), self::PERSONAL_ATTACHMENT_PARENTS_STRIP), 1)
              )
            );

            $personalAttachments[] = function() use ($file, $fileName) {
              return [
                'data' => $file->getContent(),
                'fileName' => $fileName,
                'encoding' => 'base64',
                'mimeType' => $file->getMimeType(),
              ];
            };
          } else {
            $folderPath = $this->participantFieldsService->getFieldFolderPath($fieldData->first());
            $folder = $this->userStorage->getFolder($folderPath);

            // _claus_files_camerata_projects_1997_Vereinsmitglieder_participants_claus-justus.heine_MultiCloudFile.zip
            $fileName = str_replace(
              $userIdSlug, $camelCaseSlug,
              str_replace(
                UserStorage::PATH_SEP, '_',
                substr(UserStorage::stripParents($folder->getPath(), self::PERSONAL_ATTACHMENT_PARENTS_STRIP), 1)
              )
            );
            $fileName .= '.zip';

            $personalAttachments[] = function() use ($folder, $fileName) {
              $data = $this->userStorage->getFolderArchive($folder, self::PERSONAL_ATTACHMENT_PARENTS_STRIP);
              return [
                'data' => $data,
                'fileName' => $fileName,
                'encoding' => 'base64',
                'mimeType' => 'application/zip',
              ];
            };
          }
          break;
        case FieldType::CLOUD_FOLDER:
          // simply add the folder as a zip-archive
          $fieldDatum = $fieldData->first();
          /** @var OCP\Files\Folder $folder */
          $folder = $this->participantFieldsService->getEffectiveFieldDatum($fieldDatum);
          if (!empty($folder)) {
            // _claus_files_camerata_projects_1997_Vereinsmitglieder_participants_claus-justus.heine_Anlagen Versicherung.zip
            $fileName = str_replace(
              $userIdSlug, $camelCaseSlug,
              str_replace(
                UserStorage::PATH_SEP, '_',
                substr(UserStorage::stripParents($folder->getPath(), self::PERSONAL_ATTACHMENT_PARENTS_STRIP), 1)
              )
            );
            $fileName .= '.zip';
            $personalAttachments[] = function() use ($folder, $fileName) {
              $data = $this->userStorage->getFolderArchive($folder, self::PERSONAL_ATTACHMENT_PARENTS_STRIP);
              return [
                'data' => $data,
                'fileName' => $fileName,
                'encoding' => 'base64',
                'mimeType' => 'application/zip',
              ];
            };
          }
          break;
        case FieldType::DB_FILE:
          // can be a single file or multiple files with FieldMultiplicity::PARALLEL or FieldMultiplicity::RECURRING
          $items = [];
          foreach ($fieldData as $fieldDatum) {
            /** @var Entities\File $file */
            $items[] = $this->participantFieldsService->getEffectiveFieldDatum($fieldDatum);
          }
 	  $items = array_values(array_filter($items)); // fields maybe empty ...
          if (count($items) == 1) {
            /** @var Entities\File $file */
            $file = array_shift($items);
            $personalAttachments[] = function() use($file) {
              return [
                'data' => $file->getFileData()->getData(),
                'fileName' => $file->getFileName(),
                'encoding' => 'base64',
                'mimeType' => $file->getMimeType(),
              ];
            };
          } else {
            $folderName = $fieldName . '-' . $camelCaseSlug;
            $personalAttachments[] = function() use ($folderName, $items) {

              return [
                'data' => $this->di(DatabaseStorageUtil::class)->getCollectionArchive($items, $folderName),
                'fileName' => $folderName . '.zip',
                'encoding' => 'base64',
                'mimeType' => 'application/zip',
              ];
            };
          }
          break;
      }
    }

    return $personalAttachments;
  }

  /**
   * Construct the PHPMailer instance
   *
   * @param bool $authenticate If true install the SMTP authentication. If
   * false the mailer will probably not be able to send messages (safe-guard).
   *
   * @return PHPMailer
   */
  private function getOutboundService(bool $authenticate = false):PHPMailer
  {
    $phpMailer = new PHPMailer(exceptions: true);
    $phpMailer->setLanguage($this->getLanguage());
    $phpMailer->CharSet = 'utf-8';
    $phpMailer->SingleTo = false;

    $phpMailer->IsSMTP();

    $phpMailer->Host = $this->getConfigValue('smtpserver');
    $phpMailer->Port = $this->getConfigValue('smtpport');

    if ($authenticate) {
      switch ($this->getConfigValue('smtpsecure')) {
        case 'insecure': $phpMailer->SMTPSecure = ''; break;
        case 'starttls': $phpMailer->SMTPSecure = 'tls'; break;
        case 'ssl':      $phpMailer->SMTPSecure = 'ssl'; break;
        default:         $phpMailer->SMTPSecure = ''; break;
      }
      $phpMailer->SMTPAuth = true;
      $phpMailer->Username = $this->getConfigValue('emailuser');
      $phpMailer->Password = $this->getConfigValue('emailpassword');
    }

    return $phpMailer;
  }

  private static function outBoxSubFolderFromMessageId(string $messageId):string
  {
    return str_replace([ '@', '.' ], [ self::MSG_ID_AT, '_' ], substr($messageId, 1, -1));
  }

  private static function messageIdFromOutBoxSubFolder(string $outBoxSubFolderPath):string
  {
    return '<' . str_replace([ self::MSG_ID_AT, '_' ], [ '@', '.' ], $outBoxSubFolderPath) . '>';
  }

  /**
   * Compose all "global" attachments, possibly replacing large attachments by
   * download links.
   *
   * @param PHPMailer $phpMailer
   *
   * @param string $messageId Defines the sub-folder where the attachments are
   * stored.
   */
  private function composeGlobalAttachments(PHPMailer $phpMailer, string $messageId)
  {
    // also simulate link generation for attachments exceeding a certain size
    $linkSizeLimit = $this->getConfigValue('attachmentLinkSizeLimit', (1 << 20)); // k=10, m=20
    $linkExpirationLimit = $this->getConfigValue('attachmentLinkExpirationLimit', 7); // days

    $outBoxSubFolderPath = self::outBoxSubFolderFromMessageId($messageId);
    $outBoxSubFolderPath = UserStorage::pathCat($this->getOutBoxFolderPath(), $outBoxSubFolderPath);

    $expirationDate = $linkExpirationLimit <= 0
      ? null
      : ((new \DateTimeImmutable('UTC midnight'))
        ->modify('+' . $linkExpirationLimit . ' days'));

    // Add all registered attachments.
    foreach ($this->fileAttachments() as $attachment) {
      if ($attachment['status'] != 'selected') {
        continue;
      }
      if ($attachment['type'] == 'message/rfc822') {
        $encoding = '8bit';
      } else {
        $encoding = 'base64';
      }
      $file = $this->appStorage->getFile($attachment['tmp_name']);
      if ($linkSizeLimit >= 0 && $file->getSize() > $linkSizeLimit) {
        $downloadPath = UserStorage::pathCat($outBoxSubFolderPath, $attachment['name']);
        $downloadFile = $this->userStorage->getFile($downloadPath);
        if (empty($downloadFile)) {
          $downloadFile = $this->userStorage->putContent($downloadPath, $file->getContent());
        }
        if (!empty($downloadFile)) {
          $shareLink = $this->simpleSharingService->linkShare(
            $downloadFile,
            $this->shareOwnerId(),
            sharePerms: \OCP\Constants::PERMISSION_READ,
            expirationDate: $expirationDate
            );
          $downloadAttachment = $this->l->t(
            '<!DOCTYPE html>
<html lang="%1$s">
  <head>
    <title>%2$s</title>
    <meta http-equiv="refresh" content="%6$d;URL=\'%2$s\'"/>
  </head>
  <body>
    <div class="message">
      You will be redirected to the download location in %6$d seconds. You may as well
      click on the download-link below:
    </div>
    <blockquote class="link">
      <a href="%2$s">%2$s</a>
      <div class="link-info"><code>%3$s</code> -- %4$s, %5$s</div>
    </blockquote>
    <div class="expiration-notice">
      Please note that the download link will expire on %7$s.
    </div>
  </body>
</html>', [
  $this->getLanguage(),
  $shareLink,
  basename($attachment['name']),
  Util::humanFileSize($downloadFile->getSize()),
  $attachment['type'],
  30,
  $this->dateTimeFormatter()->formatDate($expirationDate, 'long')
]
          );
          $phpMailer->addStringAttachment(
            $downloadAttachment,
            basename($attachment['name']) . '.html',
            '8bit',
            'text/html'
          );
          $this->registerAttachmentDownloadsCleaner();
          continue;
        }
      }
      $phpMailer->AddStringAttachment(
        $file->getContent(),
        basename($attachment['name']),
        $encoding,
        $attachment['type']);
    }

    // add "global" blank template attachments with just the orchestra-data
    // filled. This is needed for bulk-email as personalization just takes too
    // long.
    foreach ($this->blankTemplateAttachments() as $attachment) {
      if ($attachment['status'] != 'selected') {
        continue;
      }

      if (isset($attachment['template_id'])) {
        $templateId = $attachment['template_id'];

        /** @var FinanceService $financeService */
        $financeService = $this->di(FinanceService::class);
        switch ($templateId) {
          case ConfigService::DOCUMENT_TEMPLATE_GENERAL_DEBIT_NOTE_MANDATE:
            $membersProject = $this->entityManager->find(
              Entities\Project::class,
              $this->getClubMembersProjectId()
            );
            if (empty($membersProject)) {
              continue 2;
            }
            list($fileData, $mimeType, $fileName) = $financeService->preFilledDebitMandateForm(
              null, $membersProject, null, formName: $templateId);
            break;
          case ConfigService::DOCUMENT_TEMPLATE_MEMBER_DATA_UPDATE:
            $membersProject = $this->entityManager->find(
              Entities\Project::class,
              $this->getClubMembersProjectId()
            );
            if (empty($membersProject)) {
              continue 2;
            }
            list($fileData, $mimeType, $fileName) = $financeService->preFilledDebitMandateForm(
              null, $membersProject, null, formName: $templateId);
            break;
          case ConfigService::DOCUMENT_TEMPLATE_PROJECT_DEBIT_NOTE_MANDATE:
            if (empty($this->project)) {
              continue 2;
            }
            list($fileData, $mimeType, $fileName) = $financeService->preFilledDebitMandateForm(
              null, $this->project, null, formName: $templateId);
            break;
          default:
            continue 2;
        }
        $phpMailer->AddStringAttachment(
          $fileData,
          $fileName,
          'base64',
          $mimeType);
        continue;
      }
    }
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
   * @param string $strMessage The message to send.
   *
   * @param array $EMails The recipient list
   *
   * @param array $extraAttachements Array of callables return a flat array
   * ```
   * [ 'data' => DATA, 'fileName' => NAME, 'encoding' => ENCODING, 'mimeType' => TYPE ]
   * ```
   *
   * @param bool $addCC If @c false, then additional CC and BCC recipients will
   *                   not be added.
   *
   * @param null|string $messageId A pre-computed message-id.
   *
   * @param null|string|array $references Message id(s) for a References:
   * header. If a string then the single message-id of the master-template. If
   * an array then these are the message ids of the individual merged messages.
   *
   * @return array
   * ```
   * [
   *   'message' => "The sent Mime-message which then may be stored in the
   * Sent-Folder on the imap server (for example)",
   *   'messageId' => USED_MESSAGE_ID,
   * ]
   * ```
   */
  private function composeAndSend(
    $strMessage
    , $EMails
    , $extraAttachments = []
    , $addCC = true
    , ?string $messageId = null
    , $references = null)
  {
    // If we are sending to a single address (i.e. if $strMessage has
    // been constructed with per-member variable substitution), then
    // we do not need to send via BCC.
    $singleAddress = count($EMails) == 1;

    // Construct an array for the data-base log
    $logMessage = new SentEmailDTO;
    $logMessage->recipients = $EMails;
    $logMessage->message = $strMessage;

    // One big try-catch block. Using exceptions we do not need to
    // keep track of all return values, which is quite beneficial
    // here. Some of the stuff below clearly cannot throw, but then
    // it doesn't hurt to keep it in the try-block. All data is
    // added in the try block. There is another try-catch-construct
    // surrounding the actual sending of the message.
    try {

      $phpMailer = $this->getOutboundService(authenticate: true);
      $messageId = $messageId ?? $phpMailer->generateMessageId();

      // Provide some progress feed-back to amuse the user
      $progressStatus = $this->progressStatusService->get($this->progressToken);
      $progressStatus->update(0, null, [
        'proto' => 'smtp',
        'total' =>  $this->diagnostics['TotalPayload'],
        'active' => $this->diagnostics['TotalCount'],
      ]);
      $phpMailer->setProgressCallback(function($current, $total) use ($progressStatus) {
        $oldCurrent = $progressStatus->getCurrent();
        $oldTime = $progressStatus->getLastModified()->getTimestamp();
        $nowTime = time();
        if ($current >= $total
            || ($current - $progressStatus->getCurrent() >= self::PROGRESS_CHUNK_SIZE
                && $nowTime - $oldTime >= self::PROGRESS_THROTTLE_SECONDS)) {
          $progressStatus->update($current, $total);
        }
      });

      $phpMailer->Subject = $this->messageTag . ' ' . $this->subject();
      $logMessage->subject = $phpMailer->Subject;
      // pass the correct path in order for automatic image conversion
      $phpMailer->msgHTML($strMessage, __DIR__ . '/../../');

      $senderName = $this->fromName();
      $senderEmail = $this->fromAddress();
      $phpMailer->AddReplyTo($senderEmail, $senderName);
      $phpMailer->SetFrom($senderEmail, $senderName);

      if (!$this->constructionMode) {
        // Loop over all data-base records and add each recipient in turn
        foreach ($EMails as $recipient) {
          if ($singleAddress) {
            $phpMailer->AddAddress($recipient['email'], $recipient['name']);
          } else if ($recipient['project'] <= 0 || !$this->discloseRecipients()) {
            // blind copy, don't expose the victim to the others.
            $phpMailer->AddBCC($recipient['email'], $recipient['name']);
          } else {
            // open recipients list is requested, still some recipients are hidden.
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

      // compose all global attachments. May replace large attachments by download-links.
      $this->composeGlobalAttachments($phpMailer, is_string($references) ? $references : $messageId);

      // Add to-be-attached events.
      $events = $this->eventAttachments();
      if ($this->projectId > 0 && !empty($events)) {
        // Construct the calendar
        $calendar = $this->eventsService->exportEvents($events, $this->projectName, hideParticipants: true);

        // Encode it as attachment
        $phpMailer->AddStringEmbeddedImage($calendar,
                                           md5($this->projectName.'.ics'),
                                           $this->projectName.'.ics',
                                           'quoted-printable',
                                           'text/calendar');
      }

      // All extra (in particular: personal) attachments.
      foreach ($extraAttachments as $generator) {
        $attachment = call_user_func($generator);
        $phpMailer->addStringAttachment(
          $attachment['data'],
          $attachment['fileName'],
          $attachment['encoding'],
          $attachment['mimeType']
        );
      }

    } catch (\Throwable $t) {
      // popup an alert and abort the form-processing

      $this->executionStatus = false;
      $this->diagnostics['MailerExceptions'][] = $this->formatExceptionMessage($t);

      return false;
    }

    /** @var Entities\SentEmail $sentEmail */
    $sentEmail = $this->sentEmail($logMessage);
    if (!$sentEmail) {
      return false;
    }

    // install custom message id if given
    if (!empty($messageId)) {
      $phpMailer->MessageID = $messageId;
    }
    if (!empty($references)) {
      if (is_string($references)) {
        // the id of the master-copy
        $sentEmail->setReferencing($this->getReference(Entities\SentEmail::class, $references));
      } else {
        $references = array_merge($references, $this->referencing);
        sort($references);
        foreach ($references as $reference) {
          // Adding references unfortunately is not enough, ORM does not match
          // "un-flushed" newly persisted objects with reference
          // objects. However, find() does work and obtains the managed object.
          $referencing = $this->getDatabaseRepository(Entities\SentEmail::class)->find($reference);
          $sentEmail->getReferencedBy()->set($reference, $referencing);
          $referencing->setReferencing($sentEmail);
        }
      }
      $phpMailer->setReferences((array)$references);
    }
    if (!empty($this->inReplyToId)) {
      $phpMailer->addCustomHeader('In-Reply-To', $this->inReplyToId);
    }

    // Finally the point of no return. Send it out!!!
    try {
      // PHPMailer does only throw \Exception(), but sets the code in order to
      // distinguish between fatal and not fatal errors.
      try {
        $phpMailer->Send();
      } catch (\Exception $e) {
        switch ($e->getCode()) {
          case PHPMailer::STOP_CONTINUE:
            // this actually just means that some recipients have failed. At
            // least currently this happens only with smtp, which is just the
            // send-method we are using.
            $failedRecipients = $phpMailer->failedRecipients($e->getMessage());
            $this->diagnostics['FailedRecipients'] = array_merge(
              $this->diagnostics['FailedRecipients'],
              $failedRecipients
            );
            // something was sent in this case, so do not terminate and record
            // the sent message in the sent-folder and the DB.
            break;
          case PHPMailer::STOP_MESSAGE:
            // fallthrough
          case PHPMailer::STOP_CRITICAL:
          default:
            throw $t;
        }
      }
      $sentEmail->setMessageId($phpMailer->getLastMessageID());
      $this->persist($sentEmail);
      // $this->flush();
    } catch (\Throwable $t) {
      $this->executionStatus = false;
      $this->diagnostics['MailerExceptions'][] = $this->formatExceptionMessage($t);
      $this->logException($t);
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
    foreach ($this->fileAttachments() as $attachment) {
      if ($attachment['status'] != 'selected') {
        continue;
      }
      $size     = \OCP\Util::humanFileSize($attachment['size']);
      $name     = basename($attachment['name']).' ('.$size.')';
      $this->diagnostics['Message']['Files'][] = $name;
    }

    foreach ($this->personalAttachments() as $attachment) {
      if ($attachment['status'] != 'selected') {
        continue;
      }
      $this->diagnostics['Message']['Files'][] = $attachment['name'];
    }

    $this->diagnostics['Message']['Events'] = [];
    $events = $this->eventAttachments();
    $locale = $this->getLocale();
    $timezone = $this->getTimezone();
    foreach($events as $eventUri => $calendarId) {
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

    $imapHost   = $this->getConfigValue('imapserver');
    $imapPort   = $this->getConfigValue('imapport');
    $imapSecurity = $this->getConfigValue('imapsecurity');

    $progressStatus = $this->progressStatusService->get($this->progressToken);
    $progressStatus->update(0, null, [
      'proto' => 'imap',
      'total' =>  $this->diagnostics['TotalPayload'],
      'active' => $this->diagnostics['TotalCount'],
    ]);
    $imap = new \Net_IMAP($imapHost,
                          $imapPort,
                          $imapSecurity == 'starttls' ? true : false, 'UTF-8',
                          function($current, $total) use ($progressStatus) {
                            if ($total < 128) {
                              return; // ignore non-data transfers
                            }
                            $progressStatus->update($current, $total);
                          },
                          self::PROGRESS_CHUNK_SIZE); // 4 kb chunk-size

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
   * if this is a duplicate, true otherwise.
   *
   * @param $logMessage The email-message to record in the DB.
   *
   * @param $allowDuplicates Whether or not to check for
   * duplicates. This is currently never set to true.
   *
   */
  private function sentEmail(SentEmailDTO $logMessage, $allowDuplicates = false)
  {
    /** @var Entities\SentEmail $sentEmail */
    $sentEmail = new Entities\SentEmail;

    // Construct one MD5 for recipients subject and html-text
    $bulkRecipients = array_map(function($pair) {
      return $pair['name'].' <'.$pair['email'].'>';
    }, $logMessage->recipients);

    $sentEmail->setProject($this->project)
              ->setBulkRecipients(implode(';', $bulkRecipients))
              ->setCc($logMessage->CC)
              ->setBcc($logMessage->BCC)
              ->setSubject($logMessage->subject)
              ->setHtmlBody($logMessage->message)
      // cannot wait for the slug-handler here
              ->setBulkRecipientsHash(hash('md5', $sentEmail->getBulkRecipients()))
              ->setSubjectHash(hash('md5', $sentEmail->getSubject()))
              ->setHtmlBodyHash(hash('md5', $sentEmail->getHtmlBody()));

    // Now logging is ready to execute. But first check for
    // duplicate sending attempts. This takes only the recipients,
    // the subject and the message body into account. Rationale: if
    // you want to send an updated attachment, then you really
    // should write a comment on that. Still the test is flaky
    // enough.

    if ($allowDuplicates !== true) {

      $duplicates = $this->getDatabaseRepository(Entities\SentEmail::class)->findBy([
        'bulkRecipientsHash' => $sentEmail->getBulkRecipientsHash(),
        'subjectHash' => $sentEmail->getSubjectHash(),
        'htmlBodyHash' => $sentEmail->getHtmlBodyHash(),
      ]);

      $loggedDates = [];
      $loggedUsers = [];
      /** @var Entities\SentEmail $duplicate */
      foreach ($duplicates as $duplicate) {
        $loggedDates[] = $duplicate->getCreated();
        $loggedUsers[] = $duplicate->getCreatedBy();
      }

      if (!empty($duplicates)) {
        $this->executionStatus = false;
        $this->diagnostics['Duplicates'][] = [
          'dates' => $loggedDates,
          'authors' => $loggedUsers,
          'text' => $logMessage->message,
          'recipients' => $bulkRecipients
        ];
        return false;
      }
    }

    return $sentEmail;
  }

  /**
   * Compose and export one message to HTML.
   *
   * @param $strMessage The message to send.
   *
   * @param $eMails The recipient list
   *
   * @param array $extraAttachements Array of callables return a flat array
   * ```
   * [ 'data' => DATA, 'fileName' => NAME, 'encoding' => ENCODING, 'mimeType' => TYPE ]
   * ```
   *
   * @param $addCC If @c false, then additional CC and BCC recipients will
   *               not be added.
   *
   * @param null|string $messageId A pre-computed message-id.
   *
   * @param null|string|array $references Message id(s) for a References:
   * header. If a string then the single message-id of the master-template. If
   * an array then these are the message ids of the individual merged messages.
   *
   * @return array
   * ```
   * [
   *   'headers' => $phpMailer->getMailHeaders(),
   *   'messageId' => DUMMY_MESSAGE_ID,
   *   'body' => $strMessage,
   *   'attachments' => $attachments,
   * ]
   * ```
   *
   * @todo Fold into composeAndSend
   */
  private function composeAndExport(
    $strMessage
    , $eMails
    , $extraAttachments = []
    , $addCC = true
    , ?string $messageId = null
    , $references = null
  ) {
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

      $phpMailer = $this->getOutboundService();
      $messageId = $messageId ?? $phpMailer->generateMessageId();

      $phpMailer->Subject = $this->messageTag . ' ' . $this->subject();
      $logMessage->subject = $phpMailer->Subject;

      // pass the correct path in order for automatic image conversion
      $phpMailer->msgHTML($strMessage, __DIR__.'/../../');

      $senderName = $this->fromName();
      $senderEmail = $this->fromAddress();
      $phpMailer->AddReplyTo($senderEmail, $senderName);
      $phpMailer->SetFrom($senderEmail, $senderName);

      // Loop over all data-base records and add each recipient in turn
      foreach ($eMails as $recipient) {
        if ($singleAddress) {
          $phpMailer->AddAddress($recipient['email'], $recipient['name']);
        } else if ($recipient['project'] <= 0 || !$this->discloseRecipients()) {
          // blind copy, don't expose the victim to the others.
          $phpMailer->AddBCC($recipient['email'], $recipient['name']);
        } else {
          // open recipients list is requested, still some recipients are hidden.
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

      // compose all global attachments. May replace large attachments by download-links.
      $this->composeGlobalAttachments($phpMailer, is_string($references) ? $references : $messageId);

      // Finally possibly to-be-attached events. This cannot throw,
      // but it does not hurt to keep it here. This way we are just
      // ready with adding data to the message inside the try-block.
      $events = $this->eventAttachments();
      if ($this->projectId > 0 && !empty($events)) {
        // Construct the calendar
        $calendar = $this->eventsService->exportEvents($events, $this->projectName, hideParticipants: true);

        // Encode it as attachment
        $phpMailer->AddStringEmbeddedImage($calendar,
                                           md5($this->projectName.'.ics'),
                                           $this->projectName.'.ics',
                                           'quoted-printable',
                                           'text/calendar');
      }

      // All extra (in particular: personal) attachments.
      foreach ($extraAttachments as $generator) {
        $attachment = call_user_func($generator);
        $phpMailer->addStringAttachment(
          $attachment['data'],
          $attachment['fileName'],
          $attachment['encoding'],
          $attachment['mimeType']
        );
      }

    } catch (\Throwable $t) {
      $this->logException($t);
      // popup an alert and abort the form-processing

      $this->executionStatus = false;
      $this->diagnostics['MailerExceptions'][] = $this->formatExceptionMessage($t);

      return null;
    }

    // install custom message id if given
    if (!empty($messageId)) {
      $phpMailer->MessageID = $messageId;
    }
    if (!empty($references)) {
      $phpMailer->setReferences((array)$references);
    }
    if (!empty($this->inReplyToId)) {
      $phpMailer->addCustomHeader('In-Reply-To', $this->inReplyToId);
    }
    foreach ($this->referencing as $referenceMessageId) {
      $phpMailer->addReference($referenceMessageId);
    }

    // Finally the point of no return. Send it out!!! Well. PRE-send it out ...
    try {
      $phpMailer->preSend();
    } catch (\Throwable $t) {
      $this->logException($t);
      $this->executionStatus = false;
      $this->diagnostics['MailerExceptions'][] = $this->formatExceptionMessage($t);

      return null;
    }

    // decode the somewhat cryptic fields to a more readable variant and
    // generate a temporary cache file in order to be able to review the
    // generated attachments.
    $attachments = [];
    foreach ($phpMailer->getAttachments() as $mailerAttachment) {
      $attachment = [
        'data' => null,
        'name' => $mailerAttachment[PHPMailer::ATTACHMENT_INDEX_NAME],
        'size' => strlen($mailerAttachment[PHPMailer::ATTACHMENT_INDEX_DATA]),
        'encoding' => $mailerAttachment[PHPMailer::ATTACHMENT_INDEX_ENCODING],
        'mimeType' => $mailerAttachment[PHPMailer::ATTACHMENT_INDEX_MIME_TYPE],
      ];
      // generate a cache file for the preview page
      /** @var \OCP\ICache $cloudCache */
      $cloudCache = $this->di(\OCP\ICache::class);
      $cacheKey = (string)Uuid::create();
      $attachment['data'] =
        $cloudCache->set($cacheKey, $mailerAttachment[PHPMailer::ATTACHMENT_INDEX_DATA] ?? '', self::ATTACHMENT_PREVIEW_CACHE_TTL)
        ? $cacheKey
        : null;
      $cloudCache->set($cacheKey . '-meta', json_encode($attachment));
      $attachments[] = $attachment;
    }

    return [
      'messageId' => $phpMailer->getLastMessageID(),
      'headers' => $phpMailer->getMailHeaders(),
      'body' => $strMessage,
      'attachments' => $attachments,
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
    $previewRecipients = $this->recipients;
    if (empty($previewRecipients)) {
      /** @var Entities\Musician $dummy */
      $dummy = $this->appContainer()->get(InstrumentationService::class)->getDummyMusician($this->project);
      $previewRecipients = [
        $dummy->getId() => [
          'email' => $dummy->getEmail(),
          'name' => $dummy->getPublicName(true),
          'dbdata' => $dummy,
        ],
      ];
    }
    $realRecipients = $this->recipients;
    $this->recipients = $previewRecipients;
    if (!$this->preComposeValidation()) {
      $this->recipients = $realRecipients;
      return null;
    }

    // Preliminary checks passed, let's see what happens. The mailer may throw
    // any kind of "nasty" exceptions.
    $preview = $this->exportMessages($previewRecipients);

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
   *
   * @todo This really should be folded in to sendMessages()
   */
  private function exportMessages(?array $recipients = null)
  {
    // @todo yield needs more care concerning error management
    $messages = [];

    $this->diagnostics['TotalCount'] =
      $this->diagnostics['FailedCount'] = 0;

    // The following cannot fail, in principle. $message is then
    // the current template without any left-over globals.

    $messageTemplate = implode("\n", array_map(function($style) {
      return self::emitHtmlBodyStyle($style, self::EMAIL_PREVIEW_SELECTOR);
    }, self::DEFAULT_HTML_STYLES))
                     . $this->replaceFormVariables(self::GLOBAL_NAMESPACE);

    $hasPersonalSubstitutions = $this->hasSubstitutionNamespace(self::MEMBER_NAMESPACE, $messageTemplate);
    $hasPersonalAttachments = $this->activePersonalAttachments() > 0;

    $references = [];
    $templateMessageId = $this->getOutboundService()->generateMessageId();

    if ($hasPersonalSubstitutions || $hasPersonalAttachments) {

      $this->logInfo(
        'Composing separately because of personal substitutions / attachments '
        . (int)$hasPersonalSubstitutions
        . ' / '
        . (int)$hasPersonalAttachments);

      $this->diagnostics['TotalPayload'] = count($recipients)+1;

      foreach ($recipients as $recipient) {
        /** @var Entities\Musician $musician */
        $musician = $recipient['dbdata'];

        $this->implicitFileAttachments = [];
        $strMessage = $this->replaceFormVariables(self::MEMBER_NAMESPACE, $musician, $messageTemplate);
        $strMessage = $this->finalizeSubstitutions($strMessage);

        $this->implicitFileAttachments = array_values(array_unique($this->implicitFileAttachments));
        if (!empty($this->implicitFileAttachments)) {
          $this->personalFileAttachments = null; // have to void it ...
          $this->cgiData['attachedFiles'] = array_values(
            array_unique(
              array_merge(
                $this->cgiData['attachedFiles']??[],
                $this->implicitFileAttachments??[]
              )));
        }

        $personalAttachments = $this->composePersonalAttachments($musician);

        ++$this->diagnostics['TotalCount'];
        $message = $this->composeAndExport(
          $strMessage,
          [ $recipient ],
          $personalAttachments,
          addCC: false,
          references: $templateMessageId
        );
        if (empty($message) || !empty($this->diagnostics['AttachmentValidation'])) {
          ++$this->diagnostics['FailedCount'];
        }

        if (!empty($message)) {
          $messages[] = $message;
          $references[] = $message['messageId'];
        }
      }

      // Finally send one message without template substitution (as
      // this makes no sense) to all Cc:, Bcc: recipients and the
      // catch-all. This Message also gets copied to the Sent-folder
      // on the imap server.
      $messageTemplate = $this->finalizeSubstitutions($messageTemplate);
      ++$this->diagnostics['TotalCount'];
      $message = $this->composeAndExport(
        $messageTemplate, [], [],
        addCC: true,
        messageId: $templateMessageId,
        references: $references
      );
      if (empty($message) || !empty($this->diagnostics['AttachmentValidation'])) {
        ++$this->diagnostics['FailedCount'];
      }

      if (!empty($message)) {
        $messages[] = $message;
      }

    } else {
      $this->diagnostics['TotalPayload'] = 1;
      ++$this->diagnostics['TotalCount']; // this is ONE then ...
      $messageTemplate = $this->finalizeSubstitutions($messageTemplate);
      $message = $this->composeAndExport($messageTemplate, $recipients);
      if (empty($message) || !empty($this->diagnostics['AttachmentValidation'])) {
        ++$this->diagnostics['FailedCount'];
      }
      if (!empty($message)) {
        $messages[] = $message;
      }
    }
    return $messages;
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
   * - file attachments, temporary local copy must exist
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
      if (!$this->appStorage->fileExists($attachment['tmp_name'])) {
        $this->executionStatus = false;
        $attachment['status'] = 'unreadable';
        $this->diagnostics['AttachmentValidation']['Files'][] = $attachment;
      }
    }

    // event attachment
    $events = $this->eventAttachments();
    foreach ($events as $eventUri => $calendarId) {
      if (!$this->eventsService->fetchEvent($this->projectId, $eventUri)) {
        $this->executionStatus = false;
        $this->diagnostics['AttachmentValidation']['Events'][] = $eventUri;
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
    if (empty($freeForm)) {
      return [];
    }

    $phpMailer = new PHPMailer(true);
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
    $this->generateSubstitutionHandlers();

    $dummy = $template;

    if ($this->hasSubstitutionNamespace(self::MEMBER_NAMESPACE, $dummy)) {
      $failures = [];
      $dummy = $this->replaceFormVariables(self::MEMBER_NAMESPACE, null, $dummy, $failures);
      if (!empty($failures)) {
        $templateError[] = 'member';
        foreach ($failures as $failure) {
          if ($failure['error'] == 'unknown') {
            $this->diagnostics['TemplateValidation']['MemberErrors'][] = $this->l->t('Unknown substitution "%s".', $failure['namespace'].'::'.implode(':', $failure['variable']));
          } else {
            $this->diagnostics['TemplateValidation']['MemberErrors'] = $failures;
          }
        }
      }
    }

    if ($this->hasSubstitutionNamespace(self::GLOBAL_NAMESPACE)) {
      $failures = [];
      $dummy = $this->replaceFormVariables(self::GLOBAL_NAMESPACE, null, $dummy, $failures);
      if (!empty($failures)) {
        $templateError[] = 'global';
        foreach ($failures as $failure) {
          if ($failure['error'] == 'unknown') {
            $this->diagnostics['TemplateValidation']['GlobalErrors'][] = $this->l->t('Unknown substitution "%s".', $failure['namespace'].'::'.implode(':', $failure['variable']));
          } else {
            $this->diagnostics['TemplateValidation']['GlobalErrors'][] = $failure;
          }
        }
      }
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
      $this->catchAllName  = $this->getConfigValue(
        'emailtestname',
        $this->getConfigValue('emailfromname')
      );
    } else {
      $this->catchAllEmail = $this->getConfigValue('emailfromaddress');
      $this->catchAllName  = $this->getConfigValue('emailfromname');
    }
  }

  private function dateSubstitution(array $arg, string $nameSpace, $data = null)
  {
    try {
      $dateString = $arg[1];
      $dateFormat = $arg[2]??'long';

      // allow other global replacement variables as date-time source
      if (!empty($this->substitutions[$nameSpace][$dateString])) {
        $dateString = call_user_func(
          $this->substitutions[$nameSpace][$dateString],
          [ $dateString, 'medium' ],
          $data);
        if (empty($dateString)) {
          return $arg[0];
        }
      }

      $stamp = strtotime($dateString);
      if (\array_search($dateFormat, ['full', 'long', 'medium', 'short']) !== false) {
        return $this->formatDate($stamp, $dateFormat);
      }
      $oldLocale = setlocale(LC_TIME, '0');
      setlocale(LC_TIME, $this->getLocale());
      $oldTimezone = \date_default_timezone_get();
      \date_default_timezone_set($this->getTimezone());
      $result = strftime($dateFormat, $stamp);
      \date_default_timezone_set($oldTimezone);
      setlocale(LC_TIME, $oldLocale);
      return $result;
    } catch (\Throwable $t) {
      throw new Exceptions\SubstitutionException($this->l->t('Date-time substitution of "%s" / "%s" failed.', [ $dateString, $dateFormat ]), $t->getCode(), $t);
    }
  }

  /**
   * Generate the substitutions for the global form variables.
   *
   * @todo unify timezone and date and time formatting.
   */
  private function generateGlobalSubstitutionHandlers()
  {
    /** @var IDateTimeFormatter */
    $formatter = $this->appContainer()->get(IDateTimeFormatter::class);

    $organizationalRoleContact = function(array $arg) {
      $role = strtolower($arg[0]);
      $contact = $this->organizationalRolesService->dedicatedBoardMemberContact($role);
      $subField = Util::dashesToCamelCase(strtolower($arg[1]));
      if (empty($subField)) {
        throw new Exceptions\SubstitutionException($this->l->t('Contact subfield for "%1$s" is missing, should be one of %2$s.', [
          $arg[0],
          implode(', ', array_map('strtoupper', array_keys($contact)))
        ]));
      }
      if (!isset($contact[$subField])) {
        throw new Exceptions\SubstitutionException($this->l->t('Contact subfield "%3$s"" for "%1$s" is unknown, should be one of %2$s.', [
          $arg[0],
          implode(', ', array_map('strtoupper', array_keys($contact))),
          $arg[1],
        ]));
      }
      return $contact[$subField];
    };

    $this->substitutions[self::GLOBAL_NAMESPACE] = [
      'ORGANIZER' => function($key) {
        return $this->fetchExecutiveBoard();
      },
      'PRESIDENT' => $organizationalRoleContact,
      'TREASURER' => $organizationalRoleContact,
      'SECRETARY' => $organizationalRoleContact,
      'CREDITOR_IDENTIFIER' => function($key) {
        return $this->getConfigValue('bankAccountCreditorIdentifier');
      },
      'ADDRESS' => function($key) {
        return $this->streetAddress();
      },
      'BANK_ACCOUNT' => function($key) {
        return $this->bankAccount();
      },
      'PROJECT' => function($key) {
        $this->projectName != '' ? $this->projectName : $this->l->t('no project involved');
      },
      'BANK_TRANSACTION_DUE_DATE' => function($key) { return ''; },
      'BANK_TRANSACTION_DUE_DAYS' => function($key) { return ''; },
      'BANK_TRANSACTION_SUBMIT_DATE' => function($key) { return ''; },
      'BANK_TRANSACTION_SUBMIT_DAYS' => function($key) { return ''; },

      /**
       * Support date substitutions. Format is
       * ${GLOBAL::DATE:dateformat:datestring} where dateformat
       * default to d.m.Y (see strftime) and datestring can be
       * everything understood by strtotime.
       *
       * @todo Revise concerning timezone and locale settings
       */
      'DATE' => function(array $arg) {
        return $this->dateSubstitution($arg, self::GLOBAL_NAMESPACE);
      },
      'TIME' => function(array $arg) use ($formatter) {
        try {
          $dateString = $arg[1];
          $dateFormat = $arg[2]?:'long';
          $stamp = strtotime($dateString);
          return $formatter->formatTime($stamp, $dateFormat);
        } catch (\Throwable $t) {
          throw new Exceptions\SubstitutionException($this->l->t('Date-time substitution of "%s" / "%s" failed.', [ $dateString, $dateFormat ]), $t->getCode(), $t);
        }
      },
      'DATETIME' => function(array $arg) {
        try {
          $dateString = $arg[1];
          $dateFormat = $arg[2]?:'long';
          $stamp = strtotime($dateString);
          return $this->formatDateTime($stamp, $dateFormat);
        } catch (\Throwable $t) {
          throw new Exceptions\SubstitutionException($this->l->t('Date-time substitution of "%s" / "%s" failed.', [ $dateString, $dateFormat ]), $t->getCode(), $t);
        }
      },
    ];

    if (!empty($this->bulkTransaction)) {

      $this->substitutions[self::GLOBAL_NAMESPACE] = array_merge(
        $this->substitutions[self::GLOBAL_NAMESPACE], [
          'BANK_TRANSACTION_DUE_DAYS' => function($key) {
            return (new \DateTime())->diff($this->bulkTransaction->getDueDate())->format('%r%a');
          },
          'BANK_TRANSACTION_SUBMIT_DAYS' => function($key) {
            return (new \DateTime())->diff($this->bulkTransaction->getSubmissionDeadline())->format('%r%a');
          },
          'BANK_TRANSACTION_DUE_DATE' => function($key) {
            return $this->formatDate($this->bulkTransaction->getDueDate(), 'medium');
          },
          'BANK_TRANSACTION_SUBMIT_DATE' => function($key) {
            return $this->formatDate($this->bulkTransaction->getSubmissionDeadline(), 'medium');
          },
        ]);
    }
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
    $executiveBoardId = $this->getExecutiveBoardProjectId();

    $executiveBoardNames = $this
      ->getDatabaseRepository(Entities\ProjectParticipant::class)
      ->fetchParticipantNames($executiveBoardId, [
        'nickName' => 'ASC',
        'firstName' => 'ASC',
        'displayName' => 'ASC',
        'surName' => 'ASC',
      ]);

    $executiveBoardNickNames = [];
    foreach ($executiveBoardNames as $names) {
      $executiveBoardNickNames[] = $names['nickName'];
    }

    return $this->implodeSloppy($executiveBoardNickNames);
  }

  /**
   * Load an already sent email and prepare the data for a useful reply
   */
  public function loadSentEmail(string $messageId)
  {
    $messageId = trim($messageId, " \n\r\t\v\x00");
    /** @var Entities\SentEmail $sentEmail */
    $sentEmail = $this->getDatabaseRepository(Entities\SentEmail::class)->findOneLike([ 'messageId' => '%' . trim($messageId) . '%' ]);
    if (empty($sentEmail)) {
      $this->logInfo('UNABLE TO FETCH "' . $messageId . '"');
      return $this->executionStatus = false;
    }

    // The following seems to be used by TB
    // <blockquote type="cite" cite="mid:f1bfe41b-1f3e-8ea9-be96-374b2eaca6ea@ruhr-uni-bochum.de">
    // Am 12.04.22 um 17:50 schrieb DIE ZEIT:
    // On 12.04.22 17:50, DIE ZEIT wrote:

    $dateSent = $sentEmail->getCreated();
    $this->messageContents = '<p>'
      . $this->l->t('REPLYTO: On %1$s %2$s, %3$s wrote:', [
        $this->dateTimeFormatter()->formatDate($dateSent, 'medium'),
        $this->dateTimeFormatter()->formatTime($dateSent, 'medium'),
        $this->fromName(),
      ])
      . '</p>';
    $this->messageContents .= '<blockquote type="cite" style="" cite="mid:' . trim($messageId, '<>') . '">' . $sentEmail->getHtmlBody() . '</blockquote>';
    $this->cgiData['subject'] = preg_replace('/\[[^]]*\]\s+/', 'Re: ', $sentEmail->getSubject());

    $this->cgiData['BCC'] = $sentEmail->getBcc();
    $this->cgiData['CC'] = $sentEmail->getCc();
    $this->cgiData['inReplyTo'] = $this->inReplyToId = $messageId;


    $this->referencing = [ $this->inReplyTo() ];
    $referencing = $sentEmail->getReferencing();
    if (!empty($referencing)) {
      $this->referencing[] = $referencing->getMessageId();
    }
    $referencedBy = $sentEmail->getReferencedBy();
    foreach ($referencedBy as $referrer) {
      $this->referencing[] = $refererrer->getMessageId();
    }
    $this->cgiData['referencing'] = $this->referencing;

    $this->draftId = 0; // avoid accidental overwriting

    $parser = new \Mail_RFC822(null, null, null, false);
    $recipients = [];
    foreach (explode(';', $sentEmail->getBulkRecipients()) as $recipient) {
      $emailRecord = $parser->parseAddressList($recipient);
      $parseError = $parser->parseError();
      if ($parseError !== false || count($emailRecord) != 1) {
        continue;
      }
      $emailRecord = reset($emailRecord);
      $recipients[] = $emailRecord->mailbox . '@' . $emailRecord->host;
    }
    $musicianIds = $this->getDatabaseRepository(Entities\Musician::class)->fetchIds([ 'email' => $recipients ]);

    $this->recipientsFilter->setSelectedRecipients($musicianIds);
    $this->recipients = $this->recipientsFilter->selectedRecipients();

    return $this->executionStatus = true;
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
    $this->draftId = 0; // avoid accidental overwriting

    // clear references, as this is a new shot
    $this->inReplyToId = $this->cgiData['inReplyTo'] = null;
    $this->referencing = $this->cgiData['referencing'] = [];

    return $this->executionStatus = true;
  }

  /**
   * Normalize the given template-name: CamelCase, not spaces, no
   * dashes.
   *
   * @return array<int, string> First element is the normalized
   * version, second element a translation of the normalized version,
   * if it differs from the non-translated version.
   */
  private function normalizeTemplateName($templateName)
  {
    $normalizedName = Util::dashesToCamelCase(
      Util::normalizeSpaces($templateName), true, '_- ');
    $result = [ $normalizedName ];
    $translation = (string)$this->l->t($normalizedName);
    if ($translation != $normalizedName) {
      array_unshift($result, $translation);
    } else {
      $words = [];
      foreach (explode(' ', Util::camelCaseToDashes($normalizedName, ' ')) as $word) {
        $wordVariants = [
          $word,
          strtolower($word),
          ucfirst(strtolower($word)),
          strtoupper($word),
        ];
        $translatedWord = $word;
        foreach ($wordVariants as $variant) {
          $translatedVariant = $this->l->t($variant);
          if ($translatedVariant != $variant) {
            $translatedWord = $translatedVariant;
            break;
          }
        }
        $words[] = strtolower($translatedWord);
      }
      if (!empty($words)) {
        $translation = Util::dashesToCamelCase(implode(' ', $words), true, ' ');
        array_unshift($result, $translation);
      }
    }
    return array_map(
      function($template) { return $this->transliterate($template); },
      array_unique($result)
    );
  }

  /**
   * Fetch a specific template from the DB. Return null if that
   * template is not found
   *
   * @param int|string|Entities\EmailTemplate $templateIdentifier If a
   * string then it will first be normalized (CamelCase, not spaces,
   * no dashes) and translated.
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
        $templateNames = $this->normalizeTemplateName($templateIdentifier);

        /** @var Entities\EmailTemplate */
        $template = $this
          ->getDatabaseRepository(Entities\EmailTemplate::class)
          ->findOneBy([ 'tag' => $templateNames ]);
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
   * Return the array or collection of sent-email related to the current
   * project, or all sent-email which have been sent without project context.
   */
  private function fetchSentEmailsList()
  {
    return $this->getDatabaseRepository(Entities\SentEmail::class)->findBy([
      'project' => $this->project
    ], [
      'created' => 'DESC',
    ]);
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

    $autoSave = filter_var($this->parameterService[self::POST_TAG]['draftAutoSave'],  FILTER_VALIDATE_BOOLEAN);

    $draftData = [
      'projectId' => $this->parameterService['projectId'],
      'projectName' => $this->parameterService['projectName'],
      'bulkTransactionId' => $this->parameterService['bulkTransactionId'],
      self::POST_TAG => $this->parameterService[self::POST_TAG],
      RecipientsFilter::POST_TAG => $this->parameterService[RecipientsFilter::POST_TAG],
    ];

    unset($draftData[self::POST_TAG]['request']);
    unset($draftData[self::POST_TAG]['submitAll']);
    unset($draftData[self::POST_TAG]['saveMessage']);
    unset($draftData[self::POST_TAG]['draftAutoSave']);

    // $dataJSON = json_encode($draftData);
    $subject = $this->subjectTag() . ' ' . $this->subject();

    /** @var Entities\EmailDraft $draft */

    if ($this->draftId > 0) {
      $draft = $this->getDatabaseRepository(Entities\EmailDraft::class)
                    ->find($this->draftId);
      if (!empty($draft)) {
        $draft->setSubject($subject)
          ->setData($draftData)
          ->setAutoGenerated($draft->getAutoGenerated() && $autoSave);
      } else {
        $this->draftId = 0;
      }
    }
    if (empty($draft)) {
      $draft = Entities\EmailDraft::create()
        ->setSubject($subject)
        ->setData($draftData)
        ->setAutoGenerated($autoSave);
      $this->persist($draft);
    }
    $this->flush();
    $this->draftId = $draft->getId();

    // Update the list of attachments, if any
    foreach ($this->fileAttachments() as $attachment) {
      $this->rememberTemporaryFile($attachment['tmp_name'], $attachment['origin']);
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

    // clear references, as this is a new shot
    $this->inReplyToId = $this->cgiData['inReplyTo'] = null;
    $this->referencing = $this->cgiData['referencing'] = [];

    /** @var Entities\EmailDraft $draft */
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

    if (empty($draftData['bulkTransactionId'])) {
      $draftData['bulkTransactionId'] = null;
    }

    $this->draftId = $draftId;

    $this->executionStatus = true;

    // Clear auto-flag once the draft was actively reviewed
    $draft->setAutoGenerated(false);
    try {
      $this->flush();
    } catch (\Throwable $t) {
      $this->diagnostics['caption'] = $this->l->t('Could not clear auto-generated flag of draft %s, "%s".', [
        $draftId, $draft->getSubject(), ]);
      $this->executionStatus = false;
    }

    return $draftData;
  }

  /**
   * Delete the given or current message draft.
   *
   * @param null|int $draftId
   *
   * @return bool
   */
  public function deleteDraft(?int $draftId = null)
  {
    $draftId = $draftId??$this->draftId;
    if ($draftId > 0 )  {
      // detach any attachnments for later clean-up
      if (!$this->detachTemporaryFiles($draftId)) {
        return false;
      }

      try {
        $this->setDatabaseRepository(Entities\EmailDraft::class);
        $this->remove($draftId, true);
      } catch (\Throwable $t) {
        $this->entityManager->reopen();
        $this->logException($t);
        $this->diagnostics['caption'] = $this->l->t(
          'Deleting draft with id %d failed: %s',
          [ $this->draftId, $t->getMessage() ]);
        return $this->executionStatus = false;
      }

      // Mark as gone
      if ($draftId == $this->draftId) {
        $this->draftId = -1;
      }
    }
    return $this->executionStatus = true;
  }

  /**
   * Delte old draft-messages which still have the autoGenerated flag set to
   * on.
   *
   * @param int $age Age in seconds from now. Deleted are drafts with the
   * autogenerated flag set to true which were not modified in the last $age
   * seconds. Defaults to 1 day.
   */
  public function cleanDrafts(int $age = 60*60*24)
  {
    $updatedTime = (new \DateTimeImmutable)
      ->modify('-' . $age . ' seconds')
      ->setTimezone($this->getDateTimeZone());
    $autoGeneratedDrafts = $duplicates = $this->getDatabaseRepository(Entities\EmailDraft::class)->findBy([
      'autoGenerated' => true,
      '<updated' => $updatedTime,
    ]);
    /** @var Entities\EmailDraft $draft */
    foreach ($autoGeneratedDrafts as $draft) {
      $this->logInfo('Cleaning draft with id ' . $draft->getId());
      $this->deleteDraft($draft->getId());
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
   *
   * @todo use cloud storage
   */
  public function cleanTemporaries($fileAttach = [])
  {
    try {
      $tmpFiles = $this
        ->getDatabaseRepository(Entities\EmailAttachment::class)
        ->findBy([ 'draft' => null ]);
    } catch (\Throwable $t) {
      $this->diagnostics['caption'] = $this->l->t(
        'Cleaning temporary files failed: %s', $t->getMessage());
      return $this->executionStatus = false;
    }

    $toKeep = [];
    foreach ($fileAttach as $files) {
      $tmp = basename($files['tmp_name']);
      if ($this->appStorage->draftExists($tmp)) {
        $toKeep[] = $tmp;
      }
    }

    /** @var Entities\EmailAttachment $tmpFile */
    foreach ($tmpFiles as $tmpFile) {
      $fileName = $tmpFile->getFileName();
      if (array_search($fileName, $toKeep) !== false) {
        continue;
      }
      try {
        $file = $this->appStorage->getDraftsFile($fileName);
        if (!empty($file)) {
          $file->delete();
        }
        $this->forgetTemporaryFile($fileName);
      } catch (\Throwable $t) {
        $this->logException($t);
      }
    }
    $this->diagnostics['caption'] = $this->l->t('Cleaning temporary files succeeded.');
    return $this->executionStatus = true;
  }

  /**
   * Detach temporaries from a draft, i.e. after deleting the draft.
   *
   * @param int $draftId
   *
   * @return bool
   */
  private function detachTemporaryFiles(int $draftId)
  {
    try {
      $this->queryBuilder()
           ->update(Entities\EmailAttachment::class, 'ea')
           ->set('ea.draft', 'null')
           ->where($this->expr()->eq('ea.draft', ':id'))
           ->setParameter('id', $draftId)
           ->getQuery()
           ->execute();
      $this->flush();
    } catch (\Throwable $t) {
      $this->logException($t);
      $this->diagnostics['caption'] = $this->l->t(
        'Detaching temporary file attachments from draft %d failed: %s',
        [ $this->draftId, $t->getMessage() ]);
      return $this->executionStatus = false;
    }
    return $this->executionStatus = true;
  }

  /**
   * Remember a temporary file. Files attached to message drafts are
   * remembered across sessions, temporaries not attached to message
   * drafts are cleaned at logout and when closing the email form.
   *
   * @param string $tmpFile
   *
   * @param string|AttachmentOrigin $origin
   */
  private function rememberTemporaryFile(string $tmpFile, $origin)
  {
    if ($origin == AttachmentOrigin::PARTICIPANT_FIELD) {
      // no need to save, we just need the field-id which is stored anyway
      return true;
    }
    $tmpFile = basename($tmpFile);
    try {
      $attachment = $this
        ->getDatabaseRepository(Entities\EmailAttachment::class)
        ->findOneBy([
          'fileName' => $tmpFile,
        ]);
      if (empty($attachment)) {
        $attachment = (new Entities\EmailAttachment())
                    ->setFileName($tmpFile);
      }
      if ($this->draftId > 0) {
        $attachment->setDraft($this->getReference(Entities\EmailDraft::class, $this->draftId));
      }
      $this->persist($attachment);
      $this->flush();
    } catch (\Throwable $t) {
      $this->logException($t);
      return $this->executionStatus = false;
    }
    return $this->executionStatus = true;
  }

  /** Forget a temporary file, i.e. purge it from the data-base. */
  private function forgetTemporaryFile($tmpFile)
  {
    $tmpFile = basename($tmpFile);
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
   * @return array Copy of $fileRecord with changed temporary file which
   * survives script-reload, or @c false on error.
   */
  public function saveAttachment(&$fileRecord)
  {
    if (!empty($fileRecord['name'])) {

      $tmpFile = $this->appStorage->newTemporaryFile(AppStorage::DRAFTS_FOLDER);

      $tmpFilePath = AppStorage::PATH_SEP .AppStorage::DRAFTS_FOLDER .AppStorage::PATH_SEP . $tmpFile->getName();

      $origin = empty($fileRecord['node']) ? AttachmentOrigin::UPLOAD() : AttachmentOrigin::CLOUD();

      // Remember the file in the data-base for cleaning up later
      $this->rememberTemporaryFile($tmpFilePath, $origin);

      try {
        if (!empty($fileRecord['node'])) {
          // cloud file
          $tmpFile->putContent($fileRecord['node']->getContent());
        } else {
          // file-system file
          $this->appStorage->moveFileSystemFile($fileRecord['tmp_name'], $tmpFile);
        }

        $fileRecord['tmp_name'] = $tmpFilePath;
        $fileRecord['origin'] = $origin;

      } catch (\Throwable $t) {
        $this->logException($t);
        $tmpFile->delete();
        $this->forgetTemporaryFile($tmpFilePath);
        return false;
      }

      return $fileRecord;
    }
    return false;
  }

  /**
   * Clean expired download attachments and left over preview downloads
   */
  public function registerAttachmentDownloadsCleaner()
  {
    /** @var \OCP\BackgroundJob\IJobList $jobList */
    $jobList = $this->di(\OCP\BackgroundJob\IJobList::class);

    // the job-list takes care by itself that no duplicates are added
    $jobList->add(CleanupExpiredDownloads::class, [
      'paths' => [ $this->getOutBoxFolderPath(), ],
      'uid' => $this->shareOwnerId(),
    ]);
  }

  /**
   * Expire all orphan download shares, e.g. generated by preview
   * messages. The idea is to simply expire all orphan shares. The regular
   * cleanup-job will then delete all shares after a given time (24 hours by
    * default).
   */
  public function cleanAttachmentDownloads()
  {
    $outBoxFolderPath = $this->getOutBoxFolderPath();
    if (empty($outBoxFolderPath)) {
      $this->logError('Outbox folder path "' . $this->getOutBoxFolderPath() . '" not configured');
      return;
    }
    $outboxFolder = $this->userStorage->getFolder($this->getOutBoxFolderPath());
    if (empty($outboxFolder)) {
      $this->logError('Outbox folder "' . $this->getOutBoxFolderPath() . '" could not be found');
      return;
    }

    $numChangedShares = 0;

    /** @var \OCP\Files\Folder $node */
    foreach ($outboxFolder->getDirectoryListing() as $node) {
      if ($node->getType() != \OCP\Files\Node::TYPE_FOLDER
          || strpos($node->getName(), self::MSG_ID_AT) === false) {
        $this->logDebug('The node ' . $node->getName() . ' does not look like originating from a message id, skipping.');
        continue; // does not appear to be a message id
      }
      $messageId = self::messageIdFromOutBoxSubFolder($node->getName());
      $this->logDebug('LOOK FOR MESSAGE ID ' . $messageId);
      $sentEmail = $this->getDatabaseRepository(Entities\SentEmail::class)->find($messageId);
      if (empty($sentEmail)) {
        $this->logDebug('No sent-email with name id "' . $messageId . '" found, could delete.');
        /** @var \OCP\Files\Node $fileAttachment */
        foreach ($node->getDirectoryListing() as $fileAttachment) {
          $this->logDebug('TRY EXPIRE ' . $fileAttachment->getName());
          $numChangedShares +=
            $this->simpleSharingService->expire($fileAttachment, $this->shareOwnerId());
        }
      }
    }
    if ($numChangedShares > 0) {
      // make sure the clean-up service runs
      $this->registerAttachmentDownloadsCleaner();
    }
  }

  // public methods exporting data needed by the web-page template

  /** General form data for hidden input elements.*/
  public function formData()
  {
    return [
      'formStatus' => 'submitted',
      'messageDraftId' => $this->draftId,
      'inReplyTo' => $this->inReplyToId,
      'referencing' => $this->referencing,
    ];
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

    return [
      'drafts' => $drafts,
      'templates' => $templates,
    ];
  }

  /**
   * Return a list of previously sent emails related to the current project.
   */
  public function sentEmails()
  {
    return $this->fetchSentEmailsList();
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

  /** Export the currently replied-to message id */
  public function inReplyTo()
  {
    return $this->inReplyToId;
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

  /**
   * Return non-personalized document templates for use in mass-email.
   *
   * Rationale: personalizing templates takes too much time for
   * mass-email. Also, attaching personalized attachments means that each
   * recipients has to be addressed by its own private email. So for
   * mass-email we just want the unfilled PDF forms.
   */
  private function blankTemplateAttachments()
  {
    if ($this->templateFileAttachments !== null) {
      return $this->templateFileAttachments;
    }

    $selectedAttachments = array_flip($this->cgiValue('attachedFiles', []));

    $this->templateFileAttachments = [];

    $origin = AttachmentOrigin::TEMPLATE;

    $templateAttachments = [];
    foreach (ConfigService::DOCUMENT_TEMPLATES as $templateId => $documentTemplate) {
      if ($documentTemplate['type'] != ConfigService::DOCUMENT_TYPE_TEMPLATE
          || !$documentTemplate['blank']) {
        continue;
      }
      $attachment = [
        'value' => 'template_id',
        'template_id' => $templateId,
        'name' => $this->l->t($documentTemplate['name']),
        'origin' => $origin,
        'sub_selection' => false,
      ];
      if (isset($selectedAttachments[$origin . ':' . $attachment[$attachment['value']]])) {
        $attachment['status'] = 'selected';
      } else {
        $attachment['status'] = 'inactive';
      }
      $templateAttachments[] = $attachment;
    }

    $comparator = function($a, $b) {
      return strcmp(
        $a['origin'].$a['name'],
        $b['origin'].$b['name']
      );
    };
    usort($templateAttachments, $comparator);

    return $this->templateFileAttachments = $templateAttachments;
  }

  /**
   * Return the file attachment data.
   *
   * @return array
   * ```
   * [
   *   [
   *     'value' => 'field_id',
   *     'status' => STATUS,
   *     'field_id' => NAME_AS_STORED_ON_THE_SERVER,
   *     'name' => NAME,
   *     ...
   *   ],
   *     ...
   * ]
   * ```
   */
  private function personalAttachments()
  {
    if ($this->personalFileAttachments !== null) {
      return $this->personalFileAttachments;
    }

    if (empty($this->project)) {
      $this->personalFileAttachments = [];
      return $this->personalFileAttachments;
    }
    $selectedAttachments = array_flip($this->cgiValue('attachedFiles', []));

    // add participant fields data if present
    $this->personalFileAttachments = [];

    $origin = AttachmentOrigin::PARTICIPANT_FIELD;

    $generalAttachments = [];
    $serviceFeeAttachments = [];
    $templateAttachments = [];

    /** @var Entities\ProjectParticipantField $participantField */
    foreach ($this->project->getParticipantFields() as $participantField) {
      $fieldType = $participantField->getDataType();
      if ($fieldType != FieldType::CLOUD_FILE
          && $fieldType != FieldType::CLOUD_FOLDER
          && $fieldType != FieldType::DB_FILE
          && $fieldType != FieldType::SERVICE_FEE) {
        continue;
      }
      $fieldId = $participantField->getId();
      $fieldName = $participantField->getName();
      $attachment = [
        'value' => 'field_id',
        'field_id' => $fieldId,
        'name' => $fieldName,
        'origin' => $origin,
        'sub_selection' => false,
        'sub_options' => [],
      ];
      if (isset($selectedAttachments[$origin . ':' . $attachment[$attachment['value']]])) {
        $attachment['status'] = 'selected';
      } else {
        $attachment['status'] = 'inactive';
      }
      if ($fieldType == FieldType::SERVICE_FEE) {
        $attachment['sub_topic'] = 'bills and receipts';
        // split only the recurring receivables as there may be so many of them ...
        if ($participantField->getMultiplicity() == FieldMultiplicity::RECURRING) {
          // add sub-options in the same manner
          /** @var Entities\ProjectParticipantFieldDataOption $option */
          foreach ($participantField->getSelectableOptions() as $option) {
            $label = $option->getLabel();
            if (strpos($label, $fieldName) !== 0) {
              $label = $fieldName . ' - ' . $label;
            }
            $subOption = [
              'value' => 'option_key',
              'option_key' => (string)$option->getKey(),
              'name' => $label,
              'origin' => $origin . '-option',
            ];
            if (isset($selectedAttachments[$origin . ':' . $attachment[$attachment['value']] . ':' . $subOption[$subOption['value']]])) {
              $subOption['status'] = 'selected';
              $attachment['sub_selection'] = true;
            } else {
              $subOption['status'] = 'inactive';
            }
            $attachment['sub_options'][] = $subOption;
          }
          usort($attachment['sub_options'], function($a, $b) { return strcmp($a['name'], $b['name']); });
        }
        $serviceFeeAttachments[] = $attachment;
      } else {
        $attachment['sub_topic'] = 'general';
        $generalAttachments[] = $attachment;
      }
    }

    // add also the global document templates
    foreach (ConfigService::DOCUMENT_TEMPLATES as $templateId => $documentTemplate) {
      if ($documentTemplate['type'] != ConfigService::DOCUMENT_TYPE_TEMPLATE) {
        continue;
      }
      $attachment = [
        'value' => 'template_id',
        'template_id' => $templateId,
        'name' => $this->l->t($documentTemplate['name']),
        'origin' => $origin,
        'sub_topic' => ConfigService::DOCUMENT_TYPE_TEMPLATE,
        'sub_selection' => false,
      ];
      if ((!empty($this->bulkTransaction) && $templateId == ConfigService::DOCUMENT_TEMPLATE_MEMBER_DATA_UPDATE)
          || isset($selectedAttachments[$origin . ':' . $attachment[$attachment['value']]])) {
        $attachment['status'] = 'selected';
      } else {
        $attachment['status'] = 'inactive';
      }
      $templateAttachments[] = $attachment;
    }

    $comparator = function($a, $b) {
      return strcmp(
        $a['origin'].$a['sub_topic'].$a['name'],
        $b['origin'].$b['sub_topic'].$b['name']
      );
    };
    usort($generalAttachments, $comparator);
    usort($serviceFeeAttachments, $comparator);
    usort($templateAttachments, $comparator);

    $this->personalFileAttachments = array_merge($templateAttachments, $generalAttachments, $serviceFeeAttachments);

    return $this->personalFileAttachments;
  }

  private function activePersonalAttachments()
  {
    $numAttachments = 0;
    // walk through the list of configured attachments and attach all requested.
    foreach ($this->personalAttachments() as $attachment) {
      if ($attachment['status'] != 'selected' && !$attachment['sub_selection']) {
        continue;
      }
      ++$numAttachments;
    }
    return $numAttachments;
  }

  /**
   * Return the file attachment data.
   *
   * @return array
   * ```
   * [
   *   [
   *     'value' => 'tmp_name',
   *     'status' => STATUS,
   *     'tmp_name' => NAME_AS_STORED_ON_THE_SERVER,
   *     'name' => NAME,
   *     ...
   *   ],
   *     ...
   * ]
   * ```
   */
  public function fileAttachments()
  {
    if ($this->globalFileAttachments !== null) {
      return $this->globalFileAttachments;
    }

    // JSON encoded array
    $fileAttachJSON = $this->cgiValue('fileAttachments', '{}');
    $fileAttach = json_decode($fileAttachJSON, true);
    $selectedAttachments = array_flip($this->cgiValue('attachedFiles', []));

    $localFileAttach = [];
    $cloudFileAttach = [];
    foreach($fileAttach as $origin => $attachment) {
      $attachment['value'] = 'tmp_name';
      $origin = $attachment['origin'];
      if ($attachment['status'] == 'new') {
        $attachment['status'] = 'selected';
      } else if (isset($selectedAttachments[$origin . ':' . $attachment['tmp_name']])) {
        $attachment['status'] = 'selected';
      } else {
        $attachment['status'] = 'inactive';
      }
      // Keep only the basename part as the folder is programmatically fixed.
      $attachment['name'] = basename($attachment['name']);
      if ($attachment['origin'] == AttachmentOrigin::CLOUD) {
        $cloudFileAttach[] = $attachment;
      } else {
        $localFileAttach[] = $attachment;
      }
    }

    $comparator = function($a, $b) { return strcmp($a['name'], $b['name']); };
    usort($cloudFileAttach, $comparator);
    usort($localFileAttach, $comparator);

    $this->globalFileAttachments = array_merge($localFileAttach, $cloudFileAttach);

    return $this->globalFileAttachments;
  }

  /**
   * A helper function to generate suitable select options for
   * PageNavigation::selectOptions()
   */
  public function fileAttachmentOptions()
  {
    $fileAttachments = array_merge($this->fileAttachments(), $this->blankTemplateAttachments(), $this->personalAttachments());

    $selectOptions = [];
    foreach($fileAttachments as $attachment) {
      $value = $attachment[$attachment['value']];
      $name = $attachment['name'];
      if (isset($attachment['size'])) {
        $size = \OC_Helper::humanFileSize($attachment['size']);
        $name .= ' (' . $size . ')';
      }
      $origin = $attachment['origin'];
      switch ($origin) {
        case AttachmentOrigin::TEMPLATE: $group = $this->l->t('Blank Template'); $name .= ' (' . $this->l->t('blank') . ')'; break;
        case AttachmentOrigin::PARTICIPANT_FIELD: $group = $this->l->t('Personalized'); break;
        case AttachmentOrigin::CLOUD: $group = $this->l->t('Cloud'); break;
        case AttachmentOrigin::UPLOAD: $group = $this->l->t('Local Filesystem'); break;
      }
      $groupData = [ 'origin' => $origin, ];
      if (isset($attachment['sub_topic'])) {
        $group .= ' - ' . $this->l->t($attachment['sub_topic']);
        $groupData['sub_topic'] = $attachment['sub_topic'];
      }
      $selected = $attachment['status'] == 'selected';
      $selectOption = [
        'value' => $origin . ':' . $value,
        'name' => $name,
        'group' => $group,
        'groupData' => $groupData,
        'flags' => $selected ? PageNavigation::SELECTED : 0,
      ];
      $selectOptions[] = $selectOption;
      foreach ($attachment['sub_options']??[] as $subOption) {
        $selected = $attachment['status'] == 'selected'
          || $subOption['status'] == 'selected';
        $selectOptions[] = [
          'value' => $selectOption['value'] . ':' . $subOption[$subOption['value']],
          'name' => $subOption['name'],
          'group' => $selectOption['group'],
          'groupData' => $groupData,
          'flags' => $selected ? PageNavigation::SELECTED : 0,
          'data' => [ 'parent' => $selectOption['value'] ],
        ];
      }
    }
    return $selectOptions;
  }

  /**
   * Return the calendar attachment data.
   *
   * @return array
   * ```
   * [ URI => CAL_ID, ... ]
   * ```
   */
  public function eventAttachments()
  {
    $attachedEvents = $this->parameterService->getParam(
      'eventSelect', $this->cgiValue('attachedEvents', []));
    $events = [];
    foreach ($attachedEvents as $event) {
      $event = json_decode($event, true);
      $events[$event['uri']] = $event['calendarId'];
    }

    return $events;
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

    // build the select option control array
    $selectOptions = [];
    foreach ($eventMatrix as $eventGroup) {
      $group = $this->l->t($eventGroup['name']);
      foreach ($eventGroup['events'] as $event) {
        $datestring = $this->eventsService->briefEventDate($event, $timezone, $locale);
        $name = stripslashes($event['summary']).', '.$datestring;
        $value = json_encode([ 'uri' => $event['uri'], 'calendarId' => $event['calendarid'], ]);
        $selectOptions[] = [
          'value' => $value,
          'name' => $name,
          'group' => $group,
          'flags' => isset($attachedEvents[$event['uri']]) ? PageNavigation::SELECTED : 0
        ];
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

  private function formatExceptionMessage(\Throwable $t)
  {
    return $this->l->t('code %1$d, %2$s:%3$d -- %4$s', [
      $t->getCode(), $t->getFile(), $t->getLine(), $t->getMessage()
    ]);
  }
}
