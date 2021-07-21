<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
    <input type="hidden" name="requesttoken" value="<?php echo $csrfToken; ?>"/>
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
      <input type="hidden" name="requesttoken" value="<?php echo $csrfToken; ?>"/>
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
