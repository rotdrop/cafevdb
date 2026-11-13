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

namespace OCA\CAFEVDB\Service;

use Throwable;

use Psr\Log\LoggerInterface;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Http\Client\IClientService;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\Security\ICrypto;

use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Settings\Admin as AdminSettings;

/**
 * Submit problem reports as issues to a (public) GitHub repository on behalf
 * of the end-user, using a token configured by the cloud administrator.
 *
 * Reports with identical fingerprints are not submitted as new issues, instead
 * a comment is added to the issue created for the first occurrence. If the
 * local database is unavailable the report is simply submitted as new issue.
 */
class ProblemReportGithubService
{
  use \OCA\CAFEVDB\Wrapped\OCA\RotDrop\Toolkit\Traits\LoggerTrait;

  public const TABLE_NAME = 'cafevdb_problem_reports';

  public const API_BASE_URL = 'https://api.github.com';
  public const API_VERSION = '2022-11-28';
  public const REPOSITORY_PATTERN = '/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/';

  private const TIMEOUT = 15;

  /** {@inheritdoc} */
  public function __construct(
    protected string $appName,
    protected IClientService $clientService,
    protected IDBConnection $db,
    protected ICrypto $crypto,
    protected IL10N $l,
    protected LoggerInterface $logger,
    protected EncryptionService $encryptionService,
  ) {
  }

  /** @return string The configured repository, "owner/name", or empty string. */
  public function getRepository(): string
  {
    return (string)$this->encryptionService->getAppValue(AdminSettings::PROBLEM_REPORT_GITHUB_REPOSITORY_KEY, '');
  }

  /** @return bool Whether an access token has been stored. */
  public function hasToken(): bool
  {
    return !empty($this->encryptionService->getAppValue(AdminSettings::PROBLEM_REPORT_GITHUB_TOKEN_KEY, ''));
  }

  /** @return bool Whether the GitHub channel can be used. */
  public function isConfigured(): bool
  {
    return !empty($this->getRepository()) && $this->hasToken();
  }

  /**
   * Store the access token, encrypted with the server secret. This is
   * deliberately not tied to the app's encryption key, as that may be
   * unavailable when problems are reported.
   *
   * @param string $token Empty to remove the token.
   *
   * @return void
   */
  public function storeToken(string $token): void
  {
    if (empty($token)) {
      $this->encryptionService->deleteAppValue(AdminSettings::PROBLEM_REPORT_GITHUB_TOKEN_KEY);
    } else {
      $this->encryptionService->setAppValue(AdminSettings::PROBLEM_REPORT_GITHUB_TOKEN_KEY, $this->crypto->encrypt($token));
    }
  }

  /**
   * Check that the given token grants access to the given repository.
   *
   * @param string $repository
   *
   * @param string $token
   *
   * @return void
   *
   * @throws Exceptions\EnduserNotificationException
   */
  public function verify(string $repository, string $token): void
  {
    $this->request('GET', '/repos/' . $repository, null, $token);
  }

  /**
   * Check the stored token against the given repository.
   *
   * @param string $repository
   *
   * @return void
   *
   * @throws Exceptions\EnduserNotificationException
   */
  public function verifyStored(string $repository): void
  {
    $this->request('GET', '/repos/' . $repository);
  }

  /**
   * Submit a report: either create a new issue or comment on the existing
   * issue for the same fingerprint.
   *
   * @param string $fingerprint
   *
   * @param string $title
   *
   * @param string $body Issue body for new issues.
   *
   * @param string $comment Comment for the issue (new and existing).
   *
   * @return array{url:string, created:bool}
   *
   * @throws Exceptions\EnduserNotificationException
   */
  public function report(string $fingerprint, string $title, string $body, string $comment): array
  {
    $repository = $this->getRepository();
    if (!$this->isConfigured() || !preg_match(self::REPOSITORY_PATTERN, $repository)) {
      throw new Exceptions\EnduserNotificationException($this->l->t('The GitHub problem-report channel is not configured.'));
    }

    $known = $this->findIssue($fingerprint, $repository);
    if ($known !== null) {
      try {
        $this->request('POST', '/repos/' . $repository . '/issues/' . $known['issue_number'] . '/comments', [
          'body' => $this->l->t('The problem has been reported again.') . "\n\n" . $comment,
        ]);
        $this->touchIssue($known['id']);
        return [ 'url' => $known['issue_url'], 'created' => false ];
      } catch (Exceptions\EnduserNotificationException $e) {
        $code = $e->getPrevious()?->getCode();
        if ($code !== 404 && $code !== 410) {
          throw $e;
        }
        // the issue has been deleted or transferred, start afresh
        $this->forgetIssue($known['id']);
      }
    }

    $issue = $this->request('POST', '/repos/' . $repository . '/issues', [
      'title' => $title,
      'body' => $body,
    ]);
    $url = (string)($issue['html_url'] ?? '');
    $number = (int)($issue['number'] ?? 0);
    if (empty($url) || $number <= 0) {
      throw new Exceptions\EnduserNotificationException($this->l->t('GitHub returned an unexpected response.'));
    }
    $this->rememberIssue($fingerprint, $repository, $number, $url);
    try {
      $this->request('POST', '/repos/' . $repository . '/issues/' . $number . '/comments', [ 'body' => $comment ]);
    } catch (Throwable $t) {
      // the issue exists, the attribution is nice to have
      $this->logException($t, 'Unable to add attribution comment to GitHub issue ' . $url);
    }
    return [ 'url' => $url, 'created' => true ];
  }

