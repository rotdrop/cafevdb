<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace CAFEVDB {

  Config::init();

  $credits = $_['credits'];

  $numItems = 5;
  $items = array();
  for ($cnt = 0; $cnt < $numItems; ++$cnt) {
    $idx = mt_rand(0, count($credits)-1);
    $items[] = $credits[$idx];
    unset($credits[$idx]);
    $credits = array_values($credits);
  }

  //echo '<pre>'.print_r($credits,true).'</pre>';
  echo '<ul>
';
  foreach($items as $item) {
?>
    <li>
      <a target="_creditlink" href="<?php echo $item['link']; ?>"><?php echo $item['title']; ?></a>
    </li>
<?php 
  } // foreach
  echo '</ul>
';

} // namespace
?>
