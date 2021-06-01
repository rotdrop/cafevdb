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

namespace OCA\CAFEVDB;

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNaviation;

$title = $l->t("SEPA Bank Information for %s", $musicianName);

$expiredTip = $toolTips['sepa-mandate-expired'];

$mandateSequenceType = $mandateNonRecurring ? 'once' : 'permanent';

// do we have a bankAccount?
$haveAccount = (int)$bankAccountSequence > 0;

// do we have a mandate?
$haveMandate = (int)$mandateSequence > 0;

// compute current or default value for mandate binding
if ($haveMandate) {
  $mandateBinding = $mandateProjectId == $memberProjectId ? 'for-all-receivables' : 'only-for-project';
} else {
  $mandateBinding = ((int)$projectId > 0
    ? ($projectId == $memberProjectId ? 'for-all-receivables' : 'only-for-project')
    : 'for-all-receivables');
}

$bindingText = [
  'only-for-project' => [
    $l->t('only for'),
    $l->t('only for "%s"', $mandateProjectName),
  ],
  'for-all-receivables' => [
    $l->t('for all receivables'),
    $l->t('for all receivables'),
  ],
];

$mandateCss = implode(' ', [
  'debit-mandate',
  (empty($haveMandate) ? 'no-data' : 'have-data'),
  (empty($mandateInUse) ? 'unused' : 'used'),
  (empty($writtenMandateId) ? 'no-written-mandate' : 'have-written-mandate'),
  (!empty($isClubMember) ? 'club-member' : null),
  (empty($mandateDeleted) ? null : 'deleted'),
]);

$accountCss = implode(' ', [
  'bank-account',
  (empty($haveMandate) ? 'no-data' : 'have-data'),
  (empty($bankAccountInUse) ? 'unused' : 'used'),
  (empty($bankAccountDeleted) ? null : 'deleted'),
]);

