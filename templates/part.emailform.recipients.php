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

use CAFEVDB\Config;
use CAFEVDB\Navigation;
use CAFEVDB\L;

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

Config::init();

$projectId = $_['ProjectId'];
if ($projectId > 0) {
  $projectName = $_['ProjectName'];
} else {
  $projectName = '';
}

$noMissingClass = '';
$missingClass = '';
if (count($_['MissingEmailAddresses']) > 0) {
  $noMissingClass = ' reallyhidden';
} else {
  $missingClass = ' reallyhidden';
}
$noMissingText = $l->t('No Musician without or obviously broken email-address found :)');
$missingText = $l->t('Musicians without or obviously broken email-address');

$frozen = $_['FrozenRecipients'];

?>

<fieldset id="cafevdb-email-recipients-fieldset" class="email-recipients page">
  <?php echo Navigation::persistentCGI('emailRecipients', $_['RecipientsFormData']); ?>
  <?php if ($projectId >= 0) { ?>
    <?php if ($frozen) { ?>
      <input type="hidden"
             name="emailRecipients[BasicRecipientsSet][FromProject]"
             value="1"/>
    <?php } else { ?>
      <div class="cafevdb-email-form row">
        <span id="basic-recipient-set-wrapper" class="basic-recipients-set container outer left">
          <span class="label vmiddle">
            <label class="basic-recipients-set"
                   title="<?php echo Config::tooltips('email-recipients-basic-set'); ?>">
              <?php echo $l->t('Basic Recipients Set'); ?>
            </label>
          </span>
          <span class="basic-recipients-set from-project inner vmiddle container">
            <input type="checkbox"
                   id="basic-recipients-set-from-project"
                   class="basic-recipients-set from-project tip"
                   title="<?php echo Config::tooltips('email-recipients-from-project'); ?>"
                   name="emailRecipients[BasicRecipientsSet][FromProject]"
                   value="1"
            <?php echo $_['BasicRecipientsSet']['FromProject'] ? 'checked="checked"' : ''; ?>
                   />
            <span class="label right">
              <label for="basic-recipients-set-from-project"
                     class="tip"
                     title="<?php echo Config::tooltips('email-recipients-from-project'); ?>">
                <span class="basic-recipients-set from-project button">&isin; <?php echo $projectName; ?></span>
              </label>
            </span>
          </span>
          <span class="basic-recipients-set except-project inner vmiddle container">
            <input type="checkbox"
                   id="basic-recipients-set-except-project"
                   class="basic-recipients-set except-project tip"
                   title="<?php echo Config::tooltips('email-recipients-except-project'); ?>"
                   name="emailRecipients[BasicRecipientsSet][ExceptProject]"
                   value="1"
            <?php echo $_['BasicRecipientsSet']['ExceptProject'] ? 'checked="checked"' : ''; ?>
                   />
            <span class="label right">
              <label for="basic-recipients-set-except-project"
                     class="tip"
                     title="<?php echo Config::tooltips('email-recipients-except-project'); ?>">
                <span class="basic-recipients-set except-project button">&notin; <?php echo $projectName; ?></span>
              </label>
            </span>
          </span>
        </span>
      </div>
      <div class="spacer"></div>
    <?php } /* $projectId > = 0 */ ?>
  <?php } /* $projectId > = 0 */ ?>
  <div class="cafevdb-email-form row">
    <span class="member-status-filter container left vmiddle">
      <span class="label left">
        <label for="member-status-filter"
               title="<?php echo Config::tooltips('email-recipients-member-status-filter'); ?>"
               >
          <?php echo $l->t('Member-Status'); ?>
        </label>
      </span>
      <select id="member-status-filter"
              multiple="multiple"
              size="<?php echo count($_['MemberStatusFilter']); ?>"
              class="member-status-filter"
              title="<?php echo Config::tooltips('email-recipients-member-status-filter'); ?>"
              data-placeholder="<?php echo $l->t('Select Members by Status'); ?>"
              name="emailRecipients[MemberStatusFilter][]">
        <?php echo Navigation::selectOptions($_['MemberStatusFilter']); ?>
      </select>
    </span>
  </div>
  <div class="spacer">
    <div class="ruler"></div>
  </div>
  <div class="cafevdb-email-form row">
    <span class="recipients-select container left">
      <span class="label top">
        <label for="recipients-select"><?php echo  $l->t('Email Recipients'); ?></label>
      </span>
      <select id="recipients-select"
              multiple="multiple"
              size="18"
              title="<?php echo Config::tooltips('email-recipients-choices'); ?>"
              name="emailRecipients[SelectedRecipients][]">
        <?php echo Navigation::selectOptions($_['EmailRecipientsChoices']); ?>
      </select>
    </span>
    <span class="instruments-filter container right tooltip-top"
          title="<?php echo Config::tooltips('email-recipients-instruments-filter-container'); ?>">
      <span class="label top">
        <label for="instruments-filter"
               class="tooltip-off"
               title="<?php echo Config::tooltips('email-recipients-instruments-filter-label'); ?>">
          <?php echo $l->t('Instruments Filter'); ?>
        </label>
      </span>
      <span id="instruments-filter-wrapper">
        <select id="instruments-filter"
                multiple="multiple"
                size="18"
                class="instruments-filter"
                title="<?php echo Config::tooltips('email-recipients-instruments-filter'); ?>"
                data-placeholder="<?php echo $l->t('Select Instruments'); ?>"
                name="emailRecipients[InstrumentsFilter][]">
          <?php echo Navigation::selectOptions($_['InstrumentsFilter']); ?>
        </select>
      </span>
    </span>
  </div>
  <div class="spacer">
    <div class="ruler"></div>
  </div>
  <div class="row">
    <span class="container left missing-email-addresses tooltip-top"
          title="<?php echo Config::tooltips('email-recipients-broken-emails'); ?>">
      <span class="label top missing-email-addresses<?php echo $missingClass; ?>">
        <?php echo $missingText; ?>
      </span>
      <span class="label top missing-email-addresses empty<?php echo $noMissingClass; ?>">
        <?php echo $noMissingText; ?>
      </span>
      <span class="missing-email-addresses names">
        <?php
        $separator = '';
        foreach ($_['MissingEmailAddresses'] as $id => $name) {
          echo $separator; $separator = ', ';
          echo '<span class="missing-email-addresses personal-record" '.
               '      data-id="'.$id.'">'.$name.'</span>';
        }
        ?>
      </span>
    </span>
    <span class="container right filter-controls">
      <input type="button"
             id="instruments-filter-apply"
             value="<?php echo $l->t('Apply Filter'); ?>"
             class="instruments-filter-controls apply"
             title="<?php echo Config::tooltips('email-recipients-filter-apply'); ?>"
             name="emailRecipients[ApplyInstrumentsFilter]" />
      <input type="button"
             id="instruments-filter-undo"
             value="<?php echo $l->t('Undo Filter'); ?>"
             class="instruments-filter-controls undo"
             title="<?php echo Config::tooltips('email-recipients-filter-undo'); ?>"
             disabled="disabled"
             name="emailRecipients[UndoInstrumentsFilter]" />
      <input type="button"
             id="instruments-filter-redo"
             value="<?php echo $l->t('Redo Filter'); ?>"
             class="instruments-filter-controls redo"
             title="<?php echo Config::tooltips('email-recipients-filter-redo'); ?>"
             disabled="disabled"
             name="emailRecipients[RedoInstrumentsFilter]" />
      <input type="button"
             id="instruments-filter-reset"
             value="<?php echo $l->t('Reset Filter'); ?>"
             class="instruments-filter-controls reset"
             title="<?php echo Config::tooltips('email-recipients-filter-reset'); ?>"
             name="emailRecipients[ResetInstrumentsFilter]" />
    </span>
  </div>
</fieldset>
