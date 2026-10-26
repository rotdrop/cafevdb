<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author GitHub Copilot <223556219+Copilot@users.noreply.github.com>
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

namespace OCA\CAFEVDB\Service;

use RuntimeException;

use OCP\Http\Client\IClientService;
use OCP\IConfig;

/**
 * Client for the german-word-splitter Python microservice.
 *
 * Only talks to an already running (systemd) service on its Unix domain
 * socket and never launches a service instance itself. The socket path
 * defaults to the system-wide socket and can be overridden by the app-config
 * value "splitter_socket".
 * Talks to the service through Nextcloud's HTTP client.
 */
class WordSplitterService
{
  /** Default socket path of the system-wide german-word-splitter service. */
  private const SYSTEM_SOCKET = '/run/german-word-splitter/german-word-splitter.sock';

  /** @var string Absolute path of the Unix domain socket. */
  private string $socket;

  /**
   * @param IConfig $config Nextcloud configuration access.
   * @param IClientService $clientService Factory for HTTP clients.
   * @param string $appName
   */
  public function __construct(
    IConfig $config,
    private IClientService $clientService,
    string $appName,
  ) {
    $this->socket = $config->getAppValue($appName, 'splitter_socket', '') ?: self::SYSTEM_SOCKET;
  }

  /**
   * Check whether the service accepts connections on its socket.
   *
   * @return bool True if a connection could be established.
   *
   * @SuppressWarnings(PHPMD.ErrorControlOperator)
   */
  private function isUp(): bool
  {
    $fp = @stream_socket_client('unix://' . $this->socket, $errno, $errstr, 1);
    if ($fp === false) {
      return false;
    }
    fclose($fp);
    return true;
  }

  /**
   * Split a German compound word into its components.
   *
   * @param string $word The word to split.
   * @param string $method Splitting method, "charsplit" (default) or
   *                       "german_compound_splitter".
   *
   * @return string[] The components; contains just $word if it is not
   *                  splittable.
   *
   * @throws RuntimeException If the service is not running or the
   *                          request fails (including HTTP error status).
   * @throws \JsonException If the reply is not valid JSON.
   */
  public function split(string $word, string $method = 'charsplit'): array
  {
    if (!$this->isUp()) {
      throw new RuntimeException('Splitter service is not running on ' . $this->socket . '.');
    }

    $client = $this->clientService->newClient();
    try {
      $response = $client->post('http://localhost/split', [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode(['word' => $word, 'method' => $method]),
        'timeout' => 30,
        'curl' => [CURLOPT_UNIX_SOCKET_PATH => $this->socket],
        'proxy' => null, // never route the socket call through the Nextcloud proxy
        'nextcloud' => ['allow_local_address' => true],
      ]);
    } catch (\Throwable $e) {
      throw new RuntimeException('Splitter request failed: ' . $e->getMessage(), 0, $e);
    }

    $data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    return $data['components'] ?? [$word];
  }
}
