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

?>


