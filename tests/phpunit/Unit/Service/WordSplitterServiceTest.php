<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author GitHub Copilot <223556219+Copilot@users.noreply.github.com>
 * @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.com>
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
use PHPUnit\Framework\TestCase;

use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

use OCA\CAFEVDB\Service\WordSplitterNotificationService;
use OCA\CAFEVDB\Service\WordSplitterService;

#[Attributes\CoversClass(WordSplitterService::class)]
class WordSplitterServiceTest extends TestCase
{
  private mixed $socketServer;

  private mixed $systemSocketServer;

  private string $socket;

  private string $systemSocket;

  private string $appDataRoot;

  /** {@inheritdoc} */
  protected function setUp(): void
  {
    $this->socket = sys_get_temp_dir() . '/word-splitter-' . uniqid('', true) . '.sock';
    $this->socketServer = stream_socket_server('unix://' . $this->socket);
    $this->assertNotFalse($this->socketServer);
    $this->systemSocket = sys_get_temp_dir() . '/word-splitter-system-' . uniqid('', true) . '.sock';
    $this->systemSocketServer = stream_socket_server('unix://' . $this->systemSocket);
    $this->assertNotFalse($this->systemSocketServer);
    $this->appDataRoot = sys_get_temp_dir() . '/word-splitter-data-' . uniqid('', true);
  }

  /** {@inheritdoc} */
  protected function tearDown(): void
  {
    if (is_resource($this->socketServer)) {
      fclose($this->socketServer);
    }
    if (file_exists($this->socket)) {
      unlink($this->socket);
    }
    if (is_resource($this->systemSocketServer)) {
      fclose($this->systemSocketServer);
    }
    if (file_exists($this->systemSocket)) {
      unlink($this->systemSocket);
    }
    $appDataDir = $this->appDataRoot . '/appdata_word-splitter-test/testapp';
    if (is_dir($appDataDir)) {
      rmdir($appDataDir);
      rmdir(dirname($appDataDir));
      rmdir($this->appDataRoot);
    }
  }

  public function testSplitUsesCharsplitByDefault(): void
  {
    $client = $this->createMock(IClient::class);
    $response = $this->createStub(IResponse::class);
    $response->method('getBody')->willReturn('{"components":["Donaudampf","schifffahrt"]}');

    $client->expects($this->once())
      ->method('post')
      ->with(
        'http://localhost/split',
        $this->callback(function(array $options): bool {
          $request = json_decode($options['body'], true);
          return $request === ['word' => 'Donaudampfschifffahrt', 'method' => 'charsplit']
            && $options['headers'] === ['Content-Type' => 'application/json']
            && $options['timeout'] === 30
            && $options['curl'][CURLOPT_UNIX_SOCKET_PATH] === $this->socket
            && $options['proxy'] === null
            && $options['nextcloud'] === ['allow_local_address' => true];
        }),
      )
      ->willReturn($response);

    $service = $this->createService($client);

    $this->assertSame(
      ['Donaudampf', 'schifffahrt'],
      $service->split('Donaudampfschifffahrt'),
    );
  }

  public function testSplitPassesRequestedMethodAndFallsBackToOriginalWord(): void
  {
    $client = $this->createMock(IClient::class);
    $response = $this->createStub(IResponse::class);
    $response->method('getBody')->willReturn('{}');

    $client->expects($this->once())
      ->method('post')
      ->with(
        'http://localhost/split',
        $this->callback(function(array $options): bool {
          $request = json_decode($options['body'], true);
          return $request === ['word' => 'Handschuh', 'method' => 'german_compound_splitter'];
        }),
      )
      ->willReturn($response);

    $service = $this->createService($client);

    $this->assertSame(['Handschuh'], $service->split('Handschuh', 'german_compound_splitter'));
  }

  public function testLocalServiceStartsWithInstalledLocalesOnly(): void
  {
    $client = $this->createStub(IClient::class);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with($this->stringContains('yy_YY.UTF-8, xx_XX.UTF-8'));
    $service = $this->createService(
      $client,
      transliterationLocales: 'C.UTF-8 xx_XX.UTF-8',
      appLocale: 'yy_YY.UTF-8',
      logger: $logger,
    );

    $command = (new \ReflectionMethod($service, 'startupCommand'))->invoke($service);

    $this->assertStringContainsString(
      "GERMAN_WORD_SPLITTER_TRANSLITERATION_LOCALES='C.UTF-8'",
      $command,
    );
    $this->assertStringContainsString(
      'GERMAN_WORD_SPLITTER_SOCKET=' . escapeshellarg($this->socket),
      $command,
    );
  }

  public function testSplitUsesRunningSystemServiceWithoutStartingAppService(): void
  {
    $client = $this->createMock(IClient::class);
    $response = $this->createStub(IResponse::class);
    $response->method('getBody')->willReturn('{"components":["Haus","Tür"]}');

    $client->expects($this->once())
      ->method('post')
      ->with(
        'http://localhost/split',
        $this->callback(function(array $options): bool {
          return $options['curl'][CURLOPT_UNIX_SOCKET_PATH] === $this->systemSocket;
        }),
      )
      ->willReturn($response);

    $service = $this->createService($client, true);
    (new \ReflectionProperty($service, 'systemSocket'))->setValue($service, $this->systemSocket);
    (new \ReflectionProperty($service, 'bin'))->setValue($service, '/nonexistent/german-word-splitter');

    $this->assertSame(['Haus', 'Tür'], $service->split('Haustür'));
  }

