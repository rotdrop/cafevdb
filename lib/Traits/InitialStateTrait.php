<?php
/*
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Traits;

use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\IUser;
use OCP\IConfig;

use OCA\CAFEVDB\Common\Config;
use OCA\CAFEVDB\Service\HistoryService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;

trait InitialStateTrait {
  use ConfigTrait;

  /** @var string */
  protected $appName;

  /** @var IL10N */
  protected $l;

  /** @var IInitialStateService */
  private $initialStateService;

  /** @var HistoryService */
  private $historyService;

  protected function publishInitialStateForUser(string $userId) {
    $l = $this->l10N();

    $tooltips   = $this->getUserValue('tooltips', '');
    $directChg  = $this->getUserValue('directchange', '');
    $deselectInvisible = $this->getUserValue('deselectInvisibleMiscRecs', '');
    $editor     = $this->getUserValue('wysiwygEditor', 'tinymce');
    $expertMode = $this->getUserValue('expertmode');

    $expertMode = filter_var($expertMode, FILTER_VALIDATE_BOOLEAN);

    $adminContact = \OC::$server->query(OrganizationalRolesService::class)->cloudAdminContact(implode: true);

    $languageComplete = $l->getLanguageCode();
    list($languageShort,) = explode('_', $languageComplete);
    $locale = $l->getLocaleCode();

    $this->initialStateService->provideInitialState(
      $this->appName,
      'CAFEVDB',
      [
        'appName' => $this->appName,
        'toolTipsEnabled' => ($tooltips == 'off' ? false : true),
        'wysiwygEditor' => $editor,
        'language' => $languageShort,
        'cloudLanguage' => $languageComplete,
        'locale' => $locale,
        'currencySymbol' => $this->currencySymbol(),
        'currencyCode' => $this->currencyCode(),
        'adminContact' => $adminContact,
        'phpUserAgent' => $_SERVER['HTTP_USER_AGENT'], // @@todo get from request
        'expertMode' => $expertMode,
        'Page' => [
          'historySize' => $this->historyService->size(),
          'historyPosition' => $this->historyService->position(),
        ],
        'sharedFolder' => $this->getSharedFolderPath(),
        'projectsFolder' => $this->getProjectsFolderPath(),
      ]);

    $this->initialStateService->provideInitialState(
      $this->appName,
      'PHPMyEdit',
      [
        'directChange' => ($directChg == "on" ? true : false),
        'deselectInvisibleMiscRecs' => ($deselectInvisible == 'on' ? true : false),
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
      ]);

    $calendarApp = \OC::$server->query(\OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_App::class);
    $this->initialStateService->provideInitialState(
      $this->appName,
      'Calendar',
      [
        'categories' => $calendarApp->getCategoryOptions()
      ]
    );
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
