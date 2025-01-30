<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2022, 2024, 2025 Claus-Justus Heine
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

use OCP\AppFramework\Bootstrap\IRegistrationContext;

use OCA\CAFEVDB\Database\Legacy\PME\IOptions as IPMEOptions;
use OCA\CAFEVDB\Service\FontService;
use OCA\CAFEVDB\Constants;

/** Register template-names as dependency injection tags. */
class Registration
{
  public const TEMPLATE_PREFIX = 'template:';

  private const LEGACY_TEMPLATES = [
    ConfigCheck::TEMPLATE => ConfigCheck::class,
    AllMusicians::TEMPLATE => AllMusicians::class,
    AddMusicians::TEMPLATE => AddMusicians::class,
    Projects::TEMPLATE => Projects::class,
    ProjectParticipants::TEMPLATE => ProjectParticipants::class,
    ProjectInstrumentationNumbers::TEMPLATE => ProjectInstrumentationNumbers::class,
    ProjectPayments::TEMPLATE => ProjectPayments::class,
    SepaBankAccounts::TEMPLATE => SepaBankAccounts::class,
    SepaBulkTransactions::TEMPLATE => SepaBulkTransactions::class,
    InstrumentInsurances::TEMPLATE => InstrumentInsurances::class,
    ProjectParticipantFields::TEMPLATE => ProjectParticipantFields::class,
    Instruments::TEMPLATE => Instruments::class,
    InstrumentFamilies::TEMPLATE => InstrumentFamilies::class,
    InsuranceBrokers::TEMPLATE => InsuranceBrokers::class,
    InsuranceRates::TEMPLATE => InsuranceRates::class,
    TaxExemptionNotices::TEMPLATE => TaxExemptionNotices::class,
    DonationReceipts::TEMPLATE => DonationReceipts::class,
    Blog::TEMPLATE => Blog::class,
  ];

  /**
   * @param IRegistrationContext $context
   *
   * @return void
   */
  public static function register(IRegistrationContext $context):void
  {
    // phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace.ContentBefore
    // phpcs:disable PEAR.WhiteSpace.ScopeClosingBrace.Line
    $context->registerService(IPMEOptions::class, function($c) {
      return $c->query(PME\Config::class);
    });
    $context->registerService(self::TEMPLATE_PREFIX . 'maintenance/debug', function($c) {
      return new class extends Renderer {}; // do nothing
    });

    foreach (self::LEGACY_TEMPLATES as $template => $class) {
      $context->registerServiceAlias(self::TEMPLATE_PREFIX . $template, $class);
      // There are subtle difficulties with special characters in url
      // parameteres. Sometime double encoding is neccessary, sometimes
      // not. Not clear, what causes the problem. As our templates at most
      // contain / path separators simply work around by replacing the path
      // separator by something "normal".
      if (str_contains($template, Constants::PATH_SEP)) {
        $context->registerServiceAlias(self::TEMPLATE_PREFIX . str_replace(Constants::PATH_SEP, ':', $template), $class);
      }
    }

    // @todo find a cleaner way for the following

    $context->registerService('export:' . AllMusicians::TEMPLATE, function($c) {
      $renderer = $c->query(self::TEMPLATE_PREFIX . AllMusicians::TEMPLATE);
      $fontService = $c->query(FontService::class);
      return new Export\PMETableSpreadsheetExporter($renderer, $fontService);
    });

    $context->registerService('export:' . ProjectParticipants::TEMPLATE, function($c) {
      $renderer = $c->query(self::TEMPLATE_PREFIX . ProjectParticipants::TEMPLATE);
      $fontService = $c->query(FontService::class);
      $projectService = $c->query(\OCA\CAFEVDB\Service\ProjectService::class);
      return new Export\PMETableSpreadsheetExporter($renderer, $fontService, $projectService);
    });

    $context->registerService('export:' . SepaBankAccounts::TEMPLATE, function($c) {
      $renderer = $c->query(self::TEMPLATE_PREFIX . SepaBankAccounts::TEMPLATE);
      $fontService = $c->query(FontService::class);
      return new Export\PMETableSpreadsheetExporter($renderer, $fontService);
    });

    $context->registerServiceAlias('export:' . InstrumentInsurances::TEMPLATE, Export\InsuranceSpreadsheetExporter::class);
  }
}
