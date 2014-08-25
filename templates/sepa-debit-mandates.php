<?php
use CAFEVDB\L;
use CAFEVDB\Navigation;
use CAFEVDB\SepaDebitMandates;

$table = new SepaDebitMandates();

$project = $_['projectName'];
$projectId = $_['projectId'];
$css_pfx = SepaDebitMandates::CSS_PREFIX;

$nav = '';
if ($projectId >= 0) {
  $nav .= Navigation::button('projectlabel', $project, $projectId);
  $nav .= Navigation::button('brief', $project, $projectId);  
  $nav .= Navigation::button('detailed', $project, $projectId);  
  $nav .= Navigation::button('projects');
} else {
  $nav .= Navigation::button('projects');
  $nav .= Navigation::button('all');
  $nav .= Navigation::button('projectinstruments');
  $nav .= Navigation::button('instruments');
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
