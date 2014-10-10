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
use CAFEVDB\Navigation;
use CAFEVDB\DetailedInstrumentation;
use CAFEVDB\Projects;

$table = new DetailedInstrumentation();
$css_pfx = DetailedInstrumentation::CSS_PREFIX;
$css_class = DetailedInstrumentation::CSS_CLASS;

$nav = '';
$nav .= Navigation::button('projectlabel', $table->project, $table->projectId);
$nav .= Navigation::button('projects');
$nav .= Navigation::button('detailed', $table->project, $table->projectId);
$nav .= Navigation::button('projectinstruments', $table->project, $table->projectId);
$nav .= Navigation::button('instruments', $table->project, $table->projectId);

echo $this->inc('part.common.header',
                array('css-prefix' => $css_pfx,
                      'css-class' => $css_class,
                      'navigationcontrols' => $nav,
                      'header' => $table->headerText()));

// Issue the main part. The method will echo itself
$table->display();

if ($table->listOperation()) {
  Projects::missingInstrumentationTable($table->projectId);
}

// Close some still opened divs
echo $this->inc('part.common.footer', array('css-prefix' => $css_pfx));

?>

