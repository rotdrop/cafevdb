<?php
use CAFEVDB\L;
use CAFEVDB\Navigation;

$css_pfx = 'cafevdb-page';

$nav = '';
$nav .= Navigation::button('projects');
$nav .= Navigation::button('all');
$nav .= Navigation::button('projectinstruments');
$nav .= Navigation::button('instruments');

$header = 'Hello World';

echo $this->inc('part.common.header',
                array('css-prefix' => $css_pfx,
                      'navigationcontrols' => $nav,
                      'header' => $header));
?>

Hello World!

<?php
// Close some still opened divs
echo $this->inc('part.common.footer', array('css-prefix' => $css_pfx));

?>

