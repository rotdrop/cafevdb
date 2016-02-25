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

use CAFEVDB\L;
$css_pfx = $_['css-prefix'];
?>

      </div> <!-- page-body-inner -->
    </div> <!-- page-body -->
  </div> <!-- page-container -->
  <?php echo isset($_['footer']) ? $_['footer'] : ''; ?>
  <div class="debug" id="<?php echo $css_pfx; ?>-debug"></div>
  <div id="cafevdb-error-block" class="cafevdb-error"></div>
</div> <!-- cafevdb-general -->
</div> <!-- app-content -->
<div id="fullcalendar"></div>
<div id="dialog_holder" class="popup topleft hidden"></div>
<div id="appsettings" class="popup bottomleft hidden"></div>
<!-- fuck auto-focus attempts -->
<form class="focusstealer"><input type="checkbox" id="focusstealer" class="focusstealer"/></form>

<!-- iframes to trigger proper download action in web browser -->
<iframe name="pmeformdownloadframe"
        class="pmeformdownloadframe"
        id="pmeformdownloadframe"
        style="display:none;"
        src="about:blank"></iframe>
<iframe name="pmeformdownloadframetwo"
        class="pmeformdownloadframe"
        id="pmeformdownloadframetwo"
        style="display:none;"
        src="about:blank"></iframe>

<!-- image file upload support with drag'n drop -->
<form class="float"
      id="file_upload_form"
      action="<?php echo \OCP\Util::linkTo('cafevdb', 'ajax/inlineimage/uploadimage.php'); ?>"
      method="post"
      enctype="multipart/form-data"
      target="file_upload_target">
  <input type="hidden" name="ItemId" value="-1"/>
  <input type="hidden" name="ImageItemTable" value=""/>
  <input type="hidden" name="requesttoken" value="<?php echo $_['requesttoken']; ?>"/>
  <input type="hidden" name="ImageSize" value="1200"/>
  <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $_['uploadMaxFilesize']; ?>" id="max_upload"/>
  <input type="hidden" class="max_human_file_size" value="<?php echo $_['uploadMaxHumanFilesize']; ?>"/>
  <input id="file_upload_start" type="file" accept="image/*" name="imagefile" />
</form>

<!-- image upload form submit target frame -->
<iframe name="file_upload_target"
        id="file_upload_target"
        style="display:none;"
        src=""></iframe>

<!-- image crop form template -->
<script id="cropBoxTemplate" type="text/template">
	<form id="cropform"
		class="coords"
		method="post"
		enctype="multipart/form-data"
		target="crop_target"
		action="<?php print_unescaped(OCP\Util::linkToAbsolute('cafevdb', 'ajax/inlineimage/savecrop.php')); ?>">
		<input type="hidden" id="ItemId" name="ItemId" value="{ItemId}" />
		<input type="hidden" id="ImageItemTable" name="ImageItemTable" value="{ImageItemTable}" />
		<input type="hidden" id="ImageSize" name="ImageSize" value="{ImageSize}" />
		<input type="hidden" id="tmpkey" name="tmpkey" value="{tmpkey}" />
		<fieldset id="coords">
		<input type="hidden" id="x1" name="x1" value="" />
		<input type="hidden" id="y1" name="y1" value="" />
		<input type="hidden" id="x2" name="x2" value="" />
		<input type="hidden" id="y2" name="y2" value="" />
		<input type="hidden" id="w" name="w" value="" />
		<input type="hidden" id="h" name="h" value="" />
		</fieldset>
	</form>
</script>
