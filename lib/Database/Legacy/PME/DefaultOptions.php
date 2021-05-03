<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Database\Legacy\PME;

use OCA\CAFEVDB\Common\Util;

class DefaultOptions extends \ArrayObject implements IOptions
{
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
        'php' => function() { return true; },
        'css' => [ 'major' => 'misc', 'minor' => 'email' ],
      ],
      'labels' => [
        'Misc' => 'Em@il',
        //'Sort Field' => 'Sortierfeld',
      ],
      'css' => [
        'separator' => ' ',
        'textarea' => '',
        'position' => true,
      ],
    ];
    $options = Util::arrayMergeRecursive($default, $options);
    if (!isset($options['cgi']['append'][$options['cgi']['prefix']['sys'].'fl'])) {
      $options['cgi']['append'][$options['cgi']['prefix']['sys'].'fl'] = 0;
    }
    parent::__construct($options);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
