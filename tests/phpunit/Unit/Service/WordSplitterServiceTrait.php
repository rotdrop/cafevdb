<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author GitHub Copilot <223556219+Copilot@users.noreply.github.com>
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use PHPUnit\Framework\Attributes;

use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;

use OCA\CAFEVDB\Service\WordSplitterService;
use OCA\CAFEVDB\Tests\MockProvider;

/**
 * Helper trait in order to fake the HTTP requests to the german-word-splitter
 * microservice. The faked IClient instance is consumed by the real
 * WordSplitterService. In order to prevent the service from launching the real
 * Python executable a dummy Unix domain socket is opened which makes the
 * service believe that the splitter is already running.
 */
trait WordSplitterServiceTrait
{
  /** @var array<string, string[]> Fake dictionary, keys are lower-case words. */
  private const WORD_SPLITTER_FAKE_SPLITS = [
    'sommerkonzertreise' => ['sommer', 'konzert', 'reise'],
    'herbstkonzert' => ['herbst', 'konzert'],
    'probenwochenende' => ['proben', 'wochenende'],
    'teilnehmergebuehr' => ['teilnehmer', 'gebuehr'],
    'unkostenbeitrag' => ['Unkosten', 'Beitrag'],
    'unkostenerstattungen' => ['Unkosten', 'Erstattungen'],
    'unkostenerstattung' => ['Unkosten', 'Erstattung'],
  ];

  private MockProvider $mockProvider;

  /** @var resource|false|null */
  private mixed $wordSplitterSocketServer = null;

  private ?string $wordSplitterSocket = null;

  /**
   * @param array<string, string[]> $splits Additional fake splits, keys are
   * lower-case words.
   *
   * @return IClientService
   */
  private function getWordSplitterEndPointStub(array $splits = []): IClientService
  {
    $splits = array_merge(self::WORD_SPLITTER_FAKE_SPLITS, $splits);

    $clientService = $this->createStub(IClientService::class);
    $client = $this->createStub(IClient::class);
    $clientService->method('newClient')->willReturn($client);

    $client->method('post')->willReturnCallback(
      function(string $url, array $options = []) use ($splits): IResponse {
        $request = json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR);
        $word = $request['word'];
        $response = $this->createStub(IResponse::class);
        $response->method('getBody')->willReturn(
          json_encode(['components' => $splits[mb_strtolower($word)] ?? [$word]]),
        );
        return $response;
      },
    );

    return $clientService;
  }

  /**
   * Generate a WordSplitterService instance talking to the faked splitter.
   *
   * @param array<string, string[]> $splits Additional fake splits, keys are
   * lower-case words.
   *
   * @return WordSplitterService
   */
  private function getWordSplitterService(array $splits = []): WordSplitterService
  {
    $this->openWordSplitterSocket();

    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);
    /** @var IConfig $config */
    $config = $this->mockProvider->getCloudConfig();
    $config->setAppValue($this->mockProvider->appName, 'splitter_socket', '');

    // Partial mock: everything is the original code, only appDataDir() is
    // replaced and must be used in order to locate the splitter socket.
    $clientService = $this->getWordSplitterEndPointStub($splits);
    $service = $this->getMockBuilder(WordSplitterService::class)
      ->onlyMethods(['appDataDir'])
      ->disableOriginalConstructor()
      ->getMock();
    $service->expects($this->atLeastOnce())
      ->method('appDataDir')
      ->willReturn(dirname($this->wordSplitterSocket));
    (new \ReflectionMethod(WordSplitterService::class, '__construct'))->invoke(
      $service,
      $config,
      $clientService,
      $this->mockProvider->appName,
    );

    return $service;
  }

  /**
   * Install the faked word-splitter into the app- and server containers such
   * that everything depending on it (e.g. CamelCaseString) never talks to the
   * real service.
   *
   * @param array<string, string[]> $splits Additional fake splits, keys are
   * lower-case words.
   *
   * @return WordSplitterService
   */
  private function registerWordSplitterService(array $splits = []): WordSplitterService
  {
    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);
    $service = $this->getWordSplitterService($splits);
    $this->mockProvider->registerClassInstance(WordSplitterService::class, $service, global: true);
    return $service;
  }

  /** @return void */
  private function openWordSplitterSocket(): void
  {
    if (is_resource($this->wordSplitterSocketServer)) {
      return;
    }
    $dir = sys_get_temp_dir() . '/word-splitter-' . uniqid('', true);
    mkdir($dir, 0700, true);
    $this->wordSplitterSocket = $dir . '/german-word-splitter.sock';
    $this->wordSplitterSocketServer = stream_socket_server('unix://' . $this->wordSplitterSocket);
    $this->assertNotFalse($this->wordSplitterSocketServer);
  }

  /** @return void */
  #[Attributes\After]
  protected function closeWordSplitterSocket(): void
  {
    if (is_resource($this->wordSplitterSocketServer)) {
      fclose($this->wordSplitterSocketServer);
    }
    $this->wordSplitterSocketServer = null;
    if ($this->wordSplitterSocket !== null && file_exists($this->wordSplitterSocket)) {
      unlink($this->wordSplitterSocket);
    }
    if ($this->wordSplitterSocket !== null && is_dir(dirname($this->wordSplitterSocket))) {
      rmdir(dirname($this->wordSplitterSocket));
    }
    $this->wordSplitterSocket = null;
  }
}
