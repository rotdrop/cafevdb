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

use OCA\CAFEVDB\Service\ProblemReportSanitizer;

/** Test the scrubbing of problem reports. */
#[Attributes\CoversClass(ProblemReportSanitizer::class)]
class ProblemReportSanitizerTest extends TestCase
{
  private ProblemReportSanitizer $sanitizer;

  /** @return void */
  public function setUp(): void
  {
    $this->sanitizer = new ProblemReportSanitizer();
  }

  /**
   * @param string $input
   *
   * @param string $secret
   *
   * @return void
   */
  #[Attributes\DataProvider('secretProvider')]
  public function testSecretsAreRemoved(string $input, string $secret): void
  {
    $this->assertStringNotContainsString($secret, $this->sanitizer->sanitizeText($input));
  }

  /** @return array<string, array{string, string}> */
  public static function secretProvider(): array
  {
    return [
      'email' => [ 'contact jane.doe@example.org now', 'jane.doe@example.org' ],
      'ipv4' => [ 'from 192.168.10.77 port', '192.168.10.77' ],
      'ipv6' => [ 'from 2001:db8:85a3:0:0:8a2e:370:7334 port', '2001:db8:85a3' ],
      'url credentials' => [ 'mysql://dbuser:hunter2@localhost/db', 'hunter2' ],
      'query token' => [ 'GET /x?access_token=abcd1234&a=b', 'abcd1234' ],
      'bearer' => [ 'Authorization: Bearer abcdefghijklmnop', 'abcdefghijklmnop' ],
      'cookie' => [ 'Cookie: oc_sessionPassphrase=s3cr3t', 's3cr3t' ],
      'github token' => [ 'ghp_abcdefghijklmnopqrstuvwxyz0123456789', 'abcdefghijklmnopqrstuvwxyz' ],
      'password kv' => [ 'dbpassword = "topsecret"', 'topsecret' ],
      'password json' => [ '{"password":"topsecret"}', 'topsecret' ],
      'password php' => [ "[password] => topsecret", 'topsecret' ],
      'data path' => [ '/srv/nextcloud/data/alice/files/x.txt', 'alice' ],
      'home path' => [ '/home/bob/project/x.php', 'bob' ],
      'hex hash' => [ 'key ' . str_repeat('ab12', 10), str_repeat('ab12', 10) ],
      'long blob' => [ 'blob ' . str_repeat('aB3dE5', 8), str_repeat('aB3dE5', 8) ],
    ];
  }

  /** @return void */
  public function testLiteralsAreReplaced(): void
  {
    $result = $this->sanitizer->sanitizeText(
      'user alice at cloud.example.org in /var/www/nextcloud/apps/x.php',
      [ 'alice' => 'user-1234', 'cloud.example.org' => '[host]', '/var/www/nextcloud' => '<nextcloud>', 'ab' => 'X' ],
    );
    $this->assertSame('user user-1234 at [host] in <nextcloud>/apps/x.php', $result);
  }

  /** @return void */
  public function testDataSanitizing(): void
  {
    $data = [
      'message' => 'oops for bob@example.org',
      'user' => 'alice',
      'remoteAddr' => '10.0.0.1',
      'exception' => [
        'Exception' => 'RuntimeException',
        'Trace' => [
          [ 'file' => '/a/b.php', 'line' => 3, 'function' => 'f', 'args' => [ 'secret' ] ],
        ],
      ],
      'password' => 'topsecret',
      'nested' => [ 'apiToken' => 'abc' ],
    ];
    $clean = $this->sanitizer->sanitizeData($data);
    $json = json_encode($clean);
    foreach ([ 'bob@', 'alice', '10.0.0.1', 'topsecret', '"secret"', '"abc"' ] as $leak) {
      $this->assertStringNotContainsString($leak, $json);
    }
    $this->assertSame('RuntimeException', $clean['exception']['Exception']);
    $this->assertSame(3, $clean['exception']['Trace'][0]['line']);
    $this->assertSame('/a/b.php', $clean['exception']['Trace'][0]['file']);
  }

  /** @return void */
  public function testMarkdownNeutralization(): void
  {
    $result = $this->sanitizer->neutralizeMarkdown('ping @octocat see #123 ![x](http://t) <b>x</b>');
    $this->assertStringNotContainsString('@octocat', $result);
    $this->assertStringNotContainsString('#123', $result);
    $this->assertStringNotContainsString('<b>', $result);
    $this->assertStringContainsString('\\![x]', $result);
  }

  /** @return void */
  public function testFenceCannotBeClosedByContent(): void
  {
    $fenced = $this->sanitizer->fence("a\n```\nb\n````\nc", 'json');
    $this->assertStringStartsWith("`````json\n", $fenced);
    $this->assertStringEndsWith("\n`````", $fenced);
  }

  /** @return void */
  public function testTruncateAndSingleLine(): void
  {
    $this->assertSame(20, mb_strlen($this->sanitizer->truncate(str_repeat('x', 100), 20)));
    $this->assertSame('a b c', $this->sanitizer->singleLine("a\n b\t c"));
    $this->assertSame('short', $this->sanitizer->truncate('short', 20));
  }

  /** @return void */
  public function testQuote(): void
  {
    $this->assertSame("> a\n>\n> b", $this->sanitizer->quote("a\n\nb"));
  }
}
