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

  $table = new SepaDebitMandates();

  $projectName = $_['projectName'];
  $projectId = $_['projectId'];
  $css_pfx = SepaDebitMandates::CSS_PREFIX;

  $navListItems = $_['pageControls'] == 'listItems';

  $nav = '';
  if ($projectId >= 0) {
    $nav .= Navigation::pageControlElement('projectlabel', $navListItems, $projectName, $projectId);
    $nav .= Navigation::pageControlElement('projects', $navListItems);
    $nav .= Navigation::pageControlElement('detailed', $navListItems, $projectName, $projectId);
    $nav .= Navigation::pageControlElement('projectinstruments', $navListItems, $projectName, $projectId);
  } else {
    $nav .= Navigation::pageControlElement('projects', $navListItems);
    $nav .= Navigation::pageControlElement('all', $navListItems);
    $nav .= Navigation::pageControlElement('instruments', $navListItems);
  }

  if ($navListItems) {
    $nav = '<ul>'.$nav.'</ul>';
  }

  echo $this->inc('part.common.header',
                  array('css-prefix' => $css_pfx,
                        'navigationcontrols' => $nav,
                        'header' => $table->headerText()));

  if (Config::isTreasurer()) {
    $table->display();
  } else {
    echo '<div class="specialrole error">'.
      L::t("Sorry, this view is only available to the %s.",
           array(L::t('treasurer'))).
      '</div>';
  }

  // Close some still opened divs
  echo $this->inc('part.common.footer', array('css-prefix' => $css_pfx));

} // namespace CAFEVDB

?>
