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
Config::init();
?>
<div id="tabs-<?php echo $_['tabNr']; ?>" class="personalblock admin cms">
  <form id="cmssettings">
    <fieldset id="redaxocategories"><legend><?php echo L::t('Redaxo Categories');?></legend>
      <input type="text"
             class="redaxo"
             id="redaxoPreview"
             name="redaxoPreview"
             placeholder="<?php L::t('preview'); ?>"
             value="<?php echo $_['redaxoPreview']; ?>"
             title="<?php echo Config::toolTips('redaxo-preview'); ?>" />
      <label for="redaxopreview"><?php echo L::t('Id of Redaxo Preview Category'); ?></label>
      <br/>
      <input type="text"
             class="redaxo"
             id="redaxoArchive"
             name="redaxoArchive"
             placeholder="<?php L::t('archive'); ?>"
             value="<?php echo $_['redaxoArchive']; ?>"
             title="<?php echo Config::toolTips('redaxo-archive'); ?>" />
      <label for="redaxoarchive"><?php echo L::t('Id of Redaxo Archive Category'); ?></label>
      <br/>
      <input type="text"
             class="redaxo"
             id="redaxoTrashbin"
             name="redaxoTrashbin"
             placeholder="<?php L::t('trashbin'); ?>"
             value="<?php echo $_['redaxoTrashbin']; ?>"
             title="<?php echo Config::toolTips('redaxo-trashbin'); ?>" />
      <label for="redaxotrashbin"><?php echo L::t('Id of Redaxo Trashbin Category'); ?></label>
      <br/>
    </fieldset>
    <span class="statusmessage" id="msg"></span>  
  </form>
</div>
