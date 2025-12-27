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
use OCP\IInitialStateService;
use OCP\IL10N;

use OCA\Calendar\Service\CalendarInitialStateService;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Controller\DTO;
use OCA\CAFEVDB\Controller\EnumInitialStateKey;
use OCA\CAFEVDB\Controller\EnumPersonalSettingsKey;
use OCA\CAFEVDB\Documents\TemplateService;
use OCA\CAFEVDB\PageRenderer\DataConstants;
use OCA\CAFEVDB\PageRenderer\PMETableViewBase;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\HistoryService;
use OCA\CAFEVDB\Service\ImagesService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Settings\Admin;
use OCA\CAFEVDB\Settings\ConfigConstants;

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

    $toolTipsEnabled = filter_var($this->getUserValue(EnumPersonalSettingsKey::TOOL_TIPS_ENABLED, 'on'), FILTER_VALIDATE_BOOLEAN);
    $directChange  = filter_var($this->getUserValue(EnumPersonalSettingsKey::DIRECT_CHANGE, 'off'), FILTER_VALIDATE_BOOLEAN);
    $showDisabled = filter_var($this->getUserValue(EnumPersonalSettingsKey::SHOW_DISABLED, 'off'), FILTER_VALIDATE_BOOLEAN);
    $deselectInvisible = filter_var($this->getUserValue(EnumPersonalSettingsKey::DESELECT_INVISIBLE_MISC_RECS, 'off'), FILTER_VALIDATE_BOOLEAN);
    $initialFilterVisibility = filter_var($this->getUserValue(EnumPersonalSettingsKey::INITIAL_FILTER_VISIBILITY, 'on'), FILTER_VALIDATE_BOOLEAN);
    $wysiwygEditor = $this->getUserValue(EnumPersonalSettingsKey::WYSIWYG_EDITOR, 'tinymce');
    $pageRowsDefault = $this->getUserValue(EnumPersonalSettingsKey::PAGE_ROWS_DEFAULT, 20);
    $restoreHistory = filter_var($this->getUserValue(EnumPersonalSettingsKey::RESTORE_HISTORY, 'off'), FILTER_VALIDATE_BOOLEAN);
    $expertMode = filter_var($this->getUserValue(EnumPersonalSettingsKey::EXPERT_MODE, 'off'), FILTER_VALIDATE_BOOLEAN);
    $financeMode = filter_var($this->getUserValue(EnumPersonalSettingsKey::FINANCE_MODE, 'off'), FILTER_VALIDATE_BOOLEAN);

    $debugMode = $this->getUserValue(EnumPersonalSettingsKey::DEBUG_MODE, 0);
    $debugQuerySqlFilter = $this->getUserValue(EnumPersonalSettingsKey::DEBUG_QUERY_SQL_FILTER, '');

    $authorizationService = $this->appContainer->get(AuthorizationService::class);

    $languageComplete = $l->getLanguageCode();
    list($languageShort,) = explode('_', $languageComplete);
    $locale = $l->getLocaleCode();

    try {
      /** @var TemplateService $templateService */
      $templateService = $this->appContainer->get(TemplateService::class);
      $orchestraLogo = $templateService->getDocumentTemplate(ConfigConstants::DOCUMENT_TEMPLATE_LOGO);

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
      EnumInitialStateKey::CAFEVDB->value,
      DTO\CAFEVDBInitialState::fromArray([
        'appName' => $this->appName,
        // TRANSLATORS: default value for unconfigured setting.
        ConfigConstants::ORCHESTRA_NAME_KEY => $this->getConfigValue(ConfigConstants::ORCHESTRA_NAME_KEY, $this->l->t('unconfigured')),
        'orchestraLogo' => $orchestraLogo ?? '',
        EnumPersonalSettingsKey::TOOL_TIPS_ENABLED->value => $toolTipsEnabled,
        EnumPersonalSettingsKey::WYSIWYG_EDITOR->value => $wysiwygEditor,
        'language' => $languageShort,
        'cloudLanguage' => $languageComplete,
        'locale' => $locale,
        // app-locale
        'currencySymbol' => $this->currencySymbol(),
        'currencyCode' => $this->currencyCode(),
        'appLocale' => $this->appLocale(),
        //
        'serverRoot' => \OC::$SERVERROOT,
        EnumPersonalSettingsKey::EXPERT_MODE->value => $expertMode,
        EnumPersonalSettingsKey::FINANCE_MODE->value => $financeMode,
        EnumPersonalSettingsKey::DEBUG_MODE->value => $debugMode,
        EnumPersonalSettingsKey::DEBUG_QUERY_SQL_FILTER->value => $debugQuerySqlFilter,
        EnumPersonalSettingsKey::RESTORE_HISTORY->value => $restoreHistory,
        'userPermissions' => $authorizationService->getUserPermissions($this->userId()),
        'isGroupAdmin' => $authorizationService->isAdmin($this->userId()),
        ConfigConstants::SHARED_FOLDER => $this->getSharedFolderPath(),
        ConfigConstants::PROJECTS_FOLDER => $this->getProjectsFolderPath(),
        Admin::WIKI_NAME_SPACE_KEY => $this->getAppValue(ConfigConstants::WIKI_NAME_SPACE_KEY),
        'uploadMaxFileSize' => Util::maxUploadSize(),
      ]),
    );

    $initialStateService->provideInitialState(
      $this->appName,
      EnumInitialStateKey::PHP_MY_EDIT->value,
      DTO\PMEInitialState::fromArray([
        EnumPersonalSettingsKey::DIRECT_CHANGE->value => $directChange,
        EnumPersonalSettingsKey::SHOW_DISABLED->value => $showDisabled,
        EnumPersonalSettingsKey::DESELECT_INVISIBLE_MISC_RECS->value => $deselectInvisible,
        EnumPersonalSettingsKey::INITIAL_FILTER_VISIBILITY->value => $initialFilterVisibility,
        EnumPersonalSettingsKey::PAGE_ROWS_DEFAULT->value => $pageRowsDefault,
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
        'pageRenderer' => DataConstants::PAGE_RENDERER,
      ]),
    );

    /** @var CalendarInitialStateService $calendarInitialStateService */
    $calendarInitialStateService = $this->appContainer->get(CalendarInitialStateService::class);
    $calendarInitialStateService->run();
  }
}
