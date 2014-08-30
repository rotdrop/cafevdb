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
use CAFEVDB\Config;
?>
<div class="section">
  <form id="cafevdbadmin">
    <legend>
      <img class="svg cafevdblogo" src="<?php echo OCP\Util::imagePath(Config::APP_NAME, 'logo-greyf.svg'); ?>" >
      <strong>Camerata DB</strong><br />
    </legend>
    <input type="text" name="CAFEVgroup" id="CAFEVgroup" value="<?php echo $_['usergroup']; ?>" placeholder="<?php echo L::t('Group');?>" />
    <label for="CAFEVgroup"><?php echo L::t('User Group');?></label>
    <br/>
    <span class="msg"></span>
  </form>
</div>
