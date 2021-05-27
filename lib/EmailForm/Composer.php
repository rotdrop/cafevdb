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

use OCP\IDateTimeFormatter;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\InstrumentationService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Service\ProgressStatusService;
use OCA\CAFEVDB\Service\ConfigCheckService;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\Service\Finance\SepaBulkTransactionService;
use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\PHPMailer;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;

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

  const PROGRESS_CHUNK_SIZE = 4096;
  const PROGRESS_THROTTLE_SECONDS = 2;

  const POST_TAG = 'emailComposer';

  const ATTACHMENT_ORIGIN_CLOUD = 'cloud';
  const ATTACHMENT_ORIGIN_UPLOAD = 'upload';

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
    FIRST_NAME,
    SUR_NAME,
    NICK_NAME,
    DISPLAY_NAME,
    EMAIL,
    MOBILE_PHONE,
    FIXED_LINE_PHONE,
    STREET,
    POSTAL_CODE,
    CITY,
    COUNTRY,
    LANGUAGE,
    BIRTHDAY,
    TOTAL_FEES,
    AMOUNT_PAID,
    MISSING_AMOUNT,
    PROJECT_DATA,
    SEPA_MANDATE_REFERENCE,
    BANK_ACCOUNT_IBAN,
    BANK_ACCOUNT_BIC,
    BANK_ACCOUNT_OWNER,
    BANK_TRANSACTION_AMOUNT,
    BANK_TRANSACTION_PURPOSE,
    BANK_TRANSACTION_PARTS,
    DATE,
  ];
  /**
   * @var string
   * @todo Make this configurable
   */
  const DEFAULT_TRANSACTION_PARTS_TEMPLATE = [
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
  ];
  const DEFAULT_TRANSACTION_PARTS_STYLE = '<style>
table.transaction-parts,
table.transaction-parts tr,
table.transaction-parts th,
table.transaction-parts td {
  border-collapse:collapse;
}
table.transaction-parts th,
table.transaction-parts td {
  border: 1px solid black;
  padding: 0 2pt;
}
table.transaction-parts th {
  text-align:center;
  font-weight:bold;
}
table.transaction-parts td { text-align:left; }
table.transaction-parts tr.totalsum {
  border-top:double;
}
table.transaction-parts tr.totalsum td {
  text-align:right;
  font-weight:bold;
}
table.transaction-parts td.money {
  text-align:right;
  padding-left: 1em;
 }
</style>';
  const DEFAULT_PARTICIPANT_MONETARY_FIELDS_TEMPLATE = [
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
  ];
  const PARTICIPANT_MONETARY_FIELDS_CSS_CLASS = [
    'header' => 'monetary-fields',
    'fieldHeader' => 'field-header',
    'row' => 'field-option',
    'footer' => 'footer totalsum',
  ];
  const DEFAULT_PARTICIPANT_MONETARY_FIELDS_STYLE = '<style>
