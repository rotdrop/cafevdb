<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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
    $context->registerServiceAlias('template:'.'project-instrumentation', ProjectInstrumentation::class);
    $context->registerServiceAlias('template:'.'project-payments', ProjectPayments::class);
    $context->registerServiceAlias('template:'.'project-extra-fields', ProjectExtraFields::class);
    $context->registerServiceAlias('template:'.'instruments', Instruments::class);
    $context->registerServiceAlias('template:'.'instrument-families', InstrumentFamilies::class);
    $context->registerServiceAlias('template:'.'blog', BlogMapper::class);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
