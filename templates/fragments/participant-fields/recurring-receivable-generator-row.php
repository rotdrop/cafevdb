<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023 Claus-Justus Heine
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

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Service\Finance\IRecurringReceivablesGenerator as Generator;

/**
 * @param Entities\ProjectParticipantField $field
 * @param string $generatorSlug
 * @param string $recomputeLabel
 * @param array $updateStrategyChoices
 * @param OCA\CAFEVDB\Service\ToolTipsService $toolTips
 * @param string $toolTipsPrefix
 */

/** @var Entities\ProjectParticipantField $field */
$fieldId = $field->getId();
$fieldName = $field->getName();

foreach ($updateStrategyChoices as $tag) {
  $option = [
    'value' => $tag,
    'name' => $l->t($tag),
    'flags' => ($tag === Generator::UPDATE_STRATEGY_EXCEPTION ? PageNavigation::SELECTED : 0),
    'title' => $toolTips['participant-fields-recurring-data:update-strategy:' . $tag],
  ];
  $updateStrategyOptions[] = $option;
}
$updateStrategyOptions = PageNavigation::selectOptions($updateStrategyOptions);

?>

<tr class="generator"
    data-field-id="<?php p($fieldId); ?>"
    data-field-name="<?php p($fieldName); ?>"
>
  <td class="operations" colspan="4">
    <div class="flex-container">
      <input class="operation regenerate-all"
             title="<?php echo $toolTips['participant-fields-recurring-data:regenerate-all:' . $generatorSlug]; ?>"
             type="button"
             value="<?php p($recomputeLabel) ?>"
      />
      <label for="recurring-receivables-update-strategy-<?php p($fieldId); ?>"
             class="recurring-receivables-update-strategy update-strategy-count-<?php p(count($updateStrategyChoices)); ?>">
        <?php p($l->t('In case of Conflict')); ?>
        <select
          id="recurring-receivables-update-strategy-<?php p($fieldId); ?>"
          class="recurring-multiplicity-required recurring-receivables-update-strategy"
          name="recurringReceivablesUpdateStrategy[<?php p($fieldId); ?>]"
          title="<?php echo $toolTips['participant-fields-recurring-data:update-strategy']; ?>"
        >
          <?php echo $updateStrategyOptions ?>
        </select>
      </label>
      <input id="recurring-receivables-show-empty-options-<?php p($fieldId); ?>"
             class="show-empty-options"
             type="checkbox"
             name="recurringReceivablesShowEmptyOptions[<?php p($fieldId); ?>]"
      />
      <label for="recurring-receivables-show-empty-options-<?php p($fieldId); ?>"
             class="show-empty-options"
             title="<?php echo $toolTips[$toolTipsPrefix . ':show-empty-options']; ?>"
      >
        <?php p($l->t('show empty')); ?>
      </label>
    </div>
  </td>
</tr>
