<?php use CAFEVDB\L; ?>
<?php use CAFEVDB\Config; ?>
<div id="tabs-4" class="personalblock admin">
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
  <select name="'.$proto.'secure" id="'.$proto.'secure">';
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

/* $checked = $_[$proto.'noauth'] ? 'checked="checked"' : ''; */
/*   <br/> */
/*   <label for="'.$proto.'noauth">' */
/* .'<input type="checkbox" ' */
/* .'id="'.$proto.'noauth" ' */
/* .'name="'.$proto.'noauth" '.$checked.'/>' */
/* .L::t('unauthorized').'</label> */
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
    </fieldset>
    <fieldset id="emailidentity"><legend><?php echo L::t('Bulk Sender Identity'); ?></legend>
    <input type="text" name="emailfromname" id="emailfromname" value="<?php echo $_['emailfromname']; ?>" placeholder="<?php echo L::t('Real Sender Name');?>" />
    <input type="text" name="emailfromaddress" id="emailfromaddress" value="<?php echo $_['emailfromaddress']; ?>" placeholder="<?php echo L::t('Email From Adress');?>" />
    <label for="emailidentity"><?php echo L::t('Identity for the From: record.');?></label>
    </fieldset>
    <br/>
    <fieldset id="emailtest">
    <input type="button" name="emailtest" id="emailtestbutton" value="<?php echo L::t('Test Email Setup'); ?>" title="<?php echo L::t(Config::toolTips('emailtest')); ?>" />
    </fieldset>
    <span class="statusmessage" id="msg"></span>  
  </form>
</div>
