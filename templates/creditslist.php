<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB;

$items = array();
for ($cnt = 0; $cnt < count($credits); ++$cnt) {
    $items[] = [
        'data' => $credits[$cnt],
        'visible' => false,
    ];
}

$numItems = 5;
for ($cnt = 0; $cnt < $numItems; ++$cnt) {
    $idx = mt_rand(0, count($credits)-1);
    if (!$items[$idx]['visible']) {
        $items[$idx]['visible'] = true;
    } else {
        // work around "random" values occurring twice
        foreach ($items as &$item) {
            if (!$item['visible']) {
                $item['visible'] = true;
                break;
            }
        }
    }
}

echo '<ul>
';
foreach($items as $item) {
?>
  <li<?php p($item['visible'] ? '' : ' class=hidden'); ?>>
    <a target="_creditlink" href="<?php echo $item['data']['link']; ?>">
      <?php p($item['data']['title']); ?>
    </a>
  </li>
<?php
  } // foreach
  echo '</ul>
';