  public function testInfoReturnsServiceInformation(): void
  {
    $client = $this->createMock(IClient::class);
    $response = $this->createStub(IResponse::class);
    $response->method('getBody')->willReturn(
      '{"version":"0.7.0","default_method":"gcs","transliteration_locales":["de_DE.UTF-8"]}'
    );
    $client->expects($this->once())
      ->method('get')
      ->with(
        'http://localhost/info',
        $this->callback(fn(array $options): bool => $options['curl'][CURLOPT_UNIX_SOCKET_PATH] === $this->socket),
      )
      ->willReturn($response);

    $service = $this->createService($client);

    $this->assertSame(
      ['version' => '0.7.0', 'default_method' => 'gcs', 'transliteration_locales' => ['de_DE.UTF-8']],
      $service->info(),
    );
  }

  private function createServiceWithMisconfiguredSystemService(
    string $appLocale,
    WordSplitterNotificationService $notifications,
    ?LoggerInterface $logger = null,
  ): WordSplitterService {
    $client = $this->createStub(IClient::class);
    $response = $this->createStub(IResponse::class);
    $response->method('getBody')->willReturn(
      '{"version":"0.7.0","default_method":"gcs","transliteration_locales":["en_US.UTF-8"]}'
    );
    $client->method('get')->willReturn($response);

    $service = $this->createService(
      $client,
      true,
      appLocale: $appLocale,
      logger: $logger,
      notifications: $notifications,
    );
    (new \ReflectionProperty($service, 'systemSocket'))->setValue($service, $this->systemSocket);
    return $service;
  }

  public function testSystemServiceWithoutAppLocaleFallsBackToLocalServiceAndNotifiesOnce(): void
  {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with($this->stringContains('C.UTF-8'));
    $notifications = $this->createMock(WordSplitterNotificationService::class);
    $notifications->expects($this->once())
      ->method('notifyLocaleProblems')
      ->with('C.UTF-8', [WordSplitterNotificationService::PROBLEM_SYSTEM_SERVICE]);

    // system service is misconfigured for the app locale 'C.UTF-8'
    $service = $this->createServiceWithMisconfiguredSystemService('C.UTF-8', $notifications, $logger);
    // an already running local service stands in for the freshly started one
    (new \ReflectionProperty($service, 'socket'))->setValue($service, $this->socket);

    $ensureStarted = new \ReflectionMethod($service, 'ensureStarted');
    $ensureStarted->invoke($service);
    $ensureStarted->invoke($service);
    $this->assertSame(
      $this->socket,
      (new \ReflectionProperty($service, 'socket'))->getValue($service),
    );
  }

  public function testUninstalledAppLocaleThrowsAfterNotifyingAboutBothProblemsOnce(): void
  {
    $notifications = $this->createMock(WordSplitterNotificationService::class);
    $notifications->expects($this->once())
      ->method('notifyLocaleProblems')
      ->with(
        'yy_YY.UTF-8',
        [WordSplitterNotificationService::PROBLEM_SYSTEM_SERVICE, WordSplitterNotificationService::PROBLEM_LOCAL_SERVICE],
      );

    $service = $this->createServiceWithMisconfiguredSystemService('yy_YY.UTF-8', $notifications);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('yy_YY.UTF-8 is not installed');
    (new \ReflectionMethod($service, 'ensureStarted'))->invoke($service);
  }

  public function testUninstalledAppLocaleWithoutRunningServiceNotifiesAboutLocalProblemOnly(): void
  {
    $notifications = $this->createMock(WordSplitterNotificationService::class);
    $notifications->expects($this->once())
      ->method('notifyLocaleProblems')
      ->with('yy_YY.UTF-8', [WordSplitterNotificationService::PROBLEM_LOCAL_SERVICE]);

    $client = $this->createStub(IClient::class);
    $service = $this->createService($client, appLocale: 'yy_YY.UTF-8', notifications: $notifications);
    // no running local service
    (new \ReflectionProperty($service, 'socket'))->setValue($service, $this->socket . '.missing');

    $this->expectException(\RuntimeException::class);
    (new \ReflectionMethod($service, 'ensureStarted'))->invoke($service);
  }

  public function testSplitWrapsHttpClientFailures(): void
  {
    $client = $this->createMock(IClient::class);
    $client->expects($this->once())
      ->method('post')
      ->willThrowException(new \RuntimeException('connection refused'));

    $service = $this->createService($client);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Splitter request failed: connection refused');
    $service->split('Handschuh');
  }

  private function createService(
    IClient $client,
    bool $useDefaultSocket = false,
    string $transliterationLocales = '',
    string $appLocale = 'C.UTF-8',
    ?LoggerInterface $logger = null,
    ?WordSplitterNotificationService $notifications = null,
  ): WordSplitterService {
    $config = $this->createStub(IConfig::class);
    $config->method('getAppValue')->willReturnCallback(
      function(string $appName, string $key, string $default = '') use ($useDefaultSocket, $transliterationLocales): string {
        return match ($key) {
          'splitter_socket' => $useDefaultSocket ? '' : $this->socket,
          'splitter_transliteration_locales' => $transliterationLocales,
          default => $default,
        };
      },
    );
    $config->method('getSystemValue')->willReturnMap([
      ['datadirectory', $this->appDataRoot],
      ['instanceid', 'word-splitter-test'],
    ]);

    $clientService = $this->createStub(IClientService::class);
    $clientService->method('newClient')->willReturn($client);

    return new WordSplitterService($config, $clientService, 'testapp', $appLocale, $logger ?? $this->createStub(LoggerInterface::class), $notifications ?? $this->createStub(WordSplitterNotificationService::class));
  }
}
