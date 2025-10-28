<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020-2023, 2025 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNaviation;

$title = $l->t("SEPA Bank Information for %s", $musicianName);

$mandateExpiredTip = $toolTips['sepa-bank-data-form:debit-mandate:expired'];

$mandateSequenceType = $mandateNonRecurring ? 'once' : 'permanent';

// do we have a bankAccount?
$haveAccount = (int)$bankAccountSequence > 0;

// do we have a mandate?
$haveMandate = (int)$mandateSequence > 0;

$isMembersProject = $projectId == $memberProjectId;

// compute current or default value for mandate binding
if ($haveMandate) {
  $mandateBinding = $mandateProjectId == $memberProjectId ? 'for-all-receivables' : 'only-for-project';
} else {
  $mandateBinding = $isClubMember ? 'for-all-receivables' : 'only-for-project';
}

$mandateImpossible = !$isClubMember && empty($projectOptions);

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

$mandateCss = implode(' ', array_filter([
  'debit-mandate',
  (empty($haveMandate) ? 'no-data' : 'have-data'),
  (empty($mandateInUse) ? 'unused' : 'used'),
  (empty($writtenMandateId) ? 'no-written-mandate' : 'have-written-mandate'),
  ($isClubMember ? 'club-member' : null),
  (empty($mandateDeleted) ? null : 'deleted'),
]));

$accountCss = implode(' ', array_filter([
  'bank-account',
  (empty($haveAccount) ? 'no-data' : 'have-data'),
  (empty($bankAccountInUse) ? 'unused' : 'used'),
  (empty($bankAccountDeleted) ? null : 'deleted'),
]));

$formCss = implode(' ', array_filter([
  'sepa-debit-mandate-form',
  'sepa-bank-data',
  (empty($bankAccountDeleted) ? null : 'bank-account-deleted'),
  (empty($mandateDeleted) ? null : 'debit-mandate-deleted'),
]));

function u(string $arg):int {
  return print $arg;
}

?>
<div id="sepa-debit-mandate-dialog"
     title="<?= $title;?>"
     data-participant-folder="<?php p($participantFolder); ?>"
     data-musician-name="<?php p($musicianName); ?>"
