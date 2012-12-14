<form id="cafevdb">
  <fieldset class="personalblock">
    <strong>Camerata DB</strong><br />
    <input type="text" name="CAFEVgroup" id="CAFEVgroup" value="<?php echo $_['CAFEVgroup']; ?>" placeholder="<?php echo $l->t('Group');?>" />
    <label for="CAFEVgroup">Member's Group</label>
    <br/>
    <input type="text" name="CAFEVdbserver" id="CAFEVdbserver" value="<?php echo $_['CAFEVdbserver']; ?>" placeholder="<?php echo $l->t('Server');?>" />
    <label for="CAFEVdbserver">Database Server</label>
    <br/>
    <input type="text" name="CAFEVdbname" id="CAFEVdbname" value="<?php echo $_['CAFEVdbname']; ?>" placeholder="<?php echo $l->t('Database');?>" />
    <label for="CAFEVdbname">Database Name</label>
    <br/>
    <input type="text" name="CAFEVdbuser" id="CAFEVdbuser" value="<?php echo $_['CAFEVdbuser']; ?>" placeholder="<?php echo $l->t('User');?>" />
    <label for="CAFEVdbuser">Database User</label>
    <br/>
    <input type="password" name="CAFEVdbpasswd" id="CAFEVdbpasswd" value="<?php echo $_['CAFEVdbpasswd']; ?>" placeholder="<?php echo $l->t('Password');?>" />
    <label for="CAFEVdbpasswd">Database Password</label>
  </fieldset>
  <span class="msg"></span>
</form>

