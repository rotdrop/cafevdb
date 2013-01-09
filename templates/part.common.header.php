<?php
use CAFEVDB\L;
use CAFEVDB\Util;
use CAFEVDB\Navigation;

$css_pfx = $_['css-prefix'];
$hdr_vis = ' '.$_['headervisibility'];

$settingscontrols = Navigation::buttonsFromArray(
  array(
    'export' => array(
      'name' => L::t('Expert Operations'),
      'title' => L::t('Expert Operations like recreating views etc.'),
      'image' => OCP\Util::imagePath('core', 'actions/rename.svg'),
      'class' => 'settings expert',
      'style' => ($_['expertmode'] != 'on' ? 'display:none' : ''),
      'id' => 'expertbutton'),
    'settings' => array(
      'name' => L::t('Settings'),
      'title' => L::t('Personal Settings.'),
      'image' => OCP\Util::imagePath('core', 'actions/settings.svg'),
      'class' => 'settings generalsettings',
      'id' => 'settingsbutton')
    ));
$viewtoggle = Navigation::buttonsFromArray(
  array(
    'viewtoggle' => array(
      'name' => L::t('Toggle Visibility'),
      'type' => 'button',
      'title' => L::t('Minimize or maximize the containing block.'),
      'image' => OCP\Util::imagePath('cafevdb', 'transparent.svg'),
      'class' => 'viewtoggle '.$hdr_vis,
      'id' => 'viewtoggle')
    ));

if (!isset($_['headerblock']) && isset($_['header'])) {
  $header = $_['header']; 
} else {
  $header = '';
}

?>
<?php echo Util::emitInlineScripts(); ?>
<div id="controls">
<?php echo $_['navigationcontrols']; ?>
<form id="personalsettings">
  <?php echo $settingscontrols; ?>
</form>
</div>
<div class="cafevdb-general" id="cafevdb-general">
  <!-- divs for a header which can be hidden on button click. -->
  <?php echo isset($_['headerblock']) ? '<!-- ' : ''; ?>
  <div id="<?php echo $css_pfx; ?>-header-box" class="<?php echo $css_pfx; ?>-header-box<?php echo $hdr_vis; ?>">
    <div id="<?php echo $css_pfx; ?>-header" class="<?php echo $css_pfx; ?>-header<?php echo $hdr_vis; ?>">
      <?php echo $header; ?>
    </div>
    <?php echo $viewtoggle; ?>
    <div id="viewtogglebar" class="viewtoggle <?php echo $hdr_vis; ?>"></div>
  </div>
  <?php echo isset($_['headerblock']) ? ' -->' : ''; ?>
  <?php echo isset($_['headerblock']) ? $_['headerblock'] : ''; ?>
  <div id="<?php echo $css_pfx; ?>-body" class="<?php echo $css_pfx; ?>-body<?php echo $hdr_vis; ?>">
