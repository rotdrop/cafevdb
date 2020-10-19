<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IL10N;

use OCA\CAFEVDB\BackgroundJob\LazyUpdateGeoCoding;
use OCA\CAFEVDB\Service\ConfigService;

class BackgroundJobController extends Controller
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;

  /** @var IL10N */
  private $l;

  /** @var LazyUpdateGeoCoding */
  private $lazyUpdateGeoCoding;

  public function __construct(
    $appName,
    IRequest $request,
    ConfigService $configService,
    LazyUpdateGeoCoding $lazyUpdateGeoCoding
  ) {
    parent::__construct($appName, $request);

    $this->configService = $configService;
    $this->lazyUpdateGeoCoding = $lazyUpdateGeoCoding;
    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   */
  public function trigger()
  {
    try {
      $this->lazyUpdateGeoCoding->run();
      return self::response('Ran background jobs');
    } catch (\Throwable $t) {
      $this->logException($t);
      return self::grumble(
        $this->l->t('Caught exception \`%s\' at %s:%s',
                    [$t->getMessage(), $t->getFile(), $t->getLine()])
      );
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
