<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022, 2023, 2024 Claus-Justus Heine
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
use count;
use Closure;

use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Service\Finance\FinanceService;

/** Tool-tips management with translations. */
class ToolTipsService implements \ArrayAccess, \Countable
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  private const SUBKEY_PREFIXES = [ 'pme' ];

  public const SUB_KEY_SEP = ':';
  public const PARAGRAPH = '<p class="tooltip-paragraph">';

  /** @var bool */
  private $debug = false;

  /** @var string */
  private $lastKey = null;

  /** @var string */
  private $lastToolTip = null;

  /** @var array */
  private $failedKeys = [];

  /** {@inheritdoc} */
  public function __construct(
    protected IAppContainer $appContainer,
    protected IL10N $l,
    protected ILogger $logger,
  ) {
    try {
      $debugMode = $appContainer->get(EncryptionService::class)->getConfigValue('debugmode', 0);
      if ($debugMode & ConfigService::DEBUG_TOOLTIPS) {
        $this->debug = true;
      }
    } catch (\Throwable $t) {
      // forget it
    }
  }

  /**
   * Enable debug-messages.
   *
   * @param null|bool $debug If null, just return the setting, otherwise enable or
   * disabled debug-mode.
   *
   * @return bool The current debug-mode enabled setting.
   */
  public function debug(?bool $debug = null):bool
  {
    if ($debug === true || $debug === false) {
      $this->debug = $debug;
    }
    return $this->debug;
  }

  /**
   * Get the argument of ToolTipsService::fetch($key) of the most recent call.
   *
   * @return null|string
   */
  public function getLastKey():?string
  {
    return $this->lastKey;
  }

  /**
   * Return the array of failed tooltip-searches after the last successful
   * call.
   *
   * @return array
   */
  public function getFailedKeys():array
  {
    return $this->failedKeys;
  }

  /**
   * Return all tooltips contained in this class.
   *
   * @return array
   */
  public function toolTips():array
  {
    return ToolTipsDataService::get();
  }

  /** {@inheritdoc} */
  public function count():int
  {
    return count(ToolTipsDataService::get());
  }

  /** {@inheritdoc} */
  public function offsetExists(mixed $offset):bool
  {
    return $this->fetch($offset) !== null;
  }

  /** {@inheritdoc} */
  public function offsetGet(mixed $offset):mixed
  {
    return $this->fetch($offset);
  }

  /** {@inheritdoc} */
  public function offsetSet(mixed $offset, mixed $value):void
  {
    throw new RuntimeException($this->l->t("Unimplemented, tooltips cannot be altered at runtime yet"));
  }

  /** {@inheritdoc} */
  public function offsetUnset(mixed $offset):void
  {
    throw new RuntimeException($this->l->t("Unimplemented, tooltips cannot be altered at runtime yet"));
  }

  /**
   * Pre-process the given $key. If a key starts with a known prefix, but has
   * a '-' as separator, then replace all dashes by our standard separator.
   *
   * @param string $key The key to process.
   *
   * @return string
   */
  private function preProcessKey(string $key):string
  {
    foreach (self::SUBKEY_PREFIXES as $prefix) {
      if (str_starts_with($key, $prefix . '-')) {
        $key = str_replace(['--', '+-'], ['-minus-', '-plus-'], $key);
        $lastChar = substr($key, -1);
        switch ($lastChar) {
          case '-':
            $key = substr($key, 0, -1) . '-minus';
            break;
          case '+':
            $key = substr($key, 0, -1) . '-plus';
            break;
        }
        return str_replace('-', self::SUB_KEY_SEP, $key);
      }
    }
    return $key;
  }

  /**
   * Return a translated tool-tip for the given key.
   *
   * @param string $key The tool-tip key to look up.
   *
   * @param bool $escape Escape HTML entities.
   *
   * @return null|string
   */
  public function fetch(string $key, bool $escape = true):?string
  {
    if ($key == $this->lastKey && $escape) {
      return $this->lastToolTip;
    }
    $this->lastKey = $key;

    $toolTipsData = ToolTipsDataService::get();

    $key = $this->preprocessKey($key);

    $keys = explode(self::SUB_KEY_SEP, $key);
    while (\count($keys) > 0) {
      $key = array_shift($keys);
      $toolTipsData = $toolTipsData[$key] ?? ($toolTipsData['default'] ?? null);
    }
    $tip = $toolTipsData['default'] ?? $toolTipsData;

    if (!isset($tip['text'])) {
      $tip = null;
    }

    if (empty($tip)) {
      $this->failedKeys[] = $this->lastKey;
      if ($this->debug) {
        $tip = $this->l->t('Unknown Tooltip for key "%s" requested.', $this->lastKey);
      }
    } else {
      $this->failedKeys = [];
      $parameters = array_map(
        fn(mixed $value) => $value instanceof Closure ? $value($this->l, $this->appContainer) : $value,
        $tip['parameters'] ?? [],
      );
      $tip = $this->l->t($tip['text'], $parameters);
      if ($this->debug) {
        $tip .= ' (' . $this->l->t('ToolTip-Key "%s"', $this->lastKey) . ')';
      }
    }

    $tip = preg_replace('/(^\s*[\n])+/m', self::PARAGRAPH . "\n", $tip);

    // idea: allow markdown?

    $this->lastToolTip = empty($tip) ? null : ($escape ? htmlspecialchars($tip) : $tip);

    return $this->lastToolTip;
  }
}
