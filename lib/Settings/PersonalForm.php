<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020-2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\App\IAppManager;
use OCP\IInitialStateService;
use OCP\Util as CoreUtil;

use Cmixin\BusinessDay;

use OCA\CAFEVDB\AddressBook\AddressBookProvider;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Controller\EnumPersonalSettingsKey;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipationStatus as ParticipationStatus;
use OCA\CAFEVDB\Service\AssetService;
use OCA\CAFEVDB\Service\CloudUserConnectorService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ErrorService;
use OCA\CAFEVDB\Service\GeoCodingService;
use Composer\Spdx\SpdxLicenses;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Service\WikiRPC;
use OCA\CAFEVDB\Service\WebPagesRPC;
use OCA\CAFEVDB\Wrapped\Carbon\CarbonImmutable;

/**
 * Simple helper class in order to avoid instantiation of a bunch of
 * helper classes just for the sake of creating the menu entry in the
 * settings page.
 */
class PersonalForm
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Wrapped\OCA\RotDrop\Toolkit\Traits\ResponseTrait;

  public const ERROR_TEMPLATE = "errorpage";
  public const TEMPLATE = "personal-settings";
  public const DEFAULT_EDITOR = 'tinymce';

  public const CSP_FAILURE_REPORTING_KEY = 'cspfailurereporting';

  /** {@inheritdoc} */
  public function __construct(
    protected ConfigService $configService,
    private AssetService $assetService,
    private ProjectService $projectService,
    private ErrorService $errorService,
    private IInitialStateService $initialStateService,
    private IAppManager $appManager,
    private WikiRPC $wikiRPC,
    private WebPagesRPC $webPagesRPC,
    private AddressBookProvider $addressBookProvider,
    private UserStorage $userStorage,
    private CloudUserConnectorService $cloudUserService,
    private OrganizationalRolesService $roles,
    private GeoCodingService $geoCodingService,
  ) {
    $this->l = $this->l10N();
  }

  /**
   * Forward from Personal::getForm()
   *
   * @return TemplateResponse
   *
   * @see \OCP\Settings\ISettings
   */
  public function getForm()
  {
    CoreUtil::addScript($this->appName(), $this->assetService->getJSAsset(self::TEMPLATE));
    CoreUtil::addStyle($this->appName(), $this->assetService->getCSSAsset(self::TEMPLATE));

    if (!$this->inGroup()) {
      return $this->templateResponse(
        self::ERROR_TEMPLATE,
        [
          'error' => 'notamember',
          'userId' => $this->userId(),
        ],
      );
    }
    try {
      // Are we a group-admin?
      $isGroupAdmin = $this->isSubAdminOfGroup() && $this->encryptionKeyValid();

      // Initial state injecton for JS
      $this->initialStateService->provideInitialState(
        $this->appName(),
        'CAFEVDB',
        [
          'appName' => $this->appName(),
          'isGroupAdmin' => $isGroupAdmin,
          'financeModeAllowed' => $this->roles->inTreasurerGroup(),
          EnumPersonalSettingsKey::TOOL_TIPS_ENABLED->value => $this->getUserValue(EnumPersonalSettingsKey::TOOL_TIPS_ENABLED, 'on'),
          EnumPersonalSettingsKey::RESTORE_HISTORY->value => $this->getUserValue(EnumPersonalSettingsKey::RESTORE_HISTORY, 'off'),
          EnumPersonalSettingsKey::INITIAL_FILTER_VISIBILITY->value => $this->getUserValue(EnumPersonalSettingsKey::INITIAL_FILTER_VISIBILITY, 'off'),
          EnumPersonalSettingsKey::DIRECT_CHANGE->value => $this->getUserValue(EnumPersonalSettingsKey::DIRECT_CHANGE, 'off'),
          EnumPersonalSettingsKey::DESELECT_INVISIBLE_MISC_RECS->value => $this->getUserValue(EnumPersonalSettingsKey::DESELECT_INVISIBLE_MISC_RECS, 'off'),
          EnumPersonalSettingsKey::SHOW_DISABLED->value => $this->getUserValue(EnumPersonalSettingsKey::SHOW_DISABLED, 'off'),
          'language' => $this->configService->getCloudUserConfig()->getValueString($this->userId(), 'core', 'lang', 'en'),
          EnumPersonalSettingsKey::WYSIWYG_EDITOR->value => $this->getUserValue(EnumPersonalSettingsKey::WYSIWYG_EDITOR, 'tinymce'),
          EnumPersonalSettingsKey::EXPERT_MODE->value => $this->getUserValue(EnumPersonalSettingsKey::EXPERT_MODE),
          EnumPersonalSettingsKey::DEBUG_MODE->value => (int)$this->getUserValue(EnumPersonalSettingsKey::DEBUG_MODE, 0),
          EnumPersonalSettingsKey::DEBUG_QUERY_SQL_FILTER->value => (string)$this->getUserValue(EnumPersonalSettingsKey::DEBUG_QUERY_SQL_FILTER, ''),
          EnumPersonalSettingsKey::PAGE_ROWS_DEFAULT->value => (int)$this->getUserValue(EnumPersonalSettingsKey::PAGE_ROWS_DEFAULT, 20),
          'userId' => $this->userId(),
          'localeSymbol' => $this->getLocale(),
          'currencyCode' => $this->currencyCode(),
          'currencySymbol' => $this->currencySymbol(),
          'dateTimeZone' => $this->getDateTimeZone()->getName(),
          EnumPersonalSettingsKey::FINANCE_MODE->value => $this->getUserValue(EnumPersonalSettingsKey::FINANCE_MODE),
        ]);
      $this->initialStateService->provideInitialState($this->appName(), 'PHPMyEdit', []);

      $aboutInfo = $this->appManager->getAppInfo($this->appName());
      $licence = trim($aboutInfo['licence'] ?? '');
      $longLicence = (new SpdxLicenses)->getLicenseByIdentifier($licence);
      $credits = $aboutInfo['credits']['item'] ?? [];
      if (isset($credits['title'])) {
        $credits = [ $credits ];
      }
      $this->initialStateService->provideInitialState(
        $this->appName(),
        'about',
        [
          'name' => $aboutInfo['name'] ?? $this->appName(),
          'version' => $aboutInfo['version'] ?? '',
          'codeName' => trim($aboutInfo['codename'] ?? ''),
          'description' => $aboutInfo['description'] ? $this->l->t($aboutInfo['description']) : '',
          'lifetime' => $aboutInfo['lifetime'] ?? '',
          'licence' => $licence,
          'licenceName' => $longLicence[0] ?? $licence,
          'author' => $aboutInfo['author']['@value'] ?? '',
          'authorEmail' => $aboutInfo['author']['@attributes']['mail'] ?? '',
          'homePage' => $aboutInfo['author']['@attributes']['homepage'] ?? '',
          'credits' => array_values(array_map(
            fn(array $item) => [ 'title' => (string)$item['title'], 'link' => (string)$item['link'] ],
            $credits,
          )),
        ],
      );

      if (!$isGroupAdmin) {
        return $this->templateResponse(self::TEMPLATE, []);
      }

      try {
        $webPageCategories = array_values($this->webPagesRPC->getCategories() ?? []);
      } catch (\Throwable $t) {
        $webPageCategories = [];
        $this->logException($t);
      }

      try {
        $webPageModules = array_values($this->webPagesRPC->getModules() ?? []);
      } catch (\Throwable $t) {
        $webPageModules = [];
        $this->logException($t);
      }

      try {
        $webPageTemplates = array_values($this->webPagesRPC->getTemplates() ?? []);
      } catch (\Throwable $t) {
        $webPageTemplates = [];
        $this->logException($t);
      }

      $templateParameters = [
        'appName' => $this->appName(),
        'appNameTag' => 'app-' . $this->appName(),
        'language' => $this->l->getLanguageCode(),
        'localeSymbol' => $this->getLocale(), // locale itself should already have been provided by NC core
        'locales' => $this->findAvailableLocales(),
        'languages' => $this->findAvailableLanguages(),
        'localeCountryNames' => $this->localeCountryNames(),
        'localeLanguageNames' => $this->localeLanguageNames(),
        'currencyCode' => $this->currencyCode(),
        'currencySymbol' => $this->currencySymbol(),
        //
        'appLocale' => $this->appLocale(),
        'dateTimeZone' => $this->getDateTimeZone(),
        //
        EnumPersonalSettingsKey::TOOL_TIPS_ENABLED->value => $this->getUserValue(EnumPersonalSettingsKey::TOOL_TIPS_ENABLED),
        EnumPersonalSettingsKey::DEBUG_MODE->value => (int)$this->getUserValue(EnumPersonalSettingsKey::DEBUG_MODE, 0),
        EnumPersonalSettingsKey::DEBUG_QUERY_SQL_FILTER->value => (string)$this->getUserValue(EnumPersonalSettingsKey::DEBUG_QUERY_SQL_FILTER, ''),
        EnumPersonalSettingsKey::PAGE_ROWS_DEFAULT->value => (int)$this->getUserValue(EnumPersonalSettingsKey::PAGE_ROWS_DEFAULT, 20),
        EnumPersonalSettingsKey::INITIAL_FILTER_VISIBILITY->value => $this->getUserValue(EnumPersonalSettingsKey::INITIAL_FILTER_VISIBILITY, 'off'),
        EnumPersonalSettingsKey::RESTORE_HISTORY->value => $this->getUserValue(EnumPersonalSettingsKey::RESTORE_HISTORY, 'off'),
        EnumPersonalSettingsKey::DIRECT_CHANGE->value => $this->getUserValue(EnumPersonalSettingsKey::DIRECT_CHANGE, 'off'),
        EnumPersonalSettingsKey::DESELECT_INVISIBLE_MISC_RECS->value => $this->getUserValue(EnumPersonalSettingsKey::DESELECT_INVISIBLE_MISC_RECS, 'off'),
        EnumPersonalSettingsKey::SHOW_DISABLED->value => $this->getUserValue(EnumPersonalSettingsKey::SHOW_DISABLED, 'off'),
        EnumPersonalSettingsKey::EXPERT_MODE->value => $this->getUserValue(EnumPersonalSettingsKey::EXPERT_MODE, 'off'),
        EnumPersonalSettingsKey::FINANCE_MODE->value => $this->getUserValue(EnumPersonalSettingsKey::FINANCE_MODE, 'off'),
        EnumPersonalSettingsKey::WYSIWYG_EDITOR->value => $this->getUserValue(EnumPersonalSettingsKey::WYSIWYG_EDITOR, self::DEFAULT_EDITOR),
        'wysiwygOptions' => ConfigConstants::WYSIWYG_EDITORS,
        'webPageCategories' => $webPageCategories,
        'webPageTemplates' => $webPageTemplates,
        'webPageModules' => $webPageModules,
      ];

      $memberProject = $this->getConfigValue('memberProject', $this->l->t('ClubMembers'));
      $memberProjectId = $this->getConfigValue('memberProjectId', -1);
      $executiveBoardProject = $this->getConfigValue('executiveBoardProject', $this->l->t('ExecutiveBoardMembers'));
      $executiveBoardProjectId = $this->getConfigValue('executiveBoardProjectId', -1);

      $projectOptions = [];
      if ($this->databaseConfigured()) {
        try {
          $projectOptions = $this->projectService->projectOptions([ 'type' => 'permanent' ]);
        } catch (\Throwable $t) {
          $this->logException($t);
          $projectOptions = [];
        }
      }

      $this->logDebug('MEMBER PROJECTS '.$executiveBoardProjectId.' / '.$memberProjectId);

      if ($this->databaseConfigured() && $executiveBoardProjectId > 0) {
        // this can throw if there is no datadase configured yet.
        try {
          $executiveBoardMembers = $this->projectService->participantOptions(
            $executiveBoardProjectId,
            excludeStatus: ParticipationStatus::ASSOCIATED,
          );
        } catch (\Throwable $t) {
          $this->logException($t);
          $executiveBoardMembers = [];
        }
      } else {
        $executiveBoardMembers = [];
      }

      if ($this->databaseConfigured() && $memberProjectId > 0) {
        // this can throw if there is no datadase configured yet.
        try {
          $clubMembers = $this->projectService->participantOptions(
            projectId: $memberProjectId,
            excludeStatus: ParticipationStatus::ASSOCIATED,
          );
        } catch (\Exception $e) {
          $clubMembers = [];
        }
      } else {
        $clubMembers = [];
      }

      $musiciansAddressBookName = $this->addressBookProvider
        ->getContactsAddressBook()
        ->getDisplayName();

      $sharedFolder = $this->getConfigValue(ConfigConstants::SHARED_FOLDER);
      try {
        if (!empty($sharedFolder)) {
          $sharedFolderLink = $this->userStorage->getFilesAppLink($sharedFolder, true);
        }
      } catch (\Throwable $t) {
        // don't care
      }
      $documentTemplatesFolder = $this->getConfigValue(ConfigConstants::DOCUMENT_TEMPLATES_FOLDER);
      $postboxFolder = $this->getConfigValue(ConfigConstants::POSTBOX_FOLDER);
      $postboxFolderShareLink = $this->getConfigValue(ConfigConstants::POSTBOX_FOLDER . 'ShareLink');
      $outboxFolder = $this->getConfigValue(ConfigConstants::OUTBOX_FOLDER);

      $templateParameters = array_merge(
        $templateParameters,
        [
          Admin::ORCHESTRA_USER_GROUP_KEY => $this->getAppValue(ConfigConstants::USER_GROUP_KEY),
          ConfigConstants::STREET_ADDRESS_NAME_01 => $this->getConfigValue(ConfigConstants::STREET_ADDRESS_NAME_01),
          ConfigConstants::STREET_ADDRESS_NAME_02 => $this->getConfigValue(ConfigConstants::STREET_ADDRESS_NAME_02),
          ConfigConstants::STREET_ADDRESS_STREET => $this->getConfigValue(ConfigConstants::STREET_ADDRESS_STREET),
          ConfigConstants::STREET_ADDRESS_HOUSE_NUMBER => $this->getConfigValue(ConfigConstants::STREET_ADDRESS_HOUSE_NUMBER),
          ConfigConstants::STREET_ADDRESS_CITY => $this->getConfigValue(ConfigConstants::STREET_ADDRESS_CITY),
          ConfigConstants::STREET_ADDRESS_ZIP => $this->getConfigValue(ConfigConstants::STREET_ADDRESS_ZIP),
          ConfigConstants::STREET_ADDRESS_COUNTRY => $this->getConfigValue(ConfigConstants::STREET_ADDRESS_COUNTRY),
          'registerName' => $this->getConfigValue('registerName'),
          'registerNumber' => $this->getConfigValue('registerNumber'),

          'phoneNumber' => $this->getConfigValue('phoneNumber'),

          'projectOptions' => $projectOptions,
          'memberProject' => $memberProject,
          'memberProjectId' => $memberProjectId,
          'clubMembers' => $clubMembers,
          'executiveBoardProject' => $executiveBoardProject,
          'executiveBoardProjectId' => $executiveBoardProjectId,
          'executiveBoardMembers' => $executiveBoardMembers,
          'userGroupMembers' => array_values(array_map(fn($user) => $user->getUID(), $this->group()->getUsers())),
          'userGroups' => array_values(array_map(function($group) {
            return [ 'value' => $group->getGID(), 'name' => $group->getDisplayName(), ];
          }, $this->groupManager()->search(''))),
          ConfigConstants::ORCHESTRA_NAME_KEY => $this->getConfigValue(ConfigConstants::ORCHESTRA_NAME_KEY),

          'cloudUserRequirements' => $this->cloudUserService->checkRequirements(
            $this->getConfigValue('cloudUserViewsDatabase')
          ),
          'importClubMembersAsCloudUsers' => $this->getConfigValue('importClubMembersAsCloudUsers', 'off') === 'on',
          'cloudUserViewsDatabase' => $this->getConfigValue('cloudUserViewsDatabase'),
          'musicianPersonalizedViews' => $this->getConfigValue('musicianPersonalizedViews'),

          ConfigConstants::APP_DB_SERVER => $this->getConfigValue(ConfigConstants::APP_DB_SERVER),
          ConfigConstants::APP_DB_NAME => $this->getConfigValue(ConfigConstants::APP_DB_NAME),
          ConfigConstants::APP_DB_USER => $this->getConfigValue(ConfigConstants::APP_DB_USER),
          ConfigConstants::APP_DB_PASSWORD => $this->getConfigValue(ConfigConstants::APP_DB_PASSWORD),

          ConfigConstants::SHARE_OWNER_KEY => $this->getConfigValue(ConfigConstants::SHARE_OWNER_KEY, ''),
          ConfigConstants::CONCERTS_CALENDAR_URI . ConfigConstants::CALENDAR_KEY_POSTFIX => $this->getConfigValue(ConfigConstants::CONCERTS_CALENDAR_URI . ConfigConstants::CALENDAR_KEY_POSTFIX, $this->l->t('Concerts')),
          ConfigConstants::REHEARSALS_CALENDAR_URI . ConfigConstants::CALENDAR_KEY_POSTFIX => $this->getConfigValue(ConfigConstants::REHEARSALS_CALENDAR_URI . ConfigConstants::CALENDAR_KEY_POSTFIX, $this->l->t('Rehearsals')),
          ConfigConstants::OTHER_CALENDAR_URI . ConfigConstants::CALENDAR_KEY_POSTFIX => $this->getConfigValue(ConfigConstants::OTHER_CALENDAR_URI . ConfigConstants::CALENDAR_KEY_POSTFIX, $this->l->t('Other')),
          ConfigConstants::MANAGEMENT_CALENDAR_URI . ConfigConstants::CALENDAR_KEY_POSTFIX => $this->getConfigValue(ConfigConstants::MANAGEMENT_CALENDAR_URI . ConfigConstants::CALENDAR_KEY_POSTFIX, $this->l->t('Management')),
          ConfigConstants::FINANCE_CALENDAR_URI . ConfigConstants::CALENDAR_KEY_POSTFIX => $this->getConfigValue(ConfigConstants::FINANCE_CALENDAR_URI . ConfigConstants::CALENDAR_KEY_POSTFIX, $this->l->t('Finance')),
          ConfigConstants::EVENT_DURATION_KEY => $this->getConfigValue(ConfigConstants::EVENT_DURATION_KEY, '180'),

          ConfigConstants::GENERAL_ADDRESS_BOOK_KEY => $this->getConfigValue(ConfigConstants::GENERAL_ADDRESS_BOOK_KEY, $this->l->t('Miscellaneous')),
          ConfigConstants::MUSICIANS_ADDRESS_BOOK_KEY => $musiciansAddressBookName,

          ConfigConstants::SHARED_FOLDER => $sharedFolder,
          'sharedFolderLink' => $sharedFolderLink,
          ConfigConstants::POSTBOX_FOLDER => $postboxFolder,
          'postboxFolderShareLink' => $postboxFolderShareLink,
          ConfigConstants::OUTBOX_FOLDER => $outboxFolder,
          ConfigConstants::DOCUMENT_TEMPLATES_FOLDER => $documentTemplatesFolder,
          ConfigConstants::PROJECTS_FOLDER => $this->getConfigValue(ConfigConstants::PROJECTS_FOLDER, ''),
          ConfigConstants::PROJECT_PARTICIPANTS_FOLDER => $this->getConfigValue(ConfigConstants::PROJECT_PARTICIPANTS_FOLDER, ''),
          ConfigConstants::PROJECT_POSTERS_FOLDER => $this->getConfigValue(ConfigConstants::PROJECT_POSTERS_FOLDER, ''),
          ConfigConstants::PROJECT_PUBLIC_DOWNLOADS_FOLDER => $this->getConfigValue(ConfigConstants::PROJECT_PUBLIC_DOWNLOADS_FOLDER, ''),
          ConfigConstants::FINANCE_FOLDER => $this->getConfigValue(ConfigConstants::FINANCE_FOLDER, ''),
          ConfigConstants::TRANSACTIONS_FOLDER => $this->getConfigValue(ConfigConstants::TRANSACTIONS_FOLDER, ''),
          ConfigConstants::BALANCES_FOLDER => $this->getConfigValue(ConfigConstants::BALANCES_FOLDER, ''),

          'documentTemplates' => ConfigConstants::DOCUMENT_TEMPLATES,

          'uploadMaxFilesize' => Util::maxUploadSize(),
          'uploadMaxHumanFilesize' => \OCP\Util::humanFileSize(Util::maxUploadSize()),
        ]);

      // bank account settings
      foreach (ConfigConstants::BANK_ACCOUNT_CONFIG_KEYS as $configKey) {
        $this->parameterFromConfig($templateParameters, $configKey);
      }
      $templateParameters['bankHolidayOptions'] = $this->bankHolidayOptions($templateParameters['appLocale']);
      if (empty($templateParameters[ConfigConstants::BANK_ACCOUNT_BANK_HOLIDAYS])) {
        $nationalTag = locale_get_region($templateParameters['appLocale']) . '-NATIONAL';
        if (($templateParameters['bankHolidayOptions'][0]['value'] ?? null) === $nationalTag) {
          $templateParameters[ConfigConstants::BANK_ACCOUNT_BANK_HOLIDAYS] = $nationalTag;
        }
      }

      // document templates
      if (!empty($documentTemplatesFolder) && !empty($sharedFolder)) {
        $folder = UserStorage::PATH_SEP
                . $sharedFolder . UserStorage::PATH_SEP
                . $documentTemplatesFolder . UserStorage::PATH_SEP;
        foreach (ConfigConstants::DOCUMENT_TEMPLATES as $documentTemplate => $templateInfo) {
          $fileName = $this->getConfigValue($documentTemplate);
          $templateParameters[$documentTemplate . 'FileName'] = $fileName;
          $subFolder = $templateInfo['folder']??'';
          if (!empty($subFolder)) {
            $subFolderName = $this->getConfigValue($subFolder) . UserStorage::PATH_SEP;
          } else {
            $subFolderName = '';
          }
          $templateParameters[$documentTemplate . 'SubFolder'] = $subFolder;
          $templateParameters[$documentTemplate . 'SubFolderName'] = $subFolderName;
          if (!empty($fileName)) {
            try {
              $templateParameters[$documentTemplate . 'DownloadLink'] = $this->userStorage->getDownloadLink($folder . $subFolderName . $fileName);
            } catch (\Throwable $t) {
              $this->logException($t);
              $templateParameters[$documentTemplate . 'DownloadLink'] = null;
            }
          } else {
            $templateParameters[$documentTemplate . 'DownloadLink'] = null;
          }
        }
      }

      // musician ids of the officials
      foreach (['president', 'secretary', 'treasurer'] as $prefix) {
        foreach (['Id', 'UserId', 'GroupId'] as $postfix) {
          $this->parameterFromConfig($templateParameters, $prefix.$postfix, -1);
        }
      }

      foreach (['smtp', 'imap'] as $proto) {
        foreach (['server', 'port', 'security'] as $key) {
          $this->parameterFromConfig($templateParameters, $proto.$key);
        }
      }
      foreach ([
        ConfigConstants::EMAIL_USER,
        ConfigConstants::EMAIL_PASSWORD,
        ConfigConstants::EMAIL_FROM_NAME_KEY,
        ConfigConstants::EMAIL_FROM_ADDRESS_KEY,
        ConfigConstants::EMAIL_FROM_DOMAIN_KEY,
        ConfigConstants::EMAIL_TEST_NAME_KEY,
        ConfigConstants::EMAIL_TEST_ADDRESS_KEY,
        ConfigConstants::EMAIL_TEST_MODE,
      ] as $key) {
        $this->parameterFromConfig($templateParameters, $key);
      }
      $announcementsMailingList = $this->getConfigValue(ConfigConstants::ANNOUNCEMENTS_MAILING_LIST_KEY);
      $announcementsMailingListName = $this->getConfigValue(ConfigConstants::ANNOUNCEMENTS_MAILING_LIST_DISPLAY_NAME_KEY);
      if (!empty($announcementsMailingListName)) {
        $announcementsMailingList =  $announcementsMailingListName . ' <' . $announcementsMailingList . '>';
      }
      $templateParameters[ConfigConstants::ANNOUNCEMENTS_MAILING_LIST_KEY] = $announcementsMailingList;
      // $this->parameterFromConfig($templateParameters, ConfigConstants::ANNOUNCEMENTS_MAILING_LIST_KEY);
      foreach ([
        ConfigConstants::PRE_SEND_VALIDATION_EXTERNAL_LINKS_SSL_VERIFY,
        ConfigConstants::PRE_SEND_VALIDATION_EXTERNAL_LINKS_ENFORCE_HTTPS,
      ] as $key) {
        // stored as per-user values, "on" unless explicitly switched off
        $templateParameters[$key] = $this->getUserValue($key, 'on');
      }
      $this->parameterFromConfig($templateParameters, 'bulkEmailSubjectTag');
      $this->parameterFromConfig($templateParameters, 'bulkEmailPrivacyNotice');

      $key = ConfigConstants::ATTACHMENT_LINK_EXPIRATION_LIMIT;
      $templateParameters[$key] = $this->getConfigValue($key);
      if (!empty($templateParameters[$key])) {
        $templateParameters[$key] = $this->l->t('%d days', $templateParameters[$key]);
      }

      $key = ConfigConstants::ATTACHMENT_LINK_SIZE_LIMIT;
      $templateParameters[$key] = $this->getConfigValue($key);
      if (!empty($templateParameters[$key])) {
        $templateParameters[$key] = $this->humanFileSize($templateParameters[$key]);
      }

      foreach (ConfigConstants::MAILING_LIST_REST_CONFIG as $listConfig) {
        $this->parameterFromConfig($templateParameters, $listConfig);
      }
      foreach (ConfigConstants::MAILING_LIST_CONFIG as $listConfig) {
        $this->parameterFromConfig($templateParameters, $listConfig);
      }

      foreach (['Preview',
                'Archive',
                'Rehearsals',
                'Trashbin',
                'Template',
                'ConcertModule',
                'RehearsalsModule',
                'SubPageTemplate'] as $key) {
        $this->parameterFromConfig($templateParameters, 'redaxo'.$key);
      }

      foreach ([
        'phpmyadmin' => null,
        'phpmyadmincloud' => null,
        'sourcecode' => null,
        'sourcedocs' => null,
        'clouddev' => null,
        self::CSP_FAILURE_REPORTING_KEY => $this->urlGenerator()->linkToRouteAbsolute($this->appName().'.cspviolation.post', ['operation' => 'report']),
      ] as $link => $default) {
        $this->parameterFromConfig($templateParameters, $link, $default);
      }

      $sanitizeAdminSettings = static function(array $settings) use (&$sanitizeAdminSettings): array {
        $safeSettings = [];
        foreach ($settings as $key => $value) {
          if (preg_match('/password|encryption.?key|request.?token|secret/i', (string)$key)) {
            continue;
          }
          if (is_array($value)) {
            $safeSettings[$key] = $sanitizeAdminSettings($value);
          } elseif (is_scalar($value) || $value === null) {
            $safeSettings[$key] = $value;
          }
        }
        return $safeSettings;
      };
      $adminSettings = $sanitizeAdminSettings($templateParameters);
      $localeOptions = [];
      foreach ($templateParameters['locales'] ?? [] as $localeInfo) {
        $code = $localeInfo['code'] ?? null;
        if (empty($code) || empty(locale_get_region($code))) {
          continue;
        }
        $localeOptions[] = [
          'value' => $code,
          'name' => locale_get_display_name($code, $this->getLocale()),
        ];
      }
      usort($localeOptions, fn($a, $b) => strcmp($a['name'], $b['name']));
      $adminSettings['localeOptions'] = $localeOptions;
      [$adminSettings[ConfigConstants::ORCHESTRA_LOCALE_KEY],] = explode('.', $this->appLocale(), 2);
      $adminSettings['orchestraCurrencyCode'] = $this->currencyCode($this->appLocale());
      $adminSettings['orchestraCurrencySymbol'] = $this->currencySymbol($this->appLocale());
      $adminSettings['dateTimeZone'] = $this->getDateTimeZone()->getName();
      $this->initialStateService->provideInitialState(
        $this->appName(),
        'adminSettings',
        $adminSettings,
      );

      return $this->templateResponse(self::TEMPLATE, []);
    } catch (\Exception $e) {
      return $this->errorService->exceptionTemplate($e);
    }
  }

  /**
   * @param string $locale Orchestra locale.
   *
   * @return array Locale-specific bank holiday regions, national holidays first.
   */
  private function bankHolidayOptions(string $locale):array
  {
    $country = locale_get_region($locale);
    if (empty($country)) {
      return [];
    }
    BusinessDay::enable(CarbonImmutable::class);
    $regions = array_values(array_filter(
      array_map('strtoupper', CarbonImmutable::getHolidaysAvailableRegions()),
      fn(string $region) => str_starts_with($region, $country . '-'),
    ));
    $nationalTag = $country . '-NATIONAL';
    $nationalKey = array_search($nationalTag, $regions);
    if ($nationalKey !== false) {
      unset($regions[$nationalKey]);
      array_unshift($regions, $nationalTag);
    }
    $regionNames = [];
    try {
      $regionNames = $this->geoCodingService->getRegionNames($country) ?? [];
    } catch (\Throwable $t) {
      $this->logException($t);
    }
    return array_values(array_map(function(string $region) use ($nationalTag, $regionNames) {
      [, $regionIso] = explode('-', $region, 2);
      return [
        'value' => $region,
        'name' => $region === $nationalTag ? $this->l->t('national holidays') : ($regionNames[$regionIso] ?? $region),
      ];
    }, $regions));
  }

  /**
   * @param array $parameters Template parameters by reference.
   *
   * @param string $templateKey Key into $templateParameters.
   *
   * @param mixed $default Default value, by default null.
   *
   * @param null|string $configKey Key into the space for the parameter should be fetched from.
   *
   * @return void
   */
  private function parameterFromConfig(
    array &$parameters,
    string $templateKey,
    mixed $default = null,
    ?string $configKey = null,
  ):void {
    $parameters[$templateKey] = $this->getConfigValue($configKey ?? $templateKey, $default);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
