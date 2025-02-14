<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022, 2023, 2023, 2024, 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Traits;

use Throwable;

use OCP\AppFramework\IAppContainer;
use OCP\IConfig;
use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\IUser;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Documents\TemplateService;
use OCA\CAFEVDB\PageRenderer\PMETableViewBase;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\HistoryService;
use OCA\CAFEVDB\Service\ImagesService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;

use OCA\Calendar\Service\CalendarInitialStateService;

/** Provide an "initial state" for JavaScript. */
trait InitialStateTrait
{
  use ConfigTrait;

  /** @var string */
  protected $appName;

  /** @var IL10N */
  protected IL10N $l;

  /** @var IInitialStateService */
  protected IInitialStateService $initialStateService;

  /** @var HistoryService */
  protected HistoryService $historyService;

  /** @var ConfigService */
  protected ConfigService $configService;

  /** @var IAppContainer */
  protected IAppContainer $appContainer;

  /**
   * @param string $userId
   *
   * @return void
   *
   * @SuppressWarnings(PHPMD.Superglobals)
   */
  protected function publishInitialStateForUser(string $userId):void
  {
    $l = $this->l10N();

    $tooltips   = filter_var($this->getUserValue('tooltips', 'on'), FILTER_VALIDATE_BOOLEAN);
    $directChg  = filter_var($this->getUserValue('directchange', 'off'), FILTER_VALIDATE_BOOLEAN);
    $showDisabled = filter_var($this->getUserValue('showdisabled', 'off'), FILTER_VALIDATE_BOOLEAN);
    $deselectInvisible = filter_var($this->getUserValue('deselectInvisibleMiscRecs', 'off'), FILTER_VALIDATE_BOOLEAN);
    $initialFilterVisibility = filter_var($this->getUserValue('filtervisibility', 'on'), FILTER_VALIDATE_BOOLEAN);
    $editor     = $this->getUserValue('wysiwygEditor', 'tinymce');
    $pageRows   = $this->getUserValue('pagerows', 20);

    $restoreHistory = filter_var($this->getUserValue('restorehistory', 'off'), FILTER_VALIDATE_BOOLEAN);
    $expertMode = $this->getUserValue('expertMode');
    $expertMode = filter_var($expertMode, FILTER_VALIDATE_BOOLEAN);

    $financeMode = $this->getUserValue('financeMode');
    $financeMode = filter_var($financeMode, FILTER_VALIDATE_BOOLEAN);

    $adminContact = $this->appContainer->get(OrganizationalRolesService::class)->cloudAdminContact(implode: true);

    $authorizationService = $this->appContainer->get(AuthorizationService::class);

    $languageComplete = $l->getLanguageCode();
    list($languageShort,) = explode('_', $languageComplete);
    $locale = $l->getLocaleCode();

    try {
      /** @var TemplateService $templateService */
      $templateService = $this->appContainer->get(TemplateService::class);
      $orchestraLogo = $templateService->getDocumentTemplate(ConfigService::DOCUMENT_TEMPLATE_LOGO);

      if ($orchestraLogo) {
        /** @var ImagesService $imagesService */
        $imagesService = $this->appContainer->get(ImagesService::class);

        $orchestraLogo = $imagesService->svgFromFile($orchestraLogo, ImagesService::SVG_OPTIMIZE);
      }
    } catch (Throwable $t) {
      $this->logException($t);
    }

    /** @var IInitialStateService $initialStateService */
    $initialStateService = $this->appContainer->get(IInitialStateService::class);

    $initialStateService->provideInitialState(
      $this->appName,
      'CAFEVDB',
      [
        'appName' => $this->appName,
        ConfigService::ORCHESTRA_NAME_KEY => $this->getConfigValue(ConfigService::ORCHESTRA_NAME_KEY, $this->l->t('unconfigured')),
        'orchestraLogo' => $orchestraLogo ?? '',
        'toolTipsEnabled' => $tooltips,
        'wysiwygEditor' => $editor,
        'language' => $languageShort,
        'cloudLanguage' => $languageComplete,
        'locale' => $locale,
        'currencySymbol' => $this->currencySymbol(),
        'currencyCode' => $this->currencyCode(),
        'adminContact' => $adminContact,
        'phpUserAgent' => $_SERVER['HTTP_USER_AGENT'], // @@todo get from request
        'expertMode' => $expertMode,
        'financeMode' => $financeMode,
        'debugModes' => $this->getConfigValue('debugmode', 0),
         'restoreHistory' => $restoreHistory,
        'userPermissions' => $authorizationService->getUserPermissions($this->userId()),
        'isGroupAdmin' => $authorizationService->isAdmin($this->userId()),
        'sharedFolder' => $this->getSharedFolderPath(),
        'projectsFolder' => $this->getProjectsFolderPath(),
        'wikiNamespace' => $this->getAppValue('wikinamespace'),
        'uploadMaxFileSize' => Util::maxUploadSize(),
      ]);

    $initialStateService->provideInitialState(
      $this->appName,
      'PHPMyEdit',
      [
        'directChange' => $directChg,
        'showDisabled' => $showDisabled,
        'deselectInvisibleMiscRecs' => $deselectInvisible,
        'initialFilterVisibility' => $initialFilterVisibility,
        'pageRowsDefault' => $pageRows,
        'selectChosen' => true,
        'filterSelectPlaceholder' => $l->t("Select a filter option."),
        'filterSelectNoResult' => $l->t("No values match."),
        'filterSelectChosenTitle' => $l->t("Select from the pull-down menu. ".
                                           "Double-click will submit the form. ".
                                           "The pull-down can be closed by clicking ".
                                           "anywhere outside the menu."),
        'inputSelectPlaceholder' => $l->t("Select an option."),
        'inputSelectNoResult' => $l->t("No values match.")."'",
        'inputSelectChosenTitle' => $l->t("Select from the pull-down menu. ".
                                          "The pull-down can be closed by clicking ".
                                          "anywhere outside the menu."),
        'pageRenderer' => [
          'masterFieldSuffix' => PMETableViewBase::MASTER_FIELD_SUFFIX,
          'valuesTableSep' => PMETableViewBase::VALUES_TABLE_SEP,
          'joinKeySep' => PMETableViewBase::JOIN_KEY_SEP,
          'compKeySep' => PMETableViewBase::COMP_KEY_SEP,
          'joinFieldNameSeparator' => PMETableViewBase::JOIN_FIELD_NAME_SEPARATOR,
        ],
      ]);

    $calendarApp = \OC::$server->query(\OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_App::class);
    $this->initialStateService->provideInitialState(
      $this->appName,
      'Calendar',
      [
        'categories' => $calendarApp->getCategoryOptions()
      ]
    );

    /** @var CalendarInitialStateService $calendarInitialStateService */
    $calendarInitialStateService = $this->appContainer->get(CalendarInitialStateService::class);
    $calendarInitialStateService->run();
  }
}
