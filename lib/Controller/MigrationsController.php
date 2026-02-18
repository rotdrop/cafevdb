<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2022, 2024-2026 Claus-Justus Heine
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

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

use Throwable;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute as CoreAttributes;
use OCP\IL10N;
use OCP\IRequest;

use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\MigrationsServiceInterface;
use OCA\CAFEVDB\Settings\ConfigConstants;

/** AJAX end-points for database migrations. */
#[TSAttributes\TypeScript]
class MigrationsController extends Controller
{
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  public const BASE_PATH = 'maintenance/migrations';
  public const END_POINT_APPLY = 'apply';

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    private MigrationsServiceInterface $migrationsService,
    protected ConfigService $configService,
    protected IL10N $l,
  ) {
    parent::__construct($appName, $request);
  }
  // phpcs:enable

  /**
   * @return DataResponse
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(verb: 'GET', url: '/' . self::BASE_PATH)]
  public function get(): Http\DataResponse|Http\JSONResponse
  {
    return self::dataResponse([ ConfigConstants::MIGRATIONS_KEY => $this->migrationsService->getUnapplied(), ]);
  }

  /**
   * @param string $topic
   *
   * @param string $subTopic
   *
   * @return DataResponse
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(verb: 'POST', url: '/' . self::BASE_PATH . '/' . self::END_POINT_APPLY)]
  public function post(): Http\DataResponse|Http\JSONResponse
  {
    $unapplied = $this->migrationsService->getUnapplied();
    $applied = [];
    foreach (array_keys($unapplied) as $version) {
      try {
        $this->migrationsService->apply($version);
        $applied[] = $version;
      } catch (Throwable $t) {
        $context = [
          ConfigConstants::MIGRATIONS_KEY => new DTO\ApplyMigrationsResponse(
            payload: $unapplied,
            handled: $applied,
            failing: $version,
          ),
        ];
        $message = $this->l->t('Migration step "%1$s" ("%2$s") failed.', [
          $version, $unapplied[$version],
        ]);
        if (count($applied) > 0) {
          $message .= ' ' . $this->l->n(
            'The following migration has successfully been applied: "%1$s".',
            'The following migrations have successfully been applied: "%1$s".',
            count($applied),
            join(', ', $applied),
          );
        }
        $remaining = array_diff(array_keys($unapplied), [ $version, ...$applied ]);
        if (count($remaining) > 0) {
          $message .= ' ' . $this->l->t('Also still pending: "%1$s".', join('", "', $remaining));
        }
        throw new Exceptions\EnduserNotificationException($message, 0, $t, context: $context, httpStatusCode: Http::STATUS_INTERNAL_SERVER_ERROR);
      }
    }
    return new DTO\ApplyMigrationsResponse(
      payload: $unapplied,
      handled: $applied,
      failing: [],
    )->response();
  }
}
