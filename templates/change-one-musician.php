<?php
use CAFEVDB\L;
use CAFEVDB\Navigation;
use CAFEVDB\AddOneMusician;

$table = new AddOneMusician();
$css_pfx = AddOneMusician::CSS_PREFIX;

$nav = '';
$nav .= Navigation::button('projectlabel', $table->project, $table->projectId);
$nav .= Navigation::button('projects');
$nav .= Navigation::button('add', $table->project, $table->projectId);
$nav .= Navigation::button('brief', $table->project, $table->projectId);
$nav .= Navigation::button('projectinstruments', $table->project, $table->projectId);

// Generate our own header block
ob_start();
$table->display();
$header = ob_get_contents();
@ob_end_clean();

echo $this->inc('part.common.header',
                array('css-prefix' => $css_pfx,
                      'navigationcontrols' => $nav,
                      'headerblock' => $header));

// Issue the main part. The method will echo itself
$table->execute();

// Close some still opened divs
echo $this->inc('part.common.footer', array('css-prefix' => $css_pfx));

?>

