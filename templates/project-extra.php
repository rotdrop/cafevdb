<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

  $table   = new ProjectExtra($_['recordId']);
  $css_pfx = ProjectExtra::CSS_PREFIX;
  $project = $table->projectName;
  $projectId = $table->projectId;

  $nav = '';
  if (!empty($project)) {
    $nav .= Navigation::pageControlElement('projectlabel', $project, $projectId);
    $nav .= Navigation::pageControlElement('detailed', $project, $projectId);
    if (Config::isTreasurer()) {
      $nav .= Navigation::pageControlElement('project-payments', $project, $projectId);
      $nav .= Navigation::pageControlElement('debitmandates', $table->projectName, $table->projectId);
    }
    $nav .= Navigation::pageControlElement('projects');
    $nav .= Navigation::pageControlElement('instruments', $project, $projectId);
  } else {
    $nav .= Navigation::pageControlElement('projects');
    $nav .= Navigation::pageControlElement('instruments');
    $nav .= Navigation::pageControlElement('all');
  }

  $nav = '<ul id="navigation-list">'.$nav.'</ul>';

  echo $this->inc('part.common.header',
                  array('css-prefix' => $css_pfx,
                        'navigationcontrols' => $nav,
                        'header' => $table->headerText()));

  // Issue the main part. The method will echo itself
  $table->display();

  // Close some still opened divs
  echo $this->inc('part.common.footer', array('css-prefix' => $css_pfx));

} // CAFEVDB

?>
