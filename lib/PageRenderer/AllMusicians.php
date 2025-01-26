<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\PageRenderer;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ContactsService;
use OCA\CAFEVDB\Service\Finance\InstrumentInsuranceService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\MailingListsService;
use OCA\CAFEVDB\Service\MusicianService;
use OCA\CAFEVDB\Service\PhoneNumberService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;

/**Table generator for Musicians table. */
class AllMusicians extends Musicians
{
  const TEMPLATE = parent::ALL_TEMPLATE;

  /** {@inheritdoc} */
  public function __construct(
    ConfigService $configService,
    RequestParameterService $requestParameters,
    EntityManager $entityManager,
    PHPMyEdit $phpMyEdit,
    ToolTipsService $toolTipsService,
    PageNavigation $pageNavigation,
    GeoCodingService $geoCodingService,
    ContactsService $contactsService,
    PhoneNumberService $phoneNumberService,
    InstrumentInsuranceService $insuranceService,
    MusicianService $musicianService,
    MailingListsService $listsService,
  ) {
    parent::__construct(
      self::TEMPLATE,
      $configService,
      $requestParameters,
      $entityManager,
      $phpMyEdit,
      $toolTipsService,
      $pageNavigation,
      $geoCodingService,
      $contactsService,
      $phoneNumberService,
      $insuranceService,
      $musicianService,
      $listsService,
    );
  }

  /** {@inheritdoc} */
  public function shortTitle()
  {
    return parent::commonShortTitle() ?? $this->l->t("Add musicians to the project `%s'", [ $this->projectName ]);
  }

  /** {@inheritdoc} */
  public function navigationItems():array
  {
    return [
      AllMusicians::navigationItem(),
      Projects::navigationItem(),
      Instruments::navigationItem(),
      InstrumentInsurances::navigationItem(),
      SepaBankAccounts::navigationItem(),
      Instruments::navigationItem(),
      InstrumentFamilies::navigationItem(),
      Blog::navigationItem(),
    ];
  }

  /** {@inheritdoc} */
  public function render(bool $execute = true):void
  {
    ['opts' => $opts] = parent::generatePMEOptions();

    $export = $this->pageNavigation->tableExportButton();
    $opts['buttons'] = $this->pageNavigation->prependTableButton($export, true);

    $opts = $this->mergeDefaultOptions($opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }
}
