<?php

use CAFEVDB\L;
use CAFEVDB\Config;

$off = $_['orchestra'] == '' ? 'disabled="disabled"' : '';

?>
<div id="tabs-<?php echo $_['tabNr']; ?>" class="personalblock admin">
  <form id="orchestra">
    <fieldset <?php echo $off; ?> >
      <legend><?php echo L::t('Street Address'); ?></legend>
      <input class="streetAddressName" type="text"
           id="streetAddressName01"
           name="streetAddressName01"
           value="<?php echo $_['streetAddressName01']; ?>"
           title="<?php echo L::t('The name of the orchestra'); ?>"
           placeholder="<?php echo L::t('name of orchestra'); ?>"/><br/>
      <input class="streetAddressName" type="text"
           id="streetAddressName02"
           name="streetAddressName02"
           value="<?php echo $_['streetAddressName02']; ?>"
           title="<?php echo L::t('The name of the orchestra (line 2)'); ?>"
           placeholder="<?php echo L::t('name of orchestra'); ?>"><br/>
      <input class="streetAddressStreet" type="text"
           id="streetAddressStreet"
           name="streetAddressStreet"
           value="<?php echo $_['streetAddressStreet']; ?>"
           title="<?php echo L::t('street part of street address of orchestra'); ?>"
           placeholder="<?php echo L::t('street part of street address of orchestra'); ?>">
      <input class="streetAddressHouseNumber" type="text"
           id="streetAddressHouseNumber"
           name="streetAddressHouseNumber"
           value="<?php echo $_['streetAddressHouseNumber']; ?>"
           title="<?php echo L::t('house number part of street address of orchestra'); ?>"
           placeholder="<?php echo L::t('Nr'); ?>"><br/>
      <input class="streetAddressZIP" type="text"
           id="streetAddressZIP"
           name="streetAddressZIP"
           value="<?php echo $_['streetAddressZIP']; ?>"
           title="<?php echo L::t('ZIP part of street address of orchestra'); ?>"
           placeholder="<?php echo L::t('ZIP'); ?>">
      <input class="streetAddressCity" type="text"
           id="streetAddressCity"
           name="streetAddressCity"
           value="<?php echo $_['streetAddressCity']; ?>"
           title="<?php echo L::t('city part of address of orchestra'); ?>"
           placeholder="<?php echo L::t('city'); ?>"><br/>
      <input class="streetAddressCountry" type="text"
           id="streetAddressCountry"
           name="streetAddressCountry"
           value="<?php echo $_['streetAddressCountry']; ?>"
           title="<?php echo L::t('optional country part of address of orchestra'); ?>"
           placeholder="<?php echo L::t('country (optional)'); ?>"><br/>
    </fieldset>
    <fieldset <?php echo $off; ?> >
      <legend><?php echo L::t('Bank Account'); ?></legend>
      <input class="bankAccountOwner" type="text"
           id="bankAccountOwner"
           name="bankAccountOwner"
           value="<?php echo $_['bankAccountOwner']; ?>"
           title="<?php echo L::t('owner of the orchestra\'s bank account'); ?>"
           placeholder="<?php echo L::t('owner of bank account'); ?>"/><br/>
      <input class="bankAccountBLZ" type="text"
           id="bankAccountBLZ"
           name="bankAccountBLZ"
           value="<?php echo $_['bankAccountBLZ']; ?>"
           title="<?php echo L::t('Optional BLZ of the orchestra\'s bank account'); ?>"
           placeholder="<?php echo L::t('BLZ of bank account'); ?>"/>
      <input class="bankAccountIBAN" type="text"
           id="bankAccountIBAN"
           name="bankAccountIBAN"
           value="<?php echo $_['bankAccountIBAN']; ?>"
           title="<?php echo L::t('IBAN or number of the orchestra\'s bank account. If this is a account number,then please first enter the BLZ'); ?>"
           placeholder="<?php echo L::t('IBAN or no. of bank account'); ?>"/>
      <input class="bankAccountBIC" type="text"
           id="bankAccountBIC"
           name="bankAccountBIC"
           value="<?php echo $_['bankAccountBIC']; ?>"
           title="<?php echo L::t('Optional BIC of the orchestra\'s bank account'); ?>"
           placeholder="<?php echo L::t('BIC of bank account'); ?>"/><br/>
      <input class="bankAccountCreditorIdentifier" type="text"
           id="bankAccountCreditorIdentifier"
           name="bankAccountCreditorIdentifier"
           value="<?php echo $_['bankAccountCreditorIdentifier']; ?>"
           title="<?php echo L::t('Creditor identifier of the orchestra'); ?>"
           placeholder="<?php echo L::t('orchestra\'s CI'); ?>"/><br/>
    </fieldset>
    <fieldset <?php echo $off; ?> >
      <legend><?php echo L::t('Executive board and club members'); ?></legend>
      <input class="specialMemberTables" type="text"
           id="memberTable"
           name="memberTable"
           value="<?php echo $_['memberTable']; ?>"
           title="<?php echo L::t('Name of the table listing the permanent members of the orchestra'); ?>"
           placeholder="<?php echo L::t('member-table'); ?>"/><br/>
      <input class="specialMemberTables" type="text"
           id="executiveBoardTable"
           name="executiveBoardTable"
           value="<?php echo $_['executiveBoardTable']; ?>"
           title="<?php echo L::t('Name of the table listing the members of the executive board'); ?>"
           placeholder="<?php echo L::t('executive board table'); ?>"/><br/>
    </fieldset>
    <span class="statusmessage" id="msg"></span>
    <span class="statusmessage" id="suggestion"></span>
  </form>
</div>