<?php
use CAFEVDB\L;
use CAFEVDB\Navigation;
use CAFEVDB\Instruments;

$table = new Instruments();
$css_pfx = Instruments::CSS_PREFIX;
$project = $table->project;
$projectId = $table->projectId;

$nav = '';
if ($projectId >= 0) {
  $nav .= Navigation::button('projectlabel', $project, $projectId);
  $nav .= Navigation::button('projects');
  $nav .= Navigation::button('projectinstruments', $project, $projectId);
  $nav .= Navigation::button('brief', $project, $projectId);
  $nav .= Navigation::button('detailed', $project, $projectId);
} else {
  $nav .= Navigation::button('projects');
  $nav .= Navigation::button('projectinstruments');
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

