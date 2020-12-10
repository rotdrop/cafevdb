<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\IUserSession;
use OCP\ILogger;

use OCA\CAFEVDB\Service\AuthorizationService;

class Personal implements ISettings {
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var string */
  private $appName;

  /** @var string */
  private $userId;

  /** @var \OCA\CAFEVDB\Service\AuthorizationService */
  private $authorizationService;

  /** @var \OCP\IUserSession */
  private $userSession;

  public function __construct(
    $appName
    , $userId
    , AuthorizationService $authorizationService
    , IUserSession $userSession
    , ILogger $logger
  ) {
    $this->appName = $appName;
    $this->userId = $userId;
    $this->authorizationService = $authorizationService;
    $this->userSession = $userSession;
    $this->logger = $logger;
  }

  public function getForm() {
    if (!$this->authorizationService->authorized($this->userId)) {
      return new TemplateResponse(
        $this->appName,
        self::ERROR_TEMPLATE,
        [
          'error' => 'notamember',
          'userId' => $this->userId,
        ], 'blank');
    }
    return \OC::$server->query(PersonalForm::class)->getForm();
  }

  /**
   * @return string the section ID, e.g. 'sharing'
   * @since 9.1
   */
  public function getSection() {
    return $this->appName;
  }

  /**
   * @return int whether the form should be rather on the top or bottom of
   * the admin section. The forms are arranged in ascending order of the
   * priority values. It is required to return a value between 0 and 100.
   *
   * E.g.: 70
   * @since 9.1
   */
  public function getPriority() {
    // @@TODO could be made a configure option.
    return 50;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
