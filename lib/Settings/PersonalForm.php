<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\IInitialStateService;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\DatabaseFactory;
use OCA\CAFEVDB\Service\DatabaseService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\ErrorService;
use OCA\CAFEVDB\Service\L10N\TranslationService;
use OCA\CAFEVDB\AddressBook\AddressBookProvider;
use OCA\CAFEVDB\Storage\UserStorage;

use OCA\DokuWikiEmbedded\Service\AuthDokuWiki as WikiRPC;
use OCA\Redaxo4Embedded\Service\RPC as WebPagesRPC;

use OCA\CAFEVDB\Common\Util;

/**
 * Simple helper class in order to avoid instantiation of a bunch of
 * helper classes just for the sake of creating the menu entry in the
 * settings page.
 */
class PersonalForm {
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  const ERROR_TEMPLATE = "errorpage";
  const TEMPLATE = "settings";
  const DEFAULT_EDITOR = 'tinymce';

  /** @var ProjectService */
  private $projectService;

  /** @var ToolTipsService */
  private $toolTipsService;

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

  public function __construct(
    ConfigService $configService
    , ProjectService $projectService
    , ToolTipsService $toolTipsService
    , ErrorService $errorService
    , TranslationService $translationService
    , IInitialStateService $initialStateService
    , WikiRPC $wikiRPC
    , WebPagesRPC $webPagesRPC
    , AddressBookProvider $addressBookProvider
    , UserStorage $userStorage
  ) {
    $this->configService = $configService;
    $this->projectService = $projectService;
    $this->toolTipsService = $toolTipsService;
    $this->errorService = $errorService;
    $this->translationService = $translationService;
    $this->initialStateService = $initialStateService;
    $this->wikiRPC = $wikiRPC;
    $this->webPagesRPC = $webPagesRPC;
    $this->addressBookProvider = $addressBookProvider;
    $this->userStorage = $userStorage;
    $this->l = $this->l10N();
  }

