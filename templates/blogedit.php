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

use CAFEVDB\L;
use CAFEVDB\Config;
?>
<div id="blogedit">
  <form id="blogeditform">
    <textarea class="wysiwygeditor" id="blogtextarea" rows="15" cols="66"></textarea>
    <br/>
    <input
      type="button"
      title="<?php echo Config::toolTips('blog-acceptentry'); ?>"
      value="<?php echo L::t('Submit'); ?>"
      id="blogsubmit"
    />
    <input
      type="button"
      title="<?php echo Config::toolTips('blog-cancelentry'); ?>"
      value="<?php echo L::t('Cancel'); ?>"
      id="blogcancel"
    />
    <?php if ($_['priority'] !== false) { ?>
    <input
      type="text"
      title="<?php echo Config::toolTips('blog-priority'); ?>"
      value="<?php echo $_['priority']; ?>"
      name="priority"
      id="blogpriority"
    />
    <?php } ?>
    <?php if ($_['popup'] === false) { ?>
      <input
        type="checkbox"
        name="popupset"
        title="<?php echo Config::toolTips('blog-popup-set'); ?>"
        id="blogpopupset"
      />
      <label for="blogpopupset"
        title="<?php echo Config::toolTips('blog-popup-set'); ?>"><?php echo L::t('Set Blog Popup') ?></label>
    <?php } else { ?>
      <input
        type="checkbox"
        name="popupclear"
        title="<?php echo Config::toolTips('blog-popup-clear'); ?>"
        id="blogpopupclear"
      />
      <label for="blogpopupclear"
        title="<?php echo Config::toolTips('blog-popup-clear'); ?>"><?php echo L::t('Clear Blog Popup') ?></label>
    <?php } ?>      
      <input
        type="checkbox"
        name="readerclear"
        title="<?php echo Config::toolTips('blog-reader-clear'); ?>"
        id="blogreaderclear"
      />
      <label for="blogreaderclear"
        title="<?php echo Config::toolTips('blog-reader-clear'); ?>"><?php echo L::t('Clear Reader List') ?></label>
  </form>
</div>
