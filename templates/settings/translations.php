<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use \OCA\CAFEVDB\PageRenderer\Util\Navigation;

$phraseOptions = [];
foreach ($translations as $keyId => $data) {
    $translatedLocales = [];
    foreach ($data['translations'] as $locale => $translation) {
	$translatedLocales[] = $locale;
    }
    $translatedLocales = implode(',', $translatedLocales);
    $phraseOptions[] = [
	'value' => $keyId,
	'name'  => $data['key'],
	'title' => $translatedLocales,
        'data'  => [
            'translations' => $data['translations'],
        ],
    ];
}
?>
<div id="tabs-<?php echo $_['tabNr']; ?>" class="personalblock admin devel">
  <form id="translations">
    <fieldset id="<?php p($appName); ?>-translations-translate">
    <select
        id="<?php echo $appName; ?>-translation-phrases"
        data-placeholder="<?php echo $l->t("Select a phrase to translate"); ?>"
	class="translation-phrases"
        name="translation-phrases"
	title="<?php $toolTips['translation-phrases']; ?>" >
      <option data-translations="dummy"></option>
      <?php echo Navigation::selectOptions($phraseOptions); ?>
    </select>
    <select
        id="<?php echo $appName; ?>"-translation-locales"
        data-placeholder="<?php echo $l->t("Select a target locate (language)"); ?>"
        class="translation-locales"
        name="translation-locales"
        title="<?php $toolTips['translation-locales']; ?>" >
      <option></option>
      <?php echo Navigation::simpleSelectOptions($languages, $language); ?>
    </select>
    <input type="checkbox" id="<?php echo $appName ?>-hide-translated" name="hide-translated" class="checkbox"/>
    <label for="<?php echo $appName ?>-hide-translated" title="<?php echo $toolTips['hide-translated']; ?>" >
      <?php echo $l->t('hide translated');?>
    </label>
    <div id="<?php echo $appName; ?>-translation-key" class="translation-key">
    </div>
    <textarea id="<?php echo $appName; ?>-translation-translation" class="translation-translation">
    </textarea>
    </fieldset>
    <fieldset id="<?php p($appName); ?>-translations-download">
      <input id="<?php p($appName); ?>-translations-download-pot" type="button" name="download-translations-template" value="<?php p($l->t('Download Template (.pot)')); ?>"/>
      <!-- <input id="<?php p($appName); ?>-translations-download-translations" type="button" name="download-translations" value="<?php p($l->t('Download Translations (.po)')); ?>"/> -->
      <input id="<?php p($appName); ?>-translations-erase-all" type="button" name="erase-translations" value="<?php p($l->t('Delete Recorded Untranslated')); ?>"/>
    </fieldset>
    <div class="translation msg"></div>
  </form>
</div>
