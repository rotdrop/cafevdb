<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, 2023 Claus-Justus Heine
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

$css_pfx = $_['css-prefix'];

$uploadName = Controller\ImagesController::UPLOAD_NAME;

?>

          </div> <!-- page-body-inner -->
        </div> <!-- page-body -->
      </div> <!-- page-container -->
      <?php echo isset($_['footer']) ? $_['footer'] : ''; ?>
      <div class="debug" id="<?php echo $css_pfx; ?>-debug"></div>
      <div id="cafevdb-error-block" class="cafevdb-error"></div>
    </div> <!-- cafevdb-general -->
  </div> <!-- app-inner-content -->
</div> <!-- app-content -->
<div id="fullcalendar"></div>
<div id="dialog_holder" class="popup topleft hidden"></div>
<div id="appsettings_popup" class="personal-settings app-admin-settings popup bottomleft hidden"></div>
<!-- defeat auto-focus attempts -->
<form class="focusstealer"><input type="checkbox" id="focusstealer" class="focusstealer"/></form>

<!-- iframes to trigger proper download action in web browser -->
<iframe name="pmeformdownloadframe"
        class="pmeformdownloadframe"
        id="pmeformdownloadframe"
        style="display:none;"
        src="about:blank"></iframe>
<iframe name="pmeformdownloadframetwo"
        class="pmeformdownloadframe"
        id="pmeformdownloadframetwo"
        style="display:none;"
        src="about:blank"></iframe>

<!-- image file upload support with drag'n drop -->
<script id="imageUploadTemplate" type="text/template">
  <form id="{formId}" class="float hidden" enctype="multipart/form-data">
    <input type="hidden" name="ownerId" value="{ownerId}"/>
    <input type="hidden" name="imageId" value="{imageId}"/>
    <input type="hidden" name="joinTable" value="{joinTable}"/>
    <input type="hidden" name="requesttoken" value="{requestToken}"/>
    <input type="hidden" name="imageSize" value="{imageSize}"/>
    <input type="hidden"  class="max_upload" name="MAX_FILE_SIZE" value="<?php echo $uploadMaxFilesize; ?>"/>
    <input type="hidden" class="max_human_file_size max_upload_human" value="<?php echo $uploadMaxHumanFilesize; ?>""/>
    <input class="file_upload_start" type="file" accept="image/*" name="<?php echo $uploadName; ?>" />
  </form>
</script>

<!-- image crop form template -->
<script id="cropBoxTemplate" type="text/template">
  <form class="cropform coords"
        method="post"
        enctype="multipart/form-data"
        target="crop_target"
        action="<?php print_unescaped($urlGenerator->linkToRoute($appName.'.images.post', ['operation' => 'save'])); ?>">
    <input type="hidden" id="<?php p($appName); ?>-owner-id" name="ownerId" value="{ownerId}" />
    <input type="hidden" id="<?php p($appName); ?>-image-id" name="imageId" value="{imageId}" />
    <input type="hidden" id="<?php p($appName); ?>-join-table" name="joinTable" value="{joinTable}" />
    <input type="hidden" id="<?php p($appName); ?>-image-size" name="imageSize" value="{imageSize}" />
    <input type="hidden" id="<?php p($appName); ?>-tmp-key" name="tmpKey" value="{tmpKey}" />
    <input type="hidden" id="<?php p($appName); ?>-file-name" name="fileName" value="{fileName}" />
    <fieldset id="coords">
      <input type="hidden" id="x1" name="x1" value="" />
      <input type="hidden" id="y1" name="y1" value="" />
      <input type="hidden" id="x2" name="x2" value="" />
      <input type="hidden" id="y2" name="y2" value="" />
      <input type="hidden" id="w" name="w" value="" />
      <input type="hidden" id="h" name="h" value="" />
    </fieldset>
  </form>
</script>

