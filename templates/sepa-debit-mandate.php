<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020-2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

$title = $l->t("SEPA Debit Mandate of %s", $musicianName);

$expiredTip = $toolTips['sepa-mandate-expired'];

$recurring = $l->t('Type: ').($sequenceType == 'once' ? $l->t('once') : $l->t('permanent'));

?>
<div id="sepa-debit-mandate-dialog" title="<?php echo $title;?>">
  <div style="display:none;"
       id="mandate-expired-notice"
       class="<?php echo ($mandateExpired ? 'active' : ''); ?> mandate-expired-notice tooltip-bottom"
       title="<?php echo $mandateExpiredTip; ?>">
    <div>
      <?php echo ($mandateExpired ? $l->t('expired') : ''); ?>
    </div>
  </div>
  <form id="sepa-debit-mandate-form" class="<?php echo $cssClass; ?>" >
    <legend class="mandateCaption">
      <?php echo $l->t('Mandate-Reference: '); ?>
      <span class="reference">
        <?php echo $mandateReference; ?>
      </span>
    </legend>
    <input type="hidden" autofocus="autofocus" />
    <input type="hidden" name="mandateProjectId" value="<?php echo $mandateProjectId; ?>" />
    <input type="hidden" name="projectId" value="<?php echo $projectId; ?>" />
    <input type="hidden" name="projectName" value="<?php echo $projectName; ?>" />
    <input type="hidden" name="musicianId" value="<?php echo $musicianId; ?>" />
    <input type="hidden" name="musicianName" value="<?php echo $musicianName; ?>" />
    <input type="hidden" name="expired" value="<?php echo $mandateExpired ? '1' : '0'; ?>" />
    <input type="hidden" name="mandateReference" value="<?php echo $mandateReference; ?>" />
    <input type="hidden" name="sequenceType" value="<?php echo $sequenceType; ?>" />
    <?php if ($projectId !== $memberProjectId) { ?>
    <input id="debit-mandate-orchestra-member"
           class="bankAccount orchestraMember checkbox"
           type="checkbox"
           name="orchestraMember"
           value="member"
           <?php echo $mandateProjectId === $membersTableId ? 'checked="checked"' : ''; ?>
           />
    <label for="debit-mandate-orchestra-member"
           title="<?php echo  $toolTips['debit-mandate-orchestra-member']; ?>"
           class="tooltip-right">
      <?php echo $l->t('Club Member'); ?>
    </label>
    <?php } else { ?>
      <input type="hidden" name="orchestraMember" value="member" />
    <?php } ?>
    <input class="bankAccount bankAccountOwner" type="text"
           id="bankAccountOwner"
           name="bankAccountOwner"
           value="<?php echo $bankAccountOwner; ?>"
           title="<?php echo $l->t('owner of the bank account, probably same as musician'); ?>"
           placeholder="<?php echo $l->t('owner of bank account'); ?>"/><br/>
    <input class="bankAccount bankAccountBLZ" type="text"
           id="bankAccountBLZ"
           name="bankAccountBLZ"
           value="<?php echo $bankAccountBLZ; ?>"
           title="<?php echo $l->t('Optional BLZ of the musician\'s bank account'); ?>"
           placeholder="<?php echo $l->t('BLZ of bank account'); ?>"/>
    <input class="bankAccount bankAccountIBAN" type="text"
           id="bankAccountIBAN"
           name="bankAccountIBAN"
           value="<?php echo $bankAccountIBAN; ?>"
           title="<?php echo $l->t('IBAN or number of the bank account. If this is a account number, then please first enter the BLZ'); ?>"
           placeholder="<?php echo $l->t('IBAN or no. of bank account'); ?>"/>
    <input class="bankAccount bankAccountBIC" type="text"
           id="bankAccountBIC"
           name="bankAccountBIC"
           value="<?php echo $bankAccountBIC; ?>"
           title="<?php echo $l->t('Optionally the BIC of the account; will be computed automatically if left blank.'); ?>"
           placeholder="<?php echo $l->t('BIC of bank account'); ?>"/><br/>
    <label for="mandateDate"><?php echo $l->t("Date issued:"); ?></label>
    <input class="mandateDate" type="text"
           id="mandateDate"
           name="mandateDate"
           value="<?php echo $mandateDate; ?>"
           title="<?php echo $l->t('Date of mandate grant'); ?>"
           placeholder="<?php echo $l->t('mandate date'); ?>"/>
<?php if ($sequenceType == 'once') { ?>
    <input type="hidden" name="lastUsedDate" class="lastUsedDate" value="<?php echo $lastUsedDate; ?>"/>
<?php } else { ?>
    <label for="lastUsedDate"><?php echo $l->t("Date of last usage:"); ?>
      <input class="lastUsedDate" type="text"
             id="lastUsedDate"
             <?php echo $sequenceType == 'once' ? 'disabled' : '' ?>
             name="lastUsedDate"
             value="<?php echo $lastUsedDate; ?>"
             title="<?php echo $l->t('Date of last usage of debit-mandate'); ?>"
             placeholder="<?php echo $l->t('last used date'); ?>"/>
    </label>
<?php } ?><br/>
    <span id="debitRecurringInfo" class="debitRecurringInfo <?php echo $sequenceType; ?>">
      <span class="label"><?php echo $l->t('Type:'); ?></span>
      <span class="space">&nbsp;</span>
      <span class="type once"><?php echo $l->t('once'); ?></span>
      <span class="type permanent"><?php echo $l->t('permanent'); ?></span>
    </span>
    <label class="sepa-validation-toggle"
           for="sepa-validation-toggle"
           title="<?php echo $toolTips['sepa-instant-validation']; ?>">
      <?php echo $l->t('Instant IBAN Validation:'); ?>
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
