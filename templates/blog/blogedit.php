<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

?>
<div id="blogedit">
  <div id="blogeditscrollframe">
    <form id="blogeditform">
      <textarea class="wysiwyg-editor" id="blogtextarea" rows="15" cols="66"></textarea>
      <br/>
      <input
      type="button"
      title="<?php echo $toolTips['blog-acceptentry']; ?>"
      value="<?php echo $l->t('Submit'); ?>"
      id="blogsubmit"
      />
      <input
      type="button"
      title="<?php echo $toolTips['blog-cancelentry']; ?>"
      value="<?php echo $l->t('Cancel'); ?>"
      id="blogcancel"
      />
      <?php if ($_['priority'] !== false) { ?>
        <input
        type="text"
        title="<?php echo $toolTips['blog-priority']; ?>"
        value="<?php echo $_['priority']; ?>"
        name="priority"
        id="blogpriority"
        />
      <?php } ?>
      <?php if ($_['popup'] === false) { ?>
        <label for="blogpopupset"
               title="<?php echo $toolTips['blog-popup-set']; ?>">
          <input type="checkbox"
                 name="popupset"
                 title="<?php echo $toolTips['blog-popup-set']; ?>"
                 id="blogpopupset"/>
          <?php echo $l->t('Set Blog Popup') ?>
        </label>
      <?php } else { ?>
        <label for="blogpopupclear"
               title="<?php echo $toolTips['blog-popup-clear']; ?>">
          <input type="checkbox"
                 name="popupclear"
                 title="<?php echo $toolTips['blog-popup-clear']; ?>"
                 id="blogpopupclear"/>
          <?php echo $l->t('Clear Blog Popup') ?>
        </label>
      <?php } ?>
      <label for="blogreaderclear"
             title="<?php echo $toolTips['blog-reader-clear']; ?>">
        <input type="checkbox"
               name="readerclear"
               title="<?php echo $toolTips['blog-reader-clear']; ?>"
               id="blogreaderclear"/>
        <?php echo $l->t('Clear Reader List') ?>
      </label>
    </form>
  </div>
</div>
