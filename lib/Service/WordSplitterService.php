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
 * Starts the service on demand, listening on a Unix domain socket in the
 * app-data folder, and talks to it through Nextcloud's HTTP client.
 */
class WordSplitterService
{
  /** Maximum time in seconds to wait for the service to accept connections. */
  private const STARTUP_TIMEOUT = 60; // dictionary load can be slow

  /** @var string Absolute path of the service executable. */
  private string $bin;

  /** @var string Absolute path of the Unix domain socket. */
  private string $socket;

  /**
   * @param IConfig $config Nextcloud configuration access.
   * @param IClientService $clientService Factory for HTTP clients.
   *
   * @throws RuntimeException If the app-data directory cannot be created.
   */
  public function __construct(
    private IConfig $config,
    private IClientService $clientService,
    private string $appName,
  ) {
    $this->bin = __DIR__ . '/../../python/compound-word-splitting/venv/german-word-splitter';
    $this->socket = $config->getAppValue($this->appName, 'splitter_socket', '')
      ?: $this->appDataDir() . '/german-word-splitter.sock';
  }

  /**
   * Get the real filesystem path of the app's folder in the Nextcloud
   * app-data directory, creating it if necessary.
   *
   * @return string Directory path without trailing slash.
   *
   * @throws RuntimeException If the directory cannot be created.
   *
   * @SuppressWarnings(PHPMD.ErrorControlOperator)
   */
  private function appDataDir(): string
  {
    $dir = rtrim($this->config->getSystemValue('datadirectory'), '/')
         . '/appdata_' . $this->config->getSystemValue('instanceid')
         . '/' . $this->appName;
    if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
      throw new RuntimeException("Cannot create app-data directory $dir");
    }
    return $dir;
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
   * Make sure the service is running, launching it in the background if
   * necessary and waiting until it accepts connections.
   *
   * @return void
   *
   * @throws RuntimeException If the executable is missing or the service
   *                          does not come up within STARTUP_TIMEOUT.
   *
   * @SuppressWarnings(PHPMD.ErrorControlOperator)
   */
  private function ensureStarted(): void
  {
    if ($this->isUp()) {
      return;
    }
    if (!is_executable($this->bin)) {
      throw new RuntimeException('Splitter executable not found.');
    }
    $lock = fopen(sys_get_temp_dir() . '/german-word-splitter.lock', 'c');
    flock($lock, LOCK_EX); // serialize concurrent launches
    if (!$this->isUp()) {
      @unlink($this->socket); // remove a stale socket file
      exec('env GERMAN_WORD_SPLITTER_SOCKET=' . escapeshellarg($this->socket)
        . ' nohup ' . escapeshellarg($this->bin) . ' >/dev/null 2>&1 </dev/null &');
    }
    flock($lock, LOCK_UN);
    fclose($lock);

    $deadline = time() + self::STARTUP_TIMEOUT;
    while (!$this->isUp()) {
      if (time() > $deadline) {
        throw new RuntimeException('Splitter did not start in time.');
      }
      usleep(200000);
    }
  }

  /**
   * Split a German compound word into its components.
   *
   * Starts the service first if it is not yet running.
   *
   * @param string $word The word to split.
   * @param string $method Splitting method, "charsplit" (default) or
   *                       "german_compound_splitter".
   *
   * @return string[] The components; contains just $word if it is not
   *                  splittable.
   *
   * @throws RuntimeException If the service cannot be started or the
   *                          request fails (including HTTP error status).
   * @throws \JsonException If the reply is not valid JSON.
   */
  public function split(string $word, string $method = 'charsplit'): array
  {
    $this->ensureStarted();

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