<!-- generic file upload support with drag'n drop -->
<script id="fileUploadTemplate" type="text/template">
  <div class="file-upload-wrapper" id="{wrapperId}">
    <form class="float hidden {formClass}" enctype="multipart/form-data">
      <input class="file-upload-start" type="file" accept="{accept}" name="{uploadName}"/>
      <input type="hidden" name="uploadName" value="{uploadName}"/>
      <input type="hidden" name="data" value='{uploadData}' />
      <input type="hidden" name="requesttoken" value="{requestToken}"/>
      <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $_['uploadMaxFilesize']; ?>"/>
      <input type="hidden" class="max_human_file_size" value="<?php echo $_['uploadMaxHumanFilesize']; ?>"/>
      <input type="hidden" name="projectId" value="{projectId}" />
      <input type="hidden" name="musicianId" value="{musicianId}" />
    </form>
    <div class="uploadprogresswrapper">
      <div class="uploadprogressbar"></div>
    </div>
  </div>
</script>

<!-- cloud file-system operation chooser -->
<script id="cloudFileSystemOperations" type="text/template">
  <div>
    <div class="{widgetCssClass}-wrapper {widgetCssClass} {operations}">
      <div class="{widgetCssClass} {widgetCssClass}-file-list font-monospace">{files}</div>
      <div class="{widgetCssClass} {widgetCssClass}-controls">
        <?php foreach (['copy' => $l->t('copy'), 'move' => $l->t('move'), 'link' => $l->t('link')] as $mode => $modeName) { ?>
        <input id="{widgetCssClass}-<?php p($mode); ?>-control"
               type="radio"
               class="radio {widgetCssClass} {widgetCssClass}-input {<?php p($mode); ?>CssClass}"
               value="<?php p($mode); ?>"
               name="{widgetRadioName}"
               {<?php p($mode); ?>Disabled}
               {<?php p($mode); ?>Selected}
        />
        <label for="{widgetCssClass}-<?php p($mode); ?>-control"
               class="{widgetCssClass} {widgetCssClass}-label tooltip-auto"
               title="<?php echo $toolTips['cloud-file-system-operations:' . $mode]; ?>"
        >
          <?php p($modeName); ?>
        </label>
        <?php } ?>
      </div>
    </div>
  </div>
</script>

<!-- generic progress-wrapper -->
<script id="progressWrapperTemplate" type="text/template">
 <div class="progress-wrapper" id="{wrapperId}">
   <div class="caption">{caption}</div>
   <div class="progress">
     <span class="progressbar">
       <span class="label">{label}</span>
     </span>
   </div>
 </div>
</script>
<!-- musician address display, fed by a "flattened musican" object -->
<script id="musicianAddressViewTemplate" type="text/template">
  <div class="musician-address-view" data-id="{id}">
    <table>
      <thead>
        <tr class="personalPublicName musician-address-header">
          <th class="tag">
            <span class="tag-label"><?php p($l->t('Name')); ?>:</span>
          </th>
          <th class="data">
            <span class="value">{personalPublicName}</span>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr class="email">
          <td class="tag">
            <span class="tag-label"><?php p($l->t('Email')); ?>:</span>
          </td>
          <td class="data">
            <span class="value">{email}</span>
          </td>
        </tr>
        <tr class="phone">
          <td class="tag">
            <span class="tag-label"><?php p($l->t('Phone')); ?>:</span>
          </td>
          <td class="data">
            <span class="flex-container flex-justify-start">
              <span class="value">{mobilePhone}</span>
              <span class="separator">, </span>
              <span class="value">{fixedLinePhone}</span>
            </span>
          </td>
        </tr>
        <tr class="address">
          <td class="tag">
            <span class="tag-label"><?php p($l->t('Address')); ?>:</span>
          </td>
          <td class="data">
            <span class="flex-container flex-justify-start flex-baseline">
              <span class="value">{addressSupplement}</span>
              <span class="separator">, </span>
              <span class="value">{streetAndNumber}</span>
              <span class="separator">, </span>
              <span class="value">{postalCode} {city}</span>
              <span class="separator">, </span>
              <span class="value">{country}</span>
            </span>
          </td>
        </tr>
        <tr>
          <td class="tag">
            <span class="tag-label"><?php p($l->t('Propability')); ?>:</span>
          </td>
          <td class="data">
            <span class="value">{duplicatesPropability} ({reasons})</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</script>
</div>
