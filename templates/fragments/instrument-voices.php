<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2021, 2022 Claus-Justus Heine
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
 */

/*
 * @param array $instruments
 * @param array $inputLabel
 * @param string $dataName
 * @param mixed $toolTips
 * @param string $toolTipSlug
 */

?>

<div class="instrument-voice request">
  <?php foreach ($instruments as $instrument) { ?>
  <div class="instrument-voice container request instrument-<?php p($instrument); ?> hidden">
    <label for="instrument-voice-request-<?php p($instrument); ?>"
           class="instrument-<?php p($instrument); ?> tooltip-auto"
           title="<?php p($toolTips[$toolTipSlug]); ?>">
      <?php p(call_user_func($inputLabel, $instrument)); ?>
      <input type="number"
             id="instrument-voice-request-<?php p($instrument); ?>"
             min="1"
             name="instrumentVoiceRequest[<?php p($instrument); ?>]"
             placeholder="<?php p($l->t('e.g. %s', 3)); ?>"
             data-instrument="<?php p($instrument); ?>"
             class="instrument-voice instrument-<?php p($instrument); ?> input"/>
    </label>
    <input type="button"
           name="instrumentVoiceRequestConfirm"
           data-instrument="<?php p($instrument); ?>"
           class="instrument-voice instrument-<?php p($instrument); ?> confirm"
           title="<?php p($toolTips[$toolTipSlug . ':confirm']); ?>"
           value="<?php p($l->t('ok')); ?>"/>
    <input type="hidden"
           name="<?php p($dataName); ?>"
           value=""
           class="instrument-voice instrument-<?php p($instrument); ?> data"
           data-instrument="<?php p($instrument); ?>"
           disabled/>
  </div>
  <?php } ?>
</div>
