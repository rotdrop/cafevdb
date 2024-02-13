<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022, 2023, 2024 Claus-Justus Heine
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

use Throwable;
use OCA\CAFEVDB\Wrapped\Carbon\Carbon;
use OCA\CAFEVDB\Wrapped\Carbon\CarbonImmutable;
use Cmixin\BusinessDay;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\GeoCodingService;

/** @var GeoCodingService $geoCodingService */

$off = $orchestra == '' ? 'disabled' : '';
$countries = [];
foreach ($localeCountryNames as $country => $name) {
  $option = ['name' => $name, 'value' => $country];
  if ($country === $streetAddressCountry) {
    $option['flags'] = PageNavigation::SELECTED;
  }
  $countries[] = $option;
}

BusinessDay::enable([
  Carbon::class,
  CarbonImmutable::class,
]);

$holidayRegions = array_map(fn($x) => strtoupper($x), CarbonImmutable::getHolidaysAvailableRegions());

$holidayOptions = [];
if (!empty($appLocale)) {
  $country = locale_get_region($appLocale);
  $holidayRegions = array_filter($holidayRegions, fn($holiday) => str_starts_with($holiday, $country));

  $nationalTag = $country . '-NATIONAL';
  $nationalKey = array_search($nationalTag, $holidayRegions);
  if ($nationalKey !== false) {
    unset($holidayRegions[$nationalKey]);
    array_unshift($holidayRegions, $nationalTag);
    if (empty($bankAccountBankHolidays)) {
      $bankAccountBankHolidays = $nationalTag;
    }
  }

  try {
    $regionNames = $geoCodingService->getRegionNames($country);
  } catch (Throwable $t) {
  }

  $holidayOptions = [];
  foreach ($holidayRegions as $region) {
    if ($region == $nationalTag) {
      $name = $l->t('national holidays');
    } else {
      list(, $regionIso) = explode('-', $region);
      $name = $regionNames[$regionIso] ?? $region;
    }
    $option = [
      'name' => $name,
      'value' =>  $region,
      'group' => $localeCountryNames[$country],
    ];
    if ($region === $bankAccountBankHolidays) {
      $option['flags'] = PageNavigation::SELECTED;
    }
    $holidayOptions[] = $option;
  }
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
      <input class="registerName" type="text"
             id="registerName"
             name="registerName"
             value="<?php echo $_['registerName']; ?>"
             title="<?php echo $l->t('Name of the authority where the orchestra association is registered.'); ?>"
             placeholder="<?php echo $l->t('e.g. Local Court Denver'); ?>">
      <input class="registerNumber" type="text"
             id="registerNumber"
             name="registerNumber"
             value="<?php echo $_['registerNumber']; ?>"
             title="<?php echo $l->t('Registration number of the orchestra association.'); ?>"
             placeholder="<?php echo $l->t('e.g. VR1234'); ?>">
      <br/>
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
        <?php echo PageNavigation::selectOptions($countries); ?>
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
      <input class="bankAccountBankName" type="text"
             id="bankAccountBankName"
             name="bankAccountBankName"
             value="<?php echo $_['bankAccountBankName']; ?>"
             title="<?php echo $l->t('Name of the bank holding the orchestra\'s bank account'); ?>"
             placeholder="<?php echo $l->t('e.g. Bank of Scotland'); ?>"/><br/>
      <input class="bankAccountCreditorIdentifier" type="text"
             id="bankAccountCreditorIdentifier"
             name="bankAccountCreditorIdentifier"
             value="<?php echo $_['bankAccountCreditorIdentifier']; ?>"
             title="<?php echo $l->t('Creditor identifier of the orchestra'); ?>"
             placeholder="<?php echo $l->t('orchestra\'s CI'); ?>"/><br/>
      <select class="bankAccountBankHolidays"
              id="bankAccountBankHolidays"
              name="bankAccountBankHolidays"
              title="<?php echo $l->t('Bank-holidays to take into account when generating bulk bank transactions.'); ?>"
      >
        <?php echo PageNavigation::selectOptions($holidayOptions); ?>
      </select><br/>
    </fieldset>
    <h4><?php echo $l->t('Document templates'); ?></h4>
    <fieldset <?php echo $off; ?> class="chosen-dropup document-template">
      <!-- <legend><?php echo $l->t('Document templates'); ?></legend> -->
      <?php foreach ($documentTemplates as $documentTemplate => $templateInfo) {
        $placeholder = $templateInfo['name'];
        $type = $templateInfo['type'];
        ?>
        <div class="template-upload"
             data-document-template="<?php p($documentTemplate); ?>"
             data-document-template-sub-folder="<?php p(${$documentTemplate . 'SubFolder'}); ?>">
          <input type="button"
                 name="<?php p($documentTemplate); ?>Delete"
                 title="<?php p($toolTips['templates:delete']); ?>"
                 class="operation delete document-template <?php p($documentTemplate); ?>"
                 <?php empty(${$documentTemplate . 'FileName'}) && p('disabled'); ?>
          />
          <input type="button"
                 title="<?php p($toolTips['templates:upload:cloud:' . $documentTemplate]); ?>"
                 class="operation select-cloud document-template <?php p($documentTemplate); ?>"
                 data-placeholder="<?php p($l->t('Select '.$placeholder)); ?>"
          />
          <input type="button"
                 title="<?php p($toolTips['templates:upload:client:' . $documentTemplate]); ?>"
                 class="operation upload-replace document-template <?php p($documentTemplate); ?>"
          />
          <input class="<?php p($documentTemplate); ?> document-template upload-placeholder<?php !empty(${$documentTemplate . 'FileName'}) && p(' hidden'); ?>"
                 type="text"
                 id="<?php p($documentTemplate); ?>"
                 name="<?php p($documentTemplate); ?>"
                 value="<?php p(${$documentTemplate . 'FileName'}); ?>"
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
          <?php if ($type == ConfigService::DOCUMENT_TYPE_TEMPLATE) {  ?>
            <input type="button"
                   name="<?php p($documentTemplate); ?>AutoFillTest"
                   data-template="<?php p($documentTemplate); ?>"
                   data-format="native"
                   title="<?php p($toolTips['templates:auto-fill-test']); ?>"
                   class="operation right auto-fill-test document-template <?php p($documentTemplate); ?><?php empty(${$documentTemplate . 'FileName'}) && p(' hidden'); ?>"
                   <?php empty(${$documentTemplate . 'FileName'}) && p('disabled'); ?>
            />
            <input type="button"
                   name="<?php p($documentTemplate); ?>AutoFillTestPdf"
                   data-template="<?php p($documentTemplate); ?>"
                   data-format="pdf"
                   title="<?php p($toolTips['templates:auto-fill-test:pdf']); ?>"
                   class="operation right auto-fill-test pdf document-template <?php p($documentTemplate); ?><?php empty(${$documentTemplate . 'FileName'}) && p(' hidden'); ?>"
                   <?php empty(${$documentTemplate . 'FileName'}) && p('disabled'); ?>
            />
            <input type="Button"
                   name="<?php p($documentTemplate); ?>FillTestData"
                   data-template="<?php p($documentTemplate); ?>"
                   title="<?php p($toolTips['templates:auto-fill-test:data']); ?>"
                   class="operation right auto-fill-test-data document-template <?php p($documentTemplate); ?>"
            />
          <?php } ?>
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
      <div class="executive-board-members">
        <div class="row">
          <div class="president col-xs-4 col-sm-4">
            <div class="heading row">
              <?php p($l->t('President')); ?>
            </div>
            <div class="musician-id row">
              <select id="presidentSelect"
                      data-placeholder="<?php echo $l->t('Select the President'); ?>"
                      title="<?php echo $l->t('President of the orchestra'); ?>"
                      name="presidentId"
                      class="executive-board-ids tooltip-left">
                <option></option>
                <?php echo PageNavigation::selectOptions($executiveBoardMembers, $presidentId); ?>
              </select>
            </div>
            <div class="cloud-uid row">
              <select id="presidentUserSelect"
                      data-placeholder="<?php echo $l->t('Cloud President-User'); ?>"
                      title="<?php echo $l->t('Cloud user-id of the president of the orchestra'); ?>"
                      name="presidentUserId"
                      class="executive-board-ids tooltip-left">
                <option></option>
                <?php echo PageNavigation::simpleSelectOptions($_['userGroupMembers'], $_['presidentUserId']); ?>
              </select>
            </div>
            <div class="cloud-group row">
              <select id="presidentGroupSelect"
                      data-placeholder="<?php echo $l->t('Cloud President-Group'); ?>"
                      title="<?php echo $l->t('Cloud group-id of the president of the orchestra'); ?>"
                      name="presidentGroupId"
                      class="executive-board-ids tooltip-left">
                <option></option>
                <?php echo PageNavigation::selectOptions($_['userGroups'], $_['presidentGroupId']); ?>
              </select>
            </div>
            <div class="email row">
              <input type="text"
                     id="presidentEmail"
                     placeholder="<?php p($l->t('e.g. president@me.tld')); ?>"
                     name="presidentEmail"
                     class="executive-board-ids tooltip-left"
              />
            </div>
          </div>
          <div class="secretary col-xs-4 col-sm-4">
            <div class="heading">
              <?php p($l->t('Secretary')); ?>
            </div>
            <div class="musician-id">
              <select id="secretarySelect"
                      data-placeholder="<?php echo $l->t('Select the Secretary'); ?>"
                      title="<?php echo $l->t('Secretary of the orchestra'); ?>"
                      name="secretaryId"
                      class="executive-board-ids tooltip-left">
                <option></option>
                <?php echo PageNavigation::selectOptions($executiveBoardMembers, $secretaryId); ?>
              </select>
            </div>
            <div class="cloud-uid">
              <select id="secretaryUserSelect"
                      data-placeholder="<?php echo $l->t('Cloud Secretary-User'); ?>"
                      title="<?php echo $l->t('Cloud user-id of the secretary of the orchestra'); ?>"
                      name="secretaryUserId"
                      class="executive-board-ids tooltip-left">
                <option></option>
                <?php echo PageNavigation::simpleSelectOptions($_['userGroupMembers'], $_['secretaryUserId']); ?>
              </select>
            </div>
            <div class="cloud-group">
              <select id="secretaryGroupSelect"
                      data-placeholder="<?php echo $l->t('Cloud Secretary-Group'); ?>"
                      title="<?php echo $l->t('Cloud group-id of the secretary of the orchestra'); ?>"
                      name="secretaryGroupId"
                      class="executive-board-ids tooltip-left">
                <option></option>
                <?php echo PageNavigation::selectOptions($_['userGroups'], $_['secretaryGroupId']); ?>
              </select>
            </div>
            <div class="email">
              <input type="text"
                     id="secretaryEmail"
                     placeholder="<?php p($l->t('e.g. secretary@me.tld')); ?>"
                     name="secretaryEmail"
                     class="executive-board-ids tooltip-left"
              />
            </div>
          </div>
          <div class="treasurer col-xs-4 col-sm-4">
            <div class="heading">
              <?php p($l->t('Treasurer')); ?>
            </div>
            <div class="musician-id">
              <select id="treasurerSelect"
                      data-placeholder="<?php echo $l->t('Select the Treasurer'); ?>"
                      title="<?php echo $l->t('Treasurer of the orchestra'); ?>"
                      name="treasurerId"
                      class="executive-board-ids tooltip-left">
                <option></option>
                <?php echo PageNavigation::selectOptions($executiveBoardMembers, $treasurerId); ?>
              </select>
            </div>
            <div class="cloud-uid">
              <select id="treasurerUserSelect"
                      data-placeholder="<?php echo $l->t('Cloud Treasurer-User'); ?>"
                      title="<?php echo $l->t('Cloud user-id of the treasurer of the orchestra'); ?>"
                      name="treasurerUserId"
                      class="executive-board-ids tooltip-left">
                <option></option>
                <?php echo PageNavigation::simpleSelectOptions($_['userGroupMembers'], $_['treasurerUserId']); ?>
              </select>
            </div>
            <div class="cloud-group">
              <select id="treasurerGroupSelect"
                      data-placeholder="<?php echo $l->t('Cloud Treasurer-Group'); ?>"
                      title="<?php echo $l->t('Cloud group-id of the treasurer of the orchestra'); ?>"
                      name="treasurerGroupId"
                      class="executive-board-ids tooltip-left">
                <option></option>
                <?php echo PageNavigation::selectOptions($_['userGroups'], $_['treasurerGroupId']); ?>
              </select>
            </div>
            <div class="email">
              <input type="text"
                     id="treasurerEmail"
                     placeholder="<?php p($l->t('e.g. treasurer@me.tld')); ?>"
                     name="treasurerEmail"
                     class="executive-board-ids tooltip-left"
              />
            </div>
          </div>
        </div>
      </div>
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
