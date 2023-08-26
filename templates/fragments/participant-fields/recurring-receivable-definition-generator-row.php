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

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Service\Finance\IRecurringReceivablesGenerator as Generator;

/**
 * @param int $fieldId
 * @param null|array|Entities\ProjectParticipantFieldDataOption $generatorItem
 *     Special data item with key Uuid::NIL which holds
 *     the data for auto-generated fields.
 * @param array $generators
 * @param int $numberOfOptions
 * @param string $inputName
 * @param OCA\CAFEVDB\Service\ToolTipsService $toolTips
 * @param \OCP\IDateTimeFormatter $dateTimeFormatter
 */

foreach (['key', 'data', 'limit', 'deposit', 'label', 'tooltip'] as $prop) {
  $value = ($generatorItem[$prop] ?? '');
  if (empty($value) && $prop == 'key') {
    $value = Uuid::NIL;
  }
  if (empty($value) && $prop == 'label') {
    $value = Generator::GENERATOR_LABEL;
  }
  if ($prop == 'limit') {
    // $value is stored as Unix time-stamp, convert it to locale date.
    $value = $dateTimeFormatter->formatDate($value, 'medium');
  }
  $generatorItem[$prop] = $value;
}
unset($prop);
$generator = $generatorItem['data'];

$availableUpdateStrategies = Generator::UPDATE_STRATEGIES;
$generatorSlug = '';
if (!empty($generator)) {
  $availableUpdateStrategies = $generator::updateStrategyChoices();
  $generatorSlug = $generator::slug();
}
$updateStrategies = [ [ 'value' => '', 'name' => '', 'class' => 'hidden', ], ];
foreach ($availableUpdateStrategies as $tag) {
  $flags = 0;
  if ($tag == Generator::UPDATE_STRATEGY_EXCEPTION) {
    $flags |= PageNavigation::SELECTED;
  }
  if (array_search($tag, $availableUpdateStrategies) === false) {
    $flags |= PageNavigation::DISABLED;
    $flags &= ~PageNavigation::SELECTED; // don't select disabled options
  } elseif (count($availableUpdateStrategies) == 1) {
    $flags |= PageNavigation::SELECTED; // select the only available option.
  }
  $option = [
    'value' => $tag,
    'name' => $l->t($tag),
    'flags' => $flags,
    'title' => $this->toolTipsService['participant-fields-recurring-data:update-strategy:'.$tag],
  ];
  $updateStrategies[] = $option;
}
$updateStrategies = PageNavigation::selectOptions($updateStrategies);

$cssClass = implode(' ', [
  'data-line',
  'data-options',
  'generator',
  'active',
  'default-hidden',
  'not-multiplicity-recurring-hidden',
  'update-strategy-count-' . count($availableUpdateStrategies),
]);

?>
<tr class="data-line data-options placeholder active multiplicity-recurring-hidden"
    data-field-id="<?php p($fieldId) ?>"
    data-index="<?php p($numberOfOptions) ?>"
>
  <td class="placeholder" colspan="6">
    <input
      class="field-label"
      spellcheck="true"
      type="text"
      name="<?= $inputName ?>[-1][label]"
      value=""
      title="<?php echo $toolTips['participant-fields-data-options:placeholder'] ?>"
      placeholder="<?php p($l->t('new option')) ?>"
      size="33"
      maxlength="32"
    />
    <?php foreach (['key', 'data', 'deposit', 'limit', 'tooltip'] as $prop) { ?>
      <input
        class="field-<?= $prop ?>"
        type="hidden"
        name="<?= $inputName ?>[-1][<?= $prop ?>]"
        value=""
      />
    <?php } ?>
  </td>
</tr>
<tr
  class="<?= $cssClass ?>"
  data-generator-slug="<?= $generatorSlug ?>"
  data-generators='<?= json_encode(array_merge(array_map([ $l, 't' ], array_keys($generators)), array_values($generators))) ?>'
  data-field-id="<?= $fieldId ?>"
  data-available-update-strategies='<?= json_encode($availableUpdateStrategies) ?>'
>
  <td class="operations">
    <input
      class="operation regenerate-all"
      title="<?= $toolTips['participant-fields-recurring-data:regenerate-all:everybody'] ?>"
      type="button"
      <?php (empty($generator) || empty($fieldId)) && p('disabled') ?>
    />
    <input
      class="operation generator-run"
      title="<?= $toolTips['participant-fields-recurring-data:generator-run'] ?>"
      type="button"
      <?php (empty($generator) || empty($fieldId)) && p('disabled') ?>
    />
  </td>
  <td class="generator" colspan="5">
    <label for="recurring-receivables-update-strategy" class="recurring-receivables-update-strategy">
      <?php p($l->t('In case of Conflict')) ?>
    </label>
    <select
      id="recurring-receivables-update-strategy"
      required
      data-default-value="<?= Generator::UPDATE_STRATEGY_EXCEPTION ?>"
      class="recurring-multiplicity-required recurring-receivables-update-strategy"
      name="recurringReceivablesUpdateStrategy"
      title="<?= $toolTips['participant-fields-recurring-data:update-strategy'] ?>"
    >
      <?= $updateStrategies ?>
    </select>
    <input
      class="field-data recurring-multiplicity-required"
      spellcheck="true"
      type="text"
      name="<?= $inputName ?>[-1][data]"
      value="<?= $generator ?>"
      title="<?= $toolTips['participant-fields-recurring-data:generator'] ?>"
      placeholder="<?php p($l->t('field generator')) ?>"
      size="33"
      maxlength="1024"
      <?php !empty($generator) && p('readonly="readonly"') ?>
    />
    <input
      class="field-limit"
      type="text"
      name="<?= $inputName ?>[-1][limit]"
      value="<?= $generatorItem['limit'] ?>"
      title="<?= $toolTips['participant-fields-recurring-data:generator-startdate'] ?>"
      placeholder="<?php p($l->t('start date')) ?>"
      size="10"
      maxlength="10"
    />
    <?php foreach (['key', 'deposit', 'label', 'tooltip'] as $prop) { ?>
    <input
      class="field-<?= $prop ?>'"
      type="hidden"
      name="<?= $inputName ?>[-1][<?= $prop ?>]"
      value="<?= $generatorItem[$prop] ?>"
    />
    <?php } ?>
  </td>
</tr>
