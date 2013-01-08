<script type="text/javascript">
  <?php echo $_['jsscript']; ?>
</script>
<?php

use CAFEVDB\L;
CAFEVDB\Config::init();

$buttons = array();
$buttons['pre'] = '<div>';
$buttons['post'] = '</div>';
$buttons['between'] = '</div><div>';
$buttons['syncevents'] =
  array('name' => 'Synchronize Events',
        'title' => CAFEVDB\Config::toolTips('syncevents'),
        'id' => 'syncevents',
        'class' => 'operations expert button');
$buttons['makeviews'] =
  array('name' => 'Recreate all Views',
        'title' => 'Recreate the ``Detailed Instrumentation\'\' hybrid-table for each project',
        'id' => 'makeviews',
        'class' => 'operations expert button');
$buttons['check'] =
  array('name' => 'Check Instruments',
        'title' => 'Check whether the instrumentation numbers table and the musicians table mention the same instruments',
        'id' => 'checkinstruments',
        'class' => 'operations expert button');
$buttons['sanitize'] =
  array('name' => 'Adjust Instruments',
        'title' => 'Make sure the instruments table contains at least any instrument played by any musician.',
        'id' => 'adjustinstruments',
        'class' => 'operations expert button');
$buttons['example'] =
  array('name' => 'Example',
        'title' => 'Example Do-Nothing Button',
        'id' => 'example',
        'class' => 'operations example button');
//$btnstr = htmlspecialchars(CAFEVDB\Navigation::button($buttons));
//echo $btnstr;
?>
<div id="expertmode">
  <fieldset id="expertmode" class="operations expert">
  <input type="button" value="<?php echo L::t('Open phpmyadmin'); ?>" onclick="return window.open('<?php echo CAFEVDB\Config::$opts['phpmyadmin']; ?>','cafevdb@phpmyadmin');" title="<?php echo L::t('Open the login-window to the data-base back-bone. Although this is ``expert mode\'\' you will fall in love with the ``export\'\' facilities of the data-base back-bone. TRY IT OUT! DO IT!'); ?>"/>
  <br/>
  <input type="button" value="<?php echo L::t('Source-Code Documentation'); ?>" onclick="return window.open('apps/cafevdb/doc/db-app/html/','Doxygen4CAFEVDB');" title="<?php echo L::t('Internal documentation of the ``CAFEV-App\'\', mostly useful for web-developers.'); ?>"/>
  <br/>
  <input type="button" value="<?php echo L::t('Source-Code Archive'); ?>" onclick="return window.open('https://ch.homelinux.net:8888/git/cafevdb.git/tree/owncloud-app','GIT4CAFEVDB');" title="<?php echo L::t('View the git-repository of the ``CAFEV-App\'\', mostly useful for web-developers.'); ?>"/>
  <?php echo CAFEVDB\Navigation::button($buttons); ?>
  </fieldset>
  <br/>
  <label for="" class="bold"><?php echo L::t('Operation generated Response');?></label>
<?php
  echo CAFEVDB\Navigation::button(array('only' =>
                                        array('name' => 'Clear Output',
                                              'id' => 'clearoutput',
                                              'title' => 'Remove output, if any is present.',
                                              'class' => 'operations expert button')));
?>
<div class="msg"><span style="opacity:0.5"><?php echo L::t('empty') ?></span></div>
</div>
