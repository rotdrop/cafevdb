<?php

CAFEVDB\Config::init();

$buttons = array();
$buttons['pre'] = '<div>';
$buttons['post'] = '</div>';
$buttons['between'] = '</div><div>';
$buttons['makeviews'] =
  array('name' => $l->t('Recreate all Views'),
        'title' => $l->t('Recreate the ``Detailed Instrumentation\'\' hybrid-table for each project'),
        'id' => 'makeviews',
        'class' => 'operations expert button');
$buttons['check'] =
  array('name' => $l->t('Check Instruments'),
        'title' => $l->t('Check whether the instrumentation numbers table and the musicians table mention the same instruments'),
        'id' => 'checkinstruments',
        'class' => 'operations expert button');
$buttons['sanitize'] =
  array('name' => $l->t('Adjust Instruments'),
        'title' => $l->t('Make sure the instruments table contains at least any instrument played by any musician.'),
        'id' => 'adjustinstruments',
        'class' => 'operations expert button');
$buttons['example'] =
  array('name' => $l->t('Example'),
        'title' => $l->t('Example Do-Nothing Button'),
        'id' => 'example',
        'class' => 'operations example button');
//$btnstr = htmlspecialchars(CAFEVDB\Navigation::button($buttons));
//echo $btnstr;
?>
<div id="expertmode">
  <fieldset id="expertmode" class="operations expert">
  <strong><?php echo $l->t('Advanced operations, use with care') ?></strong><br />
  <input type="button" value="<?php echo $l->t('Open phpmyadmin'); ?>" onclick="return window.open('<?php echo CAFEVDB\Config::$opts['phpmyadmin']; ?>','cafevdb@phpmyadmin');" title="<?php echo $l->t('Open the login-window to the data-base back-bone. Although this is ``expert mode\'\' you will fall in love with the ``export\'\' facilities of the data-base back-bone. TRY IT OUT! DO IT!'); ?>"/>
  <?php echo CAFEVDB\Navigation::button($buttons); ?>
  </fieldset>
  <br/>
  <label for="" class="bold"><?php echo $l->t('Operation generated Response');?></label>
<?php
  echo CAFEVDB\Navigation::button(array('only' =>
                                        array('name' => $l->t('Clear'),
                                              'id' => 'clearoutput',
                                              'title' => $l->t('Remove output, if any is present.'),
                                              'class' => 'operations expert button')));
?>
<div class="msg"><span style="opacity:0.5"><?php echo $l->t('empty') ?></span></div>
</div>

