<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests\Unit\Service;

use OCP\AppFramework\Http;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;

use OCA\CAFEVDB\Tests\MockProvider;

/**
 * Helper trait in order to fake HTTP requests to the user-sql app.  The faked
 * IClient instance will finally be consumed by
 * \OCA\CAFEVDB\Wrapped\OCA\RotDrop\Toolkit\Service\RequestService.
 */
trait UserSqlRequestsTrait
{
  private MockProvider $mockProvider;

  private IClientService $clientService;

  /** @return IClientService */
  private function getUserSqlEndPointStub(): IClientService
  {
    $this->clientService = $this->createStub(IClientService::class);
    $client = $this->createStub(IClient::class);
    $this->clientService->method('newClient')->willReturn($client);

    $client->method('request')->willReturnCallback(
      function(string $method, string $url, array $options): IResponse {
        switch ($method) {
          case 'POST':
            $response = $this->createStub(IResponse::class);
            $response->method('getBody')->willReturn(
              json_encode(['status' => 'success', 'data' => ['message' => 'BLAH BLAH'] ]),
            );
            return $response;
          default:
            throw new UnexpectedValueException('Not implemented', code: Http::STATUS_BAD_REQUEST);
        }
      }
    );

    return $this->clientService;
  }
}
