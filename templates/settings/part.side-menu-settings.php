<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

?>
<div id="cafevdb-navigation-info"><?php echo $navBarInfo; ?></div>
<div id="app-settings-header"
     class="tooltip-<?php echo $toolTipPos; ?>"
     title="<?php echo $toolTips['settings-button']; ?>">
  <button class="settings-button" tabindex="0"><?php echo $l->t('Settings'); ?></button>
</div>
<div id="app-settings-content">
  <ul class="">
    <li>
      <input id="app-settings-tooltips"
             type="checkbox"
             name="tooltips" <?php echo $showToolTips == 'on' ? 'checked="checked"' : ''; ?>
             class="checkbox tooltips"
      />
      <label for="app-settings-tooltips"
             class="tooltip-<?php echo $toolTipPos; ?>"
             title="<?php echo $toolTips['show-tool-tips']; ?>">
        <?php echo $l->t('Tool-Tips'); ?>
      </label>
    </li>
    <li>
      <input id="app-settings-filtervisibility"
             type="checkbox"
             name="filtervisibility" <?php echo $filtervisibility == 'on' ? 'checked="checked"' : ''; ?>
             class="checkbox filtervisibility"
      />
      <label for="app-settings-filtervisibility"
             class="tooltip-<?php echo $toolTipPos; ?>"
             title="<?php echo $toolTips['filter-visibility']; ?>">
        <?php echo $l->t('Filter-Controls'); ?>
      </label>
    </li>
    <li>
      <input id="app-settings-directchange"
             type="checkbox"
             name="directchange" <?php echo $directchange == 'on' ? 'checked="checked"' : ''; ?>
             class="checkbox directchange"
      />
      <label for="app-settings-directchange"
             class="tooltip-<?php echo $toolTipPos; ?>"
             title="<?php echo $toolTips['direct-change']; ?>">
        <?php echo $l->t('Quick Change-Dialog'); ?>
      </label>
    </li>
    <li class="chosen-dropup">
      <select id="app-settings-table-pagerows"
	      name="pagerows"
              data-placeholder="<?php echo $l->t('#Rows'); ?>"
              class="table-pagerows pagerows chosen-dropup"
      >
        <?php
        foreach($pageRowsOptions as $value => $text) {
          $selected = $value == $pageRows ? ' selected="selected"' : '';
          echo '<option value="'.$value.'"'.$selected.'>'.$text.'</option>'."\n";
        }
        ?>
      </select>
      <label for="app-settings-table-pagerows"
             class="tooltip-<?php echo $toolTipPos; ?>"
             title="<?php echo $toolTips['table-rows-per-page']; ?>">
        <?php echo $l->t('#Rows/Page in Tables'); ?>
      </label>
    </li>
    <li>
      <input id="app-settings-expertmode"
             type="checkbox"
             name="expertmode" <?php echo $expertMode == 'on' ? 'checked="checked"' : ''; ?>
             class="checkbox expertmode"
      />
      <label for="app-settings-expertmode"
             class="tooltip-<?php echo $toolTipPos; ?>"
             title="<?php echo $toolTips['expert-mode']; ?>">
        <?php echo $l->t('Expert-Mode'); ?>
      </label>
    </li>
    <li class="<?php echo $expertClass; ?> expertmode-container">
      <input id="app-settings-showdisabled"
             type="checkbox"
             name="showdisabled" <?php echo $showdisabled == 'on' ? 'checked="checked"' : ''; ?>
             class="checkbox showdisabled"
      />
      <label for="app-settings-showdisabled"
             class="tooltip-<?php echo $toolTipPos; ?>"
             title="<?php echo $toolTips['show-disabled']; ?>">
        <?php echo $l->t('Show Disabled Data-Sets'); ?>
      </label>
    </li>
    <li class="<?php echo $expertClass; ?> expertmode-container chosen-dropup">
      <?php echo $this->inc('settings/part.debug-mode', [ 'toolTipsPos' => $toolTipPos ]); ?>
    </li>
    <li>
      <a id="app-settings-further-settings"
         class="settings generalsettings tooltip-<?php echo $toolTipPos; ?>"
         title="<?php echo $toolTips['further-settings']; ?>"
         href="#">
        <?php echo $l->t('Further Settings'); ?>
      </a>
    </li>
  </ul>
</div>
