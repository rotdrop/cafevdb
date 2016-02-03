<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

use CAFEVDB\L;
use CAFEVDB\Config;

$title = L::t("SEPA Debit Mandate of %s", array($_['MusicianName']));

$mandateId  = $_['mandateId'];
$prjId      = $_['ProjectId'];
$mdtPrjId   = $_['MandateProjectId'];
$prjName    = $_['ProjectName'];
$musId      = $_['MusicianId'];
$musName    = $_['MusicianName'];
$class      = $_['CSSClass'];

$membersTableId = Config::getSetting('memberTableId', -1);

$recurring = L::t('Type: ').($_['sequenceType'] == 'once' ? L::t('once') : L::t('permanent'));

?>
<div id="sepa-debit-mandate-dialog" title="<?php echo $title;?>">
  <form id="sepa-debit-mandate-form" class="<?php echo $class; ?>" >
    <legend class="mandateCaption">
      <?php echo L::t('Mandate-Reference: '); ?>
      <span class="reference">
        <?php echo $_['mandateReference']; ?>
      </span>
    </legend>
    <input type="hidden" autofocus="autofocus" />
    <input type="hidden" name="MandateProjectId" value="<?php echo $mdtPrjId; ?>" />
    <input type="hidden" name="ProjectId" value="<?php echo $prjId; ?>" />
    <input type="hidden" name="ProjectName" value="<?php echo $prjName; ?>" />
    <input type="hidden" name="MusicianId" value="<?php echo $musId; ?>" />
    <input type="hidden" name="MusicianName" value="<?php echo $musName; ?>" />
    <input type="hidden" name="mandateReference" value="<?php echo $_['mandateReference']; ?>" />
    <input type="hidden" name="sequenceType" value="<?php echo $_['sequenceType']; ?>" />
    <?php if ($prjId !== $membersTableId) { ?>
    <input id="debit-mandate-orchestra-member"
           class="bankAccount orchestraMember checkbox"
           type="checkbox"
           name="orchestraMember"
           value="member"
           <?php echo $mdtPrjId === $membersTableId ? 'checked="checked"' : ''; ?>
           />
    <label for="debit-mandate-orchestra-member"
           title="<?php echo  Config::toolTips('debit-mandate-orchestra-member'); ?>"
           class="tooltip-right">
      <?php echo L::t('Club Member'); ?>
    </label>
    <?php } else { ?>
      <input type="hidden" name="orchestraMember" value="member" />
    <?php } ?>
    <input class="bankAccount bankAccountOwner" type="text"
           id="bankAccountOwner"
           name="bankAccountOwner"
           value="<?php echo $_['bankAccountOwner']; ?>"
           title="<?php echo L::t('owner of the bank account, probably same as musician'); ?>"
           placeholder="<?php echo L::t('owner of bank account'); ?>"/><br/>
    <input class="bankAccount bankAccountBLZ" type="text"
           id="bankAccountBLZ"
           name="bankAccountBLZ"
           value="<?php echo $_['bankAccountBLZ']; ?>"
           title="<?php echo L::t('Optional BLZ of the musician\'s bank account'); ?>"
           placeholder="<?php echo L::t('BLZ of bank account'); ?>"/>
    <input class="bankAccount bankAccountIBAN" type="text"
           id="bankAccountIBAN"
           name="bankAccountIBAN"
           value="<?php echo $_['bankAccountIBAN']; ?>"
           title="<?php echo L::t('IBAN or number of the bank account. If this is a account number, then please first enter the BLZ'); ?>"
           placeholder="<?php echo L::t('IBAN or no. of bank account'); ?>"/>
    <input class="bankAccount bankAccountBIC" type="text"
           id="bankAccountBIC"
           name="bankAccountBIC"
           value="<?php echo $_['bankAccountBIC']; ?>"
           title="<?php echo L::t('Optionally the BIC of the account; will be computed automatically if left blank.'); ?>"
           placeholder="<?php echo L::t('BIC of bank account'); ?>"/><br/>
    <label for="mandateDate"><?php echo L::t("Date issued:"); ?></label>
    <input class="mandateDate" type="text"
           id="mandateDate"
           name="mandateDate"
           value="<?php echo $_['mandateDate']; ?>"
           title="<?php echo L::t('Date of mandate grant'); ?>"
           placeholder="<?php echo L::t('mandate date'); ?>"/>
<?php if ($_['sequenceType'] == 'once') { ?>
    <input type="hidden" name="lastUsedDate" value="<?php echo $_['lastUsedDate']; ?>"/>
<?php } else { ?>
    <label for="lastUsedDate"><?php echo L::t("Date of last usage:"); ?>
      <input class="lastUsedDate" type="text"
             id="lastUsedDate"
             <?php echo $_['sequenceType'] == 'once' ? 'disabled' : '' ?>
             name="lastUsedDate"
             value="<?php echo $_['lastUsedDate']; ?>"
             title="<?php echo L::t('Date of last usage of debit-mandate'); ?>"
             placeholder="<?php echo L::t('last used date'); ?>"/>
    </label>
<?php } ?><br/>
    <span id="debitRecurringInfo"><?php echo $recurring; ?></span>
    <label class="sepa-validation-toggle"
           for="sepa-validation-toggle"
           title="<?php echo Config::toolTips('sepa-instant-validation'); ?>">
      <?php echo L::t('Instant IBAN Validation:'); ?>
      <input type="checkbox"
             checked="checked"
             class="sepa-validation-toggle"
             name="sepa-validation-toggle"
             id="sepa-validation-toggle"/>
      <div class="sepa-validation-toggle"></div>
    </label>
  </form>
  <div class="sepastatusblock">
    <span class="statusmessage" style="display:inline-block;" id="msg"></span>
    <span class="statusmessage" style="display:inline-block;" id="suggestions"></span>
    <div id="debug"></div>
  </div>
</div>
