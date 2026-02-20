<?php
/**
 * Some PHP classes shared between my Nextcloud apps.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\RotDrop\Tests;

use Throwable;

/**
 * Exception class optionally thrown on deprecation warnings.
 */
class DeprecationException extends \Exception
{
  /**
   * {@inheritdoc}
   *
   * @param array $deprecationWarning
   */
  public function __construct(
    string $message = '',
    int $code = 0,
    ?Throwable $previous = null,
    protected array $deprecationWarning = [],
  ) {
    parent::__construct($message, $code, $previous);
  }

  /**
   * @return array Return the error handler arguments of the triggered
   * deprecation warning.
   */
  public function getDeprecationWarning(): array
  {
    return $this->deprecationWarning;
  }

  /**
   * @param ?string $exclude Optional regexp with an exclude pattern.
   *
   * @param int $excludeFromCore Do not throw if the deprecation was triggered
   * from NC core code. This parameter is the stack-depth: if any of the
   * previous frames looking up this many frames belongs to the
   * '\OC'-namespace then no exception is thrown. Defaults to 4.
   *
   * @return ?callable
   */
  public static function throwOnDeprecations(?string $exclude = null, int $excludeFromCore = 4): ?callable
  {
    return set_error_handler(
      function(
        int $errno,
        string $message,
        string $file,
        int $line,
      ) use (
        $exclude,
        $excludeFromCore,
      ) {
        if (($errno == E_DEPRECATED || $errno == E_USER_DEPRECATED)
            && ($exclude === null || !preg_match($exclude, $message))) {
          $blah = '';
          if ($excludeFromCore) {
            $backTrace = debug_backtrace(limit: $excludeFromCore);
            foreach ($backTrace as $frame) {
              $blah .= ' ' . $frame['class'] ?? 'NO CLASS';
              if ($frame['class'] && str_starts_with($frame['class'], 'OC\\')) {
                return;
              }
            }
          }
          throw new DeprecationException(message: "{$blah} || {$file}:{$line} -- $message", deprecationWarning: compact('errno', 'message', 'file', 'line'));
        }
      },
    );
  }
}
