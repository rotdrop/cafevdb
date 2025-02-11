<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB;
?>

<div id="blogframe">
  <form id="blogform" method="post">
    <input type="hidden" name="template" value="<?php p($template); ?>" />
    <input
      type="submit"
      title="<?php echo $toolTips['blog:newentry'];?>"
      value="<?php echo $l->t('New note'); ?>"
      id="blognewentry"
      />
  </form>

  <div id="blogthreads" class="cafevdb-blogthread">
    <?php echo $this->inc('blog/blogthreads', $_); ?>
  </div>
</div>
