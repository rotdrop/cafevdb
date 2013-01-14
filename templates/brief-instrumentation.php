<?php
use CAFEVDB\L;
use CAFEVDB\Navigation;
use CAFEVDB\BriefInstrumentation;

$table = new BriefInstrumentation();
$css_pfx = BriefInstrumentation::CSS_PREFIX;

$nav = '';
$nav .= Navigation::button('projectlabel', $table->project, $table->projectId);
$nav .= Navigation::button('projects');
$nav .= Navigation::button('detailed', $table->project, $table->projectId);
$nav .= Navigation::button('add', $table->project, $table->projectId);
$nav .= Navigation::button('projectinstruments', $table->project, $table->projectId);

echo $this->inc('part.common.header',
                array('css-prefix' => $css_pfx,
                      'navigationcontrols' => $nav,
                      'header' => $table->headerText()));

// Issue the main part. The method will echo itself
$table->display();

// Close some still opened divs
echo $this->inc('part.common.footer', array('css-prefix' => $css_pfx));

?>

