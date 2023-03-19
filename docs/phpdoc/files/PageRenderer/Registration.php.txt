<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2022 Claus-Justus Heine
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
    $context->registerService('template:' . 'maintenance/configcheck', function($c) {
      return new class extends Renderer {}; // do nothing
    });
    $context->registerService('template:' . 'maintenance/debug', function($c) {
      return new class extends Renderer {}; // do nothing
    });
    $context->registerServiceAlias('template:'.'all-musicians', Musicians::class);
    $context->registerService('template:'.'add-musicians', function($c) {
      $musicians = $c->query('template:'.'all-musicians');
      $musicians->enableProjectMode();
      return $musicians;
    });
    $context->registerServiceAlias('template:'.'projects', Projects::class);
    $context->registerServiceAlias('template:'.'project-participants', ProjectParticipants::class);
    $context->registerServiceAlias('template:'.'project-instrumentation-numbers', ProjectInstrumentationNumbers::class);
    $context->registerServiceAlias('template:'.'project-payments', ProjectPayments::class);
    $context->registerServiceAlias('template:'.'sepa-bank-accounts', SepaBankAccounts::class);
    $context->registerServiceAlias('template:'.'sepa-bulk-transactions', SepaBulkTransactions::class);
    $context->registerServiceAlias('template:'.'instrument-insurance', InstrumentInsurances::class);
    $context->registerServiceAlias('template:'.'project-participant-fields', ProjectParticipantFields::class);
    $context->registerServiceAlias('template:'.'instruments', Instruments::class);
    $context->registerServiceAlias('template:'.'instrument-families', InstrumentFamilies::class);
    $context->registerServiceAlias('template:'.'insurance-brokers', InsuranceBrokers::class);
    $context->registerServiceAlias('template:'.'insurance-rates', InsuranceRates::class);
    $context->registerServiceAlias('template:'.'blog/blog', BlogMapper::class);

    // @todo find a cleaner way for the following

    $context->registerService('export:'.'all-musicians', function($c) {
      $renderer = $c->query('template:'.'all-musicians');
      $fontService = $c->query(FontService::class);
      return new Export\PMETableSpreadsheetExporter($renderer, $fontService);
    });

    $context->registerService('export:'.'project-participants', function($c) {
      $renderer = $c->query('template:'.'project-participants');
      $fontService = $c->query(FontService::class);
      $projectService = $c->query(\OCA\CAFEVDB\Service\ProjectService::class);
      return new Export\PMETableSpreadsheetExporter($renderer, $fontService, $projectService);
    });

    $context->registerService('export:'.'sepa-bank-accounts', function($c) {
      $renderer = $c->query('template:'.'sepa-bank-accounts');
      $fontService = $c->query(FontService::class);
      return new Export\PMETableSpreadsheetExporter($renderer, $fontService);
    });

    $context->registerServiceAlias('export:'.'instrument-insurance', Export\InsuranceSpreadsheetExporter::class);
  }
}
