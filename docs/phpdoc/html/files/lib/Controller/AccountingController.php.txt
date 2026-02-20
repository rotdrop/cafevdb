<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine
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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute as CoreAttributes;
use OCP\AppFramework\Http;

use OCP\IRequest;
use Psr\Log\LoggerInterface;

use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service\Finance\GnuCashConnectorService;

/**
 * Staff related to financial accounting. Just autocomplete for the GnuCash
 * accounts ATM.
 */
#[TSAttributes\TypeScript]
class AccountingController extends Controller
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  public const END_POINT = 'accounting/autocomplete/gnucash-accounts';

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    private GnuCashConnectorService $gnuCashConnectorService,
    protected LoggerInterface $logger,
  ) {
    parent::__construct($appName, $request);
  }
  // phpcs:enable

  /**
   * @param int|string $project If given either the numeric id or the project name.
   *
   * @return DataResponse
   *
   * @throws Exceptions\EndUserNotificationException
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(verb: 'GET', url: '/' . self::END_POINT . '/{project}')]
  public function autocompleteGnuCashAccounts(
    int|string $project,
  ): Http\DataResponse|Http\JSONResponse {
    $autocompleteData = $this->gnuCashConnectorService->getAccountsAutocompleteData($project);

    return DTO\AutocompleteGnuCashAccountsResponse::fromArray($autocompleteData)->response();
  }
}
