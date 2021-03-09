<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/* The form expects the following data in $_[key] for key equal to:
 *
 * ProjectId:   project Id if in project mode, otherwise -1
 *
 * EmailRecipientsChoices, MemberStatusFilter, InstrumentsFilter
 * array suitable to be fed int o Navigation::selectOptions()
 *
 * MissingEmailAdresses: flat array of musician without email, displayed
 * at the bottom below the recipient filters.
 *
 * FormData: array which will be dumped into hidden input elements vi
 * Navigation::persistenCGI(). Arguably, one could nowadays rather use
 * data-element and JSON in or to do this in a more compact way.
 *
 * If ProjectId > 0:
 * ProjectName: name of the project if in project mode
 * BasicRecipientSet: array(FromProject => 0/1, ExceptProject: 0/1)
 *
 */

namespace OCA\CAFEVDB;

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

$noMissingClass = '';
$missingClass = '';
if (count($missingEmailAddresses) > 0) {
  $noMissingClass = ' reallyhidden';
} else {
  $missingClass = ' reallyhidden';
}
$noMissingText = $l->t('No Musician without or obviously broken email-address found :)');
$missingText = $l->t('Musicians without or obviously broken email-address');

$frozen = $frozenRecipients;

?>

<fieldset id="cafevdb-email-recipients-fieldset" class="email-recipients page">
  <?php echo PageNavigation::persistentCGI('emailRecipients', $recipientsFormData); ?>
  <?php if ($projectId >= 0) { ?>
    <?php if ($frozen) { ?>
      <input type="hidden"
             name="emailRecipients[basicRecipientsSet][fromProject]"
             value="1"/>
    <?php } else { ?>
      <div class="cafevdb-email-form <?php p($rowClass); ?>">
        <span id="basic-recipient-set-wrapper" class="basic-recipients-set <?php p($containerClass); ?> outer left">
          <span class="label vmiddle">
            <label class="basic-recipients-set"
                   title="<?php echo $toolTips['email-recipients-basic-set']; ?>">
              <?php echo $l->t('Basic Recipients Set'); ?>
            </label>
          </span>
          <span class="basic-recipients-set from-project inner vmiddle <?php p($containerClass); ?>">
            <input type="checkbox"
                   id="basic-recipients-set-from-project"
                   class="basic-recipients-set from-project tip"
                   title="<?php echo $toolTips['email-recipients-from-project']; ?>"
                   name="emailRecipients[basicRecipientsSet][fromProject]"
                   value="1"
            <?php echo $basicRecipientsSet['fromProject'] ? 'checked="checked"' : ''; ?>
                   />
            <span class="label right">
              <label for="basic-recipients-set-from-project"
                     class="tip"
                     title="<?php echo $toolTips['email-recipients-from-project']; ?>">
                <span class="basic-recipients-set from-project button">&isin; <?php echo $projectName; ?></span>
              </label>
            </span>
          </span>
          <span class="basic-recipients-set except-project inner vmiddle <?php p($containerClass); ?>">
            <input type="checkbox"
                   id="basic-recipients-set-except-project"
                   class="basic-recipients-set except-project tip"
                   title="<?php echo $toolTips['email-recipients-except-project']; ?>"
                   name="emailRecipients[basicRecipientsSet][exceptProject]"
                   value="1"
            <?php echo $basicRecipientsSet['exceptProject'] ? 'checked="checked"' : ''; ?>
                   />
            <span class="label right">
              <label for="basic-recipients-set-except-project"
                     class="tip"
                     title="<?php echo $toolTips['email-recipients-except-project']; ?>">
                <span class="basic-recipients-set except-project button">&notin; <?php echo $projectName; ?></span>
              </label>
            </span>
          </span>
        </span>
      </div>
      <div class="spacer"></div>
    <?php } /* $projectId > = 0 */ ?>
  <?php } /* $projectId > = 0 */ ?>
  <div class="cafevdb-email-form <?php p($rowClass); ?>">
    <span class="member-status-filter <?php p($containerClass); ?> left vmiddle">
      <span class="label left">
        <label for="member-status-filter"
               title="<?php echo $toolTips['email-recipients-member-status-filter']; ?>"
               >
          <?php echo $l->t('Member-Status'); ?>
        </label>
      </span>
      <select id="member-status-filter"
              multiple="multiple"
              size="<?php echo count($memberStatusFilter); ?>"
              class="member-status-filter"
              title="<?php echo $toolTips['email-recipients-member-status-filter']; ?>"
              data-placeholder="<?php echo $l->t('Select Members by Status'); ?>"
              name="emailRecipients[memberStatusFilter][]">
        <?php echo PageNavigation::selectOptions($memberStatusFilter); ?>
      </select>
    </span>
  </div>
  <div class="spacer">
    <div class="ruler"></div>
  </div>
  <div class="cafevdb-email-form <?php p($rowClass); ?>">
    <span class="recipients-select <?php p($containerClass); ?> left">
      <span class="label top">
        <label for="recipients-select"><?php echo  $l->t('Email Recipients'); ?></label>
      </span>
      <select id="recipients-select"
              multiple="multiple"
              size="18"
              title="<?php echo $toolTips['email-recipients-choices']; ?>"
              name="emailRecipients[selectedRecipients][]">
        <?php echo PageNavigation::selectOptions($emailRecipientsChoices); ?>
      </select>
    </span>
    <span class="instruments-filter <?php p($containerClass); ?> right tooltip-top"
          title="<?php echo $toolTips['email-recipients-instruments-filter-container']; ?>">
      <span class="label top">
        <label for="instruments-filter"
               class="tooltip-off"
               title="<?php echo $toolTips['email-recipients-instruments-filter-label']; ?>">
          <?php echo $l->t('Instruments Filter'); ?>
        </label>
      </span>
      <span id="instruments-filter-wrapper">
        <select id="instruments-filter"
                multiple="multiple"
                size="18"
                class="instruments-filter"
                title="<?php echo $toolTips['email-recipients-instruments-filter']; ?>"
                data-placeholder="<?php echo $l->t('Select Instruments'); ?>"
                name="emailRecipients[instrumentsFilter][]">
          <?php echo PageNavigation::selectOptions($instrumentsFilter); ?>
        </select>
      </span>
    </span>
  </div>
  <div class="spacer">
    <div class="ruler"></div>
  </div>
  <div class="<?php p($rowClass); ?>">
    <span class="<?php p($containerClass); ?> left missing-email-addresses tooltip-top"
          title="<?php echo $toolTips['email-recipients-broken-emails']; ?>">
      <span class="label top missing-email-addresses<?php echo $missingClass; ?>">
        <?php echo $missingText; ?>
      </span>
      <span class="label top missing-email-addresses empty<?php echo $noMissingClass; ?>">
        <?php echo $noMissingText; ?>
      </span>
      <span class="missing-email-addresses names">
        <?php
        $separator = '';
        foreach ($missingEmailAddresses as $id => $name) {
          echo $separator; $separator = ', ';
          echo '<span class="missing-email-addresses personal-record" '.
               '      data-id="'.$id.'">'.$name.'</span>';
        }
        ?>
      </span>
    </span>
    <span class="<?php p($containerClass); ?> right filter-controls">
      <input type="button"
             id="instruments-filter-apply"
             value="<?php echo $l->t('Apply Filter'); ?>"
             class="instruments-filter-controls apply"
             title="<?php echo $toolTips['email-recipients-filter-apply']; ?>"
             name="emailRecipients[applyInstrumentsFilter]" />
      <input type="button"
             id="instruments-filter-undo"
             value="<?php echo $l->t('Undo Filter'); ?>"
             class="instruments-filter-controls undo"
             title="<?php echo $toolTips['email-recipients-filter-undo']; ?>"
             disabled="disabled"
             name="emailRecipients[undoInstrumentsFilter]" />
      <input type="button"
             id="instruments-filter-redo"
             value="<?php echo $l->t('Redo Filter'); ?>"
             class="instruments-filter-controls redo"
             title="<?php echo $toolTips['email-recipients-filter-redo']; ?>"
             disabled="disabled"
             name="emailRecipients[redoInstrumentsFilter]" />
      <input type="button"
             id="instruments-filter-reset"
             value="<?php echo $l->t('Reset Filter'); ?>"
             class="instruments-filter-controls reset"
             title="<?php echo $toolTips['email-recipients-filter-reset']; ?>"
             name="emailRecipients[resetInstrumentsFilter]" />
    </span>
  </div>
</fieldset>
