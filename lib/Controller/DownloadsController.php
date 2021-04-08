<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IL10N;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\CalDavService;
use OCA\CAFEVDB\Common\Util;

class DownloadsController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;

  /** @var CalDavService */
  private $calDavService;

  public function __construct(
    $appName
    , IRequest $request
    , ConfigService $configService
    , CalDavService $calDavService
  ) {

    parent::__construct($appName, $request);

    $this->configService = $configService;
    $this->calDavService = $calDavService;
    $this->l = $this->l10N();
  }

  /**
   * Fetch something and return it as download.
   *
   * @param string $section Cosmetics, for grouping purposes
   *
   * @param sting $object Something identifying the object in the
   * context of $section.
   *
   * @return mixed \OCP\Response Something derived from \OCP\Response
   *
   * @NoAdminRequired
   */
  public function fetch($section, $object)
  {
    switch ($section) {
    case 'test':
      switch ($object) {
      case 'pdfletter':
        /** @var \OCA\CAFEVDB\Documents\PDFLetter $letterGenerator */
        $letterGenerator = $this->di(\OCA\CAFEVDB\Documents\PDFLetter::class);
        $fileName = 'cafevdb-test-letter.pdf';
        $letter = $letterGenerator->testLetter($fileName, 'S');
        return new DataDownloadResponse($letter, $fileName, 'application/pdf');
      }
      break;
    }
    return self::grumble($this->l->t('Unknown Request'));
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
