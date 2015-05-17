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
  
  $table = new ProjectInstruments($_['recordId']);
  $css_pfx = ProjectInstruments::CSS_PREFIX;
  $project = $table->projectName;
  $projectId = $table->projectId;

  $navListItems = $_['pageControls'] == 'listItems';

  $nav = '';
  if ($project != '') {
    $nav .= Navigation::pageControlElement('projectlabel', $navListItems, $project, $projectId);
    $nav .= Navigation::pageControlElement('projects', $navListItems);
    $nav .= Navigation::pageControlElement('instruments', $navListItems, $project, $projectId);  
    $nav .= Navigation::pageControlElement('add', $navListItems, $project, $projectId);  
    $nav .= Navigation::pageControlElement('detailed', $navListItems, $project, $projectId);
  } else {
    $nav .= Navigation::pageControlElement('projects', $navListItems);
    $nav .= Navigation::pageControlElement('instruments', $navListItems);
    $nav .= Navigation::pageControlElement('all', $navListItems);
  }

  if ($navListItems) {
    $nav = '<ul>'.$nav.'</ul>';
  }

  echo $this->inc('part.common.header',
                  array('css-prefix' => $css_pfx,
                        'navigationcontrols' => $nav,
                        'header' => $table->headerText()));

  // Issue the main part. The method will "echo itself"
  $table->display();

  // Close some still opened divs
  echo $this->inc('part.common.footer', array('css-prefix' => $css_pfx));

} // CAFEVDB

?>
