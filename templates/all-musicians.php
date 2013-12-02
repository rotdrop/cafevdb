<?php
use CAFEVDB\L;
use CAFEVDB\Navigation;
use CAFEVDB\Musicians;

$table = new Musicians();
$css_pfx = Musicians::CSS_PREFIX;

$nav = '';
$nav .= Navigation::button('projects');
$nav .= Navigation::button('projectinstruments');
$nav .= Navigation::button('instruments');

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
