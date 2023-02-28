<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IL10N;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\MigrationsService;

/** AJAX end-points for database migrations. */
class MigrationsController extends Controller
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;

  const ALL_MIGRATIONS = 'all';
  const UNAPPLIED_MIGRATIONS = 'unapplied';
  const LATEST_MIGRATION = 'latest';
  const MIGRATION_DESCRIPTION = 'description';

  /** @var MigrationsService */
  private $migrationsService;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    ConfigService $configService,
    MigrationsService $migrationsService,
  ) {
    parent::__construct($appName, $request);

    $this->configService = $configService;
    $this->migrationsService = $migrationsService;
    $this->l = $this->l10N();
  }
  // phpcs:enable

  /**
   * @param string $what
   *
   * @return DataResponse
   *
   * @NoAdminRequired
   */
  public function get(string $what):DataResponse
  {
    switch ($what) {
      case self::ALL_MIGRATIONS:
        return self::dataResponse([ 'migrations' => $this->migrationsService->getAll(), ]);
      case self::UNAPPLIED_MIGRATIONS:
        return self::dataResponse([ 'migrations' => $this->migrationsService->getUnapplied(), ]);
      case self::LATEST_MIGRATION:
        return self::dataResponse([ 'latest' => $this->migrationsService->getLatest(), ]);
    }
    return self::grumble($this->l->t('Unknown Request "%s".', $what));
  }

  /**
   * @param string $migrationVersion
   *
   * @return DataResponse
   *
   * @NoAdminRequired
   */
  public function getDescription(string $migrationVersion):DataResponse
  {
    return self::dataResponse([
      'version' => $migrationVersion,
      'description' => $this->migrationsService->description($migrationVersion),
    ]);
  }

  /**
   * @param string $topic
   *
   * @param string $subTopic
   *
   * @return DataResponse
   *
   * @NoAdminRequired
   */
  public function serviceSwitch(string $topic, string $subTopic):DataResponse
  {
    switch ($topic) {
      case 'apply':
        switch ($subTopic) {
          case 'all':
            $unapplied = $this->migrationsService->getUnapplied();
            $applied = [];
            foreach (array_keys($unapplied) as $version) {
              try {
                $this->migrationsService->apply($version);
                $applied[] = $version;
              } catch (\Throwable $t) {
                $data = $this->exceptionChainData($t);
                $data['migrations'] = [
                  'payload' => $unapplied,
                  'handled' => $applied,
                  'failing' => $version,
                ];
                return self::grumble($data);
              }
            }
            return self::dataResponse([
              'migrations' => [
                'payload' => $unapplied,
                'handled' => $applied,
                'failing' => [],
              ],
            ]);
          default:
            $version = $subTopic;
            try {
              $this->migrationsService->apply($version);
            } catch (\Throwable $t) {
              $data = $this->exceptionChainData($t);
              $data['migrations'] = [
                'payload' => [ $version, ],
                'handled' => [],
                'failing' => $version,
              ];
              return self::grumble($data);
            }
            return self::dataResponse([
              'migrations' => [
                'payload' => [ $version, ],
                'handled' => [ $version, ],
                'failing' => [],
              ],
            ]);
        }
    }
    return self::grumble($this->l->t('Unknown Request "%s".', $topic));
  }
}
