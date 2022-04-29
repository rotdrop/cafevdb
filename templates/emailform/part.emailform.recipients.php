<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\EmailForm\RecipientsFilter;
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

$rowClass = $appName.'-'.'row';
$containerClass = $appName.'-'.'container';

$filterReadonly = $basicRecipientsSet[RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY] ? 'readonly' : '';

$announcementsMailingListTitle = !empty($announcementsMailingList)
 ? $toolTips['emailform:recipients:filter:basic-set:announcements-mailing-list']
 : htmlspecialchars($l->t('The global announcements mailing list is not configured or the mailing-list server is unreachable.'));

$projectMailingListTitle = !empty($projectMailingList)
 ? $toolTips['emailform:recipients:filter:basic-set:project-mailing-list']
 : htmlspecialchars($l->t('The project mailing list is not configured or the mailing-list server is unreachable.'));

function cgiName(string $key, ?string $subKey = null)
{
  return RecipientsFilter::POST_TAG . '[' . $key . ']' . ($subKey ? '[' . $subKey . ']' : '');
}

function basicSetName(string $key)
{
  return cgiName(RecipientsFilter::BASIC_RECIPIENTS_SET_KEY) . '[]';
}

function basicSetValue(string $key)
{
  return $key;
}

$recipientsSetFlags = array_keys(array_filter($basicRecipientsSet));

$recipientSetDescriptions = RecipientsFilter::getUserBaseDescriptions($l);

?>