  /**
   * @param string $method
   *
   * @param string $path
   *
   * @param null|array $payload
   *
   * @param null|string $token Override the stored token.
   *
   * @return array Decoded response.
   *
   * @throws Exceptions\EnduserNotificationException
   */
  private function request(string $method, string $path, ?array $payload = null, ?string $token = null): array
  {
    if ($token === null) {
      try {
        $token = $this->crypto->decrypt((string)$this->encryptionService->getAppValue(AdminSettings::PROBLEM_REPORT_GITHUB_TOKEN_KEY, ''));
      } catch (Throwable $t) {
        throw new Exceptions\EnduserNotificationException($this->l->t('Unable to decrypt the GitHub access token.'), 0, $t);
      }
    }
    $options = [
      'headers' => [
        'Accept' => 'application/vnd.github+json',
        'Authorization' => 'Bearer ' . $token,
        'X-GitHub-Api-Version' => self::API_VERSION,
        'User-Agent' => $this->appName . '-problem-report',
      ],
      'timeout' => self::TIMEOUT,
    ];
    if ($payload !== null) {
      $options['body'] = json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
      $options['headers']['Content-Type'] = 'application/json';
    }
    try {
      $client = $this->clientService->newClient();
      $response = $client->{strtolower($method)}(self::API_BASE_URL . $path, $options);
      $decoded = json_decode((string)$response->getBody(), true);
      return is_array($decoded) ? $decoded : [];
    } catch (Throwable $t) {
      // never log the options, they contain the token
      $this->logError('GitHub request ' . $method . ' ' . $path . ' failed: ' . get_class($t) . ' ' . $t->getCode());
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('Communication with GitHub failed (code %d).', (int)$t->getCode()),
        0,
        $t,
      );
    }
  }

  /**
   * @param string $fingerprint
   *
   * @param string $repository
   *
   * @return null|array
   */
  protected function findIssue(string $fingerprint, string $repository): ?array
  {
    try {
      $qb = $this->db->getQueryBuilder();
      $qb->select('id', 'issue_number', 'issue_url')
        ->from(self::TABLE_NAME)
        ->where($qb->expr()->eq('fingerprint', $qb->createNamedParameter($fingerprint)))
        ->andWhere($qb->expr()->eq('repository', $qb->createNamedParameter($repository)));
      $result = $qb->executeQuery();
      $row = $result->fetch();
      $result->closeCursor();
      return $row ?: null;
    } catch (Throwable $t) {
      $this->logException($t, 'Unable to look up problem report fingerprint, not de-duplicating.');
      return null;
    }
  }

  /**
   * @param string $fingerprint
   *
   * @param string $repository
   *
   * @param int $number
   *
   * @param string $url
   *
   * @return void
   */
  protected function rememberIssue(string $fingerprint, string $repository, int $number, string $url): void
  {
    try {
      $qb = $this->db->getQueryBuilder();
      $qb->insert(self::TABLE_NAME)
        ->values([
          'fingerprint' => $qb->createNamedParameter($fingerprint),
          'repository' => $qb->createNamedParameter($repository),
          'issue_number' => $qb->createNamedParameter($number, IQueryBuilder::PARAM_INT),
          'issue_url' => $qb->createNamedParameter($url),
          'occurrences' => $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT),
          'last_reported' => $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT),
        ])
        ->executeStatement();
    } catch (Throwable $t) {
      $this->logException($t, 'Unable to remember problem report fingerprint.');
    }
  }

  /**
   * @param int|string $id
   *
   * @return void
   */
  protected function touchIssue(int|string $id): void
  {
    try {
      $qb = $this->db->getQueryBuilder();
      $qb->update(self::TABLE_NAME)
        ->set('occurrences', $qb->createFunction('occurrences + 1'))
        ->set('last_reported', $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT))
        ->where($qb->expr()->eq('id', $qb->createNamedParameter((int)$id, IQueryBuilder::PARAM_INT)))
        ->executeStatement();
    } catch (Throwable $t) {
      $this->logException($t, 'Unable to update problem report statistics.');
    }
  }

  /**
   * @param int|string $id
   *
   * @return void
   */
  protected function forgetIssue(int|string $id): void
  {
    try {
      $qb = $this->db->getQueryBuilder();
      $qb->delete(self::TABLE_NAME)
        ->where($qb->expr()->eq('id', $qb->createNamedParameter((int)$id, IQueryBuilder::PARAM_INT)))
        ->executeStatement();
    } catch (Throwable $t) {
      $this->logException($t, 'Unable to forget problem report fingerprint.');
    }
  }
}
