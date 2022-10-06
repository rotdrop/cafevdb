<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine
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
use OCP\Settings\ISettings;
use OCP\IInitialStateService;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\AssetService;
use OCA\CAFEVDB\Service\DatabaseService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\ErrorService;
use OCA\CAFEVDB\Service\L10N\TranslationService;
use OCA\CAFEVDB\AddressBook\AddressBookProvider;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Service\CloudUserConnectorService;

use OCA\DokuWikiEmbedded\Service\AuthDokuWiki as WikiRPC;
use OCA\Redaxo4Embedded\Service\RPC as WebPagesRPC;

use OCA\CAFEVDB\Common\Util;

/**
 * Simple helper class in order to avoid instantiation of a bunch of
 * helper classes just for the sake of creating the menu entry in the
 * settings page.
 */
class PersonalForm
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  const ERROR_TEMPLATE = "errorpage";
  const TEMPLATE = "settings";
  const DEFAULT_EDITOR = 'tinymce';

  /** @var AssetService */
  private $assetService;

  /** @var ProjectService */
  private $projectService;

  /** @var ErrorService */
  private $errorService;

  /** @var OCA\CAFEVDB\Service\L10N\TranslationService */
  private $translationService;

  /** @var OCA\DokuWikiEmedded\Service\AuthDokuWiki */
  private $wikiRPC;

  /** @var OCA\Redaxo4Embedded\Service\RPC */
  private $webPagesRPC;

  /** @var AddressBookProvider */
  private $addressBookProvider;

  /** @var UserStorage */
  private $userStorage;

  /** @var CloudUserConnectorService */
  private $cloudUserService;

  /** @var IInitialStateService */
  private $initialStateService;

  /** @var GeoCodingService */
  private $geoCodingService;

  /** {@inheritdoc} */
  public function __construct(
    ConfigService $configService,
    AssetService $assetService,
    ProjectService $projectService,
    ErrorService $errorService,
    TranslationService $translationService,
    IInitialStateService $initialStateService,
    WikiRPC $wikiRPC,
    WebPagesRPC $webPagesRPC,
    AddressBookProvider $addressBookProvider,
    UserStorage $userStorage,
    CloudUserConnectorService $cloudUserService,
    GeoCodingService $geoCodingService,
  ) {
    $this->configService = $configService;
    $this->assetService = $assetService;
    $this->projectService = $projectService;
    $this->errorService = $errorService;
    $this->translationService = $translationService;
    $this->initialStateService = $initialStateService;
    $this->wikiRPC = $wikiRPC;
    $this->webPagesRPC = $webPagesRPC;
    $this->addressBookProvider = $addressBookProvider;
    $this->userStorage = $userStorage;
    $this->cloudUserService = $cloudUserService;
    $this->geoCodingService = $geoCodingService;
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
    if (!$this->inGroup()) {
      return new TemplateResponse(
        $this->appName(),
        self::ERROR_TEMPLATE,
        [
          'assets' => [
            AssetService::JS => $this->assetService->getJSAsset(self::TEMPLATE),
            AssetService::CSS => $this->assetService->getCSSAsset(self::TEMPLATE),
          ],
          'error' => 'notamember',
          'userId' => $this->userId(),
        ], 'blank');
    }
    try {
      // Initial state injecton for JS
      $this->initialStateService->provideInitialState(
        $this->appName(),
        'CAFEVDB',
        [
          'appName' => $this->appName(),
          'toolTipsEnabled' => $this->getUserValue('tooltips', ''),
          'language' => $this->getUserValue('lang', 'en'),
          'wysiwygEditor' =>$this->getUserValue('wysiwygEditor', 'tinymce'),
          'expertMode' => $this->getUserValue('expertmode'),
        ]);
      $this->initialStateService->provideInitialState($this->appName(), 'PHPMyEdit', []);
      $this->initialStateService->provideInitialState($this->appName(), 'Calendar', []);

      // Are we a group-admin?
      $isGroupAdmin = $this->isSubAdminOfGroup() && $this->encryptionKeyValid();

      try {
        $webPageCategories = $this->webPagesRPC->getCategories();
      } catch (\Throwable $t) {
        $webPageCategories = [];
        $this->logException($t);
      }

      try {
        $webPageModules = $this->webPagesRPC->getModules();
      } catch (\Throwable $t) {
        $webPageModules = [];
        $this->logException($t);
      }

      try {
        $webPageTemplates = $this->webPagesRPC->getTemplates();
      } catch (\Throwable $t) {
        $webPageTemplates = [];
        $this->logException($t);
      }

      $templateParameters = [
        'assets' => [
          AssetService::JS => $this->assetService->getJSAsset(self::TEMPLATE),
          AssetService::CSS => $this->assetService->getCSSAsset(self::TEMPLATE),
        ],
        'appName' => $this->appName(),
        'userId' => $this->userId(),
        //
        'locale' => $this->getLocale(),
        'language' => $this->l->getLanguageCode(),
        'locales' => $this->findAvailableLocales(),
        'languages' => $this->findAvailableLanguages(),
        'localeCountryNames' => $this->localeCountryNames(),
        'localeLanguageNames' => $this->localeLanguageNames(),
        'currencyCode' => $this->currencyCode(),
        'currencySymbol' => $this->currencySymbol(),
        'geoCodingService' => $this->geoCodingService,
        //
        'appLocale' => $this->appLocale(),
        'appL' => $this->appL10n(),
        //
        'dateTimeFormatter' => $this->dateTimeFormatter(),
        'dateTimeZone' => $this->getDateTimeZone(),
        //
        'adminsettings' => $isGroupAdmin,
        'encryptionkey' => $this->getAppEncryptionKey(),
        'showToolTips' => $this->getUserValue('tooltips', 'on'),
        'debugMode' => (int)$this->getConfigValue('debugmode', 0), // @todo depend on group admin
        'pagerows' => $this->getUserValue('pagerows', 20),
        'toolTips' => $this->toolTipsService(),
        'filtervisibility' => $this->getUserValue('filtervisibility', 'off'),
        'restorehistory' => $this->getUserValue('restorehistory', 'off'),
        'directchange' => $this->getUserValue('directchange', 'off'),
        'deselectInvisibleMiscRecs' => $this->getUserValue('deselectInvisibleMiscRecs', 'off'),
        'showdisabled' => $this->getUserValue('showdisabled', 'off'),
        'expertMode' => $this->getUserValue('expertmode', 'off'),
        'wysiwygEditor' => $this->getUserValue('wysiwygEditor', self::DEFAULT_EDITOR),
        'wysiwygOptions' => ConfigService::WYSIWYG_EDITORS,
        'webPageCategories' => $webPageCategories,
        'webPageTemplates' => $webPageTemplates,
        'webPageModules' => $webPageModules,
      ];

      if ($isGroupAdmin) {
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
            $executiveBoardMembers = $this->projectService->participantOptions($executiveBoardProjectId, $executiveBoardProject);
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
            $clubMembers = $this->projectService->participantOptions($memberProjectId, $memberProject);
          } catch (\Exception $e) {
            $clubMembers = [];
          }
        } else {
          $clubMembers = [];
        }

        $musiciansAddressBookName = $this->addressBookProvider
          ->getContactsAddressBook()
          ->getDisplayName();

        $sharedFolder = $this->getConfigValue(ConfigService::SHARED_FOLDER);
        try {
          if (!empty($sharedFolder)) {
            $sharedFolderLink = $this->userStorage->getFilesAppLink($sharedFolder, true);
          }
        } catch (\Throwable $t) {
          // don't care
        }
        $documentTemplatesFolder = $this->getConfigValue(ConfigService::DOCUMENT_TEMPLATES_FOLDER);
        $postboxFolder = $this->getConfigValue(ConfigService::POSTBOX_FOLDER);
        $postboxFolderShareLink = $this->getConfigValue(ConfigService::POSTBOX_FOLDER . 'ShareLink');
        $outboxFolder = $this->getConfigValue(ConfigService::OUTBOX_FOLDER);

        $translations = null;
        try {
          $translations = $this->translationService->getTranslations();
        } catch (\Throwable $t) {
          $this->logException($t);
        }

        $templateParameters = array_merge(
          $templateParameters,
          [
            'streetAddressName01' => $this->getConfigValue('streetAddressName01'),
            'streetAddressName02' => $this->getConfigValue('streetAddressName02'),
            'streetAddressStreet' => $this->getConfigValue('streetAddressStreet'),
            'streetAddressHouseNumber' => $this->getConfigValue('streetAddressHouseNumber'),
            'streetAddressCity' => $this->getConfigValue('streetAddressCity'),
            'streetAddressZIP' => $this->getConfigValue('streetAddressZIP'),
            'streetAddressCountry' => $this->getConfigValue('streetAddressCountry'),
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
            'userGroupMembers' => array_map(fn($user) => $user->getUID(), $this->group()->getUsers()),
            'userGroups' => array_map(function($group) {
              return [ 'value' => $group->getGID(), 'name' => $group->getDisplayName(), ];
            }, $this->groupManager()->search('')),
            'orchestra' => $this->getConfigValue('orchestra'),

            'cloudUserRequirements' => $this->cloudUserService->checkRequirements(
              $this->getConfigValue('cloudUserViewsDatabase')
            ),
            'importClubMembersAsCloudUsers' => $this->getConfigValue('importClubMembersAsCloudUsers', 'off') === 'on',
            'cloudUserViewsDatabase' => $this->getConfigValue('cloudUserViewsDatabase'),
            'musicianPersonalizedViews' => $this->getConfigValue('musicianPersonalizedViews'),

            'dbserver' => $this->getConfigValue('dbserver'),
            'dbname' => $this->getConfigValue('dbname'),
            'dbuser' => $this->getConfigValue('dbuser'),
            'dbpassword' => $this->getConfigValue('dbpassword'),

            'shareowner' => $this->getConfigValue('shareowner', ''),
            'concertscalendar' => $this->getConfigValue('concertscalendar', $this->l->t('Concerts')),
            'rehearsalscalendar' => $this->getConfigValue('rehearsalscalendar', $this->l->t('Rehearsals')),
            'othercalendar' => $this->getConfigValue('othercalendar', $this->l->t('Other')),
            'managementcalendar' => $this->getConfigValue('managementcalendar', $this->l->t('Management')),
            'financecalendar' => $this->getConfigValue('financecalendar', $this->l->t('Finance')),
            'eventduration' => $this->getConfigValue('eventduration', '180'),

            'generaladdressbook' => $this->getConfigValue('generaladdressbook', $this->l->t('Miscellaneous')),
            'musiciansaddressbook' => $musiciansAddressBookName,

            ConfigService::SHARED_FOLDER => $sharedFolder,
            'sharedFolderLink' => $sharedFolderLink,
            ConfigService::POSTBOX_FOLDER => $postboxFolder,
            'postboxFolderShareLink' => $postboxFolderShareLink,
            ConfigService::OUTBOX_FOLDER => $outboxFolder,
            ConfigService::DOCUMENT_TEMPLATES_FOLDER => $documentTemplatesFolder,
            ConfigService::PROJECTS_FOLDER => $this->getConfigValue(ConfigService::PROJECTS_FOLDER, ''),
            ConfigService::PROJECT_PARTICIPANTS_FOLDER => $this->getConfigValue(ConfigService::PROJECT_PARTICIPANTS_FOLDER, ''),
            ConfigService::PROJECT_POSTERS_FOLDER => $this->getConfigValue(ConfigService::PROJECT_POSTERS_FOLDER, ''),
            ConfigService::PROJECT_PUBLIC_DOWNLOADS_FOLDER => $this->getConfigValue(ConfigService::PROJECT_PUBLIC_DOWNLOADS_FOLDER, ''),
            ConfigService::FINANCE_FOLDER => $this->getConfigValue(ConfigService::FINANCE_FOLDER, ''),
            ConfigService::TRANSACTIONS_FOLDER => $this->getConfigValue(ConfigService::TRANSACTIONS_FOLDER, ''),
            ConfigService::BALANCES_FOLDER => $this->getConfigValue(ConfigService::BALANCES_FOLDER, ''),

            'translations' => $translations,

            'documentTemplates' => ConfigService::DOCUMENT_TEMPLATES,

            'uploadMaxFilesize' => Util::maxUploadSize(),
            'uploadMaxHumanFilesize' => \OCP\Util::humanFileSize(Util::maxUploadSize()),

            'requesttoken' => \OCP\Util::callRegister(),
          ]);

        // bank account settings
        foreach (ConfigService::BANK_ACCOUNT_CONFIG_KEYS as $configKey) {
          $this->parameterFromConfig($templateParameters, $configKey);
        }

        // document templates
        if (!empty($documentTemplatesFolder) && !empty($sharedFolder)) {
          $folder = UserStorage::PATH_SEP
                  . $sharedFolder . UserStorage::PATH_SEP
                  . $documentTemplatesFolder . UserStorage::PATH_SEP;
          foreach (ConfigService::DOCUMENT_TEMPLATES as $documentTemplate => $templateInfo) {
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
        foreach (['user', 'password', 'fromname', 'fromaddress', 'testaddress', 'testmode'] as $key) {
          $this->parameterFromConfig($templateParameters, 'email'.$key);
        }
        $announcementsMailingList = $this->getConfigValue('announcementsMailingList');
        $announcementsMailingListName = $this->getConfigValue('announcementsMailingListName');
        if (!empty($announcementsMailingListName)) {
          $announcementsMailingList =  $announcementsMailingListName . ' <' . $announcementsMailingList . '>';
        }
        $templateParameters['announcementsMailingList'] = $announcementsMailingList;
        // $this->parameterFromConfig($templateParameters, 'announcementsMailingList');
        $this->parameterFromConfig($templateParameters, 'bulkEmailSubjectTag');
        $this->parameterFromConfig($templateParameters, 'bulkEmailPrivacyNotice');

        $key = 'attachmentLinkExpirationLimit';
        $templateParameters[$key] = $this->getConfigValue($key);
        if (!empty($templateParameters[$key])) {
          $templateParameters[$key] = $this->l->t('%d days', $templateParameters[$key]);
        }

        $key = 'attachmentLinkSizeLimit';
        $templateParameters[$key] = $this->getConfigValue($key);
        if (!empty($templateParameters[$key])) {
          $templateParameters[$key] = $this->humanFileSize($templateParameters[$key]);
        }

        foreach (ConfigService::MAILING_LIST_REST_CONFIG as $listConfig) {
          $this->parameterFromConfig($templateParameters, $listConfig);
        }
        foreach (ConfigService::MAILING_LIST_CONFIG as $listConfig) {
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
          'cspfailurereporting' => $this->urlGenerator()->linkToRouteAbsolute($this->appName().'.csp_violation.post', ['operation' => 'report']),
        ] as $link => $default) {
          $this->parameterFromConfig($templateParameters, $link, $default);
        }
      }

      return new TemplateResponse(
        $this->appName(),
        self::TEMPLATE,
        $templateParameters,
        'blank',
      );
    } catch (\Exception $e) {
      return $this->errorService->exceptionTemplate($e);
    }
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
