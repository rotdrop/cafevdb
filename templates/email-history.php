<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

  $projectName = Util::cgiValue('ProjectName');
  $projectId = Util::cgiValue('ProjectId',-1);
  $css_pfx = EmailHistory::CSS_PREFIX;

  $nav = '';
  if ($projectId >= 0) {
    $nav .= Navigation::pageControlElement('projectlabel', $projectName, $projectId);
    $nav .= Navigation::pageControlElement('projects');
    $nav .= Navigation::pageControlElement('email', $projectName, $projectId);
    $nav .= Navigation::pageControlElement('detailedg', $projectName, $projectId);
    $nav .= Navigation::pageControlElement('projectinstruments', $projectName, $projectId);
    $nav .= Navigation::pageControlElement('instruments', $projectName, $projectId);
  } else {
    $nav .= Navigation::pageControlElement('projects');
    $nav .= Navigation::pageControlElement('email');
    $nav .= Navigation::pageControlElement('all');
    $nav .= Navigation::pageControlElement('projectinstruments');
    $nav .= Navigation::pageControlElement('instruments');
  }

  echo $this->inc('part.common.header',
                  array('css-prefix' => $css_pfx,
                        'navigationcontrols' => $nav,
                        'header' => EmailHistory::headerText()));

  // Issue the main part. The method will echo itself
  EmailHistory::display();

  // Close some still opened divs
  echo $this->inc('part.common.footer', array('css-prefix' => $css_pfx));

}

?>
