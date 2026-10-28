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
use Psr\Log\LoggerInterface as ILogger;

/**
 * Client for the german-word-splitter Python microservice.
 *
 * Uses the system-wide service when available, otherwise starts an app-local
 * service on a Unix domain socket in the app-data folder.
 * Talks to the service through Nextcloud's HTTP client.
 */
class WordSplitterService
{
  /** Maximum time in seconds to wait for the service to accept connections. */
  private const STARTUP_TIMEOUT = 60; // dictionary load can be slow

  /** Default socket path of the system-wide german-word-splitter service. */
  private const SYSTEM_SOCKET = '/run/german-word-splitter/german-word-splitter.sock';

  /** @var string Absolute path of the service executable. */
  private string $bin;

  /** @var string Absolute path of the Unix domain socket. */
  private string $socket;

  /** @var string Socket path of the system-wide service. */
  private string $systemSocket = self::SYSTEM_SOCKET;

  /** @var string[] Locales requested for service transliteration. */
  private array $transliterationLocales;

  /** @var string The app locale which should be known to the service. */
  private string $appLocale;

  /** @var bool Whether the locales of a running system service have been checked. */
  private bool $systemLocalesChecked = false;

  /** @var bool Whether the app-specific socket was explicitly configured. */
  private bool $allowSystemSocket;

  /**
   * @param IConfig $config Nextcloud configuration access.
   * @param IClientService $clientService Factory for HTTP clients.
   * @param string $appName The app id.
   * @param string $appLocale The configured app locale, always made
   * available to the service for transliteration.
   * @param ILogger $logger Used to report missing locales.
   *
   * @throws RuntimeException If the app-data directory cannot be created.
   */
  public function __construct(
    private IConfig $config,
    private IClientService $clientService,
    private string $appName,
    string $appLocale,
    private ILogger $logger,
  ) {
    $this->appLocale = $appLocale;
    $this->bin = __DIR__ . '/../../python/compound-word-splitting/venv/german-word-splitter';
    $configuredSocket = $config->getAppValue($this->appName, 'splitter_socket', '');
    $this->allowSystemSocket = $configuredSocket === '';
    $this->socket = $configuredSocket ?: $this->appDataDir() . '/german-word-splitter.sock';
    $extraLocales = preg_split('/[\s,]+/', $config->getAppValue($this->appName, 'splitter_transliteration_locales', ''), -1, PREG_SPLIT_NO_EMPTY);
    $this->transliterationLocales = array_values(array_unique([$appLocale, ...$extraLocales]));
  }

  /**
   * Restrict the given locales to those installed on this system. The
   * splitter refuses to start if one of its locales is missing, so
   * uninstalled ones are dropped.
   *
   * @param string[] $locales
   *
   * @return string[]
   */
  private function installedLocales(array $locales): array
  {
    $previous = setlocale(LC_CTYPE, '0');
    $installed = array_values(array_filter($locales, fn(string $locale) => setlocale(LC_CTYPE, $locale) !== false));
    setlocale(LC_CTYPE, $previous);
    $missing = array_diff($locales, $installed);
    if (!empty($missing)) {
      $this->logger->error('Locales required by the word splitter are not installed: ' . implode(', ', $missing));
    }
    return $installed;
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
  private function isUp(string $socket): bool
  {
    $fp = @stream_socket_client('unix://' . $socket, $errno, $errstr, 1);
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
    if ($this->allowSystemSocket && $this->isUp($this->systemSocket)) {
      $this->socket = $this->systemSocket;
      $this->checkSystemServiceLocales();
      return;
    }
    if ($this->isUp($this->socket)) {
      return;
    }
    if (!is_executable($this->bin)) {
      throw new RuntimeException('Splitter executable not found.');
    }
    $lock = fopen(sys_get_temp_dir() . '/german-word-splitter.lock', 'c');
    flock($lock, LOCK_EX); // serialize concurrent launches
    $systemServiceUp = $this->allowSystemSocket && $this->isUp($this->systemSocket);
    if ($systemServiceUp) {
      $this->socket = $this->systemSocket;
    } elseif (!$this->isUp($this->socket)) {
      @unlink($this->socket); // remove a stale socket file
      exec($this->startupCommand());
    }
    flock($lock, LOCK_UN);
    fclose($lock);
    if ($systemServiceUp) {
      $this->checkSystemServiceLocales();
      return;
    }

    $deadline = time() + self::STARTUP_TIMEOUT;
    while (!$this->isUp($this->socket)) {
      if (time() > $deadline) {
        throw new RuntimeException('Splitter did not start in time.');
      }
      usleep(200000);
    }
  }

  /**
   * Build the command which starts the app-local service.
   *
   * @return string
   */
  private function startupCommand(): string
  {
    return 'env GERMAN_WORD_SPLITTER_SOCKET=' . escapeshellarg($this->socket)
      . ' GERMAN_WORD_SPLITTER_TRANSLITERATION_LOCALES='
      . escapeshellarg(implode(',', $this->installedLocales($this->transliterationLocales)))
      . ' nohup ' . escapeshellarg($this->bin) . ' >/dev/null 2>&1 </dev/null &';
  }

  /**
   * Log an error if the running system-wide service does not know the app
   * locale. Its locales are fixed at its start and cannot be changed from here.
   *
   * @return void
   */
  private function checkSystemServiceLocales(): void
  {
    if ($this->systemLocalesChecked) {
      return;
    }
    $this->systemLocalesChecked = true;
    try {
      $locales = $this->info()['transliteration_locales'] ?? null;
    } catch (\Throwable $t) {
      return; // older services do not provide the info endpoint
    }
    if (!is_array($locales)) {
      return;
    }
    $appLanguage = locale_get_primary_language($this->appLocale);
    foreach ($locales as $locale) {
      if (locale_get_primary_language((string)$locale) === $appLanguage) {
        return;
      }
    }
    $this->logger->error('The running word splitter service is not configured for the app locale ' . $this->appLocale
      . ', configured locales: ' . implode(', ', $locales));
  }

  /**
   * Get the service information.
   *
   * @return array{version: string, default_method: string, transliteration_locales: string[]}
   *
   * @throws RuntimeException If the service cannot be started or the
   *                          request fails.
   * @throws \JsonException If the reply is not valid JSON.
   */
  public function info(): array
  {
    $this->ensureStarted();

    try {
      $response = $this->clientService->newClient()->get('http://localhost/info', [
        'timeout' => 30,
        'curl' => [CURLOPT_UNIX_SOCKET_PATH => $this->socket],
        'proxy' => null,
        'nextcloud' => ['allow_local_address' => true],
      ]);
    } catch (\Throwable $e) {
      throw new RuntimeException('Splitter info request failed: ' . $e->getMessage(), 0, $e);
    }

    return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
  }

  /**
   * Split a German compound word into its components.
   *
   * Starts the service first if it is not yet running.
   *
   * @param string $word The word to split.
   * @param string $method Splitting method, "charsplit" (default),
   *                       "german_compound_splitter" or its shortcut "gcs".
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
