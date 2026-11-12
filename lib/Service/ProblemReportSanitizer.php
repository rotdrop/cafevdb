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

/**
 * Remove personal and security relevant data from problem reports before they
 * are published, e.g. as an issue in a public repository.
 *
 * This is a best-effort scrubber: it is deliberately conservative, i.e. it
 * prefers to redact too much over leaking data.
 */
class ProblemReportSanitizer
{
  public const REDACTED = '[REDACTED]';

  private const ZERO_WIDTH_SPACE = "\u{200B}";

  /** Array keys which carry personal data or secrets. */
  private const SECRET_KEY_PATTERN = '/(pass(word|wd|phrase)?|pwd|secret|token|api[_-]?key|auth|cookie|session|credential|private[_-]?key|signature)/i';

  /** Array keys which are redacted unconditionally. */
  private const PERSONAL_KEY_PATTERN = '/^(user|userid|uid|remoteaddr|useragent|clientip|ip|email|mail|displayname)$/i';

  /** The "args" of stack-trace frames regularly contain credentials. */
  private const TRACE_ARGS_KEY_PATTERN = '/^args$/i';

  /**
   * @param string $text Free-form text.
   *
   * @param array<string, string> $literals Literal replacements, e.g. host
   * names, server paths or user ids.
   *
   * @return string
   */
  public function sanitizeText(string $text, array $literals = []): string
  {
    $text = $this->replaceLiterals($text, $literals);

    $patterns = [
      // credentials in URLs and DSNs
      '~\b([a-z][a-z0-9+.-]*://)[^/\s:@]+(?::[^/\s@]*)?@~i' => '$1' . self::REDACTED . '@',
      // secrets in URL query strings
      '~([?&;](?:[a-z_]*(?:token|pass(?:word|wd)?|secret|key|auth|sig(?:nature)?|session|code)[a-z_]*)=)[^&\s"\'<>]+~i' => '$1' . self::REDACTED,
      // header lines
      '~\b((?:Proxy-)?Authorization|Set-Cookie|Cookie|X-[A-Za-z-]*(?:Token|Auth|Key)[A-Za-z-]*)(\s*[:=]\s*)[^\r\n]+~i' => '$1$2' . self::REDACTED,
      '~\b(Bearer|Basic)\s+[A-Za-z0-9._\~+/=-]{8,}~' => '$1 ' . self::REDACTED,
      // well-known token formats
      '~\b(?:gh[pousr]_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,})\b~' => self::REDACTED,
      // key = value and "key": "value" style secrets
      '~\b((?:db|database|mail|smtp|admin)?[_-]?(?:pass(?:word|wd|phrase)?|pwd|secret|token|api[_-]?key|private[_-]?key|credentials?)["\']?\]?\s*(?:=>|:|=)\s*["\']?)[^\s"\',;)\]}]+~i' => '$1' . self::REDACTED,
      // email addresses
      '~[A-Za-z0-9._%+\'-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}~' => '[email]',
      // IPv4 and IPv6 addresses
      '~\b(?:(?:25[0-5]|2[0-4]\d|1?\d?\d)\.){3}(?:25[0-5]|2[0-4]\d|1?\d?\d)\b~' => '[ip]',
      '~\b(?:[0-9a-f]{1,4}:){3,7}[0-9a-f]{1,4}\b~i' => '[ip]',
      // user names embedded in file system paths
      '~(/data/)[^/\s"\']+(/)~' => '$1[user]$2',
      '~(/home/)[^/\s"\']+(/)~' => '$1[user]$2',
      '~(/files/)[^/\s"\']+(/)~' => '$1[user]$2',
      // long hex strings (hashes, keys, ids) and long mixed alphanumeric blobs
      '~\b[A-Fa-f0-9]{32,}\b~' => '[hash]',
      '~(?<![A-Za-z0-9+_/-])(?=[A-Za-z0-9+_-]*\d)(?=[A-Za-z0-9+_-]*[A-Za-z])[A-Za-z0-9+_-]{40,}={0,2}(?![A-Za-z0-9+_/-])~' => self::REDACTED,
    ];

    foreach ($patterns as $pattern => $replacement) {
      $replaced = preg_replace($pattern, $replacement, $text);
      if ($replaced === null) {
        // regexp failure, e.g. catastrophic backtracking: better drop the
        // text than risk leaking it.
        return self::REDACTED;
      }
      $text = $replaced;
    }

    return $text;
  }

