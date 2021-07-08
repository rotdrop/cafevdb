<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

script($appName, 'admin-settings');
style($appName, 'admin-settings');

if (empty($wikiVersion)) {
  $wikiHide = '';
  $wikiShow = 'hidden';
} else {
  $wikiHide = 'hidden';
  $wikiShow = '';
}

?>

<div class="section">
  <h2>Camerata DB</h2>
  <form id="cafevdb-admin-settings">
    <div>
      <input type="text"
             class="orchetraUserGroup"
             name="orchestraUserGroup"
             id="orchestraUserGroup"
             value="<?php p($userGroup); ?>"
             title="<?php p($l->t('Add the name of a dedicated user-group for the people allowed to access the orchestra-administration app.')); ?>"
             placeholder="<?php p($l->t('Group')); ?>" />
      <label for="orchestraUserGroup"><?php p($l->t('User Group')); ?></label>
    </div>
    <div class="<?php p($wikiShow); ?>">
      <input type="text"
             class="wikiNameSpace"
             name="wikiNameSpace"
             id="wikiNameSpace"
             value="<?php p($wikiNameSpace); ?>"
             title="<?php p($l->t('Add the name of a DokuWiki namespace which will host all wiki-pages of the orchestra. The namespace should be all lower-case and must not contain any spaces or fancy characters.')); ?>"
             placeholder="<?php p($l->t('Wiki NameSpace'));?>" />
      <label for="wikiNameSpace"><?php p($l->t('Wiki Name-Space')); ?></label>
    </div>
    <div class="<?php p($wikiHide); ?>">
      <?php p($l->t('Wiki is inaccessible')); ?>
    </div>
    <span class="msg"></span>
  </form>
</div>
