<?php
use CAFEVDB\L;
$css_pfx = $_['css-prefix'];
?>

  </div>
  <?php echo isset($_['footer']) ? $_['footer'] : ''; ?>
  <div class="debug" id="<?php echo $css_pfx; ?>-debug"></div>
</div> <!-- cafevdb-general -->
<div id="fullcalendar"></div>
<div id="dialog_holder" class="popup topleft hidden"></div>
<div id="appsettings" class="popup topright hidden"></div>

<script id="cropBoxTemplate" type="text/template">
	<form id="cropform"
		class="coords"
		method="post"
		enctype="multipart/form-data"
		target="crop_target"
		action="<?php print_unescaped(OCP\Util::linkToAbsolute('cafevdb', 'ajax/inlineimage/savecrop.php')); ?>">
		<input type="hidden" id="RecordId" name="RecordId" value="{RecordId}" />
		<input type="hidden" id="ImagePHPClass" name="ImagePHPClass" value="{ImagePHPClass}" />
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

