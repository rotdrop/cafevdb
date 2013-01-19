<?php
use CAFEVDB\L;
use CAFEVDB\Util;
use CAFEVDB\Config;
use CAFEVDB\Navigation;

$css_pfx  = $_['css-prefix'];
$hdr_vis  = ' '.$_['headervisibility'];
$_hdr_vis = ' '.$_['headervisibility'];

$settingscontrols = Navigation::buttonsFromArray(
  array(
    'home' => array(
      'name' => L::t('Startpage'),
      'title' => L::t('Navigate back to the start-page.'),
      'image' => OCP\Util::imagePath('core', 'places/home.svg'),
      'class' => 'settings navigation home',
      'id' => 'homebutton'),
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
      'class' => 'viewtoggle'.$_hdr_vis,
      'id' => 'viewtoggle')
    ));

if (!isset($_['headerblock']) && isset($_['header'])) {
  $header = $_['header']; 
} else {
  $header = '';
}

?>
<?php echo Util::emitExternalScripts(); ?>
<?php echo Util::emitInlineScripts(); ?>
<div id="controls">
<?php echo $_['navigationcontrols']; ?>
<form id="personalsettings" method="post" action="?app=<?php echo Config::APP_NAME; ?>">
  <input type="hidden" name="headervisibility" value="<?php echo $hdr_vis; ?>" />
  <?php echo $settingscontrols; ?>
</form>
</div>
<div class="cafevdb-general" id="cafevdb-general">
  <!-- divs for a header which can be hidden on button click. -->
  <?php echo isset($_['headerblock']) ? '<!-- ' : ''; ?>
  <div id="<?php echo $css_pfx; ?>-header-box" class="<?php echo $css_pfx; ?>-header-box<?php echo $_hdr_vis; ?>">
    <div id="<?php echo $css_pfx; ?>-header" class="<?php echo $css_pfx; ?>-header<?php echo $_hdr_vis; ?>">
      <?php echo $header; ?>
    </div>
    <?php echo $viewtoggle; ?>
    <div id="viewtogglebar" class="viewtoggle <?php echo $_hdr_vis; ?>" title="<?php echo L::t('Minimize or maximize the containing block.'); ?>"></div>
  </div>
  <?php echo isset($_['headerblock']) ? ' -->' : ''; ?>
  <?php echo isset($_['headerblock']) ? $_['headerblock'] : ''; ?>
  <div id="<?php echo $css_pfx; ?>-body" class="<?php echo $css_pfx; ?>-body<?php echo $_hdr_vis; ?>">
