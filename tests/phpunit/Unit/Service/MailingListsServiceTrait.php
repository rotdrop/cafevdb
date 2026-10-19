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

use UnexpectedValueException;

use OCP\AppFramework\Http;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;

use OCA\CAFEVDB\Service\MailingListsService;
use OCA\CAFEVDB\Tests\MockProvider;

/** Generate a MailingListsService instance talking to a faked rest-service. */
trait MailingListsServiceTrait
{
  private MockProvider $mockProvider;

  private IClientService $clientService;

  private MailingListsService $mailingListsService;

  /** @return RestClient */
  private function getMailManRestServiceStub(): IClientService
  {
    $this->clientService = $this->createStub(IClientService::class);
    $client = $this->createStub(IClient::class);
    $this->clientService->method('newClient')->willReturn($client);

    $client->method('get')->willReturnCallback(
      function(string $url, array $options = []): IResponse {
        switch (true) {
          case str_ends_with($url, '3.1/system/configuration'):
            $response = $this->createStub(IResponse::class);
            $response->method('getBody')->willReturn(
              json_encode(['NOT EMPTY']),
            );
            return $response;
          default:
            throw new UnexpectedValueException('Not implemented', code: Http::STATUS_BAD_REQUEST);
        }
      }
    );
    return $this->clientService;
  }

  /** @return MailingListsService */
  private function getMailingListsService(): MailingListsService
  {
    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $this->mailingListsService = new MailingListsService(
      clientService: $this->getMailManRestServiceStub(),
      configService: $this->mockProvider->getConfigService(),
    );

    return $this->mailingListsService;
  }
}
