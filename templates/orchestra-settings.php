<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace CAFEVDB {

  $off = $_['orchestra'] == '' ? 'disabled="disabled"' : '';
  $countries = array();
  foreach (Util::countryNames() as $country => $name) {
    $option = array('name' => $name, 'value' => $country);
    if ($country === $_['streetAddressCountry']) {
      $option['flags'] = Navigation::SELECTED;
    }
    $countries[] = $option;
  }

?>
  <div id="tabs-<?php echo $_['tabNr']; ?>" class="personalblock admin">
    <form id="orchestra">
      <fieldset <?php echo $off; ?> >
        <legend><?php echo L::t('Street Address'); ?></legend>
        <input class="streetAddressName" type="text"
               id="streetAddressName01"
               name="streetAddressName01"
               value="<?php echo $_['streetAddressName01']; ?>"
               title="<?php echo L::t('The name of the orchestra'); ?>"
               placeholder="<?php echo L::t('name of orchestra'); ?>"/><br/>
        <input class="streetAddressName" type="text"
               id="streetAddressName02"
               name="streetAddressName02"
               value="<?php echo $_['streetAddressName02']; ?>"
               title="<?php echo L::t('The name of the orchestra (line 2)'); ?>"
               placeholder="<?php echo L::t('name of orchestra'); ?>"><br/>
        <input class="streetAddressStreet" type="text"
               id="streetAddressStreet"
               name="streetAddressStreet"
               value="<?php echo $_['streetAddressStreet']; ?>"
               title="<?php echo L::t('street part of street address of orchestra'); ?>"
               placeholder="<?php echo L::t('street part of street address of orchestra'); ?>">
        <input class="streetAddressHouseNumber" type="text"
               id="streetAddressHouseNumber"
               name="streetAddressHouseNumber"
               value="<?php echo $_['streetAddressHouseNumber']; ?>"
               title="<?php echo L::t('house number part of street address of orchestra'); ?>"
               placeholder="<?php echo L::t('Nr'); ?>"><br/>
        <input class="streetAddressZIP" type="text"
               id="streetAddressZIP"
               name="streetAddressZIP"
               value="<?php echo $_['streetAddressZIP']; ?>"
               title="<?php echo L::t('ZIP part of street address of orchestra'); ?>"
               placeholder="<?php echo L::t('ZIP'); ?>">
        <input class="streetAddressCity" type="text"
               id="streetAddressCity"
               name="streetAddressCity"
               value="<?php echo $_['streetAddressCity']; ?>"
               title="<?php echo L::t('city part of address of orchestra'); ?>"
               placeholder="<?php echo L::t('city'); ?>"><br/>
        <select class="streetAddressCountry"
                id="streetAddressCountry"
                name="streetAddressCountry"
                title="<?php echo L::t('country part of address of orchestra'); ?>"
                placeholder="<?php echo L::t('country'); ?>">
          <option></option>
          <?php echo Navigation::selectOptions($countries); ?>
        </select><br/>
        <input class="phoneNumber" type="text"
               id="phoneNumber"
               name="phoneNumber"
               value="<?php echo $_['phoneNumber']; ?>"
               title="<?php echo L::t('Phone number in international format, e.g. +49-761-123456.'); ?>"
               placeholder="<?php echo L::t('phone'); ?>"><br/>
      </fieldset>
      <fieldset <?php echo $off; ?> >
        <legend><?php echo L::t('Bank Account'); ?></legend>
        <input class="bankAccountOwner" type="text"
               id="bankAccountOwner"
               name="bankAccountOwner"
               value="<?php echo $_['bankAccountOwner']; ?>"
               title="<?php echo L::t('owner of the orchestra\'s bank account'); ?>"
               placeholder="<?php echo L::t('owner of bank account'); ?>"/><br/>
        <input class="bankAccountBLZ" type="text"
               id="bankAccountBLZ"
               name="bankAccountBLZ"
               value="<?php echo $_['bankAccountBLZ']; ?>"
               title="<?php echo L::t('Optional BLZ of the orchestra\'s bank account'); ?>"
               placeholder="<?php echo L::t('BLZ of bank account'); ?>"/>
        <input class="bankAccountIBAN" type="text"
               id="bankAccountIBAN"
               name="bankAccountIBAN"
               value="<?php echo $_['bankAccountIBAN']; ?>"
               title="<?php echo L::t('IBAN or number of the orchestra\'s bank account. If this is a account number,then please first enter the BLZ'); ?>"
               placeholder="<?php echo L::t('IBAN or no. of bank account'); ?>"/>
        <input class="bankAccountBIC" type="text"
               id="bankAccountBIC"
               name="bankAccountBIC"
               value="<?php echo $_['bankAccountBIC']; ?>"
               title="<?php echo L::t('Optional BIC of the orchestra\'s bank account'); ?>"
               placeholder="<?php echo L::t('BIC of bank account'); ?>"/><br/>
        <input class="bankAccountCreditorIdentifier" type="text"
               id="bankAccountCreditorIdentifier"
               name="bankAccountCreditorIdentifier"
               value="<?php echo $_['bankAccountCreditorIdentifier']; ?>"
               title="<?php echo L::t('Creditor identifier of the orchestra'); ?>"
               placeholder="<?php echo L::t('orchestra\'s CI'); ?>"/><br/>
      </fieldset>
    <fieldset <?php echo $off; ?> class="chosen-dropup">
      <legend><?php echo L::t('Executive board and club members'); ?></legend>
      <input class="specialMemberTables" type="text"
             id="memberTable"
             name="memberTable"
             value="<?php echo $_['memberTable']; ?>"
             title="<?php echo Config::toolTips('club-member-project'); ?>"
             placeholder="<?php echo L::t('member-table'); ?>"/>
      <label for="memberTable"
             title="<?php echo Config::toolTips('club-member-project'); ?>">
        <?php echo L::t('Club Member Project'); ?>
      </label>
      <br/>
      <input class="specialMemberTables" type="text"
             id="executiveBoardTable"
             name="executiveBoardTable"
             value="<?php echo $_['executiveBoardTable']; ?>"
             title="<?php echo Config::toolTips('executive-board-project'); ?>"
             placeholder="<?php echo L::t('executive board table'); ?>"/>
      <label for="executiveBoardTable"
             title="<?php echo Config::toolTips('executive-board-project'); ?>">
        <?php echo L::t('Executive Board Project'); ?>
      </label>
      <br/>
      <select id="presidentSelect"
              data-placeholder="<?php echo L::t('Select the President'); ?>"
              title="<?php echo L::t('President of the orchestra'); ?>"
                name="presidentId"
              class="executive-board-ids tooltip-left">
          <option></option>
          <?php
          echo Navigation::selectOptions(
            Projects::participantOptions($_['executiveBoardTableId'], $_['executiveBoardTable'], $_['presidentId']));
          ?>
      </select>
      <select id="secretarySelect"
              data-placeholder="<?php echo L::t('Select the Secretary'); ?>"
              title="<?php echo L::t('Secretary of the orchestra'); ?>"
              name="secretaryId"
              class="executive-board-ids tooltip-left">
        <option></option>
        <?php
        echo Navigation::selectOptions(
          Projects::participantOptions($_['executiveBoardTableId'], $_['executiveBoardTable'], $_['secretaryId']));
        ?>
      </select>
      <select id="treasurerSelect"
              data-placeholder="<?php echo L::t('Select the Treasurer'); ?>"
              title="<?php echo L::t('Treasurer of the orchestra'); ?>"
              name="treasurerId"
              class="executive-board-ids tooltip-left">
        <option></option>
        <?php
        echo Navigation::selectOptions(
          Projects::participantOptions($_['executiveBoardTableId'], $_['executiveBoardTable'], $_['treasurerId']));
        ?>
      </select>
      <br/>
      <select id="presidentUserSelect"
              data-placeholder="<?php echo L::t('OwnCloud President-User'); ?>"
              title="<?php echo L::t('OwnCloud user-id of the president of the orchestra'); ?>"
              name="presidentUserId"
              class="executive-board-ids tooltip-left">
        <option></option>
        <?php
        echo Navigation::simpleSelectOptions($_['userGroupMembers'], $_['presidentUserId']);
        ?>
      </select>
      <select id="secretaryUserSelect"
              data-placeholder="<?php echo L::t('OwnCloud Secretary-User'); ?>"
              title="<?php echo L::t('OwnCloud user-id of the secretary of the orchestra'); ?>"
              name="secretaryUserId"
              class="executive-board-ids tooltip-left">
        <option></option>
        <?php
        echo Navigation::simpleSelectOptions($_['userGroupMembers'], $_['secretaryUserId']);
        ?>
      </select>
      <select id="treasurerUserSelect"
              data-placeholder="<?php echo L::t('OwnCloud Treasurer-User'); ?>"
              title="<?php echo L::t('OwnCloud user-id of the treasurer of the orchestra'); ?>"
              name="treasurerUserId"
              class="executive-board-ids tooltip-left">
        <option></option>
        <?php
        echo Navigation::simpleSelectOptions($_['userGroupMembers'], $_['treasurerUserId']);
        ?>
      </select>
      <br/>
      <select id="presidentGroupSelect"
              data-placeholder="<?php echo L::t('OwnCloud President-Group'); ?>"
              title="<?php echo L::t('OwnCloud group-id of the president of the orchestra'); ?>"
              name="presidentGroupId"
              class="executive-board-ids tooltip-left">
        <option></option>
        <?php
        echo Navigation::simpleSelectOptions($_['userGroups'], $_['presidentGroupId']);
        ?>
      </select>
      <select id="secretaryGroupSelect"
              data-placeholder="<?php echo L::t('OwnCloud Secretary-Group'); ?>"
              title="<?php echo L::t('OwnCloud group-id of the secretary of the orchestra'); ?>"
              name="secretaryGroupId"
              class="executive-board-ids tooltip-left">
        <option></option>
        <?php
        echo Navigation::simpleSelectOptions($_['userGroups'], $_['secretaryGroupId']);
        ?>
      </select>
      <select id="treasurerGroupSelect"
              data-placeholder="<?php echo L::t('OwnCloud Treasurer-Group'); ?>"
              title="<?php echo L::t('OwnCloud group-id of the treasurer of the orchestra'); ?>"
              name="treasurerGroupId"
              class="executive-board-ids tooltip-left">
        <option></option>
        <?php
        echo Navigation::simpleSelectOptions($_['userGroups'], $_['treasurerGroupId']);
        ?>
      </select>
      <br/>
    </fieldset>
    <div class="statuscontainer">
      <span class="statusmessage" id="msg"></span>
      <span class="statusmessage" id="suggestion"></span>
      &nbsp;
    </div>
  </form>
  </div>
<?php
} // namespace CAFEVDB
?>
