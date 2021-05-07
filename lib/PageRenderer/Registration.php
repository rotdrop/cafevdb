<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 * License along with this library.  If not, see <http://www.gnuorg/licenses/>.
 */

namespace OCA\CAFEVDB\PageRenderer;

use OCP\AppFramework\Bootstrap\IRegistrationContext;

use OCA\CAFEVDB\Database\Legacy\PME\IOptions as IPMEOptions;
use OCA\CAFEVDB\Database\Cloud\Mapper\BlogMapper;

class Registration
{
  static public function register($context)
  {
    $context->registerService(IPMEOptions::class, function($c) {
      return $c->query(PME\Config::class);
    });
    $context->registerService('template:'.'configcheck', function($c) {
      return new class extends Renderer {}; // do nothing
    });
    $context->registerService('template:'.'debug', function($c) {
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
    $context->registerServiceAlias('template:'.'blog', BlogMapper::class);

    // @todo find a cleaner way for the following

    $context->registerService('export:'.'all-musicians', function($c) {
      $renderer = $c->query('template:'.'all-musicians');
      return new Export\PMETableSpreadsheetExporter($renderer);
    });

    $context->registerService('export:'.'project-participants', function($c) {
      $renderer = $c->query('template:'.'project-participants');
      $projectService = $c->query(\OCA\CAFEVDB\Service\ProjectService::class);
      return new Export\PMETableSpreadsheetExporter($renderer, $projectService);
    });

    $context->registerService('export:'.'sepa-bank-accounts', function($c) {
      $renderer = $c->query('template:'.'sepa-bank-accounts');
      return new Export\PMETableSpreadsheetExporter($renderer);
    });

    $context->registerServiceAlias('export:'.'instrument-insurance', Export\InsuranceSpreadsheetExporter::class);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