table.monetary-fields,
table.monetary-fields tr,
table.monetary-fields th,
table.monetary-fields td {
  border-collapse:collapse;
}
table.monetary-fields th,
table.monetary-fields td {
  border: 1px solid black;
  padding: 0 2pt;
}
table.monetary-fields tr.field-header {
  border-top:double;
  border-bottom:2px solid black;
}
table.monetary-fields tr.field-header td {
  font-style:italic;
  text-align:center;
}
table.monetary-fields th {
  font-weight:bold;
  text-align:center;
}
table.monetary-fields tbody.field-option.number-of-options-1 td.row-label {
  font-style:italic;
}
table.monetary-fields td.date {
  font-style:italic;
  text-align:center;
  opacity:0;
}
table.monetary-fields tbody.footer td.date,
table.monetary-fields tbody.number-of-options-1 td.date {
  opacity:inherit;
}
table.monetary-fields td.money {
  text-align:right;
  padding-left: 1em;
}
table.monetary-fields tr.totalsum {
  border-top:3px double black;
  border-bottom:3px double black;
}
table.monetary-fields tr.totalsum td {
  font-weight:bold;
}
table.monetary-fields tr.totalsum td.row-label {
  text-align:right;
}
table.monetary-fields.number-of-fields-0 tr.totalsum,
table.monetary-fields.number-of-fields-1 tr.totalsum,
table.monetary-fields tbody.field-header.number-of-options-0,
table.monetary-fields tbody.field-header.number-of-options-1,
table.monetary-fields tr.field-header.number-of-options-0,
table.monetary-fields tr.field-header.number-of-options-1,
table.monetary-fields tbody:empty {
  display:none;
}
</style>';

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

  /** @var AppStorage */
  private $appStorage;

  /** @var int */
  private $progressToken;

  /** @var array */
  private $substitutions;

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
    , AppStorage $appStorage
  ) {
    $this->configService = $configService;
    $this->eventsService = $eventsService;
    $this->progressStatusService = $progressStatusService;
    $this->appStorage = $appStorage;
    $this->entityManager = $entityManager;
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

    // @todo fill with real contents
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
      $result = $this->formatDate($date, $keyArg[1]?:'full');

      return $result;
    };

    $this->substitutions[self::MEMBER_NAMESPACE]['DATE'] =  function(array $keyArg, ?Entities\Musician $musician) {
      if (empty($musician)) {
        return $keyArg[0];
      }
      return $this->dateSubstitution($keyArg, self::MEMBER_NAMESPACE, $musician);
    };

    $this->substitutions[self::MEMBER_NAMESPACE]['TOTAL_FEES'] =  function(array $keyArg, ?Entities\Musician $musician) use ($obligations) {
      if (empty($musician)) {
        return $keyArg[0];
      }
      $obligations = ProjectParticipantFieldsService::participantMonetaryObligations($musician, $this->project);
      return $this->moneyValue($obligations['sum']);
    };

    $this->substitutions[self::MEMBER_NAMESPACE]['AMOUNT_PAID'] =  function(array $keyArg, ?Entities\Musician $musician) use ($obligations) {
      if (empty($musician)) {
        return $keyArg[0];
      }
      $obligations = ProjectParticipantFieldsService::participantMonetaryObligations($musician, $this->project);
      return $this->moneyValue($obligations['received']);
    };

    $this->substitutions[self::MEMBER_NAMESPACE]['MISSING_AMOUNT'] =  function(array $keyArg, ?Entities\Musician $musician) use ($obligations) {
      if (empty($musician)) {
        return $keyArg[0];
      }
      $obligations = ProjectParticipantFieldsService::participantMonetaryObligations($musician, $this->project);
      return $this->moneyValue($obligations['sum'] - $obligations['received']);
    };

    // per-participant project-data
    $this->substitutions[self::MEMBER_NAMESPACE]['PROJECT_DATA'] =  function(array $keyArg, ?Entities\Musician $musician) use ($obligations) {
      if (empty($musician)) {
        return $keyArg[0];
      }

      /** @var Entities\ProjectParticipant $projectParticipant */
      $projectParticipant = $musician->getProjectParticipantOf($this->project);

      $participantFields = $this->project->getParticipantFields();
      $fieldsByType = [
        'monetary' => $participantFields->filter(function($field) {
          /** @var Entities\ProjectParticipantField $field */
          return ($field->getDataType() == FieldType::SERVICE_FEE
                  || $field->getDataType() == FieldType::DEPOSIT);
        }),
        'files' => $participantFields->filter(function($field) {
          /** @var Entities\ProjectParticipantField $field */
          return ($field->getDataType() == FieldType::CLOUD_FILE
                  || $field->getDataType() == FieldType::DB_FILE);
        }),
        'other' => $participantFields->filter(function($field) {
          /** @var Entities\ProjectParticipantField $field */
          return ($field->getDataType() != FieldType::SERVICE_FEE
                  && $field->getDataType() != FieldType::DEPOSIT
                  && $field->getDataType() != FieldType::CLOUD_FILE
                  && $field->getDataType() != FieldType::DB_FILE);
        }),
      ];

      if (count($keyArg) == 2) {
        $found = false;
        $selector = strtolower($keyArg[1]);
        $specificField = $participantFields->filter(function($field) use ($selector) {
          /** @var Entities\ProjectParticipantField $field */
          return strtolower($field->getName()) == $selector;
        });
        if ($specificField->count() == 1) {
          $found = true;
          switch ($specificField->first()->getDataType()) {
          case FieldType::SERVICE_FEE:
          case FieldType::DEPOSIT:
            $fieldsByType = ['monetary' => $specificField ];
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
            $this->logInfo('TYPES '.$type.' / '.print_r($variants, true));
            if (array_search($selector, $variants) !== false) {
              $this->logInfo('SPECIFIC '.$selector);
              $fieldsByType = [ $type => $fieldsByType[$type] ];
              $found = true;
              break;
            }
          }
        }
        if (!$found) {
          return $keyArg[0];
        }
      }

      $html = '';
      foreach ($fieldsByType as $type => $fields) {

        $numberOfFields = $fields->count();

        // First output the simple options, then the ones with
        // multiple options, then the recurring options. Also sort by
        // name.
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
              $html .= '<li>'.$field->getName().'</li>';
            }
          }
          $html .= '</ul>';
        } else if ($type == 'monetary') {

          $headerReplacements = [
            'option' => $this->l->t('Option'),
            'totals' => $this->l->t('Total Amount'),
            'received' => $this->l->t('Received'),
            'remaining' => $this->l->t('Remaining'),
            'dueDate' => $this->l->t('Due Date'),
          ];
          $replacementKeys = array_keys($headerReplacements);
          $header = self::DEFAULT_PARTICIPANT_MONETARY_FIELDS_TEMPLATE['header'];
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

          /** @var Entities\ProjectParticipantField $field */
          foreach ($fieldsByMultiplicity as $multiplicity => $fields) {
            foreach ($fields as $field) {

              // for field->options
              //   - check if set for participant
              //   - generate sub-row with value
              //   - for recurring fields only include the "booked" ones
              //   - for single values omit the header
              //   - include payment information

              $dueDate = $field->getDueDate();
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
                $this->logInfo('DUEDATE '.$field->getName().' '.$dueDate);
              }

              $numberOfOptions = $field->getSelectableOptions()->count();

              // generate a field-header for multiple options
              $replacements = [
                'field-name' => $field->getName(),
                'dueDate' => $dueDate,
              ];

              $fieldHeader = self::DEFAULT_PARTICIPANT_MONETARY_FIELDS_TEMPLATE['fieldHeader'];
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

                $option = $fieldOption->getLabel() ?: $field->getName();

                $fieldData = $projectParticipant->getParticipantFieldsDatum($fieldOption->getKey());
                if (!empty($fieldData)) {
                  $totals = $fieldData->amountPayable();
                  $received = $fieldData->amountPaid();
                  $remaining = $totals - $received;
                } else {
                  $totals = '--';
                  $received = '--';
                  $remaining = '--';
                }

                // compute substitution values
                $replacements = [];
                foreach ($replacementKeys as $key) {
                  if ($key == 'option' || $key == 'dueDate' ) {
                    $replacements[$key] = ${$key};
                    continue;
                  }
                  if (${$key} != '--') {
                    $totalSum[$key] += ${$key};
                    $replacements[$key] = $this->moneyValue(${$key});
                  } else {
                    $replacements[$key] = ${$key};
                  }
                }

                // inject into template
                $row = self::DEFAULT_PARTICIPANT_MONETARY_FIELDS_TEMPLATE['row'];
                foreach ($replacementKeys as $key) {
                  $keyVariants = array_map(
                    function($key) { return '['.$key.']'; },
                    $this->translationVariants($key)
                  );
                  $row = str_ireplace($keyVariants, $replacements[$key], $row);
                }
                $row = str_replace('[CSSROWCLASS]', $cssRowClass, $row);
                $html .= $row;
              }
            }
          }

          $footer = self::DEFAULT_PARTICIPANT_MONETARY_FIELDS_TEMPLATE['footer'];
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
          ]);
          $footer = str_replace('[CSSCLASS]', $cssClass, $footer);
          $html .= $footer;
        }
      }

      return $html;
    };

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
            'header' => $keyArg[1]?:self::DEFAULT_TRANSACTION_PARTS_TEMPLATE['header'],
            'row' => $keyArg[2]?:self::DEFAULT_TRANSACTION_PARTS_TEMPLATE['row'],
            'footer' => $keyArg[3]?:self::DEFAULT_TRANSACTION_PARTS_TEMPLATE['footer'],
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
        $this->substitutions[$nameSpace][$this->l->t($key)] = function(array $keyArg, ?Entities\Musician $musician) use ($handler) { return $handler($keyArg, $musician); };
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
        $handler = $this->substitutions[$nameSpace][$variable[0]];
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
    $messageTemplate = self::DEFAULT_TRANSACTION_PARTS_STYLE
                     . self::DEFAULT_PARTICIPANT_MONETARY_FIELDS_STYLE
                     . $this->replaceFormVariables(self::GLOBAL_NAMESPACE);

    if ($this->hasSubstitutionNamespace(self::MEMBER_NAMESPACE, $messageTemplate)) {

      $this->diagnostics['TotalPayload'] = count($this->recipients)+1;

      foreach ($this->recipients as $recipient) {
        /** @var Entities\Musician */
        $musician = $recipient['dbdata'];
        $strMessage = $this->replaceFormVariables(self::MEMBER_NAMESPACE, $musician, $messageTemplate);
        $strMessage = $this->finalizeSubstitutions($strMessage);

        $databaseAttachments = [];
        if (!empty($this->bulkTransaction)) {
          // find payments and potential attachments

          /** @var Entities\CompositePayment $compositePayment */
          $compositePayment = $this->bulkTransaction->getPayments()->get($musician->getId());
          if (!empty($compositePayment)) {
            /** @var Entities\ProjectPayment $projectPayment */
            foreach ($compositePayment->getProjectPayments() as $projectPayment) {
              $supportingDocument = $projectPayment->getSupportingDocument();
              if (!empty($supportingDocument)) {
                $databaseAttachments[] = $supportingDocument;
              } else {
                $supportingDocument = $projectPayment->getReceivable()->getSupportingDocument();
                if (!empty($supportingDocument)) {
                  $databaseAttachments[] = $supportingDocument;
                }
              }
            }
          }
        }
        $msg = $this->composeAndSend($strMessage, [ $recipient ], $databaseAttachments, false);
        ++$this->diagnostics['TotalCount'];
        if (!empty($msg['message'])) {
          $this->copyToSentFolder($msg['message']);
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
      $mimeMsg = $this->composeAndSend($messageTemplate, [], [], true);
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
   * @param string $strMessage The message to send.
   *
   * @param array $EMails The recipient list
   *
   * @param array $databaseAttachements
   *
   * @param bool $addCC If @c false, then additional CC and BCC recipients will
   *                   not be added.
   *
   * @return string The sent Mime-message which then may be stored in the
   * Sent-Folder on the imap server (for example).
   */
  private function composeAndSend($strMessage, $EMails, $databaseAttachments = [], $addCC = true)
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

      $phpMailer = new PHPMailer(true);
      $phpMailer->CharSet = 'utf-8';
      $phpMailer->SingleTo = false;

      $phpMailer->IsSMTP();

      // Provide some progress feed-back to amuse the user
      $progressStatus = $this->progressStatusService->get($this->progressToken);
      $progressStatus->update(0, null, [
        'proto' => 'smtp',
        'total' =>  $this->diagnostics['TotalPayload'],
        'active' => $this->diagnostics['TotalCount'],
      ]);
      $phpMailer->progressCallback = function($current, $total) use ($progressStatus) {
        $oldCurrent = $progressStatus->getCurrent();
        $oldTime = $progressStatus->getLastModified()->getTimestamp();
        $nowTime = time();
        if ($current >= $total
            || ($current - $progressStatus->getCurrent() >= self::PROGRESS_CHUNK_SIZE
                && $nowTime - $oldTime >= self::PROGRESS_THROTTLE_SECONDS)) {
          $progressStatus->update($current, $total);
        }
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
        $this->logInfo('CONSTRUCTION MODE');
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
      foreach ($attachments as $attachment) {
        if ($attachment['status'] != 'selected') {
          continue;
        }
        if ($attachment['type'] == 'message/rfc822') {
          $encoding = '8bit';
        } else {
          $encoding = 'base64';
        }
        $file = $this->appStorage->getDraftsFile($attachment['tmp_name']);
        $phpMailer->AddStringAttachment(
          $file->getContent(),
          basename($attachment['name']),
          $encoding,
          $attachment['type']);
      }

      // Finally possibly to-be-attached events. This cannot throw,
      // but it does not hurt to keep it here. This way we are just
      // ready with adding data to the message inside the try-block.
      $events = $this->eventAttachments();
      if ($this->projectId > 0 && !empty($events)) {
        // Construct the calendar
        $calendar = $this->eventsService->exportEvents($events, $this->projectName);

        // Encode it as attachment
        $phpMailer->AddStringEmbeddedImage($calendar,
                                           md5($this->projectName.'.ics'),
                                           $this->projectName.'.ics',
                                           'quoted-printable',
                                           'text/calendar');
      }

      // add database-attachment
      /** @var Entities\EncryptedFile $encryptedFile */
      foreach ($databaseAttachments as $encryptedFile) {
        $phpMailer->addStringAttachment(
          $encryptedFile->getFileData()->getData(),
          $encryptedFile->getFileName(),
          'base64',
          $encryptedFile->getMimeType());
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

    /** @var Entities\SentEmail $sentEmail */
    $sentEmail = $this->sentEmail($logMessage);
    if (!$sentEmail) {
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
        // catch errors?
        $sentEmail->setMessageId($phpMailer->getLastMessageID());
        $this->persist($sentEmail);
        $this->flush();
      }
    } catch (\Throwable $t) {
      $this->executionStatus = false;
      $this->diagnostics['MailerExceptions'][] =
        $t->getFile() . '(' . $t->getLine() . '): ' . $t->getMessage();

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

    $sentEmail->setBulkRecipients(implode(';', $bulkRecipients))
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

      $phpMailer = new PHPMailer(true);
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
      if ($this->projectId > 0 && !empty($events)) {
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
      $this->logException($exception);
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
      $this->logException($exception);
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
    $previewRecipients = $this->recipients;
    if (empty($previewRecipients)) {
      /** @var Entities\Musician $dummy */
      $dummy = $this->appContainer()->get(InstrumentationService::class)->getDummyMusician();
      $displayName = $dummy['displayName']?: ($dummy['nickName']?:$dummy['firstName']).' '.$dummy['surName'];
      $previewRecipients = [
        $dummy->getId() => [
          'email' => $dummy->getEmail(),
          'name' => $displayName,
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
   */
  private function exportMessages(?array $recipients = null)
  {
    // @todo yield needs more care concerning error management
    $messages = [];

    // The following cannot fail, in principle. $message is then
    // the current template without any left-over globals.
    $messageTemplate = self::DEFAULT_TRANSACTION_PARTS_STYLE
                     . self::DEFAULT_PARTICIPANT_MONETARY_FIELDS_STYLE
                     . $this->replaceFormVariables(self::GLOBAL_NAMESPACE);

    if ($this->hasSubstitutionNamespace(self::MEMBER_NAMESPACE, $messageTemplate)) {

      $this->diagnostics['totalPayload'] = count($recipients)+1;


      foreach ($recipients as $recipient) {
        /** @var Entities\Musician */
        $musician = $recipient['dbdata'];
        $strMessage = $this->replaceFormVariables(self::MEMBER_NAMESPACE, $musician, $messageTemplate);
        $strMessage = $this->finalizeSubstitutions($strMessage);
        ++$this->diagnostics['totalCount'];
        $message = $this->composeAndExport($strMessage, [ $recipient ], false);
        if (empty($message)) {
          ++$this->diagnostics['failedCount'];
          return;
        }
        $messages[] = $message;
        //yield $message;
      }

      // Finally send one message without template substitution (as
      // this makes no sense) to all Cc:, Bcc: recipients and the
      // catch-all. This Message also gets copied to the Sent-folder
      // on the imap server.
      $messageTemplate = $this->finalizeSubstitutions($messageTemplate);
      ++$this->diagnostics['totalCount'];
      $message = $this->composeAndExport($messageTemplate, [], true);
      if (empty($message)) {
        ++$this->diagnostics['failedCount'];
        return;
      }
      $messages[] = $message;
      // yield $message;
    } else {
      $this->diagnostics['totalPayload'] = 1;
      ++$this->diagnostics['totalCount']; // this is ONE then ...
      $messageTemplate = $this->finalizeSubstitutions($messageTemplate);
      $message = $this->composeAndExport($messageTemplate, $recipients);
      if (empty($message)) {
        ++$this->diagnostics['failedCount'];
        return;
      }
      $messages[] = $message;
      // yield $message;
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
      $dateFormat = $arg[2]?:'long';

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

    $this->substitutions[self::GLOBAL_NAMESPACE] = [
      'ORGANIZER' => function($key) {
        return $this->fetchExecutiveBoard();
      },
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
        $translatedWord = $this->l->t($word);
        if ($translatedWord == $word) {
          $words = null;
          break;
        }
        $words[] = $translatedWord;
      }
      if (!empty($words)) {
        $translation = Util::dashesToCamelCase(implode(' ', $words), true, ' ');
        array_unshift($result, $translation);
      }
    }
    return $result;
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
      'bulkTransactionId' => $this->parameterService['bulkTransactionId'],
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
                    ->find($this->draftId);
      if (!empty($draft)) {
        $draft->setSubject($subject)
              ->setData($draftData);
      } else {
        $this->draftId = 0;
      }
    }
    if (empty($draft)) {
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

    if (empty($draftData['bulkTransactionId'])) {
      $draftData['bulkTransactionId'] = -1;
    }

    $this->draftId = $draftId;

    $this->executionStatus = true;

    return $draftData;
  }

  /** Delete the current message draft. */
  public function deleteDraft()
  {
    if ($this->draftId > 0 )  {
      // detach any attachnments for later clean-up
      if (!$this->detachTemporaryFiles()) {
        return false;
      }

      try {
        $this->setDatabaseRepository(Entities\EmailDraft::class);
        $this->remove($this->draftId, true);
      } catch (\Throwable $t) {
        $this->entityManager->reopen();
        $this->logException($t);
        $this->diagnostics['caption'] = $this->l->t(
          'Deleting draft with id %d failed: %s',
          [ $this->draftId, $t->getMessage() ]);
        return $this->executionStatus = false;
      }

      // Mark as gone
      $this->draftId = -1;
    }
    return $this->executionStatus = true;
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
        ->findBy([ 'user' => $this->userId(), 'draft' => null ]);
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

  /** Detach temporaries from a draft, i.e. after deleting the draft. */
  private function detachTemporaryFiles()
  {
    try {
      $this->queryBuilder()
           ->update(Entities\EmailAttachment::class, 'ea')
           ->set('ea.draft', 'null')
           ->set('ea.user', ':user')
           ->where($this->expr()->eq('ea.draft', ':id'))
           ->setParameter('user', $this->userId())
           ->setParameter('id', $this->draftId)
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
   */
  private function rememberTemporaryFile(string $tmpFile)
  {
    $tmpFile = basename($tmpFile);
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
   *
   * @todo Use IAppData and use temporaries in the cloud storage.
   */
  public function saveAttachment(&$fileRecord)
  {
    if (!empty($fileRecord['name'])) {

      $tmpFile = $this->appStorage->newTemporaryFile(AppStorage::DRAFTS_FOLDER);

      $tmpFilePath = AppStorage::PATH_SEP.AppStorage::DRAFTS_FOLDER.AppStorage::PATH_SEP.$tmpFile->getName();

      // Remember the file in the data-base for cleaning up later
      $this->rememberTemporaryFile($tmpFilePath);

      try {
        if (!empty($fileRecord['node'])) {
          // cloud file
          $tmpFile->putContent($fileRecord['node']->getContent());
        } else {
          // file-system file
          $this->appStorage->moveFileSystemFile($fileRecord['tmp_name'], $tmpFile);
        }

        $fileRecord['tmp_name'] = $tmpFilePath;

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
        $datestring = $this->eventsService->briefEventDate($event, $timezone, $locale);
        $name = stripslashes($event['summary']).', '.$datestring;
        $value = $event['uri'];
        $selectOptions[] = [
          'value' => $value,
          'name' => $name,
          'group' => $group,
          'flags' => isset($attachedEvents[$value]) ? PageNavigation::SELECTED : 0
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
}
