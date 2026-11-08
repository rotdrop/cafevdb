<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\Stub;

use Psr\Log\LoggerInterface;

use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\Security\ICrypto;

use OCA\CAFEVDB\Exceptions\EnduserNotificationException;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\ProblemReportGithubService;

/** Test the GitHub channel of the problem reports. */
#[Attributes\CoversClass(ProblemReportGithubService::class)]
class ProblemReportGithubServiceTest extends TestCase
{
  private const REPOSITORY = 'owner/repo';
  private const TOKEN = 'ghp_0123456789abcdefghijklmnopqrstuvwxyz';

  private IClient&Stub $client;

  private EncryptionService&Stub $encryptionService;

  /** @var null|array{id:int, issue_number:int, issue_url:string} */
  private ?array $known = null;

  /** @var string[] */
  public array $forgotten = [];

  /** @return void */
  public function setUp(): void
  {
    $this->known = null;
    $this->forgotten = [];
    $this->client = $this->createStub(IClient::class);
    $this->encryptionService = $this->createStub(EncryptionService::class);
    $this->encryptionService->method('getAppValue')->willReturnCallback(
      fn(string $key, mixed $default = null) => match ($key) {
        'problemReportGithubRepository' => self::REPOSITORY,
        'problemReportGithubToken' => 'encrypted',
        default => $default,
      },
    );
  }

  /**
   * @param array $body
   *
   * @return IResponse
   */
  private function response(array $body): IResponse
  {
    $response = $this->createStub(IResponse::class);
    $response->method('getBody')->willReturn(json_encode($body));
    return $response;
  }

  /** @return ProblemReportGithubService */
  private function service(): ProblemReportGithubService
  {
    $clientService = $this->createStub(IClientService::class);
    $clientService->method('newClient')->willReturn($this->client);
    $crypto = $this->createStub(ICrypto::class);
    $crypto->method('decrypt')->willReturn(self::TOKEN);
    $l = $this->createStub(IL10N::class);
    $l->method('t')->willReturnCallback(fn(string $text, $parameters = []) => vsprintf($text, (array)$parameters));
    $test = $this;

    return new class(
      'cafevdb',
      $clientService,
      $this->createStub(IDBConnection::class),
      $crypto,
      $l,
      $this->createStub(LoggerInterface::class),
      $this->encryptionService,
      $test,
    ) extends ProblemReportGithubService {
      // phpcs:disable Squiz.Commenting.FunctionComment.Missing
      public function __construct(
        string $appName,
        IClientService $clientService,
        IDBConnection $db,
        ICrypto $crypto,
        IL10N $l,
        LoggerInterface $logger,
        EncryptionService $encryptionService,
        private ProblemReportGithubServiceTest $test,
      ) {
        parent::__construct($appName, $clientService, $db, $crypto, $l, $logger, $encryptionService);
      }
      protected function findIssue(string $fingerprint, string $repository): ?array
      {
        return $this->test->known();
      }
      protected function rememberIssue(string $fingerprint, string $repository, int $number, string $url): void
      {
      }
      protected function touchIssue(int|string $id): void
      {
      }
      protected function forgetIssue(int|string $id): void
      {
        $this->test->forgotten[] = (string)$id;
      }
      // phpcs:enable
    };
  }

  /** @return null|array */
  public function known(): ?array
  {
    return $this->known;
  }

  /** @return void */
  public function testNewIssueIsCreatedAndAttributed(): void
  {
    $calls = [];
    $this->client->method('post')->willReturnCallback(function(string $url, array $options) use (&$calls) {
      $calls[] = [ $url, $options ];
      return $this->response(str_ends_with($url, '/issues') ? [ 'html_url' => 'https://github.com/owner/repo/issues/7', 'number' => 7 ] : []);
    });

    $result = $this->service()->report('fp', 'title', 'body', 'comment');

    $this->assertSame([ 'url' => 'https://github.com/owner/repo/issues/7', 'created' => true ], $result);
    $this->assertCount(2, $calls);
    $this->assertSame('https://api.github.com/repos/owner/repo/issues', $calls[0][0]);
    $this->assertSame('Bearer ' . self::TOKEN, $calls[0][1]['headers']['Authorization']);
    $this->assertSame('title', json_decode($calls[0][1]['body'], true)['title']);
    $this->assertSame('https://api.github.com/repos/owner/repo/issues/7/comments', $calls[1][0]);
  }

  /** @return void */
  public function testKnownIssueGetsComment(): void
  {
    $this->known = [ 'id' => 3, 'issue_number' => 5, 'issue_url' => 'https://github.com/owner/repo/issues/5' ];
    $urls = [];
    $this->client->method('post')->willReturnCallback(function(string $url) use (&$urls) {
      $urls[] = $url;
      return $this->response([]);
    });

    $result = $this->service()->report('fp', 'title', 'body', 'comment');

    $this->assertSame([ 'url' => 'https://github.com/owner/repo/issues/5', 'created' => false ], $result);
    $this->assertSame([ 'https://api.github.com/repos/owner/repo/issues/5/comments' ], $urls);
  }

  /** @return void */
  public function testDeletedIssueIsForgottenAndRecreated(): void
  {
    $this->known = [ 'id' => 3, 'issue_number' => 5, 'issue_url' => 'https://github.com/owner/repo/issues/5' ];
    $this->client->method('post')->willReturnCallback(function(string $url) {
      if (str_ends_with($url, '/issues/5/comments')) {
        throw new \RuntimeException('gone', 404);
      }
      return $this->response(str_ends_with($url, '/issues') ? [ 'html_url' => 'https://github.com/owner/repo/issues/9', 'number' => 9 ] : []);
    });

    $result = $this->service()->report('fp', 'title', 'body', 'comment');

    $this->assertTrue($result['created']);
    $this->assertSame([ '3' ], $this->forgotten);
  }

  /** @return void */
  public function testFailureDoesNotLeakToken(): void
  {
    $this->client->method('post')->willThrowException(new \RuntimeException('Bearer ' . self::TOKEN, 401));
    $this->expectException(EnduserNotificationException::class);
    $this->expectExceptionMessageMatches('/401/');
    $this->service()->report('fp', 'title', 'body', 'comment');
  }
}
