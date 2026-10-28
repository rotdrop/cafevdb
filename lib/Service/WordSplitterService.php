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

  /** @var string[] Locale problems detected but not yet reported to the admins. */
  private array $pendingProblems = [];

  /** @var null|bool Cached decision whether the running system service can be used. */
  private ?bool $systemServiceUsable = null;

  /** @var bool Whether the app-specific socket was explicitly configured. */
  private bool $allowSystemSocket;

  /**
   * @param IConfig $config Nextcloud configuration access.
   * @param IClientService $clientService Factory for HTTP clients.
   * @param string $appName The app id.
   * @param string $appLocale The configured app locale, always made
   * available to the service for transliteration.
   * @param ILogger $logger Used to report missing locales.
   * @param WordSplitterNotificationService $notifications Used to notify the
   * admins about locale problems.
   *
   * @throws RuntimeException If the app-data directory cannot be created.
   */
  public function __construct(
    private IConfig $config,
    private IClientService $clientService,
    private string $appName,
    string $appLocale,
    private ILogger $logger,
    private WordSplitterNotificationService $notifications,
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
    $installed = array_values(array_filter($locales, fn(string $locale) => $this->isLocaleInstalled($locale)));
    $missing = array_diff($locales, $installed);
    if (!empty($missing)) {
      $this->logger->error('Locales required by the word splitter are not installed: ' . implode(', ', $missing));
    }
    return $installed;
  }

  /**
   * Check whether the given locale is installed on this system.
   *
   * @param string $locale
   *
   * @return bool
   */
  private function isLocaleInstalled(string $locale): bool
  {
    $previous = setlocale(LC_CTYPE, '0');
    $installed = setlocale(LC_CTYPE, $locale) !== false;
    setlocale(LC_CTYPE, $previous);
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
   * @throws RuntimeException If the app locale is not installed, the
   *                          executable is missing or the service does not
   *                          come up within STARTUP_TIMEOUT.
   */
  private function ensureStarted(): void
  {
    try {
      $this->startService();
      if (empty($this->pendingProblems)) {
        $this->clearProblemNotifications();
      }
    } finally {
      // report once, also if the start fails with an exception
      $this->reportPendingProblems();
    }
  }

  /**
   * Remove stale locale notifications after a problem-free start.
   *
   * @return void
   */
  private function clearProblemNotifications(): void
  {
    try {
      $this->notifications->clearLocaleProblems();
    } catch (\Throwable $t) {
      $this->logger->warning('Unable to clear the word splitter locale notifications.', [ 'exception' => $t ]);
    }
  }

  /**
   * Notify the admins about all problems found while starting the service
   * with a single notification.
   *
   * @return void
   */
  private function reportPendingProblems(): void
  {
    if (empty($this->pendingProblems)) {
      return;
    }
    $problems = $this->pendingProblems;
    $this->pendingProblems = [];
    try {
      $this->notifications->notifyLocaleProblems($this->appLocale, $problems);
    } catch (\Throwable $t) {
      $this->logger->error('Unable to notify the admins about the word splitter locale problems.', [ 'exception' => $t ]);
    }
  }

  /**
   * @return void
   *
   * @throws RuntimeException
   *
   * @SuppressWarnings(PHPMD.ErrorControlOperator)
   */
  private function startService(): void
  {
    if ($this->useSystemService()) {
      $this->socket = $this->systemSocket;
      return;
    }
    if ($this->isUp($this->socket)) {
      return;
    }
    if (!$this->isLocaleInstalled($this->appLocale)) {
      $this->pendingProblems[] = WordSplitterNotificationService::PROBLEM_LOCAL_SERVICE;
      $message = 'The app locale ' . $this->appLocale . ' is not installed, the word splitter cannot be started.';
      $this->logger->error($message);
      throw new RuntimeException($message);
    }
    if (!is_executable($this->bin)) {
      throw new RuntimeException('Splitter executable not found.');
    }
    $lock = fopen(sys_get_temp_dir() . '/german-word-splitter.lock', 'c');
    flock($lock, LOCK_EX); // serialize concurrent launches
    $systemServiceUp = $this->useSystemService();
    if ($systemServiceUp) {
      $this->socket = $this->systemSocket;
    } elseif (!$this->isUp($this->socket)) {
      @unlink($this->socket); // remove a stale socket file
      exec($this->startupCommand());
    }
    flock($lock, LOCK_UN);
    fclose($lock);
    if ($systemServiceUp) {
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
   * Check whether the system-wide service is running and knows the app
   * locale. If it is running but lacks the app locale an error is logged
   * and the service is not used, such that an app-local service is started
   * instead. The decision is cached.
   *
   * @return bool
   */
  private function useSystemService(): bool
  {
    if (!$this->allowSystemSocket || !$this->isUp($this->systemSocket)) {
      return false;
    }
    if ($this->systemServiceUsable === null) {
      $this->systemServiceUsable = $this->systemServiceKnowsAppLocale();
    }
    return $this->systemServiceUsable;
  }

  /**
   * The locales of a running system-wide service are fixed at its start and
   * cannot be changed from here.
   *
   * @return bool False if the service reports locales not containing the
   * language of the app locale, true otherwise, also if the service does not
   * provide the info endpoint (older versions).
   */
  private function systemServiceKnowsAppLocale(): bool
  {
    try {
      $locales = $this->fetchInfo($this->systemSocket)['transliteration_locales'] ?? null;
    } catch (\Throwable $t) {
      return true;
    }
    if (!is_array($locales)) {
      return true;
    }
    $appLanguage = $this->primaryLanguage($this->appLocale);
    foreach ($locales as $locale) {
      if ($this->primaryLanguage((string)$locale) === $appLanguage) {
        return true;
      }
    }
    $this->logger->error('The running system-wide word splitter service is not configured for the app locale '
      . $this->appLocale . ' (configured locales: ' . implode(', ', $locales)
      . '), starting an app-local service instead.');
    $this->pendingProblems[] = WordSplitterNotificationService::PROBLEM_SYSTEM_SERVICE;
    return false;
  }

  /**
   * @param string $locale A locale like de_DE.UTF-8.
   *
   * @return string The lower-case language part.
   */
  private function primaryLanguage(string $locale): string
  {
    return strtolower(preg_split('/[_.@-]/', $locale)[0]);
  }

  /**
   * Get the service information from the given socket without starting anything.
   *
   * @param string $socket
   *
   * @return array{version: string, default_method: string, transliteration_locales: string[]}
   *
   * @throws RuntimeException If the request fails.
   * @throws \JsonException If the reply is not valid JSON.
   */
  private function fetchInfo(string $socket): array
  {
    try {
      $response = $this->clientService->newClient()->get('http://localhost/info', [
        'timeout' => 30,
        'curl' => [CURLOPT_UNIX_SOCKET_PATH => $socket],
        'proxy' => null,
        'nextcloud' => ['allow_local_address' => true],
      ]);
    } catch (\Throwable $e) {
      throw new RuntimeException('Splitter info request failed: ' . $e->getMessage(), 0, $e);
    }

    return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
  }

  /**
   * Get the information of the service in use.
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

    return $this->fetchInfo($this->socket);
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
