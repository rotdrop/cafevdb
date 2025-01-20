<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Templates;

?>
<div  id="<?php p($cssPrefix)_?>-blog-header" class="<?php p($cssPrefix) ?>-blog">
  <?php echo t('Camerata DB start page - the data-base operations can be accessed
hrough the menu-button %s (left top). Please click on the login-name
(top right) for logout and configuration options. Click right of the
cloud-icon (top left) to reach the app-menu.',
        [ '<div class="icon-menu inline"></div>'  ],
      ) ?>
</div>