$formCss = implode(' ', [
  'sepa-debit-mandate-form',
  'sepa-bank-data',
  (empty($bankAccountDeleted) ? null : 'bank-account-deleted'),
  (empty($mandateDeleted) ? null : 'debit-mandate-deleted'),
]);

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
  <form id="sepa-debit-mandate-form" class="<?php p($formCss); ?>">
    <div class="ui-widget-overlay form-blocker hidden-no-data"></div>
    <input type="hidden" autofocus="autofocus" />
    <!-- @todo perhaps better use a JSON data-field for this mess -->
    <!-- <input type="hidden" name="mandateProjectId" value="<?php echo $mandateProjectId; ?>" /> -->
    <input type="hidden" name="mandateProjectName" value="<?php echo $mandateProjectName; ?>" />
    <input type="hidden" name="projectId" value="<?php echo $projectId; ?>" />
    <input type="hidden" name="projectName" value="<?php echo $projectName; ?>" />
    <input type="hidden" name="musicianId" value="<?php echo $musicianId; ?>" />
    <input type="hidden" name="musicianName" value="<?php echo $musicianName; ?>" />
    <input type="hidden" name="bankAccountSequence" value="<?php echo $bankAccountSequence; ?>" />
    <input type="hidden" name="mandateSequence" value="<?php echo $mandateSequence; ?>" />
    <input type="hidden" name="mandateReference" value="<?php echo $mandateReference; ?>" />
    <input type="hidden" name="mandateNonRecurring" value="<?php p((int)$mandateNonRecurring); ?>" />
    <input type="hidden" name="writtenMandateId" value="<?php echo $writtenMandateId; ?>" />
    <input type="hidden" name="memberProjectId" value="<?php echo $memberProjectId; ?>" />
    <fieldset class="<?php p($accountCss); ?>">
      <legend>
        <?php echo $l->t('Bank Account'); ?>
      </legend>
      <input class="bankAccount bankAccountOwner" type="text"
             id="bankAccountOwner"
             name="bankAccountOwner"
             required
             value="<?php echo $bankAccountOwner; ?>"
             title="<?php echo $l->t('owner of the bank account, probably same as musician'); ?>"
             data-autocomplete='<?php echo json_encode([$musicianName]); ?>'
             autocomplete="name"
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
               required
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
    <fieldset class="<?php p($mandateCss); ?>">
      <legend class="hidden-no-data">
        <span class="reference-label">
          <?php p($l->t('Mandate-Reference: %s', $mandateReference)); ?>
        </span>
      </legend>
      <input id="debit-mandate-registration"
             name="mandateRegistration"
             class="checkbox debit-mandate-registration hidden-have-data-unused"
             type="checkbox" />
      <label for="debit-mandate-registration" class="debit-mandate-registration block hidden-have-data-unused">
        <legend class="mandateCaption inline-block">
          <span class="registration-label">
            <?php p($l->t('New Debit-Mandate')); ?>
          </span>
      </label>
      <div></div>
      <div class="debit-mandate-details inline-block">
        <div class="ui-widget-overlay debit-mandate-blocker hidden-have-data"></div>

        <input type="checkbox"
               id="debit-mandate-binding-lock"
               class="checkbox hidden-no-data debit-mandate-binding-lock"
               <?php !empty($haveMandate) && p('checked'); ?>
        />

        <?php if (count($projectOptions) > 0) { ?>
          <span class="debit-mandate-binding hidden-have-data hidden-if-locked">
            <input id="sepa-debit-mandate-only-for-project"
                   class="only-for-project bankAccount projectMandate checkbox"
                   type="radio"
                   name="mandateBinding"
                   value="only-for-project"
                   <?php echo $mandateBinding == 'only-for-project' ? 'checked' : ''; ?>
            />
            <label for="sepa-debit-mandate-only-for-project"
                   title="<?php echo  $toolTips['sepa-debit-mandate-only-for-project']; ?>"
                   class="tooltip-right">
              <?php p($bindingText['only-for-project'][0]); ?>
            </label>
            <?php if (count($projectOptions) > 1) { ?>
              <select name="mandateProjectId"
                      class="mandateProjectId only-for-project selectize"
                      placeholder="<?php p($l->t('Select a Project')); ?>"
                      <?php ($mandateBinding == 'for-all-receivables') && p('disabled'); ?>
                      <?php ($mandateBinding == 'only-for-project') && p('required'); ?>
              >
                <option value=""></option>
                <?php echo PageNaviation::selectOptions($projectOptions, $mandateProjectId); ?>
              </select>
            <?php } else {
              $projectOption = reset($projectOptions); ?>
              <span class="debit-mandate-project">
                <input type="hidden"
                       class="mandateProjectId only-for-project"
                       name="mandateProjectId"
                       <?php ($mandateBinding == 'for-all-receivables') && p('disabled'); ?>
                       value="<?php p($projectOption['value']); ?>"
                />
                <?php p($projectOption['name']); ?>
              </span>
            <?php } ?>
          </span>
        <?php } ?>
        <span class="debit-mandate-binding hidden-have-data hidden-if-locked">
          <input type="hidden"
                 class="mandateProjectId for-all-receivables"
                 name="mandateProjectId"
                 value="<?php p($memberProjectId); ?>"
                 <?php ($mandateBinding == 'only-for-project') && p('disabled'); ?>
          />
          <input id="sepa-debit-mandate-for-all-receivables"
                 class="for-all-receivables bankAccount projectMandate checkbox"
                 type="radio"
                 name="mandateBinding"
                 value="for-all-receivables"
                 <?php ($mandateBinding == 'for-all-receivables') && p('checked'); ?>
          />
          <label for="sepa-debit-mandate-for-all-receivables"
                 title="<?php echo  $toolTips['sepa-debit-mandate-for-all-receivables']; ?>"
                 class="tooltip-right">
            <?php p($bindingText['for-all-receivables'][0]); ?>
          </label>
        </span>
        <span class="debit-mandate-binding hidden-no-data  hidden-if-unlocked">
          <span class="debit-mandate-binding label">
            <?php p($l->t('Project-binding:')); ?>
          </span>
          <span class="debit-mandate-binding value">
            <?php p($bindingText[$mandateBinding][1]); ?>
          </span>
        </span>

        <label for="debit-mandate-binding-lock" class="hidden-no-data debit-mandate-binding-lock"></label>

        <span id="debitRecurringInfo" class="debitRecurringInfo <?php echo $mandateSequenceType; ?>">
          <span class="label"><?php echo $l->t('Reusable:'); ?></span>
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
        <label class="lastUsedDate" for="lastUsedDate"><?php echo $l->t("Date of last usage:"); ?>
          <input class="lastUsedDate locked"
                 type="text"
                 id="lastUsedDate"
                 <?php echo $mandateNonRecurring ? 'disabled' : '' ?>
                 name="lastUsedDate"
                 value="<?php echo $lastUsedDate; ?>"
                 title="<?php echo $l->t('Date of last usage of debit-mandate'); ?>"
                 placeholder="<?php echo $l->t('last used date'); ?>"/>
        </label>
        <div class="written-mandate-upload block">
          <div class="operations inline-block">
            <input type="button" title="<?php echo $toolTips['sepa-bank-data-form:download-mandate-form']; ?>" class="operation download-mandate-form"/>
            <input type="button" title="<?php echo $toolTips['sepa-bank-data-form:upload-replace-written-mandate']; ?>" class="operation upload-replace"/>
          </div>
          <div class="file-data inline-block">
            <a class="download-link hidden-no-written-mandate" title="<?php echo $toolTips['sepa-bank-data-form:download-written-mandate']; ?>" href="<?php echo $writtenMandateDownloadLink; ?>"><?php p($writtenMandateFileName); ?></a>
            <input class="upload-placeholder no-validation hidden-have-written-mandate"
                   title="<?php echo $toolTips['sepa-bank-data-form:upload-written-mandate']; ?>"
                   placeholder="<?php echo $l->t('Upload filled SEPA debit mandate');  ?>"
                   type="text"
                   name="uploadPlaceholder"
                   value="<?php p($writtenMandateFileName); ?>"
                   <?php !empty($haveMandate) && p('required'); ?>
            />
            <input type="hidden" name="writtenMandateFileUpload" class="written-mandate-file-upload" value=""/>
          </div>
          <input id="upload-written-mandate-later"
                 class="upload-written-mandate-later bankAccount projectMandate checkbox inline-block hidden-have-written-mandate"
                 type="checkbox"
                 name="mandateUploadLater"
                 value="mandateUploadLater"
          />
          <label for="upload-written-mandate-later"
                 title="<?php echo $toolTips['sepa-bank-data-form:upload-written-mandate-later']; ?>"
                 class="tooltip-right inline-block hidden-have-written-mandate">
            <?php echo $l->t('upload later'); ?>
          </label>
        </div>
      </div>
    </fieldset>
  </form>
  <div class="statusmessage messagte"></div>
</div>
