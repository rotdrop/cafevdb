<?php
use CAFEVDB\L;
use CAFEVDB\Util;
use CAFEVDB\Navigation;
use CAFEVDB\BulkAddMusicians;

$table = new BulkAddMusicians();
$css_pfx = BulkAddMusicians::CSS_PREFIX;

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