  /**
   * Recursively sanitize decoded JSON.
   *
   * @param mixed $data
   *
   * @param array<string, string> $literals
   *
   * @param int $maxDepth
   *
   * @return mixed
   */
  public function sanitizeData(mixed $data, array $literals = [], int $maxDepth = 16): mixed
  {
    if ($maxDepth <= 0) {
      return '[truncated]';
    }
    if (is_string($data)) {
      return $this->sanitizeText($data, $literals);
    }
    if (is_object($data)) {
      $data = get_object_vars($data);
    }
    if (!is_array($data)) {
      return $data;
    }
    $result = [];
    foreach ($data as $key => $value) {
      if (is_string($key)) {
        if (preg_match(self::TRACE_ARGS_KEY_PATTERN, $key)
            || preg_match(self::PERSONAL_KEY_PATTERN, $key)
            || (preg_match(self::SECRET_KEY_PATTERN, $key) && !is_array($value))) {
          $result[$key] = self::REDACTED;
          continue;
        }
        $key = $this->sanitizeText($key, $literals);
      }
      $result[$key] = $this->sanitizeData($value, $literals, $maxDepth - 1);
    }
    return $result;
  }

  /**
   * Make user supplied text safe for being posted to GitHub: no mentions, no
   * cross-references, no images, no raw HTML.
   *
   * @param string $text
   *
   * @return string
   */
  public function neutralizeMarkdown(string $text): string
  {
    $text = strip_tags($text);
    $text = preg_replace('/(?<![\w`])@(?=[\w-])/u', '@' . self::ZERO_WIDTH_SPACE, $text) ?? self::REDACTED;
    $text = preg_replace('/(?<![\w&])#(?=\d)/', '#' . self::ZERO_WIDTH_SPACE, $text) ?? self::REDACTED;
    $text = preg_replace('/\bGH-(?=\d)/i', '$0' . self::ZERO_WIDTH_SPACE, $text) ?? self::REDACTED;
    $text = str_replace('![', '\\![', $text);
    return $text;
  }

  /**
   * Quote text as markdown block quote.
   *
   * @param string $text
   *
   * @return string
   */
  public function quote(string $text): string
  {
    return implode("\n", array_map(fn(string $line) => rtrim('> ' . $line), preg_split('/\R/', $text)));
  }

  /**
   * Enclose in a fenced code block which cannot be closed by the content.
   *
   * @param string $text
   *
   * @param string $language
   *
   * @return string
   */
  public function fence(string $text, string $language = ''): string
  {
    $longest = 2;
    if (preg_match_all('/`+/', $text, $matches)) {
      foreach ($matches[0] as $run) {
        $longest = max($longest, strlen($run));
      }
    }
    $fence = str_repeat('`', $longest + 1);
    return $fence . $language . "\n" . $text . "\n" . $fence;
  }

  /**
   * Truncate to at most $maxLength characters, indicating the truncation.
   *
   * @param string $text
   *
   * @param int $maxLength
   *
   * @return string
   */
  public function truncate(string $text, int $maxLength): string
  {
    if (mb_strlen($text) <= $maxLength) {
      return $text;
    }
    $marker = '… [truncated]';
    return mb_substr($text, 0, max(0, $maxLength - mb_strlen($marker))) . $marker;
  }

  /**
   * Reduce to a single line suitable as an issue title.
   *
   * @param string $text
   *
   * @param int $maxLength
   *
   * @return string
   */
  public function singleLine(string $text, int $maxLength = 100): string
  {
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
    return $this->truncate($text, $maxLength);
  }

  /**
   * @param string $text
   *
   * @param array<string, string> $literals
   *
   * @return string
   */
  private function replaceLiterals(string $text, array $literals): string
  {
    $literals = array_filter($literals, fn($needle) => is_string($needle) && strlen($needle) >= 3, ARRAY_FILTER_USE_KEY);
    uksort($literals, fn($a, $b) => strlen($b) <=> strlen($a));
    foreach ($literals as $needle => $replacement) {
      $text = str_ireplace((string)$needle, $replacement, $text);
    }
    return $text;
  }
}