>
  <div style="display:none;"
       id="mandate-expired-notice"
       class="<?= ($mandateExpired ? 'active' : ''); ?> mandate-expired-notice tooltip-bottom"
       title="<?= $mandateExpiredTip; ?>">
    <div>
      <?= ($mandateExpired ? $l->t('expired') : ''); ?>
    </div>
  </div>
  <form id="sepa-debit-mandate-form" class="<?php p($formCss); ?>">
    <div class="ui-widget-overlay form-blocker hidden-no-data"></div>
    <input type="hidden" autofocus="autofocus" />
    <!-- @todo perhaps better use a JSON data-field for this mess -->
    <!-- <input type="hidden" name="mandateProjectId" value="<?= $mandateProjectId; ?>" /> -->
    <input type="hidden" name="mandateProjectName" value="<?= $mandateProjectName; ?>" />
    <input type="hidden" name="projectId" value="<?= $projectId; ?>" />
    <input type="hidden" name="projectName" value="<?= $projectName; ?>" />
    <input type="hidden" name="musicianId" value="<?= $musicianId; ?>" />
    <input type="hidden" name="musicianName" value="<?= $musicianName; ?>" />
    <input type="hidden" name="bankAccountSequence" value="<?= $bankAccountSequence; ?>" />
    <input type="hidden" name="mandateSequence" value="<?= $mandateSequence; ?>" />
    <input type="hidden" name="mandateReference" value="<?= $mandateReference; ?>" />
    <input type="hidden" name="mandateNonRecurring" value="<?= (string)(int)$mandateNonRecurring; ?>" />
    <input type="hidden" name="writtenMandateId" value="<?= $writtenMandateId; ?>" />
    <input type="hidden" name="memberProjectId" value="<?= $memberProjectId; ?>" />
    <fieldset class="<?php p($accountCss); ?>">
      <legend>
        <?= $l->t('Bank Account'); ?>
      </legend>
      <input class="bankAccount bankAccountOwner" type="text"
             id="bankAccountOwner"
             name="bankAccountOwner"
             required
             value="<?= $bankAccountOwner; ?>"
             title="<?= $l->t('owner of the bank account, probably same as musician'); ?>"
             data-autocomplete='<?= json_encode([$musicianName]); ?>'
             autocomplete="name"
             placeholder="<?= $l->t('owner of bank account'); ?>"/><br/>
      <div class="bank-account-identifier">
        <input class="bankAccount bankAccountBLZ" type="text"
               id="bankAccountBLZ"
               name="bankAccountBLZ"
               value="<?= $bankAccountBLZ; ?>"
               title="<?= $l->t('Optional BLZ of the musician\'s bank account'); ?>"
               placeholder="<?= $l->t('BLZ of bank account'); ?>"/>
        <input class="bankAccount bankAccountIBAN" type="text"
               id="bankAccountIBAN"
               name="bankAccountIBAN"
               required
               value="<?= $bankAccountIBAN; ?>"
               title="<?= $l->t('IBAN or number of the bank account. If this is a account number, then please first enter the BLZ'); ?>"
               placeholder="<?= $l->t('IBAN or no. of bank account'); ?>"/>
        <input class="bankAccount bankAccountBIC" type="text"
               id="bankAccountBIC"
               name="bankAccountBIC"
               value="<?= $bankAccountBIC; ?>"
               title="<?= $l->t('Optionally the BIC of the account; will be computed automatically if left blank.'); ?>"
               placeholder="<?= $l->t('BIC of bank account'); ?>"/>
      </div>
      <label class="sepa-validation-toggle"
             for="sepa-validation-toggle"
             title="<?= $toolTips['sepa-bank-data-form:instant-validation']; ?>">
        <?= $l->t('Instant IBAN Validation:'); ?>
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
      <legend class="debit-mandate-registration inline-block">
        <input id="debit-mandate-registration"
               name="mandateRegistration"
               class="checkbox debit-mandate-registration hidden-have-data-unused"
               type="checkbox"
               <?php $mandateImpossible && u('disabled'); ?>
        />
        <label for="debit-mandate-registration" class="debit-mandate-registration block hidden-have-data-unused">
          <?php if ($mandateImpossible) { ?>
          <span class="registration-label bold">
            <?php p($l->t('%s is not yet a club-member and did not yet participate in any project.', $musicianName)) ?>
          </span>
          <?php } else { ?>
          <span class="registration-label">
            <?php p($l->t('New Debit-Mandate')); ?>
          </span>
          <?php } ?>
        </label>
        <div></div>
        <div class="ui-widget-overlay debit-mandate-blocker hidden-have-data"></div>
      </legend>
      <fieldset class="<?php p($mandateCss); ?>">
        <legend class="mandateCaption hidden-no-data">
          <span class="reference-label">
            <?php p($l->t('Mandate-Reference: %s', $mandateReference)); ?>
          </span>
        </legend>
        <div class="debit-mandate-details inline-block">
          <input type="checkbox"
                 id="debit-mandate-binding-lock"
                 class="checkbox hidden-no-data debit-mandate-binding-lock"
                 <?php !empty($haveMandate) && u('checked'); ?>
          />

          <?php if (count($projectOptions) > 0) { ?>
          <span class="debit-mandate-binding hidden-have-data hidden-if-locked">
            <input id="sepa-debit-mandate-only-for-project"
                   class="only-for-project bankAccount projectMandate checkbox"
                   type="radio"
                   name="mandateBinding"
                   value="only-for-project"
                   <?php ($mandateBinding == 'only-for-project') && u('checked') ?>
            />
            <label for="sepa-debit-mandate-only-for-project"
                   title="<?= $toolTips['sepa-bank-data-form:debit-mandate:only-for-project']; ?>"
                   class="tooltip-right">
              <?php p($bindingText['only-for-project'][0]); ?>
            </label>
            <?php if (count($projectOptions) > 1) { ?>
            <select name="mandateProjectId"
                    class="mandateProjectId only-for-project selectize"
                    placeholder="<?php p($l->t('Select a Project')); ?>"
                    <?php ($mandateBinding == 'for-all-receivables') && u('disabled'); ?>
                    <?php ($mandateBinding == 'only-for-project') && u('required'); ?>
            >
              <option value=""></option>
              <?= PageNaviation::selectOptions($projectOptions, $mandateProjectId); ?>
            </select>
            <?php } else {
              $projectOption = reset($projectOptions); ?>
            <span class="debit-mandate-project">
              <input type="hidden"
                     class="mandateProjectId only-for-project"
                     name="mandateProjectId"
                     <?php ($mandateBinding == 'for-all-receivables') && u('disabled'); ?>
                     value="<?php p($projectOption['value']); ?>"
              />
              <?php p($projectOption['name']); ?>
            </span>
            <?php } ?>
          </span>
          <?php } else { ?>
          <input type="hidden"
                 class="mandateProjectId only-for-project"
                 name="mandateProjectId"
                 <?php ($mandateBinding == 'for-all-receivables') && u('disabled'); ?>
                 value="0"
          />
          <?php } ?>
          <span class="debit-mandate-binding hidden-have-data hidden-if-locked">
            <input type="hidden"
                   class="mandateProjectId for-all-receivables"
                   name="mandateProjectId"
                   value="<?php p($memberProjectId); ?>"
                   <?php ($mandateBinding == 'only-for-project') && u('disabled'); ?>
            />
            <input id="sepa-debit-mandate-for-all-receivables"
                   class="for-all-receivables bankAccount projectMandate checkbox"
                   type="radio"
                   name="mandateBinding"
                   value="for-all-receivables"
                   <?php !$isClubMember && u('data-no-club-member="1"'); ?>
                   <?php !$isClubMember && u('disabled'); ?>
                   <?php ($isClubMember && $mandateBinding == 'for-all-receivables') && u('checked'); ?>
            />
            <label for="sepa-debit-mandate-for-all-receivables"
                   title="<?= $toolTips['sepa-bank-data-form:debit-mandate:for-all-receivables']; ?>"
                   class="tooltip-right">
              <?php p($bindingText['for-all-receivables'][0]); ?>
            </label>
          </span>
          <span class="debit-mandate-binding hidden-no-data hidden-if-unlocked">
            <span class="debit-mandate-binding label">
              <?php p($l->t('Project-binding:')); ?>
            </span>
            <span class="debit-mandate-binding value">
              <?php p($bindingText[$mandateBinding][1]); ?>
            </span>
          </span>

          <label for="debit-mandate-binding-lock" class="hidden-no-data debit-mandate-binding-lock"></label>

          <span id="debitRecurringInfo" class="debitRecurringInfo <?= $mandateSequenceType; ?>">
            <span class="label"><?= $l->t('Reusable:'); ?></span>
            <span class="type once"><?= $l->t('once'); ?></span>
            <span class="type permanent"><?= $l->t('permanent'); ?></span>
          </span>
          <br/>
          <label class="mandateDate" for="mandateDate"><?= $l->t("Date issued:"); ?>
            <input class="mandateDate tooltip-top" type="text"
                   id="mandateDate"
                   name="mandateDate"
                   required="required"
                   value="<?= $dateTimeFormatter->formatDate($mandateDate, 'medium'); ?>"
                   title="<?= $l->t('Date of mandate grant'); ?>"
                   placeholder="<?= $l->t('mandate date'); ?>"/>
          </label>
          <label class="lastUsedDate" for="lastUsedDate"><?= $l->t("Date of last usage:"); ?>
            <input class="lastUsedDate locked"
                   type="text"
                   id="lastUsedDate"
                   <?= $mandateNonRecurring ? 'disabled' : '' ?>
                   name="mandateLastUsedDate"
                   value="<?= (empty($mandateLastUsedDate) ?  '' : $dateTimeFormatter->formatDate($mandateLastUsedDate, 'medium')); ?>"
                   title="<?= $l->t('Date of last usage of debit-mandate'); ?>"
                   placeholder="<?= $l->t('last used date'); ?>"/>
          </label>
          <div class="written-mandate-upload block">
            <div class="operations inline-block">
              <input type="button" title="<?= $toolTips['sepa-bank-data-form:debit-mandate:download:form']; ?>" class="operation download-mandate-form"/>
              <input type="button"
                     title="<?= $toolTips['sepa-bank-data-form:debit-mandate:upload:from-client']; ?>"
                     class="operation upload-button upload-from-client"
              />
              <input type="button"
                     title="<?= $toolTips['sepa-bank-data-form:debit-mandate:upload:from-cloud']; ?>"
                     class="operation upload-button upload-from-cloud"
              />
            </div>
            <div class="file-data inline-block">
              <a class="download-link hidden-no-written-mandate"
                 title="<?= $toolTips['sepa-bank-data-form:debit-mandate:download']; ?>"
                 href="<?= $writtenMandateDownloadLink; ?>"><?php p($writtenMandateFileName); ?>
              </a>
              <input class="upload-placeholder no-validation hidden-have-written-mandate"
                     title="<?= $toolTips['sepa-bank-data-form:upload:from-client']; ?>"
                     placeholder="<?= $l->t('Upload filled SEPA debit mandate');  ?>"
                     type="text"
                     name="uploadPlaceholder"
                     value="<?php p($writtenMandateFileName); ?>"
                     <?php !empty($haveMandate) && u('required'); ?>
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
                   title="<?= $toolTips['sepa-bank-data-form:debit-mandate:upload:later']; ?>"
                   class="tooltip-right inline-block hidden-have-written-mandate">
              <?= $l->t('upload later'); ?>
            </label>
          </div>
        </div>
      </fieldset>
    </fieldset>
  </form>
  <div class="statusmessage messagte"></div>
</div>
