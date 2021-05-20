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

namespace OCA\CAFEVDB;

use \OCA\CAFEVDB\PageRenderer\Util\Navigation;

$off = $orchestra == '' ? 'disabled' : '';
$countries = [];
foreach ($localeCountryNames as $country => $name) {
  $option = ['name' => $name, 'value' => $country];
  if ($country === $streetAddressCountry) {
    $option['flags'] = Navigation::SELECTED;
  }
  $countries[] = $option;
}

?>
<div id="tabs-<?php echo $_['tabNr']; ?>" class="personalblock admin">
  <form id="orchestra" class="orchestra">
    <h4><?php echo $l->t('Street Address'); ?></h4>
    <fieldset <?php echo $off; ?> >
      <!-- <legend><?php echo $l->t('Street Address'); ?></legend> -->
      <input class="streetAddressName" type="text"
             id="streetAddressName01"
             name="streetAddressName01"
             value="<?php echo $_['streetAddressName01']; ?>"
             title="<?php echo $l->t('The name of the orchestra'); ?>"
             placeholder="<?php echo $l->t('name of orchestra'); ?>"/><br/>
      <input class="streetAddressName" type="text"
             id="streetAddressName02"
             name="streetAddressName02"
             value="<?php echo $_['streetAddressName02']; ?>"
             title="<?php echo $l->t('The name of the orchestra (line 2)'); ?>"
             placeholder="<?php echo $l->t('name of orchestra'); ?>"><br/>
      <input class="streetAddressStreet" type="text"
             id="streetAddressStreet"
             name="streetAddressStreet"
             value="<?php echo $_['streetAddressStreet']; ?>"
             title="<?php echo $l->t('street part of street address of orchestra'); ?>"
             placeholder="<?php echo $l->t('street part of street address of orchestra'); ?>">
      <input class="streetAddressHouseNumber" type="text"
             id="streetAddressHouseNumber"
             name="streetAddressHouseNumber"
             value="<?php echo $_['streetAddressHouseNumber']; ?>"
             title="<?php echo $l->t('house number part of street address of orchestra'); ?>"
             placeholder="<?php echo $l->t('Nr'); ?>"><br/>
      <input class="streetAddressZIP" type="text"
             id="streetAddressZIP"
             name="streetAddressZIP"
             value="<?php echo $_['streetAddressZIP']; ?>"
             title="<?php echo $l->t('ZIP part of street address of orchestra'); ?>"
             placeholder="<?php echo $l->t('ZIP'); ?>">
      <input class="streetAddressCity" type="text"
             id="streetAddressCity"
             name="streetAddressCity"
             value="<?php echo $_['streetAddressCity']; ?>"
             title="<?php echo $l->t('city part of address of orchestra'); ?>"
             placeholder="<?php echo $l->t('city'); ?>"><br/>
      <select class="streetAddressCountry"
              id="streetAddressCountry"
              name="streetAddressCountry"
              title="<?php echo $l->t('country part of address of orchestra'); ?>"
              placeholder="<?php echo $l->t('country'); ?>">
        <option></option>
        <?php echo Navigation::selectOptions($countries); ?>
      </select><br/>
      <input class="phoneNumber" type="text"
             id="phoneNumber"
             name="phoneNumber"
             value="<?php echo $_['phoneNumber']; ?>"
             title="<?php echo $l->t('Phone number in international format, e.g. +49-761-123456.'); ?>"
             placeholder="<?php echo $l->t('phone'); ?>"><br/>
    </fieldset>
    <h4><?php echo $l->t('Bank Account'); ?></h4>
    <fieldset <?php echo $off; ?> >
      <!-- <legend><?php echo $l->t('Bank Account'); ?></legend> -->
      <input class="bankAccountOwner" type="text"
             id="bankAccountOwner"
             name="bankAccountOwner"
             value="<?php echo $_['bankAccountOwner']; ?>"
             title="<?php echo $l->t('owner of the orchestra\'s bank account'); ?>"
             placeholder="<?php echo $l->t('owner of bank account'); ?>"/><br/>
      <input class="bankAccountBLZ" type="text"
             id="bankAccountBLZ"
             name="bankAccountBLZ"
             value="<?php echo $_['bankAccountBLZ']; ?>"
             title="<?php echo $l->t('Optional BLZ of the orchestra\'s bank account'); ?>"
             placeholder="<?php echo $l->t('BLZ of bank account'); ?>"/>
      <input class="bankAccountIBAN" type="text"
             id="bankAccountIBAN"
             name="bankAccountIBAN"
             value="<?php echo $_['bankAccountIBAN']; ?>"
             title="<?php echo $l->t('IBAN or number of the orchestra\'s bank account. If this is a account number,then please first enter the BLZ'); ?>"
             placeholder="<?php echo $l->t('IBAN or no. of bank account'); ?>"/>
      <input class="bankAccountBIC" type="text"
             id="bankAccountBIC"
             name="bankAccountBIC"
             value="<?php echo $_['bankAccountBIC']; ?>"
             title="<?php echo $l->t('Optional BIC of the orchestra\'s bank account'); ?>"
             placeholder="<?php echo $l->t('BIC of bank account'); ?>"/><br/>
      <input class="bankAccountCreditorIdentifier" type="text"
             id="bankAccountCreditorIdentifier"
             name="bankAccountCreditorIdentifier"
             value="<?php echo $_['bankAccountCreditorIdentifier']; ?>"
             title="<?php echo $l->t('Creditor identifier of the orchestra'); ?>"
             placeholder="<?php echo $l->t('orchestra\'s CI'); ?>"/><br/>
    </fieldset>
    <h4><?php echo $l->t('Document templates'); ?></h4>
    <fieldset <?php echo $off; ?> class="chosen-dropup document-template">
      <!-- <legend><?php echo $l->t('Document templates'); ?></legend> -->
      <?php foreach ($documentTemplates as $documentTemplate => $placeholder) { ?>
        <div class="template-upload" data-document-template="<?php p($documentTemplate); ?>">
          <input type="button"
                 name="<?php p($documentTemplate); ?>Delete"
                 title="<?php p($toolTips['templates:delete']); ?>"
                 class="operation delete document-template operation <?php p($documentTemplate); ?>"
                 data-placeholder="<?php p($l->t('Select '.$placeholder)); ?>"
                 <?php empty(${$documentTemplate . 'FileName'}) && p('disabled'); ?>
          />
          <input type="button"
                 title="<?php p($toolTips['templates:' . $documentTemplate . '-cloud']); ?>"
                 class="operation select-cloud document-template operation <?php p($documentTemplate); ?>"
                 data-placeholder="<?php p($l->t('Select '.$placeholder)); ?>"
          />
          <input type="button"
                 title="<?php p($toolTips['templates:' . $documentTemplate . '-upload']); ?>"
                 class="operation upload-replace document-template operation <?php p($documentTemplate); ?>"
          />
          <input class="<?php p($documentTemplate); ?> document-template upload-placeholder<?php !empty(${$documentTemplate . 'FileName'}) && p(' hidden'); ?>"
                 type="text"
                 id="<?php p($documentTemplate); ?>"
                 name="<?php p($documentTemplate); ?>"
                 value="<?php p(${$documentTemplate}); ?>"
                 title="<?php echo $toolTips['templates:' . $documentTemplate]; ?>"
                 placeholder="<?php p($l->t($placeholder) . ' - ' . $l->t('drop or click')); ?>"
          />
          <a class="tooltip-auto document-template downloadlink<?php empty(${$documentTemplate . 'FileName'}) && p(' hidden'); ?>"
             download="<?php p(${$documentTemplate . 'FileName'}); ?>"
             href="<?php echo ${$documentTemplate . 'DownloadLink'}; ?>"
             title="<?php echo $toolTips['templates:' . $documentTemplate]; ?>"
          >
            <?php p(${$documentTemplate . 'FileName'}); ?>
          </a>
        </div>
      <?php } ?>
    </fieldset>
    <h4><?php echo $l->t('Executive board and club members'); ?></h4>
    <fieldset <?php echo $off; ?> class="chosen-dropup">
      <input class="specialMemberProjects memberProjectCreate validate"
             type="button"
             name="memberProjectValidate"
             data-project-name="<?php p($memberProject); ?>"
             data-project-id="<?php p($memberProjectId); ?>"
             value=""
             title="<?php echo $l->t('Ensure that the club-member\'s project and the necessary infrastructure exists.'); ?>"
      />
      <input class="specialMemberProjects" type="text"
             id="memberProject"
             name="memberProject"
             value="<?php echo $memberProject; ?>"
             title="<?php echo $toolTips['club-member-project']; ?>"
             placeholder="<?php echo $l->t('club members project'); ?>"
             data-projects='<?php echo json_encode($projectOptions); ?>'
      />
      <label for="memberProject"
             title="<?php echo $toolTips['club-member-project']; ?>">
        <?php echo $l->t('Club Member Project'); ?>
      </label>
      <br/>
      <input class="specialMemberProjects executiveBoardProjectCreate validate"
             type="button"
             name="executiveBoardProjectValidate"
             data-project-name="<?php p($executiveBoardProject); ?>"
             data-project-id="<?php p($executiveBoardProjectId); ?>"
             title="<?php echo $l->t('Ensure that the executive-board project and the necessary infrastructure exists.'); ?>"
      />
      <input class="specialMemberProjects" type="text"
             id="executiveBoardProject"
             name="executiveBoardProject"
             value="<?php echo $executiveBoardProject; ?>"
             title="<?php echo $toolTips['executive-board-project']; ?>"
             placeholder="<?php echo $l->t('executive board project'); ?>"
             data-projects='<?php echo json_encode($projectOptions); ?>'
      />
      <label for="executiveBoardProject"
             title="<?php echo $toolTips['executive-board-project']; ?>">
        <?php echo $l->t('Executive Board Project'); ?>
      </label>
      <br/>
      <table class="executive-board-members">
        <thead>
          <tr>
            <th><?php p($l->t('President')); ?></th>
            <th><?php p($l->t('Secretary')); ?></th>
            <th><?php p($l->t('Treasurer')); ?></th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>
              <select id="presidentSelect"
                      data-placeholder="<?php echo $l->t('Select the President'); ?>"
                      title="<?php echo $l->t('President of the orchestra'); ?>"
                      name="presidentId"
                      class="executive-board-ids tooltip-left">
                <option></option>
                <?php echo Navigation::selectOptions($executiveBoardMembers, $presidentId); ?>
              </select>
            </td>
            <td>
              <select id="secretarySelect"
                      data-placeholder="<?php echo $l->t('Select the Secretary'); ?>"
                      title="<?php echo $l->t('Secretary of the orchestra'); ?>"
                      name="secretaryId"
                      class="executive-board-ids tooltip-left">
                <option></option>
                <?php echo Navigation::selectOptions($executiveBoardMembers, $secretaryId); ?>
              </select>
            </td>
            <td>
              <select id="treasurerSelect"
                      data-placeholder="<?php echo $l->t('Select the Treasurer'); ?>"
                      title="<?php echo $l->t('Treasurer of the orchestra'); ?>"
                      name="treasurerId"
                      class="executive-board-ids tooltip-left">
                <option></option>
                <?php echo Navigation::selectOptions($executiveBoardMembers, $treasurerId); ?>
              </select>
            </td>
          </tr>
          <tr>
            <td>
              <select id="presidentUserSelect"
                      data-placeholder="<?php echo $l->t('Cloud President-User'); ?>"
                      title="<?php echo $l->t('Cloud user-id of the president of the orchestra'); ?>"
                      name="presidentUserId"
                      class="executive-board-ids tooltip-left">
                <option></option>
                <?php echo Navigation::simpleSelectOptions($_['userGroupMembers'], $_['presidentUserId']); ?>
              </select>
            </td>
            <td>
              <select id="secretaryUserSelect"
                      data-placeholder="<?php echo $l->t('Cloud Secretary-User'); ?>"
                      title="<?php echo $l->t('Cloud user-id of the secretary of the orchestra'); ?>"
                      name="secretaryUserId"
                      class="executive-board-ids tooltip-left">
                <option></option>
                <?php echo Navigation::simpleSelectOptions($_['userGroupMembers'], $_['secretaryUserId']); ?>
              </select>
            </td>
            <td>
              <select id="treasurerUserSelect"
                      data-placeholder="<?php echo $l->t('Cloud Treasurer-User'); ?>"
                      title="<?php echo $l->t('Cloud user-id of the treasurer of the orchestra'); ?>"
                      name="treasurerUserId"
                      class="executive-board-ids tooltip-left">
                <option></option>
                <?php echo Navigation::simpleSelectOptions($_['userGroupMembers'], $_['treasurerUserId']); ?>
              </select>
            </td>
          </tr>
          <tr>
            <td>
              <select id="presidentGroupSelect"
                      data-placeholder="<?php echo $l->t('Cloud President-Group'); ?>"
                      title="<?php echo $l->t('Cloud group-id of the president of the orchestra'); ?>"
                  name="presidentGroupId"
                      class="executive-board-ids tooltip-left">
                <option></option>
                <?php echo Navigation::simpleSelectOptions($_['userGroups'], $_['presidentGroupId']); ?>
              </select>
            </td>
            <td>
              <select id="secretaryGroupSelect"
                      data-placeholder="<?php echo $l->t('Cloud Secretary-Group'); ?>"
                      title="<?php echo $l->t('Cloud group-id of the secretary of the orchestra'); ?>"
                      name="secretaryGroupId"
                      class="executive-board-ids tooltip-left">
                <option></option>
                <?php echo Navigation::simpleSelectOptions($_['userGroups'], $_['secretaryGroupId']); ?>
              </select>
            </td>
            <td>
              <select id="treasurerGroupSelect"
                      data-placeholder="<?php echo $l->t('Cloud Treasurer-Group'); ?>"
                      title="<?php echo $l->t('Cloud group-id of the treasurer of the orchestra'); ?>"
                      name="treasurerGroupId"
                      class="executive-board-ids tooltip-left">
                <option></option>
                <?php echo Navigation::simpleSelectOptions($_['userGroups'], $_['treasurerGroupId']); ?>
              </select>
            </td>
          </tr>
        </tbody>
      </table>
    </fieldset>
  </form>
  <div class="statuscontainer">
    <span class="statusmessage" id="msg"></span>
    <span class="statusmessage" id="suggestion"></span>
    &nbsp;
  </div>
  <div class="document-template-upload-wrapper hidden" id="document-template-upload-wrapper">
    <form id="document-template-upload-form" class="file-upload-form document-template-upload-form" enctype="multipart/form-data">
      <input class="file-upload-start" type="file" accept="*" name="files"/>
      <input type="hidden" name="uploadName" value="files"/>
      <input type="hidden" name="requesttoken" value="<?php p($requesttoken); ?>"/>
      <input type="hidden" name="MAX_FILE_SIZE" value="<?php p($uploadMaxFilesize); ?>"/>
      <input type="hidden" class="max_human_file_size" value="<?php p($uploadMaxHumanFilesize); ?>"/>
    </form>
    <div class="uploadprogresswrapper">
      <div class="uploadprogressbar"></div>
    </div>
  </div>
</div>
