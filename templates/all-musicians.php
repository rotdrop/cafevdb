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
use CAFEVDB\Navigation;
use CAFEVDB\Musicians;

$table = new Musicians();
$css_pfx = Musicians::CSS_PREFIX;

$nav = '';
$nav .= Navigation::button('projects');
//$nav .= Navigation::button('projectinstruments');
$nav .= Navigation::button('instruments');
$nav .= Navigation::button('insurances');
$nav .= Navigation::button('debitmandates');

echo $this->inc('part.common.header',
                array('css-prefix' => $css_pfx,
                      'navigationcontrols' => $nav,
                      'header' => $table->headerText()));


// Issue the main part. The method will echo itself
$table->display();

// Close some still opened divs
echo $this->inc('part.common.footer', array('css-prefix' => $css_pfx));

// Photo upload support:

if (!$table->changeOperation()) {
  // Don't display the image dialog when not in single-record mode
  echo "<!-- \n";
}

?>

<form class="float" id="file_upload_form" action="<?php echo OCP\Util::linkTo('cafevdb', 'ajax/inlineimage/uploadimage.php'); ?>" method="post" enctype="multipart/form-data" target="file_upload_target">
  <input type="hidden" name="requesttoken" value="<?php echo $_['requesttoken'] ?>">
  <input type="hidden" name="RecordId" value="<?php echo $_['recordId'] ?>">
  <input type="hidden" name="ImagePHPClass" value="CAFEVDB\Musicians">
  <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $_['uploadMaxFilesize'] ?>" id="max_upload">
  <input type="hidden" class="max_human_file_size" value="(max <?php echo $_['uploadMaxHumanFilesize']; ?>)">
  <input id="file_upload_start" type="file" accept="image/*" name="imagefile" />
</form>

<div id="edit_photo_dialog" title="Edit photo">
		<div id="edit_photo_dialog_img"></div>
</div>

<?php

if (!$table->changeOperation()) {
  echo "-->\n";
}

?>