  public function getForm() {
    if (!$this->inGroup()) {
      return new TemplateResponse(
        $this->appName(),
        self::ERROR_TEMPLATE,
        [
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
        'appName' => $this->appName(),
        'userId' => $this->userId(),
        'locale' => $this->getLocale(),
        'language' => $this->l->getLanguageCode(),
        'locales' => $this->findAvailableLocales(),
        'languages' => $this->findAvailableLanguages(),
        'localeCountryNames' => $this->localeCountryNames(),
        'timezone' => $this->getTimezone(),
        'adminsettings' => $isGroupAdmin,
        'encryptionkey' => $this->getAppEncryptionKey(),
        'showToolTips' => $this->getUserValue('tooltips', 'on'),
        'debugMode' => $this->getConfigValue('debugmode', 0), // @todo depend on group admin
        'pagerows' => $this->getUserValue('pagerows', 20),
        'toolTips' => $this->toolTipsService,
        'filtervisibility' => $this->getUserValue('filtervisibility', 'off'),
        'directchange' => $this->getUserValue('directchange', 'off'),
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

        if ($this->databaseConfigured()) {
          $projectOptions = $this->projectService->projectOptions([ 'type' => 'permanent' ]);
        } else {
          $projectOptions = [];
        }

        $this->logDebug('MEMBER PROJECTS '.$executiveBoardProjectId.' / '.$memberProjectId);

        if ($this->databaseConfigured() && $executiveBoardProjectId > 0) {
          // this can throw if there is no datadase configured yet.
          try {
            $executiveBoardMembers = $this->projectService->participantOptions($executiveBoardProjectId, $executiveBoardProject);
          } catch(\Throwable $t) {
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
          } catch(\Exception $e) {
            $clubMembers = [];
          }
        } else {
          $clubBoardMembers = [];
        }

        $musiciansAddressBookName = $this->addressBookProvider
          ->getContactsAddressBook()
          ->getDisplayName();

        $sharedFolder = $this->getConfigValue('sharedfolder');
        try {
          if (!empty($sharedFolder)) {
            $sharedFolderLink = $this->userStorage->getFilesAppLink($sharedFolder);
          }
        } catch (\Throwable $t) {
          // don't care
        }
        $documentTemplatesFolder = $this->getConfigValue('documenttemplatesfolder');

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

            'phoneNumber' => $this->getConfigValue('phoneNumber'),

            'bankAccountOwner' => $this->getConfigValue('bankAccountOwner'),
            'bankAccountIBAN' => $this->getConfigValue('bankAccountIBAN'),
            'bankAccountBLZ' => $this->getConfigValue('bankAccountBLZ'),
            'bankAccountBIC' => $this->getConfigValue('bankAccountBIC'),
            'bankAccountCreditorIdentifier' => $this->getConfigValue('bankAccountCreditorIdentifier'),

            'projectOptions' => $projectOptions,
            'memberProject' => $memberProject,
            'memberProjectId' => $memberProjectId,
            'clubMembers' => $clubMembers,
            'executiveBoardProject' => $executiveBoardProject,
            'executiveBoardProjectId' => $executiveBoardProjectId,
            'executiveBoardMembers' => $executiveBoardMembers,
            'userGroupMembers' => array_map(function($user) { return $user->getUID(); }, $this->group()->getUsers()),
            'userGroups' => array_map(function($group) { return $group->getGID(); }, $this->groupManager()->search('')),
            'orchestra' => $this->getConfigValue('orchestra'),

            'dbserver' => $this->getConfigValue('dbserver'),
            'dbname' => $this->getConfigValue('dbname'),
            'dbuser' => $this->getConfigValue('dbuser'),
            'dbpassword' => $this->getConfigValue('dbpassword'),
            'encryptionkey' => $this->getConfigValue('encryptionkey'),

            'shareowner' => $this->getConfigValue('shareowner', ''),
            'concertscalendar' => $this->getConfigValue('concertscalendar', $this->l->t('Concerts')),
            'rehearsalscalendar' => $this->getConfigValue('rehearsalscalendar', $this->l->t('Rehearsals')),
            'othercalendar' => $this->getConfigValue('othercalendar', $this->l->t('Other')),
            'managementcalendar' => $this->getConfigValue('managementcalendar', $this->l->t('Management')),
            'financecalendar' => $this->getConfigValue('financecalendar', $this->l->t('Finance')),
            'eventduration' => $this->getConfigValue('eventduration', '180'),

            'generaladdressbook' => $this->getConfigValue('generaladdressbook', $this->l->t('Miscellaneous')),
            'musiciansaddressbook' => $musiciansAddressBookName,

            'sharedfolder' => $sharedFolder,
            'sharedFolderLink' => $sharedFolderLink,
            'documenttemplatesfolder' => $documentTemplatesFolder,
            'projectsfolder' => $this->getConfigValue('projectsfolder',''),
            'projectparticipantsfolder' => $this->getConfigValue('projectparticipantsfolder',''),
            'projectsbalancefolder' => $this->getConfigValue('projectsbalancefolder',''),

            'translations' => $this->translationService->getTranslations(),

            'documentTemplates' => ConfigService::DOCUMENT_TEMPLATES,

            'uploadMaxFilesize' => Util::maxUploadSize(),
            'uploadMaxHumanFilesize' => \OCP\Util::humanFileSize(Util::maxUploadSize()),

            'requesttoken' => \OCP\Util::callRegister(),
          ]);

        // document templates
        if (!empty($documentTemplatesFolder) && !empty($sharedFolder)) {
          $folder = UserStorage::PATH_SEP
                  . $sharedFolder . UserStorage::PATH_SEP
                  . $documentTemplatesFolder . UserStorage::PATH_SEP;
          foreach (array_keys(ConfigService::DOCUMENT_TEMPLATES) as $documentTemplate) {
            $fileName = $this->getConfigValue($documentTemplate);
            $this->logInfo('TEMPLATE '.$documentTemplate.': '.$folder.$fileName);
            if (!empty($fileName)) {
              $templateParameters[$documentTemplate . 'FileName'] = $fileName;
              $templateParameters[$documentTemplate . 'DownloadLink'] = $this->userStorage->getDownloadLink($folder . $fileName);
            }
          }
        }

        // musician ids of the officials
        foreach (['president', 'secretary', 'treasurer'] as $prefix) {
          foreach (['Id', 'UserId', 'GroupId'] as $postfix) {
            $official = $prefix.$postfix;
            $templateParameters[$official] = $this->getConfigValue($official, -1);
          }
        }

        foreach (['smtp', 'imap'] as $proto) {
          foreach (['server', 'port', 'security'] as $key) {
            $templateParameters[$proto.$key] =  $this->getConfigValue($proto.$key);
          }
        }
        foreach (['user', 'password', 'fromname', 'fromaddress', 'testaddress', 'testmode'] as $key) {
          $templateParameters['email'.$key] = $this->getConfigValue('email'.$key);
        }

        foreach (['Preview',
                  'Archive',
                  'Rehearsals',
                  'Trashbin',
                  'Template',
                  'ConcertModule',
                  'RehearsalsModule'] as $key) {
          $templateParameters['redaxo'.$key] = $this->getConfigValue('redaxo'.$key);
        }

        foreach ([
          'phpmyadmin' => null,
          'phpmyadmincloud' => null,
          'sourcecode' => null,
          'sourcedocs' => null,
          'clouddev' => null,
          'cspfailurereporting' => $this->urlGenerator()->linkToRouteAbsolute($this->appName().'.csp_violation.post', ['operation' => 'report']),
        ] as $link => $default) {
          $templateParameters[$link] = $this->getConfigValue($link, $default);
        }
      }

      return new TemplateResponse(
        $this->appName(),
        self::TEMPLATE,
        $templateParameters,
        'blank',
      );
    } catch(\Exception $e) {
      return $this->errorService->exceptionTemplate($e);
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
