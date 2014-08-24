<?php
use CAFEVDB\L;
use CAFEVDB\Util;

echo Util::emitExternalScripts();
echo Util::emitInlineScripts();

$tooltipstitle  = L::t("Control the display of tooltips. ".
                      "Warning: this works globally for all OwnCloud applications.");
$headervistitle = L::t("Start with visible page header-texts. This affects only ".
                       "the initial visibility of the page-headers.");
$filtervistitle = L::t("Initially display the filter-controls on all atable. This affects only ".
                       "the initial visibility of the filter-buttons and -inputs.");
$experttitle    = L::t("Show a second button which leads to a dialog with `advanced' settings");
$debugtitle     = L::t("Show a certain amount of debug information, normally not needed.");
?>
<?php if ($_['adminsettings']) { ?>
<ul id="adminsettingstabs">
  <li><a href="#tabs-1"><?php echo L::t('Personal'); ?></a></li>
  <li><a href="#tabs-2"><?php echo L::t('Orchestra'); ?></a></li>
  <li><a href="#tabs-3"><?php echo L::t('Data-Base'); ?></a></li>
  <li><a href="#tabs-4"><?php echo L::t('Sharing'); ?></a></li>
  <li><a href="#tabs-5"><?php echo L::t('Email'); ?></a></li>
  <li><a href="#tabs-6"><?php echo L::t('Development'); ?></a></li>
</ul>
<?php } ?>

<div id="tabs-1" class="personalblock <?php if ($_['adminsettings']) echo 'admin'; ?>">
  <form id="cafevdb">
    <input id="tooltips" type="checkbox" name="tooltips" <?php echo $_['tooltips'] == 'on' ? 'checked="checked"' : ''; ?> id="tooltips" title="<?php echo $tooltipstitle ?>"/>
    <label for="tooltips" title="<?php echo $tooltipstitle; ?>"><?php echo L::t('Tool-Tips') ?></label>
    <br />
    <input id="headervisibility" type="checkbox" name="headervisibility" <?php echo $_['headervisibility'] == 'expanded' ? 'checked="checked"' : ''; ?> id="tooltips" title="<?php echo $headervistitle ?>"/>
    <label for="headervisibility" title="<?php echo $headervistitle; ?>"><?php echo L::t('Page-Header') ?></label>
    <br />
    <input id="filtervisibility" type="checkbox" name="filtervisibility" <?php echo $_['filtervisibility'] == 'on' ? 'checked="checked"' : ''; ?> id="tooltips" title="<?php echo $headervistitle ?>"/>
    <label for="filtervisibility" title="<?php echo $filtervistitle; ?>"><?php echo L::t('Filter-Controls') ?></label>
    <br />
    <input id="expertmode" type="checkbox" name="expertmode" <?php echo $_['expertmode'] == 'on' ? 'checked="checked"' : ''; ?> id="expertmode" title="<?php echo $experttitle ?>"/>
    <label for="expertmode" title="<?php echo $experttitle; ?>"><?php echo L::t('Expert-Mode') ?></label>
    <br />
    <input id="debugmode" type="checkbox" name="debugmode" <?php echo $_['debugmode'] == 'on' ? 'checked="checked"' : ''; ?> id="debugmode" title="<?php echo $debugtitle ?>"/>
    <label for="debugmode" title="<?php echo $debugtitle; ?>"><?php echo L::t('Debug-Mode') ?></label>
    <br />
    <label for="exampletext" title="<?php echo L::t('Example Text') ?>"><?php echo L::t('Example') ?></label>
    <input type="text" name="exampletext" id="exampletext"
      value="<?php echo $_['exampletext'] ?>"
      placeholder="<?php echo L::t('Example Text') ?>"
      title="<?php echo L::t('Example Text') ?>" />
    <br />
    <input type="text" style="display:none;width:0%;float: left;" name="dummy" id="dummy" value="dummy" placeholder="dummy" title="<?php echo L::t('Dummy'); ?>" />
    <span class="statusmessage" id="msg"></span>
  </form>
  <form id="userkey">
    <input class="cafevdb-password" type="password" id="password" name="password" placeholder="<?php echo L::t('Own Password');?>" data-typetoggle="#password-show" />
    <input class="cafevdb-password-show" type="checkbox" id="password-show" name="password-show" />
    <label class="cafevdb-password-show" for="password-show"><?php echo L::t('show');?></label>
    <input class="cafevdb-password" type="password" id="encryptionkey" name="encryptionkey" value="<?php echo (true ? '' : $_['encryptionkey']); ?>" placeholder="<?php echo L::t('DB Encryption Key');?>" data-typetoggle="#userkey-show" />
    <input class="cafevdb-password-show" type="checkbox" id="userkey-show" name="userkey-show" />
    <label class="cafevdb-password-show" for="userkey-show"><?php echo L::t('show');?></label>
    <input id="button" type="button" value="<?php echo L::t('Set Encryption Key');?>" />
    <div class="statusmessage" id="changed"><?php echo L::t('The encryption key has been set successfully.');?></div>
    <div class="statusmessage" id="error"><?php echo L::t('Unable to set the encryption key.');?></div>
  </form>
</div>
<?php
  $tabNo = 2;
  if ($_['adminsettings']) {
    echo $this->inc("orchestra-settings", array('tabNr' => $tabNo++));
    echo $this->inc("app-settings", array('tabNr' => $tabNo++));
    echo $this->inc("share-settings", array('tabNr' => $tabNo++));
    echo $this->inc("email-settings", array('tabNr' => $tabNo++));
    echo $this->inc("devel-settings", array('tabNr' => $tabNo++));
  }
?>
