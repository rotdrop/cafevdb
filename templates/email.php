<?php
use CAFEVDB\L;
use CAFEVDB\Util;
use CAFEVDB\Navigation;
use CAFEVDB\Email;

$table = new Email(OCP\USER::getUser());

$project = $_['projectName'];
$projectId = $_['projectId'];
$css_pfx = Email::CSS_PREFIX;

$nav = '';
if ($projectId >= 0) {
  $nav .= Navigation::button('projectlabel', $project, $projectId);
  $nav .= Navigation::button('projects');
  $nav .= Navigation::button('emailhistory', $project, $projectId);
  $nav .= Navigation::button('detailed', $project, $projectId);
} else {
  $nav .= Navigation::button('projects');
  $nav .= Navigation::button('emailhistory');
  $nav .= Navigation::button('all');
}

echo $this->inc('part.common.header',
                array('css-prefix' => $css_pfx,
                      'navigationcontrols' => $nav,
                      'header' => $table->headerText()));

// Issue the main part. The method will echo itself
$table->display();

// Close some still opened divs
echo $this->inc('part.common.footer', array('css-prefix' => $css_pfx));

?>

<form data-upload-id='1'
      id="data-upload-form"
      class="file_upload_form"
      action="<?php print_unescaped(OCP\Util::linkTo('cafevdb', 'ajax/email/uploadattachment.php')); ?>"
      method="post"
      enctype="multipart/form-data"
      target="file_upload_target_1">
  <input type="hidden" name="MAX_FILE_SIZE" id="max_upload"
						   value="<?php p($_['uploadMaxFilesize']) ?>">
  <!-- Send the requesttoken, this is needed for older IE versions
       because they don't send the CSRF token via HTTP header in this case -->
  <input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>" id="requesttoken">
  <input type="hidden" class="max_human_file_size"
	 value="(max <?php p($_['uploadMaxHumanFilesize']); ?>)">
  <input type="file" id="file_upload_start" name="files[]" multiple>
</form>
<div id="uploadprogresswrapper">
  <div id="uploadprogressbar"></div>
  <input type="button" class="stop" style="display:none"
	 value="<?php p($l->t('Cancel upload'));?>"
	 />
</div>
