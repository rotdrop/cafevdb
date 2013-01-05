<?php
use CAFEVDB\L;
use CAFEVDB\Util;
use CAFEVDB\Navigation;
Util::emitInlineScripts();
$css_pfx = $_['css-prefix'];
$hdr_vis = ' '.$_['headervisibility'];

$settingscontrols = Navigation::buttonsFromArray(
  array(
    'export' => array('name' => 'Expert Operations',
                      'title' => 'Expert Operations like recreating views etc.',
                      'image' => OCP\Util::imagePath('core', 'actions/rename.svg'),
                      'class' => 'settings expert',
                      'style' => ($_['expertmode'] != 'on' ? 'display:none' : ''),
                      'id' => 'expertbutton'),
    'settings' => array('name' => 'Settings',
                        'title' => 'Personal Settings.',
                        'image' => OCP\Util::imagePath('core', 'actions/settings.svg'),
                        'class' => 'settings generalsettings',
                        'id' => 'settingsbutton')
    ));
$viewtoggle = Navigation::buttonsFromArray(
  array(
    'viewtoggle' => array('name' => 'Toggle Visibility',
                          'type' => 'button',
                          'title' => 'Minimize or maximize the containing block.',
                          'image' => OCP\Util::imagePath('cafevdb', 'transparent.svg'),
                          'class' => 'viewtoggle '.$hdr_vis,
                          'id' => 'viewtoggle')
    ));

?>
<div id="controls">
<?php echo $_['navigationcontrols']; ?>
<form id="personalsettings">
  <?php echo $settingscontrols; ?>
</form>
</div>
<div class="cafevdb-general" id="cafevdb-general">
  <?php echo !isset($_['header']) ? '<!-- ' : ''; ?>
  <!-- divs for a header which can be hidden on button click. -->
  <div id="<?php echo $css_pfx; ?>-header-box" class="<?php echo $css_pfx; ?>-header-box<?php echo $hdr_vis; ?>">
    <div id="<?php echo $css_pfx; ?>-header" class="<?php echo $css_pfx; ?>-header<?php echo $hdr_vis; ?>">
      <?php echo isset($_['header']) ? $_['header'] : ''; ?>
    </div>
    <?php echo $viewtoggle; ?>
  </div>
  <?php echo !isset($_['header']) ? ' -->' : ''; ?>
  <div id="<?php echo $css_pfx; ?>-body" class="<?php echo $css_pfx; ?>-body<?php echo $hdr_vis; ?>">
