<?php
use CAFEVDB\L;
use CAFEVDB\Util;
use CAFEVDB\Navigation;
use CAFEVDB\Email;

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
                      'header' => Email::headerText()));

// Issue the main part. The method will echo itself
Email::display(OCP\USER::getUser());

// Close some still opened divs
echo $this->inc('part.common.footer', array('css-prefix' => $css_pfx));

?>

<!-- Upload support via Ajax/jsscript magic. -->
<form class="float" id="file_upload_form" action="<?php echo OCP\Util::linkTo('cafevdb', 'ajax/email/uploadattachment.php'); ?>" method="post" enctype="multipart/form-data" target="file_upload_target">
  <input type="hidden" name="requesttoken" value="<?php echo $_['requesttoken'] ?>">
  <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $_['uploadMaxFilesize'] ?>" id="max_upload">
  <input type="hidden" class="max_human_file_size" value="<?php echo $_['uploadMaxHumanFilesize']; ?>">
  <input id="file_upload_start" type="file" name="fileAttach" />
</form>
<iframe name="file_upload_target" id='file_upload_target' src=""></iframe>

