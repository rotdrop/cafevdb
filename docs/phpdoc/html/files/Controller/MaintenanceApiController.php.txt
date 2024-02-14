<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\AppFramework\OCS;
use OCP\AppFramework\Http\DataResponse;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ProjectService;

/** AJAX end-points for an app-maintenance API. */
class MaintenanceApiController extends OCSController
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  const TOPIC_PLAYGROUND = 'playground';
  const TOPIC_ENCRYPTION_KEY = 'encryption-key';
  const TOPIC_WIKI = 'wiki';

  const ENCRYPTION_KEY_STATUS = 'status';
  const ENCRYPTION_KEY_SET = 'set';
  const ENCRYPTION_KEY_DISTRIBUTE = 'distribute';
  const ENCRYPTOIN_KEY_RECRYPT = 'recrypt';

  const PLAYGROUND_HELLO = 'hello';

  const WIKI_PROJECTS_OVERVIEW = 'projects-overview';

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    protected ConfigService $configService,
  ) {
    parent::__construct($appName, $request);

    $this->l = $this->l10n();
  }
  // phpcs:enable

  /**
   * @param string $topic
   *
   * @param string $operation
   *
   * @return DataResponse
   *
   * @throws OCS\OCSNotFoundException
   *
   * @CORS
   * @NoCSRFRequired
   * @NoAdminRequired
   * @ServiceAccountRequired
   */
  public function serviceSwitch(string $topic, string $operation):DataResponse
  {
    switch ($topic) {
      case self::TOPIC_ENCRYPTION_KEY:
        switch ($operation) {
          case self::ENCRYPTION_KEY_DISTRIBUTE:
            return new DataResponse($this->distributeEncryptionKey());
          case self::ENCRYPTION_KEY_SET:
            // unconditionally set the encryption key just for the current
            // of given user
            $userId = $this->request->getParam('user-id', $this->userId());
            if (!$this->encryptionKeyValid()) {
              // perhaps this should throw
              $data = [ 'keyStatus' => 'invalid', ];
            } else {
              $appEncryptionKey = $this->getAppEncryptionKey();
              $data = [
                'keyStatus' => empty($appEncryptionKey) ? 'unset' : 'set',
                'userStatus' => [
                  $this->setUserEncryptionKey($userId, $appEncryptionKey),
                ],
              ];
            }
            return new DataResponse($data);
          case self::ENCRYPTION_KEY_RECRYPT:
            // perhaps ...
        }
        break;
      case self::TOPIC_WIKI:
        switch ($operation) {
          case self::WIKI_PROJECTS_OVERVIEW:
            /** @var ProjectService $projectService */
            $projectService = $this->di(ProjectService::class);
            if ($projectService->generateWikiOverview()) {
              return new DataResponse([ 'status' => 'ok' ]);
            } else {
              return new DataResponse([ 'status' => 'failed' ]);
            }
            break;
        }
        break;
    }
    throw new OCS\OCSNotFoundException;
  }

  /**
   * @param string $topic
   *
   * @param string $subTopic
   *
   * @return DataResponse
   *
   * @throws OCS\OCSNotFoundException
   *
   * @CORS
   * @NoCSRFRequired
   * @NoAdminRequired
   * @ServiceAccountRequired
   */
  public function get(string $topic, string $subTopic):DataResponse
  {
    switch ($topic) {
      case self::TOPIC_PLAYGROUND:
        switch ($subTopic) {
          case self::PLAYGROUND_HELLO:
            return new DataResponse([ 'response' => 'Hello World!' ]);
          default:
            break;
        }
        break;
      case self::TOPIC_ENCRYPTION_KEY:
        switch ($subTopic) {
          case self::ENCRYPTION_KEY_STATUS:
            if (!$this->encryptionKeyValid()) {
              $data = [
                'keyStatus' => 'invalid',
              ];
            } else {
              $appEncryptionKey = $this->getAppEncryptionKey();
              $data = [
                'keyStatus' => empty($appEncryptionKey) ? 'unset' : 'set',
              ];
            }
            return new DataResponse($data);
        }
        break;
    }
    throw new OCS\OCSNotFoundException;
  }

  /**
   * @param string $userId
   *
   * @param null|string $appEncryptionKey
   *
   * @return array
   */
  private function setUserEncryptionKey(string $userId, ?string $appEncryptionKey):array
  {
    $data = [
      'user' => $userId,
    ];
    try {
      $this->encryptionService()->setUserEncryptionKey($appEncryptionKey, $userId);
      $data['status'] = 'set';
    } catch (Exceptions\EncryptionKeyException $e) {
      $data['status'] = 'no-public-key';
    } catch (\Throwable $t) {
      $data['status'] = 'fatal';
      $data['message'] = $t->getMessage();
    }
    return $data;
  }

  /**
   * Distribute the encryption key to all users by storing them in
   * their personal preferences, encrypted with their SSL key-pair.
   *
   * @return array
   */
  private function distributeEncryptionKey():array
  {
    if (!$this->encryptionKeyValid()) {
      return [
        'keyStatus' => 'invalid',
      ];
    }
    $appEncryptionKey = $this->getAppEncryptionKey();

    $data = [
      'keyStatus' => empty($appEncryptionKey) ? 'unset' : 'set',
      'userStatus' => [],
    ];
    // $noKeyUsers = [];
    // $fatalUsers = [];
    // $modifiedUsers = [];
    foreach ($this->group()->getUsers() as $user) {
      $data['userStatus'][] = $this->setUserEncryptionKey($user->getUID(), $appEncryptionKey);
    }
    return $data;
  }
}
