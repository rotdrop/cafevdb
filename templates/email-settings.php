<?php use CAFEVDB\L; ?>
<?php use CAFEVDB\Config; ?>

<div id="tabs-<?php echo $_['tabNr']; ?>" class="personalblock admin email">
  <form id="emailsettings">
<!-- SMTP and IMAP settings -->  
<?php
foreach (array('smtp', 'imap') as $proto) {
  $upproto = strtoupper($proto);
  echo ''
    .'<fieldset id="email'.$proto.'fields">'
    .'<legend>'.$upproto.' '.L::t('Settings').'</legend>
  <input type="text" name="'.$proto.'server" id="'.$proto.'server" '
    .'value="'.$_[$proto.'server'].'" '
    .'placeholder="'.$upproto.' Server" />
  <label for="'.$proto.'server">'.$upproto.' Server</label>
  <br/>
  <input type="text" name="'.$proto.'port" id="'.$proto.'port" '
    .'value="'.$_[$proto.'port'].'" '
    .'placeholder="'.$upproto.' Port" />
  <label for="'.$proto.'port">'.$upproto.' Port</label>
  <br/>
  <label for="'.$proto.'secure" id="'.$proto.'securelabel">
  <select name="'.$proto.'secure" id="'.$proto.'secure" '
  .'data-placeholder="'.$upproto.' '.L::t('security').'" >
    <option value=""></option>';
  foreach (array('insecure', 'starttls', 'ssl') as $value) {
    $upvalue = strtoupper($value);
    $sel = ($_[$proto.'secure'] == $value) ? 'selected="selected"' : '';
    echo '<option value="'.$value.'" '.$sel.'>'.$upvalue.'</option>'."\n";
  }
  echo '
  </select>'.$upproto.' '.L::t('security').'</label>'."\n";
  echo '
</fieldset>';
  if ($proto == 'smtp') {
    echo '&nbsp;&nbsp;&nbsp;&nbsp;'."\n";
  }
}
?>
<!-- GENERAL EMAIL STUFF -->
  <br/>
    <fieldset id="emailaccount"><legend><?php echo L::t('Email Account'); ?></legend>
      <input type="text" name="emailuser" id="emailuser" value="<?php echo $_['emailuser']; ?>" placeholder="<?php echo L::t('Email-User');?>" />
      <label for="emailuser"><?php echo L::t('Login for email account.');?></label>
      <br/>
<!-- EMAIL password -->
      <fieldset id="emailpassword">
        <input type="password" value="<?php echo $_['emailpassword']; ?>" id="emailpassword" name="emailpassword" placeholder="<?php echo L::t('New Password');?>" data-typetoggle="#emailpassword-show" />
        <input type="checkbox" id="emailpassword-show" name="emailpassword-show" /><label for="emailpassword-show"><?php echo L::t('show');?></label>
        <input id="button" type="button" value="<?php echo L::t('Change email password');?>" />
        <div class="statusmessage" id="changed"><?php echo L::t('The email password was changed');?></div>
        <div class="statusmessage" id="error"><?php echo L::t('Unable to change the email password');?></div>
      </fieldset>
      <fieldset id="emaildistribute">
        <input id="emaildistributebutton" type="button" name="emaildistribute" value="<?php echo L::t('Distribute Email Account');?>" title="<?php echo  Config::toolTips('email-account-distribute');?>" />
        <span class="statusmessage" id="email-account-distribute-message"></span>
      </fieldset>
    </fieldset>
    <fieldset id="emailidentity"><legend><?php echo L::t('Bulk Sender Identity'); ?></legend>
      <input type="text" name="emailfromname" id="emailfromname" value="<?php echo $_['emailfromname']; ?>" placeholder="<?php echo L::t('Real Sender Name');?>" />
      <label for="emailfromname"><?php echo L::t('From: name');?></label>
      <input type="text" name="emailfromaddress" id="emailfromaddress" value="<?php echo $_['emailfromaddress']; ?>" placeholder="<?php echo L::t('Email From Adress');?>" />
      <label for="emailfromaddress"><?php echo L::t('From: address');?></label>
    </fieldset>
    <fieldset id="emailtest"><legend><?php echo L::t('Test Settings'); ?></legend>
      <input type="button" name="emailtest" id="emailtestbutton" value="<?php echo L::t('Test Email Setup'); ?>" title="<?php echo Config::toolTips('emailtest'); ?>" />
      <input id="emailtestmode" type="checkbox" name="emailtestmode" <?php echo $_['emailtestmode'] == 'on' ? 'checked="checked"' : ''; ?> id="emailtestmode" title="<?php echo L::t('Email test-mode; send emails only to the email test-address.') ?>"/>
      <label for="emailtestmode" title="<?php echo L::t('Email test-mode; send emails only to the email test-address.') ?>"><?php echo L::t('Test-Mode'); ?></label>
      <input <?php echo $_['emailtestmode'] == 'on' ? '' : 'disabled' ?> type="text" name="emailtestaddress" id="emailtestaddress" value="<?php echo $_['emailtestaddress']; ?>" placeholder="<?php echo L::t('Test Email Adress');?>" />
      <label for="emailtestaddress"><?php echo L::t('Test address');?></label>
    </fieldset>
    <span class="statusmessage" id="msg"></span>  
  </form>
</div>
