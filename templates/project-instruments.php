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
use CAFEVDB\Util;
use CAFEVDB\Navigation;
use CAFEVDB\ProjectInstruments;

$table = new ProjectInstruments($_['recordId']);
$css_pfx = ProjectInstruments::CSS_PREFIX;
$project = $table->project;
$projectId = $table->projectId;

$nav = '';
if ($project != '') {
  $nav .= Navigation::button('projectlabel', $project, $projectId);
  $nav .= Navigation::button('projects');
  $nav .= Navigation::button('instruments', $project, $projectId);  
  $nav .= Navigation::button('add', $project, $projectId);  
  $nav .= Navigation::button('brief', $project, $projectId);
} else {
  $nav .= Navigation::button('projects');
  $nav .= Navigation::button('instruments');
  $nav .= Navigation::button('all');
}

if ($projectId >= 0) {
  $xferStatus = $table->transferInstruments();
  $xferStatus = $xferStatus ? L::t('Success!') : '';
  $xferButton = '
<div>
  <br/>
  <table id="transfer-instruments">
    <TR>
      <TD>'
.Navigation::button('transfer-instruments', $project, $projectId).
     '</TD>
      <TD><span>'.$xferStatus.'</span></TD
    </TR>
  </TABLE>
</div>';
} else {
  $xferButton = '';
}


echo $this->inc('part.common.header',
                array('css-prefix' => $css_pfx,
                      'navigationcontrols' => $nav,
                      'header' => $table->headerText()."\n".$xferButton));

// Issue the main part. The method will "echo itself"
$table->display();

// Close some still opened divs
echo $this->inc('part.common.footer', array('css-prefix' => $css_pfx));

?>

