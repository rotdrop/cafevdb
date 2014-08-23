<?php

use CAFEVDB\L;
use CAFEVDB\Config;

$title = L::t("SEPA Debit Mandate of %s", array($_['MusicianName']));

$prjId   = $_['ProjectId'];
$prjName = $_['ProjectName'];
$musId   = $_['MusicianId'];
$musName = $_['MusicianName'];
$class   = $_['CSSClass'];

$recurring = L::t('Type: ').($_['nonrecurring'] ? L::t('nonrecurring') : L::t('permanent'));

?>
<div id="sepa-debit-mandate-dialog" title="<?php echo $title;?>">
  <form id="sepa-debit-mandate-form" class="<?php echo $class; ?>" >
    <legend><?php echo L::t('Mandate-Reference: %s', array($_['mandateReference'])); ?></legend>
    <input type="hidden" autofocus="autofocus" />
    <input type="hidden" name="ProjectId"   value="<?php echo $prjId; ?>" />
    <input type="hidden" name="ProjectName" value="<?php echo $prjName; ?>" />
    <input type="hidden" name="MusicianId"   value="<?php echo $musId; ?>" />
    <input type="hidden" name="MusicianName" value="<?php echo $musName; ?>" />
    <input type="hidden" name="mandateReference" value="<?php echo $_['mandateReference']; ?>" />
    <input class="bankAccountOwner" type="text"
           id="bankAccountOwner"
           name="bankAccountOwner"
           value="<?php echo $_['bankAccountOwner']; ?>"
           title="<?php echo L::t('owner of the bank account, probably same as musician'); ?>"
           placeholder="<?php echo L::t('owner of bank account'); ?>"/><br/>
    <input class="bankAccountBLZ" type="text"
           id="bankAccountBLZ"
           name="bankAccountBLZ"
           value="<?php echo $_['bankAccountBLZ']; ?>"
           title="<?php echo L::t('Optional BLZ of the musician\'s bank account'); ?>"
           placeholder="<?php echo L::t('BLZ of bank account'); ?>"/>
    <input class="bankAccountIBAN" type="text"
           id="bankAccountIBAN"
           name="bankAccountIBAN"
           value="<?php echo $_['bankAccountIBAN']; ?>"
           title="<?php echo L::t('IBAN or number of the bank account. If this is a account number, then please first enter the BLZ'); ?>"
           placeholder="<?php echo L::t('IBAN or no. of bank account'); ?>"/>
    <input class="bankAccountBIC" type="text"
           id="bankAccountBIC"
           name="bankAccountBIC"
           value="<?php echo $_['bankAccountBIC']; ?>"
           title="<?php echo L::t('Optional BIC of the account'); ?>"
           placeholder="<?php echo L::t('BIC of bank account'); ?>"/><br/>
    <label for="mandateDate"><?php echo L::t("Date issued:"); ?></label>
    <input class="mandateDate" type="text"
           id="mandateDate"
           name="mandateDate"
           value="<?php echo $_['mandateDate']; ?>"
           title="<?php echo L::t('Date of mandate grant'); ?>"
           placeholder="<?php echo L::t('mandate date'); ?>"/>
    <label for="lastUsedDate"><?php echo L::t("Date of last usage:"); ?></label>
    <input class="lastUsedDate" type="text"
           id="lastUsedDate"
           <?php echo $_['nonrecurring'] ? 'disabled' : '' ?>
           name="lastUsedDate"
           value="<?php echo $_['lastUsedDate']; ?>"
           title="<?php echo L::t('Date of last usage of debit-mandate'); ?>"
           placeholder="<?php echo L::t('last used date'); ?>"/><br/>
    <span id="debitRecurringInfo"><?php echo $recurring; ?></span>
  </form>
  <div class="sepastatusblock">
    <span class="statusmessage" id="msg"></span>
    <span class="statusmessage" id="suggestion"></span>
    <div id="debug"></div>
  </div>
</div>

