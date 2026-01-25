<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020-2023, 2025, 2026 Claus-Justus Heine
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

// phpcs:disable PSR1.Files.SideEffects

/* The form expects the following data in $_[key] for key equal to:
 *
 * ProjectId:   project Id if in project mode, otherwise -1
 *
 * EmailRecipientsChoices, ParticipantStatusFilter, InstrumentsFilter
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

namespace OCA\CAFEVDB\LegacyTemplates\EmailForm\Recipients;

use OCA\CAFEVDB\EmailForm\RecipientsFilter;
use OCA\CAFEVDB\EmailForm\RecipientsFilterCgiKeys;
use OCA\CAFEVDB\EmailForm\RecipientsFilterCssClasses;
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

$recipientsSetFlags = array_keys(array_filter($basicRecipientsSet));

$recipientSetDescriptions = RecipientsFilter::getUserBaseDescriptions($l);

?>

<fieldset id="cafevdb-email-recipients-fieldset" class="email-recipients page">
  <?= PageNavigation::persistentCGI(RecipientsFilter::POST_TAG, $recipientsFormData) ?>
  <?php if ($projectId > 0 && $frozen) { ?>
    <input type="hidden"
           name="<?= RecipientsFilter::POST_TAG ?>[<?= RecipientsFilterCgiKeys::BASIC_RECIPIENTS_SET ?>][]"
           value="<?= RecipientsFilter::FROM_PROJECT_CONFIRMED_KEY ?>"
    />
    <input type="hidden"
           name="<?= RecipientsFilter::POST_TAG ?>[<?= RecipientsFilterCgiKeys::BASIC_RECIPIENTS_SET ?>][]"
           value="<?= RecipientsFilter::FROM_PROJECT_PRELIMINARY_KEY ?>"
    />
  <?php } else { ?>
    <div class="cafevdb-email-form <?php p($rowClass) ?>">
      <span id="basic-recipient-set-wrapper" class="basic-recipients-set <?php p($containerClass) ?> outer left <?php p(implode(' ', $recipientsSetFlags)) ?>">
        <span class="label vmiddle">
          <label class="basic-recipients-set"
                 title="<?= $toolTips['emailform:recipients:filter:basic-set'] ?>">
            <?= $l->t('Basic Recipients Set') ?>
          </label>
        </span>
        <span class="dropdown-container dropdown-no-hover">
          <button class="menu-title action-menu-toggle basic-recipients-set">...</button>
          <nav class="dropdown-content dropdown-align-left">
            <ul class="dropdown-time-list">
    <?php if ($projectId > 0) { ?>
              <li class="dropdown-item tooltip-auto">
                <span class="basic-recipients-set from-project confirmed inner vmiddle <?php p($containerClass) ?>">
                  <input type="checkbox"
                         id="basic-recipients-set-from-project-confirmed"
                         class="basic-recipients-set from-project confirmed"
                         name="<?= RecipientsFilter::POST_TAG ?>[<?= RecipientsFilterCgiKeys::BASIC_RECIPIENTS_SET ?>][]"
                         value="<?= RecipientsFilter::FROM_PROJECT_CONFIRMED_KEY ?>"
                         <?= $basicRecipientsSet[RecipientsFilter::FROM_PROJECT_CONFIRMED_KEY] ? 'checked' : '' ?>
                  />
                  <label for="basic-recipients-set-from-project-confirmed"
                         class="tip"
                         title="<?= $toolTips['emailform:recipients:filter:basic-set:from-project:confirmed'] ?>">
                    <?= $l->t('IS_PARTICIPANT_OF: &isin; %s (confirmed)', $projectName) ?>
                  </label>
                </span>
              </li>
              <li class="dropdown-item tooltip-auto">
                <span class="basic-recipients-set from-project preliminary inner vmiddle <?php p($containerClass) ?>">
                  <input type="checkbox"
                         id="basic-recipients-set-from-project-preliminary"
                         class="basic-recipients-set from-project prelminary"
                         name="<?= RecipientsFilter::POST_TAG ?>[<?= RecipientsFilterCgiKeys::BASIC_RECIPIENTS_SET ?>][]"
                         value="<?= RecipientsFilter::FROM_PROJECT_PRELIMINARY_KEY ?>"
                         <?= $basicRecipientsSet[RecipientsFilter::FROM_PROJECT_PRELIMINARY_KEY] ? 'checked' : '' ?>
                  />
                  <label for="basic-recipients-set-from-project-preliminary"
                         class="tip"
                         title="<?= $toolTips['emailform:recipients:filter:basic-set:from-project:preliminary'] ?>">
                    <?= $l->t('IS_PARTICIPANT_OF: &isin; %s (preliminary)', $projectName) ?>
                  </label>
                </span>
              </li>
              <li class="dropdown-item tooltip-auto">
                <span class="basic-recipients-set except-project inner vmiddle <?php p($containerClass) ?>">
                  <input type="checkbox"
                         id="basic-recipients-set-except-project"
                         class="basic-recipients-set except-project tip"
                         name="<?= RecipientsFilter::POST_TAG ?>[<?= RecipientsFilterCgiKeys::BASIC_RECIPIENTS_SET ?>][]"
                         value="<?= RecipientsFilter::EXCEPT_PROJECT_KEY ?>"
                         <?= $basicRecipientsSet[RecipientsFilter::EXCEPT_PROJECT_KEY] ? 'checked' : '' ?>
                  />
                  <label for="basic-recipients-set-except-project"
                         class="tip"
                         title="<?= $toolTips['emailform:recipients:filter:basic-set:except-project'] ?>">
                    <?= $l->t('IS_NON_PARTICIPANT_OF: &notin; %s', $projectName) ?>
                  </label>
                </span>
              </li>
              <li class="dropdown-item tooltip-auto">
                <span class="basic-recipients-set mailing-list project-mailing-list inner vmiddle <?php p($containerClass) ?>">
                  <input type="checkbox"
                         id="basic-recipients-set-project-mailing-list"
                         class="basic-recipients-set mailing-list project-mailing-list tip"
                         name="<?= RecipientsFilter::POST_TAG ?>[<?= RecipientsFilterCgiKeys::BASIC_RECIPIENTS_SET ?>][]"
                         value="<?= RecipientsFilter::PROJECT_MAILING_LIST_KEY ?>"
                         <?php $basicRecipientsSet[RecipientsFilter::PROJECT_MAILING_LIST_KEY] && !empty($announcementsMailingList) && p('checked') ?>
                         <?php empty($projectMailingList) && p('disabled') ?>
                  />
                  <label for="basic-recipients-set-project-mailing-list"
                         class="tip"
                         title="<?= $projectMailingListTitle ?>">
                    <?php p($l->t('Project Mailing List')) ?>
                  </label>
                </span>
              </li>
    <?php } else { ?>
              <li class="dropdown-item tooltip-auto">
                <span class="basic-recipients-set mailing-list announcements-mailing-list inner vmiddle <?php p($containerClass) ?>">
                  <input type="radio"
                         id="basic-recipients-set-database"
                         class="basic-recipients-set database tip"
                         name="<?= RecipientsFilter::POST_TAG ?>[<?= RecipientsFilterCgiKeys::BASIC_RECIPIENTS_SET ?>][]"
                         value="<?= '' ?>"
                         <?php ($basicRecipientsSet[RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY] || empty($announcementsMailingList)) && p('checked') ?>
                  />
                  <label for="basic-recipients-set-database"
                         class="tip"
                         title="<?= $toolTips['emailform:recipients:filter:basic-set:database'] ?>">
                    <?php p($l->t('Database')) ?>
                  </label>
                </span>
              </li>
                <!-- <span class="fill-word conjunction"><?php p($l->t('CONJUNCTION: or')) ?></span>  -->
    <?php } ?>
              <li class="dropdown-item tooltip-auto">
                <span class="basic-recipients-set mailing-list announcements-mailing-list inner vmiddle <?php p($containerClass) ?>">
                  <input type="<?php p($projectId > 0 ? 'checkbox' : 'radio') ?>"
                         id="basic-recipients-set-announcements-mailing-list"
                         class="basic-recipients-set mailing-list announcements-mailing-list tip"
                         name="<?= RecipientsFilter::POST_TAG ?>[<?= RecipientsFilterCgiKeys::BASIC_RECIPIENTS_SET ?>][]"
                         value="<?= RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY ?>"
                         <?php $basicRecipientsSet[RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY] && p('checked') ?>
                         <?php empty($announcementsMailingList) && p('disabled') ?>
                  />
                  <label for="basic-recipients-set-announcements-mailing-list"
                         class="tip"
                         title="<?= $announcementsMailingListTitle ?>">
                    <?php p($l->t('Announcements Mailing List')) ?>
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
          <span class="basic-recipients-set brief-description <?php p($conditions) ?>"><?php p($text) ?></span>
        <?php } ?>
      </span>
    </div>
    <div class="spacer"></div>
  <?php } /* !($projectId > 0 && $frozen) */ ?>
  <div class="cafevdb-email-form <?php p($rowClass) ?> flex-container flex-justify-full flex-start">
    <span class="participation-status-filter <?php p($containerClass) ?> left vmiddle tooltip-right"
          title="<?= $toolTips['emailform:recipients:filter:participation-status'] ?>"
    >
      <span class="label top">
        <label for="participation-status-filter"
               >
          <?= $l->t('Recipient-Status') ?>
        </label>
      </span>
      <select id="participation-status-filter"
              multiple="multiple"
              size="<?= count($participationStatusFilter) ?>"
              class="participation-status-filter"
              data-placeholder="<?= $l->t('Select Members by Status') ?>"
              name="<?= RecipientsFilter::POST_TAG ?>[<?= RecipientsFilterCgiKeys::PARTICIPATION_STATUS_FILTER ?>][]"
              <?php p($filterReadonly) ?>
      >
        <?= $this->inc('emailform/part.participation-status-filter', []) ?>
      </select>
    </span>
    <span class="instruments-filter <?php p($containerClass) ?> right vmiddle tooltip-left"
          title="<?= $toolTips['emailform:recipients:filter:instruments:filter'] ?>">
      <span class="label top">
        <label for="instruments-filter">
          <?= $l->t('Instruments Filter') ?>
        </label>
      </span>
      <span id="instruments-filter-wrapper">
        <select id="instruments-filter"
                multiple="multiple"
                size="18"
                class="instruments-filter"
                data-placeholder="<?= $l->t('Select Instruments') ?>"
                name="<?= RecipientsFilter::POST_TAG ?>[<?= RecipientsFilterCgiKeys::INSTRUMENTS_FILTER ?>][]"
                <?php p($filterReadonly) ?>
        >
          <?= $this->inc('emailform/part.instruments-filter', []) ?>
        </select>
      </span>
    </span>
  </div>
  <div class="spacer">
    <div class="ruler"></div>
  </div>
  <div class="cafevdb-email-form <?php p($rowClass) ?>">
    <span class="recipients-select <?php p($containerClass) ?> left">
      <span class="label top">
        <label for="recipients-select"><?=  $l->t('Email Recipients') ?></label>
      </span>
      <select id="recipients-select"
              multiple="multiple"
              size="18"
              title="<?= $toolTips['emailform:recipients:choices'] ?>"
              name="<?= RecipientsFilter::POST_TAG ?>[<?= RecipientsFilterCgiKeys::SELECTED_RECIPIENTS ?>][]"
              <?php p($filterReadonly) ?>
      >
        <?= PageNavigation::selectOptions($emailRecipientsChoices, initialIndent: 8) ?>
      </select>
    </span>
  </div>
  <div class="spacer">
    <div class="ruler"></div>
  </div>
  <div class="<?php p($rowClass) ?>">
    <span class="<?php p($containerClass) ?> right filter-controls">
      <input type="button"
             id="instruments-filter-apply"
             value="<?= $l->t('Apply Filter') ?>"
             class="instruments-filter-controls apply"
             title="<?= $toolTips['emailform:recipients:filter:apply'] ?>"
             name="<?= RecipientsFilter::POST_TAG ?>[<?= RecipientsFilterCgiKeys::APPLY_INSTRUMENTS_FILTER ?>]"
             <?php p($filterReadonly) ?>
      />
      <input type="button"
             id="instruments-filter-undo"
             value="<?= $l->t('Undo Filter') ?>"
             class="instruments-filter-controls undo"
             title="<?= $toolTips['emailform:recipients:filter:undo'] ?>"
             disabled
             name="<?= RecipientsFilter::POST_TAG ?>[<?= RecipientsFilterCgiKeys::UNDO_INSTRUMENTS_FILTER ?>]"
             <?php p($filterReadonly) ?>
      />
      <input type="button"
             id="instruments-filter-redo"
             value="<?= $l->t('Redo Filter') ?>"
             class="instruments-filter-controls redo"
             title="<?= $toolTips['emailform:recipients:filter:redo'] ?>"
             disabled
             name="<?= RecipientsFilter::POST_TAG ?>[<?= RecipientsFilterCgiKeys::REDO_INSTRUMENTS_FILTER ?>]"
             <?php p($filterReadonly) ?>
      />
      <input type="button"
             id="instruments-filter-reset"
             value="<?= $l->t('Reset Filter') ?>"
             class="instruments-filter-controls reset"
             title="<?= $toolTips['emailform:recipients:filter:reset'] ?>"
             name="<?= RecipientsFilter::POST_TAG ?>[<?= RecipientsFilterCgiKeys::RESET_INSTRUMENTS_FILTER ?>]"
             <?php p($filterReadonly) ?>
      />
    </span>
    <span class="<?php p($containerClass) ?> left missing-email-addresses tooltip-top"
          title="<?= $toolTips['emailform:recipients:broken-emails'] ?>">
      <span class="label top missing-email-addresses<?= $missingClass ?>">
        <?= $missingText ?>
      </span>
      <span class="label top missing-email-addresses empty<?= $noMissingClass ?>">
        <?= $noMissingText ?>
      </span>
      <span class="missing-email-addresses names">
        <?= $this->inc('emailform/part.broken-email-addresses', []) ?>
      </span>
    </span>
  </div>
  <div class="busy-indicator hidden"><?= $l->t('Reloading recipients from database, please wait ...') ?></div>
</fieldset>