<fieldset id="cafevdb-email-recipients-fieldset" class="email-recipients page">
  <?php echo PageNavigation::persistentCGI(RecipientsFilter::POST_TAG, $recipientsFormData); ?>
  <?php if ($projectId > 0 && $frozen) { ?>
    <input type="hidden"
           name="<?php echo basicSetName(RecipientsFilter::FROM_PROJECT_CONFIRMED_KEY); ?>"
           value="<?php echo basicSetValue(RecipientsFilter::FROM_PROJECT_CONFIRMED_KEY); ?>"
    />
    <input type="hidden"
           name="<?php echo basicSetName(RecipientsFilter::FROM_PROJECT_PRELIMINARY_KEY); ?>"
           value="<?php echo basicSetValue(RecipientsFilter::FROM_PROJECT_PRELIMINARY_KEY); ?>"
    />
  <?php } else { ?>
    <div class="cafevdb-email-form <?php p($rowClass); ?>">
      <span id="basic-recipient-set-wrapper" class="basic-recipients-set <?php p($containerClass); ?> outer left <?php p(implode(' ', $recipientsSetFlags)); ?>">
        <span class="label vmiddle">
          <label class="basic-recipients-set"
                 title="<?php echo $toolTips['emailform:recipients:filter:basic-set']; ?>">
            <?php echo $l->t('Basic Recipients Set'); ?>
          </label>
        </span>
        <span class="dropdown-container dropdown-no-hover">
          <button class="menu-title action-menu-toggle basic-recipients-set">...</button>
          <nav class="dropdown-content dropdown-align-left">
            <ul class="dropdown-time-list">
<?php if ($projectId > 0) { ?>
              <li class="dropdown-item tooltip-auto">
                <span class="basic-recipients-set from-project confirmed inner vmiddle <?php p($containerClass); ?>">
                  <input type="checkbox"
                         id="basic-recipients-set-from-project-confirmed"
                         class="basic-recipients-set from-project confirmed"
                         name="<?php echo basicSetName(RecipientsFilter::FROM_PROJECT_CONFIRMED_KEY); ?>"
                         value="<?php echo basicSetValue(RecipientsFilter::FROM_PROJECT_CONFIRMED_KEY); ?>"
                         <?php echo $basicRecipientsSet[RecipientsFilter::FROM_PROJECT_CONFIRMED_KEY] ? 'checked' : ''; ?>
                  />
                  <label for="basic-recipients-set-from-project-confirmed"
                         class="tip"
                         title="<?php echo $toolTips['emailform:recipients:filter:basic-set:from-project:confirmed']; ?>">
                    <?php echo $l->t('IS_PARTICIPANT_OF: &isin; %s (confirmed)', $projectName); ?>
                  </label>
                </span>
              </li>
              <li class="dropdown-item tooltip-auto">
                <span class="basic-recipients-set from-project preliminary inner vmiddle <?php p($containerClass); ?>">
                  <input type="checkbox"
                         id="basic-recipients-set-from-project-preliminary"
                         class="basic-recipients-set from-project prelminary"
                         name="<?php echo basicSetName(RecipientsFilter::FROM_PROJECT_PRELIMINARY_KEY); ?>"
                         value="<?php echo basicSetValue(RecipientsFilter::FROM_PROJECT_PRELIMINARY_KEY); ?>"
                         <?php echo $basicRecipientsSet[RecipientsFilter::FROM_PROJECT_PRELIMINARY_KEY] ? 'checked' : ''; ?>
                  />
                  <label for="basic-recipients-set-from-project-preliminary"
                         class="tip"
                         title="<?php echo $toolTips['emailform:recipients:filter:basic-set:from-project:preliminary']; ?>">
                    <?php echo $l->t('IS_PARTICIPANT_OF: &isin; %s (preliminary)', $projectName); ?>
                  </label>
                </span>
              </li>
              <li class="dropdown-item tooltip-auto">
                <span class="basic-recipients-set except-project inner vmiddle <?php p($containerClass); ?>">
                  <input type="checkbox"
                         id="basic-recipients-set-except-project"
                         class="basic-recipients-set except-project tip"
                         name="<?php echo basicSetName(RecipientsFilter::EXCEPT_PROJECT_KEY); ?>"
                         value="<?php echo basicSetValue(RecipientsFilter::EXCEPT_PROJECT_KEY); ?>"
                         <?php echo $basicRecipientsSet[RecipientsFilter::EXCEPT_PROJECT_KEY] ? 'checked' : ''; ?>
                  />
                  <label for="basic-recipients-set-except-project"
                         class="tip"
                         title="<?php echo $toolTips['emailform:recipients:filter:basic-set:except-project']; ?>">
                    <?php echo $l->t('IS_NON_PARTICIPANT_OF: &notin; %s', $projectName); ?>
                  </label>
                </span>
              </li>
              <li class="dropdown-item tooltip-auto">
                <span class="basic-recipients-set mailing-list project-mailing-list inner vmiddle <?php p($containerClass); ?>">
                  <input type="checkbox"
                         id="basic-recipients-set-project-mailing-list"
                         class="basic-recipients-set mailing-list project-mailing-list tip"
                         name="<?php echo basicSetName(RecipientsFilter::PROJECT_MAILING_LIST_KEY); ?>"
                         value="<?php echo basicSetValue(RecipientsFilter::PROJECT_MAILING_LIST_KEY); ?>"
                         <?php $basicRecipientsSet[RecipientsFilter::PROJECT_MAILING_LIST_KEY] && p('checked'); ?>
                         <?php empty($projectMailingList) && p('disabled'); ?>
                  />
                  <label for="basic-recipients-set-project-mailing-list"
                         class="tip"
                         title="<?php echo $projectMailingListTitle; ?>">
                    <?php p($l->t('Project Mailing List')); ?>
                  </label>
                </span>
              </li>
<?php } else { ?>
              <li class="dropdown-item tooltip-auto">
                <span class="basic-recipients-set mailing-list announcements-mailing-list inner vmiddle <?php p($containerClass); ?>">
                  <input type="radio"
                         id="basic-recipients-set-database"
                         class="basic-recipients-set database tip"
                         name="<?php echo basicSetName(RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY); ?>"
                         value="<?php echo basicSetValue(''); ?>"
                         <?php $basicRecipientsSet[RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY] || empty($announcementsMailingList) || p('checked'); ?>
                  />
                  <label for="basic-recipients-set-database"
                         class="tip"
                         title="<?php echo $toolTips['emailform:recipients:filter:basic-set:database']; ?>">
                    <?php p($l->t('Database')); ?>
                  </label>
                </span>
              </li>
                <!-- <span class="fill-word conjunction"><?php p($l->t('CONJUNCTION: or')); ?></span>  -->
<?php } ?>
              <li class="dropdown-item tooltip-auto">
                <span class="basic-recipients-set mailing-list announcements-mailing-list inner vmiddle <?php p($containerClass); ?>">
                  <input type="<?php p($projectId > 0 ? 'checkbox' : 'radio'); ?>"
                         id="basic-recipients-set-announcements-mailing-list"
                         class="basic-recipients-set mailing-list announcements-mailing-list tip"
                         name="<?php echo basicSetName(RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY); ?>"
                         value="<?php echo basicSetValue(RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY); ?>"
                         <?php $basicRecipientsSet[RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY] && p('checked'); ?>
                         <?php empty($announcementsMailingList) && p('disabled'); ?>
                  />
                  <label for="basic-recipients-set-announcements-mailing-list"
                         class="tip"
                         title="<?php echo $announcementsMailingListTitle; ?>">
                    <?php p($l->t('Announcements Mailing List')); ?>
                  </label>
                </span>
              </li>
            </ul>
          </nav>
        </span> <!-- dropdown container  -->
        <?php
        foreach ($recipientSetDescriptions as $description) {
          $text = $description['text'];
          $conditions = implode(' ', $description['conditions']);
        ?>
          <span class="basic-recipients-set brief-description <?php p($conditions); ?>"><?php p($text); ?></span>
        <?php } ?>
      </span>
    </div>
    <div class="spacer"></div>
  <?php } /* !($projectId > 0 && $frozen) */ ?>
  <div class="cafevdb-email-form <?php p($rowClass); ?> flex-container flex-justify-full flex-start">
    <span class="member-status-filter <?php p($containerClass); ?> left vmiddle tooltip-right"
          title="<?php echo $toolTips['emailform:recipients:filter:member-status']; ?>"
    >
      <span class="label top">
        <label for="member-status-filter"
               >
          <?php echo $l->t('Member-Status'); ?>
        </label>
      </span>
      <select id="member-status-filter"
              multiple="multiple"
              size="<?php echo count($memberStatusFilter); ?>"
              class="member-status-filter"
              data-placeholder="<?php echo $l->t('Select Members by Status'); ?>"
              name="emailRecipients[memberStatusFilter][]"
              <?php p($filterReadonly); ?>
      >
        <?php echo PageNavigation::selectOptions($memberStatusFilter); ?>
      </select>
    </span>
    <span class="instruments-filter <?php p($containerClass); ?> right vmiddle tooltip-left"
          title="<?php echo $toolTips['emailform:recipients:filter:instruments:filter']; ?>">
      <span class="label top">
        <label for="instruments-filter">
          <?php echo $l->t('Instruments Filter'); ?>
        </label>
      </span>
      <span id="instruments-filter-wrapper">
        <select id="instruments-filter"
                multiple="multiple"
                size="18"
                class="instruments-filter"
                data-placeholder="<?php echo $l->t('Select Instruments'); ?>"
                name="emailRecipients[instrumentsFilter][]"
                <?php p($filterReadonly); ?>
        >
          <?php echo $this->inc('emailform/part.instruments-filter', []); ?>
        </select>
      </span>
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
              title="<?php echo $toolTips['emailform:recipients:choices']; ?>"
              name="emailRecipients[selectedRecipients][]"
              <?php p($filterReadonly); ?>
      >
        <?php echo PageNavigation::selectOptions($emailRecipientsChoices); ?>
      </select>
    </span>
  </div>
  <div class="spacer">
    <div class="ruler"></div>
  </div>
  <div class="<?php p($rowClass); ?>">
    <span class="<?php p($containerClass); ?> right filter-controls">
      <input type="button"
             id="instruments-filter-apply"
             value="<?php echo $l->t('Apply Filter'); ?>"
             class="instruments-filter-controls apply"
             title="<?php echo $toolTips['emailform:recipients:filter:apply']; ?>"
             name="emailRecipients[applyInstrumentsFilter]"
             <?php p($filterReadonly); ?>
      />
      <input type="button"
             id="instruments-filter-undo"
             value="<?php echo $l->t('Undo Filter'); ?>"
             class="instruments-filter-controls undo"
             title="<?php echo $toolTips['emailform:recipients:filter:undo']; ?>"
             disabled
             name="emailRecipients[undoInstrumentsFilter]"
             <?php p($filterReadonly); ?>
      />
      <input type="button"
             id="instruments-filter-redo"
             value="<?php echo $l->t('Redo Filter'); ?>"
             class="instruments-filter-controls redo"
             title="<?php echo $toolTips['emailform:recipients:filter:redo']; ?>"
             disabled
             name="emailRecipients[redoInstrumentsFilter]"
             <?php p($filterReadonly); ?>
      />
      <input type="button"
             id="instruments-filter-reset"
             value="<?php echo $l->t('Reset Filter'); ?>"
             class="instruments-filter-controls reset"
             title="<?php echo $toolTips['emailform:recipients:filter:reset']; ?>"
             name="emailRecipients[resetInstrumentsFilter]"
             <?php p($filterReadonly); ?>
      />
    </span>
    <span class="<?php p($containerClass); ?> left missing-email-addresses tooltip-top"
          title="<?php echo $toolTips['emailform:recipients:broken-emails']; ?>">
      <span class="label top missing-email-addresses<?php echo $missingClass; ?>">
        <?php echo $missingText; ?>
      </span>
      <span class="label top missing-email-addresses empty<?php echo $noMissingClass; ?>">
        <?php echo $noMissingText; ?>
      </span>
      <span class="missing-email-addresses names">
        <?php echo $this->inc('emailform/part.broken-email-addresses', []); ?>
      </span>
    </span>
  </div>
  <div class="busy-indicator hidden"><?php echo $l->t('Reloading recipients from database, please wait ...') ?></div>
</fieldset>
