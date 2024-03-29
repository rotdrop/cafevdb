<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2022, 2024 Claus-Justus Heine
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
use OCA\CAFEVDB\Database\Cloud\Mapper\BlogMapper;
use OCA\CAFEVDB\Service\FontService;

/** Register template-names as dependency injection tags. */
class Registration
{
  public const TEMPLATE_PREFIX = 'template:';
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
    $context->registerService(self::TEMPLATE_PREFIX . 'maintenance/configcheck', function($c) {
      return new class extends Renderer {}; // do nothing
    });
    $context->registerService(self::TEMPLATE_PREFIX . 'maintenance/debug', function($c) {
      return new class extends Renderer {}; // do nothing
    });
    $context->registerServiceAlias(self::TEMPLATE_PREFIX.'all-musicians', Musicians::class);
    $context->registerService(self::TEMPLATE_PREFIX.'add-musicians', function($c) {
      $musicians = $c->query(self::TEMPLATE_PREFIX.'all-musicians');
      $musicians->enableProjectMode();
      return $musicians;
    });
    $context->registerServiceAlias(self::TEMPLATE_PREFIX . Projects::TEMPLATE, Projects::class);
    $context->registerServiceAlias(self::TEMPLATE_PREFIX . ProjectParticipants::TEMPLATE, ProjectParticipants::class);
    $context->registerServiceAlias(self::TEMPLATE_PREFIX . ProjectInstrumentationNumbers::TEMPLATE, ProjectInstrumentationNumbers::class);
    $context->registerServiceAlias(self::TEMPLATE_PREFIX . ProjectPayments::TEMPLATE, ProjectPayments::class);
    $context->registerServiceAlias(self::TEMPLATE_PREFIX . SepaBankAccounts::TEMPLATE, SepaBankAccounts::class);
    $context->registerServiceAlias(self::TEMPLATE_PREFIX . SepaBulkTransactions::TEMPLATE, SepaBulkTransactions::class);
    $context->registerServiceAlias(self::TEMPLATE_PREFIX . InstrumentInsurances::TEMPLATE, InstrumentInsurances::class);
    $context->registerServiceAlias(self::TEMPLATE_PREFIX . ProjectParticipantFields::TEMPLATE, ProjectParticipantFields::class);
    $context->registerServiceAlias(self::TEMPLATE_PREFIX . Instruments::TEMPLATE, Instruments::class);
    $context->registerServiceAlias(self::TEMPLATE_PREFIX . InstrumentFamilies::TEMPLATE, InstrumentFamilies::class);
    $context->registerServiceAlias(self::TEMPLATE_PREFIX . InsuranceBrokers::TEMPLATE, InsuranceBrokers::class);
    $context->registerServiceAlias(self::TEMPLATE_PREFIX . InsuranceRates::TEMPLATE, InsuranceRates::class);
    $context->registerServiceAlias(self::TEMPLATE_PREFIX . TaxExemptionNotices::TEMPLATE, TaxExemptionNotices::class);
    $context->registerServiceAlias(self::TEMPLATE_PREFIX . DonationReceipts::TEMPLATE, DonationReceipts::class);
    $context->registerServiceAlias(self::TEMPLATE_PREFIX . Blog::TEMPLATE, BlogMapper::class);

    // @todo find a cleaner way for the following

    $context->registerService('export:'.'all-musicians', function($c) {
      $renderer = $c->query(self::TEMPLATE_PREFIX.'all-musicians');
      $fontService = $c->query(FontService::class);
      return new Export\PMETableSpreadsheetExporter($renderer, $fontService);
    });

    $context->registerService('export:'.'project-participants', function($c) {
      $renderer = $c->query(self::TEMPLATE_PREFIX.'project-participants');
      $fontService = $c->query(FontService::class);
      $projectService = $c->query(\OCA\CAFEVDB\Service\ProjectService::class);
      return new Export\PMETableSpreadsheetExporter($renderer, $fontService, $projectService);
    });

    $context->registerService('export:'.'sepa-bank-accounts', function($c) {
      $renderer = $c->query(self::TEMPLATE_PREFIX.'sepa-bank-accounts');
      $fontService = $c->query(FontService::class);
      return new Export\PMETableSpreadsheetExporter($renderer, $fontService);
    });

    $context->registerServiceAlias('export:'.'instrument-insurance', Export\InsuranceSpreadsheetExporter::class);
  }
}
