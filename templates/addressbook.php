<?php
/**Orchestra member, musician and project management application.
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

use CAFEVDB\L;
use CAFEVDB\Util;
use CAFEVDB\Navigation;
use CAFEVDB\Email;

CAFEVDB\Error::exceptions(true);

?>

<select class="address-book-emails"
        name="adressBookEmails[]"
        data-placeholder="<?php echo L::t('Select Em@il Recipients'); ?>"
        multiple="multiple">
  <?php
  foreach ($_['EmailOptions'] as $email => $display) {
    $selected = '';
    if (isset($_['EmailSelection'][$email])) {
      $selected = ' selected="selected"';
    } 
    $email = htmlspecialchars($email);
    $display = '<em>'.htmlspecialchars($display).'</em>';
    echo '
  <option value="'.$email.'"'.$selected.'>'.$display.'</option>';
  }
?>
</select>
