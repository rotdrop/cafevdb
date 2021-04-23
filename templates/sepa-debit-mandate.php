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

// do we have a mandate?
$haveMandate = (int)$mandateSequence > 0;

// compute current or default value for mandate binding
if ($haveMandate) {
  $mandateBinding = $mandateProjectId > 0 ? 'only-project' : 'all-receivables';
} else {
  $mandateBinding = ((int)$projectId > 0
    ? ($projectId == $memberProjectId ? 'all-receivables' : 'only-project')
    : 'all-receivables');
}

$hidden = [
  'haveMandate' => ($haveMandate ? 'hidden' : ''),
  'noMandate' => (!$haveMandate ? 'hidden' : ''),
  'writtenMandate' => (!empty($writtenMandate) ? 'hidden' : ''),
  'noWrittenMandate' => (empty($writtenMandate) ? 'hidden' : ''),
  'project' => ((int)$projectId > 0 ? 'hidden' : ''),
  'noProject' => ((int)$projectId <= 0 ? 'hidden' : ''),
];

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
  <form id="sepa-debit-mandate-form" class="sepa-bank-data <?php echo $cssClass; ?>" >
    <input type="hidden" autofocus="autofocus" />
    <!-- @todo perhaps better use a JSON data-field for this mess -->
    <input type="hidden" name="mandateProjectId" value="<?php echo $mandateProjectId; ?>" />
    <input type="hidden" name="mandateProjectName" value="<?php echo $mandateProjectName; ?>" />
    <input type="hidden" name="projectId" value="<?php echo $projectId; ?>" />
    <input type="hidden" name="projectName" value="<?php echo $projectName; ?>" />
    <input type="hidden" name="musicianId" value="<?php echo $musicianId; ?>" />
    <input type="hidden" name="musicianName" value="<?php echo $musicianName; ?>" />
    <input type="hidden" name="bankAccountSequence" value="<?php echo $bankAccountSequence; ?>" />
    <input type="hidden" name="mandateSequence" value="<?php echo $mandateSequence; ?>" />
    <input type="hidden" name="mandateReference" value="<?php echo $mandateReference; ?>" />
    <input type="hidden" name="sequenceType" value="<?php echo $sequenceType; ?>" />
    <input type="hidden" name="nonRecurring" value="<?php echo $nonRecurring; ?>" />
    <input type="hidden" name="writtenMandate" value="<?php echo $writtenMandate; ?>" />
    <fieldset>
      <legend>
        <?php echo $l->t('Bank Account'); ?>
      </legend>
      <input class="bankAccount bankAccountOwner" type="text"
             id="bankAccountOwner"
             name="bankAccountOwner"
             value="<?php echo $bankAccountOwner; ?>"
             title="<?php echo $l->t('owner of the bank account, probably same as musician'); ?>"
             placeholder="<?php echo $l->t('owner of bank account'); ?>"/><br/>
      <div class="bank-account-identifier">
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
               placeholder="<?php echo $l->t('BIC of bank account'); ?>"/>
      </div>
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
      <div class="statusmessage suggestions"></div>
    </fieldset>
    <fieldset class="debit-mandate <?php empty($haveMandate) && p('no-mandate'); ?> <?php empty($writtenMandate) && p('no-written-mandate'); ?>">
      <input id="sepa-debit-mandate-upload-later"
             class="bankAccount projectMandate checkbox inline-block <?php p($hidden['writtenMandate']); ?>"
             type="checkbox"
             name="uploadLater"
             value="uploadLater"
      />
      <label for="sepa-debit-mandate-upload-later"
             title="<?php echo $toolTips['sepa-bank-data-form:upload-written-mandate-later']; ?>"
             class="tooltip-right inline-block <?php p($hidden['writtenMandate']); ?>">
            <?php echo $l->t('upload later'); ?>
      </label>
      <legend class="mandateCaption inline-block">
        <span class="registration-label <?php p($hidden['haveMandate']); ?>">
          <?php p($l->t('Mandate-Registration')); ?>
        </span>
        <span class="registration-for-label hidden">
          <?php p($l->t('Mandate-Registration for %s', $mandateReference)); ?>
        </span>
        <span class="reference-label <?php p($hidden['noMandate']); ?>">
          <?php p($l->t('Mandate-Reference: %s', $mandateReference)); ?>
        </span>
      </legend>
      <div class="debit-mandate-details <?php echo (!$haveMandate ? ' hidden' : '');?>">
        <span class="debit-mandate-binding <?php p($hidden['noProject']); ?>">
          <input id="sepa-debit-mandate-only-for-project"
                 class="bankAccount projectMandate checkbox"
                 type="radio"
                 name="debitMandateBinding"
                 value="only-project"
                 <?php echo $mandateBinding == 'only-project' ? 'checked' : ''; ?>
          />
          <label for="sepa-debit-mandate-only-for-project"
             title="<?php echo  $toolTips['sepa-debit-mandate-only-for-project']; ?>"
                 class="tooltip-right">
            <?php echo $l->t('only for "%s"', $memberProjectName); ?>
          </label>
        </span>
      <span class="debit-mandate-binding">
        <input id="sepa-debit-mandate-for-all-receivables"
               class="bankAccount projectMandate checkbox"
               type="radio"
               name="debitMandateBinding"
               value="all-receivables"
               <?php echo $mandateBinding == 'all-receivables' ? 'checked' : ''; ?>
        />
        <label for="sepa-debit-mandate-for-all-receivables"
               title="<?php echo  $toolTips['sepa-debit-mandate-for-all-receivables']; ?>"
               class="tooltip-right">
        <?php echo $l->t('for all receivables'); ?>
        </label>
      </span>
      <span id="debitRecurringInfo" class="debitRecurringInfo <?php echo $sequenceType; ?>">
        <span class="label"><?php echo $l->t('Type:'); ?></span>
        <span class="space">&nbsp;</span>
        <span class="type once"><?php echo $l->t('once'); ?></span>
        <span class="type permanent"><?php echo $l->t('permanent'); ?></span>
      </span>
      <br/>
      <label class="mandateDate" for="mandateDate"><?php echo $l->t("Date issued:"); ?>
        <input class="mandateDate" type="text"
               id="mandateDate"
               name="mandateDate"
               required="required"
               value="<?php echo $dateTimeFormatter->formatDate($mandateDate, 'medium'); ?>"
               title="<?php echo $l->t('Date of mandate grant'); ?>"
               placeholder="<?php echo $l->t('mandate date'); ?>"/>
      </label>
      <?php if ($sequenceType == 'once') { ?>
      <input type="hidden" name="lastUsedDate" class="lastUsedDate" value="<?php echo $lastUsedDate; ?>"/>
      <?php } else { ?>
      <label class="lastUsedDate" for="lastUsedDate"><?php echo $l->t("Date of last usage:"); ?>
        <input class="lastUsedDate" type="text"
               id="lastUsedDate"
               <?php echo $sequenceType == 'once' ? 'disabled' : '' ?>
               name="lastUsedDate"
               value="<?php echo $lastUsedDate; ?>"
               title="<?php echo $l->t('Date of last usage of debit-mandate'); ?>"
               placeholder="<?php echo $l->t('last used date'); ?>"/>
      </label>
      <?php } ?>
      </div>
      <div></div>
      <div class="debit-mandate-register inline-block">
        <div class="operations inline-block">
          <input type="button"
                 <?php empty($writtenMandate) && p('disabled'); ?>
                 title="<?php echo $toolTipsService['participant-attachment-delete']; ?>"
                 class="operation delete-undelete"/>
          <input type="button" title="<?php echo $toolTipsService['sepa-bank-data-form:upload-replace-written-mandate']; ?>" class="operation upload-replace"/>
        </div>
        <div class="file-data inline-block">
          <a class="download-link <?php empty($writtenMandate) && p('hidden'); ?>" title="<?php echo $toolTipsService['sepa-bank-data-form:download-written-mandate']; ?>" href="<?php echo $writtenMandateDownloadLink; ?>"><?php echo $writtenMandateFileName; ?></a>
          <input class="upload-placeholder <?php !empty($writtenMandate) && p('hidden'); ?>" title="<?php echo $toolTipsService['sepa-bank-data-form:upload-written-mandate']; ?>" placeholder="<?php echo $l->t('Upload filled SEPA debit mandate');  ?>" type="text"/>
        </div>
      </div>
    </fieldset>
  </form>
  <div class="statusmessage messagte"></div>
</div>
