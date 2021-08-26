<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\PageRenderer\Util\Navigation;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Common\Util;

$probablyOffline = empty($webPageCategories);
if ($probablyOffline) {
  $hideOffline = ' hidden';
  $hideOnline = '';
} else {
  $hideOffline = '';
  $hideOnline = ' hidden';
}

$categoryOptions = [];
foreach ($webPageCategories as $category) {
  $name = $category['name'];
  $indent = '';
  for ($i = 0; $i < $category['level']*3; ++$i) {
    $indent .= '&nbsp;';
  }
  $name = $indent.$category['name'].' ['.$category['id'].']';
  $categoryOptions[] = [
    'value' => $category['id'],
    'name' => $name,
  ];
}

$moduleOptions = [];
foreach ($webPageModules as $module) {
  $name = $module['name'];
  $moduleOptions[] = [
    'value' => $module['id'],
    'name' => $module['name'],
  ];
}

$templateOptions = [];
foreach ($webPageTemplates as $template) {
  $templateOptions[] = [
    'value' => $template['id'],
    'name' => $template['name'],
  ];
}

?>
<div id="tabs-<?php echo $_['tabNr']; ?>" class="personalblock admin cms">
  <form id="cmssettings">
    <fieldset id="redaxocategories"><legend><?php echo $l->t('Redaxo Categories');?></legend>
      <?php
      foreach (ConfigService::CMS_CATEGORIES as $categorySlug) {
        $class = 'redaxo';
        $ucSlug = ucfirst($categorySlug);
        $id = $class.$ucSlug;
        $name = $id;
        $title = $class.'-'.$categorySlug;
      ?>
        <div class="<?php p($class); ?> textfield <?php p($hideOnline); ?>">
          <input type="text"
                 class="<?php p($class); ?>"
                 id="<?php p($id); ?>-textfield"
                 name="<?php p($name); ?>"
                 placeholder="<?php p($l->t($categorySlug)); ?>"
                 value="<?php p($_[$name]); ?>"
                 title="<?php p($toolTips[$title]); ?>" />
          <label for="<?php p($id); ?>-textfield"><?php p($l->t('Id of Redaxo %s Category', [$ucSlug])); ?></label>
        </div>
        <div class="<?php p($class); ?> select  <?php p($hideOffline); ?>">
          <select class="<?php p($class); ?>"
                  id="<?php p($id); ?>-select"
                  name="<?php p($name); ?>"
                  title="<?php p($toolTips[$title]); ?>">
            <?php echo Navigation::selectOptions($categoryOptions, [ $_[$name] ]); ?>
          </select>
          <label for="<?php p($id); ?>-select"><?php p($l->t('Id of Redaxo %s Category', [$ucSlug])); ?></label>
        </div>
      <?php } ?>
    </fieldset>
    <fieldset id="redaxotemplates"><legend><?php echo $l->t('Redaxo Templates');?></legend>
      <?php
      foreach (ConfigService::CMS_TEMPLATES as $templateSlug) {
        $class = 'redaxo';
        $ucSlug = Util::dashesToCamelCase($templateSlug, true);
        $id = $class.$ucSlug.'Template';
        $name = $id;
        $title = $class.'-'.$templateSlug;
      ?>
        <div class="<?php p($class); ?> textfield <?php p($hideOnline); ?>">
          <input type="text"
                 class="<?php p($class); ?>"
                 id="<?php p($id); ?>-textfield"
                 name="<?php p($name); ?>"
                 placeholder="<?php p($l->t($templateSlug)); ?>"
                 value="<?php p($_[$name]); ?>"
                 title="<?php p($toolTips[$title]); ?>" />
          <label for="<?php p($id); ?>-textfield"><?php p($l->t('Id of Redaxo %s Template', [$ucSlug])); ?></label>
        </div>
        <div class="<?php p($class); ?> select  <?php p($hideOffline); ?>">
          <select class="<?php p($class); ?>"
                  id="<?php p($id); ?>-select"
                  name="<?php p($name); ?>"
                  title="<?php p($toolTips[$title]); ?>">
            <?php echo Navigation::selectOptions($templateOptions, [ $_[$name] ]); ?>
          </select>
          <label for="<?php p($id); ?>-select"><?php p($l->t('Id of Redaxo %s Template', [$ucSlug])); ?></label>
        </div>
      <?php } ?>
    </fieldset>
    <fieldset id="redaxomodules"><legend><?php echo $l->t('Redaxo Modules');?></legend>
      <?php
      foreach (ConfigService::CMS_MODULES as $moduleSlug) {
        $class = 'redaxo';
        $ucSlug = ucfirst($moduleSlug);
        $id = $class.$ucSlug.'Module';
        $name = $id;
        $title = $class.'-'.$moduleSlug;
      ?>
        <div class="<?php p($class); ?> textfield <?php p($hideOnline); ?>">
          <input type="text"
                 class="<?php p($class); ?>"
                 id="<?php p($id); ?>-textfield"
                 name="<?php p($name); ?>"
                 placeholder="<?php p($l->t($moduleSlug)); ?>"
                 value="<?php p($_[$name]); ?>"
                 title="<?php p($toolTips[$title]); ?>" />
          <label for="<?php p($id); ?>-textfield"><?php p($l->t('Id of Redaxo %s Module', [$ucSlug])); ?></label>
        </div>
        <div class="<?php p($class); ?> select  <?php p($hideOffline); ?>">
          <select class="<?php p($class); ?>"
                  id="<?php p($id); ?>-select"
                  name="<?php p($name); ?>"
                  title="<?php p($toolTips[$title]); ?>">
            <?php echo Navigation::selectOptions($moduleOptions, [ $_[$name] ]); ?>
          </select>
          <label for="<?php p($id); ?>-select"><?php p($l->t('Id of Redaxo %s Module', [$ucSlug])); ?></label>
        </div>
      <?php } ?>
    </fieldset>
    <span class="statusmessage" id="msg"></span>
  </form>
</div>
