<?php
use CAFEVDB\L;
use CAFEVDB\Navigation;
use CAFEVDB\Projects;

$css_pfx = Projects::CSS_PREFIX;

$nav = '';
$nav .= Navigation::button('projectinstruments');
$nav .= Navigation::button('instruments');
$nav .= Navigation::button('all');

echo $this->inc('part.common.header',
                array('css-prefix' => $css_pfx,
                      'navigationcontrols' => $nav,
                      'header' => Projects::headerText()));

// Issue the main part. The method will echo itself
Projects::display();

// Close some still opened divs
echo $this->inc('part.common.footer', array('css-prefix' => $css_pfx));

?>

