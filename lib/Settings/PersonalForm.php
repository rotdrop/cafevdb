<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\DatabaseFactory;
use OCA\CAFEVDB\Service\DatabaseService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\ErrorService;
use OCA\CAFEVDB\Service\TranslationService;

use OCA\DokuWikiEmbedded\Service\AuthDokuWiki as WikiRPC;
use OCA\Redaxo4Embedded\Service\RPC as WebPagesRPC;

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

  /** @var OCA\CAFEVDB\Service\TranslationService */
  private $translationService;

  /** @var OCA\DokuWikiEmedded\Service\AuthDokuWiki */
  private $wikiRPC;

  /** @var OCA\Redaxo4Embedded\Service\RPC */
  private $webPagesRPC;

  public function __construct(
    ConfigService $configService
    , ProjectService $projectService
    , ToolTipsService $toolTipsService
    , ErrorService $errorService
    , TranslationService $translationService
    , WikiRPC $wikiRPC
    , WebPagesRPC $webPagesRPC
  ) {
    $this->configService = $configService;
    $this->projectService = $projectService;
    $this->toolTipsService = $toolTipsService;
    $this->errorService = $errorService;
    $this->translationService = $translationService;
    $this->wikiRPC = $wikiRPC;
    $this->webPagesRPC = $webPagesRPC;
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
        'debugMode' => $this->getUserValue('debug', 0),
        'pagerows' => $this->getUserValue('pagerows', 20),
        'toolTips' => $this->toolTipsService,
        'filtervisibility' => $this->getUserValue('filtervisibility', 'off'),
        'directchange' => $this->getUserValue('directchange', 'off'),
        'showdisabled' => $this->getUserValue('showdisabled', 'off'),
        'expertmode' => $this->getUserValue('expertmode', 'off'),
        'wysiwygEditor' => $this->getUserValue('wysiwygEditor', self::DEFAULT_EDITOR),
        'wysiwygOptions' => ConfigService::WYSIWYG_EDITORS,
        'webPageCategories' => $webPageCategories,
        'webPageTemplates' => $webPageTemplates,
        'webPageModules' => $webPageModules,
      ];

      if ($isGroupAdmin) {
        $executiveBoardTable = $this->getConfigValue('executiveBoardTable', $this->l->t('ExecutiveBoardMembers'));
        $executiveBoardTableId = $this->getConfigValue('executiveBoardTableId', -1);
        if ($this->databaseConfigured() && $executiveBoardTableId > 0) {
          // this can throw if there is no datadase configured yet.
          try {
            $executiveBoardMembers = $this->projectService->participantOptions($executiveBoardTableId, $executiveBoardTable);
          } catch(\Exception $e) {
            $executiveBoardMembers = [];
          }
        } else {
          $executiveBoardMembers = [];
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

            'phoneNumber' => $this->getConfigValue('phoneNumber'),

            'bankAccountOwner' => $this->getConfigValue('bankAccountOwner'),
            'bankAccountIBAN' => $this->getConfigValue('bankAccountIBAN'),
            'bankAccountBLZ' => $this->getConfigValue('bankAccountBLZ'),
            'bankAccountBIC' => $this->getConfigValue('bankAccountBIC'),
            'bankAccountCreditorIdentifier' => $this->getConfigValue('bankAccountCreditorIdentifier'),

            'memberTable' => $this->getConfigValue('memberTable', $this->l->t('ClubMembers')),
            'memberTableId' => $this->getConfigValue('memberTableId', -1),
            'executiveBoardTable' => $executiveBoardTable,
            'executiveBoardTableId' => $executiveBoardTableId,
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
            'musiciansaddressbook' => $this->getConfigValue('musisiciansaddressbook', $this->l->t('Musicians')),

            'sharedfolder' => $this->getConfigValue('sharedfolder',''),
            'projectsfolder' => $this->getConfigValue('projectsfolder',''),
            'projectsbalancefolder' => $this->getConfigValue('projectsbalancefolder',''),

            'translations' => $this->translationService->getTranslations(),
          ]);

        // musician ids of the officials
        foreach (['president', 'secretary', 'treasurer'] as $prefix) {
          foreach (['Id', 'UserId', 'GroupId'] as $postfix) {
            $official = $prefix.$postfix;
            $templateParameters[$official] = $this->getConfigValue($official, -1);
          }
        }

        foreach (['smtp', 'imap'] as $proto) {
          foreach (['server', 'port', 'secure'] as $key) {
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

        foreach (['phpmyadmin',
                  'phpmyadmincloud',
                  'sourcecode',
                  'sourcedocs',
                  'ownclouddev'] as $link) {
          $templateParameters[$link] = $this->getConfigValue($link);
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
