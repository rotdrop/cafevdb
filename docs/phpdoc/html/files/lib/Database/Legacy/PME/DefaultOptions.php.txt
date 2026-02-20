<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Legacy\PME;

use OCA\CAFEVDB\Common\Util;

/** Default legacy PME options object. */
class DefaultOptions implements IOptions
{
  private array $options;

  /**
   * @param array $options
   */
  public function __construct(array $options = [])
  {
    $default = [
      'logtable' => 'ChangeLog',
      'options' => 'ACPVDF',
      // Set default prefixes for variables for PME
      'js' => [ 'prefix' => 'PME_js_' ],
      'dhtml' => [ 'prefix' => 'PME_dhtml_' ],
      'cgi' => [
        'prefix' => [
          'operation' => 'PME_op_',
          'sys' => 'PME_sys_',
          'data' => 'PME_data_',
        ],
      ],
      'display' => [
        'disabled' => 'disabled', // or 'readonly'
        'readonly' => 'readonly', // or 'disabled'
        'query' => 'always',
      ],
      'navigation' => 'GUDM',
      'misc' => [
        'php' => fn() => true,
        'css' => [ 'major' => 'misc', 'minor' => 'email' ],
        'deselect_invisible' => false,
      ],
      'labels' => [
        'Misc' => 'Em@il',
        //'Sort Field' => 'Sortierfeld',
      ],
      'css' => [
        'prefix' => 'pme',
        'separator' => ' ',
        'textarea' => '',
        'position' => true,
      ],
    ];
    $options = Util::arrayMergeRecursive($default, $options);
    if (!isset($options['cgi']['append'][$options['cgi']['prefix']['sys'].'fl'])) {
      $options['cgi']['append'][$options['cgi']['prefix']['sys'].'fl'] = 0;
    }
    $this->options = $options;
  }

  /**  {@inheritdoc} */
  public function offsetExists(mixed $offset): bool
  {
    return isset($this->options[$offset]);
  }

  /**  {@inheritdoc} */
  public function offsetGet(mixed $offset): mixed
  {
    return $this->options[$offset];
  }

  /**  {@inheritdoc} */
  public function offsetSet(mixed $offset, mixed $value): void
  {
    $this->options[$offset] = $value;
  }

  /**  {@inheritdoc} */
  public function offsetUnset(mixed $offset): void
  {
    unset($this->options[$offset]);
  }

  /**  {@inheritdoc} */
  public function toArray(): array
  {
    return $this->options;
  }
}
