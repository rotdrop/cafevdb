<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or1
 * modify it under th52 terms of the GNU GENERAL PUBLIC LICENSE
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

namespace OCA\CAFEVDB\Service;

use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\Accounts\IAccountManager;
use OCP\Accounts\IAccount;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IL10N;
use OCP\Image;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/**
 * Check for authorization and supply contact information for several
 * administrative roles.
 */
class OrganizationalRolesService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\CloudAdminTrait;

  const CLOUD_ADMIN_ROLE = 'cloudAdmin';
  const GROUP_ADMIN_ROLE = 'groupAdmin';
  const TREASURER_ROLE = 'treasurer';
  const SECRETARY_ROLE = 'secretary';
  const PRESIDENT_ROLE = 'president';
  const BOARD_MEMBER_ROLE = 'boardMember';

  const ROLES = [
    self::CLOUD_ADMIN_ROLE,
    self::GROUP_ADMIN_ROLE,
    self::PRESIDENT_ROLE,
    self::SECRETARY_ROLE,
    self::TREASURER_ROLE,
    self::BOARD_MEMBER_ROLE,
  ];

  const BOARD_MEMBERS = [
    self::PRESIDENT_ROLE,
    self::SECRETARY_ROLE,
    self::TREASURER_ROLE,
  ];

  public function __construct(
    ConfigService $configService
  ) {
    $this->configService = $configService;
    $this->l = $this->l10n();
  }

  /**
   * Return email and display name of one of the principal organizators for error
   * feedback messages and substitutions.
   */
  public function dedicatedBoardMemberContact(string $role)
  {
    $participant = $this->dedicatedBoardMemberParticipant($role);
    if (empty($participant)) {
      return null;
    }
    $musician = $participant->getMusician();
    $data = [
      'email' => $musician->getEmail(),
      'name' => $musician->getPublicName(true),
      'street' => $musician->getStreet(),
      'streetNumber' => $musician->getStreetNumber(),
      'streetAndNumber' => $musician->getStreet() . ' ' . $musician->getStreetNumber(),
      'postalCode' => $musician->getPostalCode(),
      'city' => $musician->getCity(),
      'phone' => $musician->getFixedLinePhone(),
      'mobile' => $musician->getMobilePhone(),
    ];

    // Override with configured functional address
    $roleEmail = $this->getConfigValue($role . 'Email', null);
    if (!empty($roleEmail)) {
      $data['email'] = $roleEmail;
    }

    $missingFields = array_keys(array_filter($data, function($value) { empty($value); }));

    // if some field are missing try to fill in from the cloud data
    if (!empty($missingFields)) {

      $roleUid = $this->getConfigValue($role.'UserId', null);
      if (!empty($roleUid)) {
        $user = $this->user($roleUid);
        /** @var IAccountManager $accountManager */
        $accountManager = $this->di(IAccountManager::class);
        /** @var IAccount $account */
        $account = $accountManager->getAccount($user);

        foreach ($missingFields as $key) {
          switch ($key) {
          case 'email': $item = $user->getEMailAddress(); break;
          case 'name': $item =  $name = $user->getDisplayName(); break;
          case 'streetAndNumber': $item = $account->getProperty(IAccountManager::PROPERTY_ADDRESS); break;
          case 'phone': $item = $account->getProperty(IAccountManager::PROPERTY_PHONE); break;
          case 'postalCode':
          case 'city':
          case 'mobile':
            $item = null;
          }
          if (!empty($item)) {
            $data[$key] = $item;
          }
        }
      }
    }

    return $data;
  }

  private function executiveBoardProject():?Entities\Project
  {
    /** @var ProjectService $projectService */
    $projectService = $this->di(ProjectService::class);
    return $projectService->findById($this->getExecutiveBoardProjectId());
  }

  public function dedicatedBoardMemberParticipant(string $role, int $musicianId= 0):?Entities\ProjectParticipant
  {
    if ($role != self::BOARD_MEMBER_ROLE) {
      $musicianId = $this->getConfigValue($role.'Id', null);
    }
    if (empty($musicianId)) {
      return null;
    }
    /** @var ProjectService $projectService */
    $projectService = $this->di(ProjectService::class);
    return $projectService->findParticipant(
      $this->getExecutiveBoardProjectId(), $musicianId);
  }

  /**
   * Fetch the signature image for the given musician from the
   * executive-board table.
   *
   * @param in
   */
  public function dedicatedBoardMemberSignature(string $role, int $musicianId = 0)
  {
    $project = $this->executiveBoardProject();
    if (empty($project)) {
      return null;
    }
    $participant = $this->dedicatedBoardMemberParticipant($role, $musicianId);
    if (empty($participant)) {
      return null;
    }
    /** @var ProjectParticipantFieldsService $fieldsService */
    $fieldsService = $this->di(ProjectParticipantFieldsService::class);

    /** @var Entities\ProjectParticipantFieldDatum */
    $signatureData = $fieldsService->filterByFieldName(
      $participant->getParticipantFieldsData(),
      ConfigService::SIGNATURE_FIELD_NAME);
    if (!($signatureData instanceof Entities\ProjectParticipantFieldDatum)) {
      // did not work out
      return null;
    }
    $signatureFile = $fieldsService->getEffectiveFieldDatum($signatureData);
    if (!$signatureFile instanceof Entities\EncryptedFile) {
      return null;
    }
    $image = new \OCP\Image();
    $image->loadFromData($signatureFile->getFileData()->getData());

    return $image;
  }

  public function treasurerSignature():?Image
  {
    return $this->dedicatedBoardMemberSignature(self::TREASURER_ROLE);
  }

  public function secretarySignature():?Image
  {
    return $this->dedicatedBoardMemberSignature(self::SECRETARY_ROLE);
  }

  public function presidentSignature():?Image
  {
    return $this->dedicatedBoardMemberSignature(self::PRESIDENT_ROLE);
  }

  public function getTreasurer():?Entities\ProjectParticipant
  {
    return $this->dedicatedBoardMemberParticipant(self::TREASURER_ROLE);
  }

  public function getSecretary():?Entities\ProjectParticipant
  {
    return $this->dedicatedBoardMemberParticipant(self::SECRETARY_ROLE);
  }

  public function getPresident():?Entities\ProjectParticipant
  {
    return $this->dedicatedBoardMemberParticipant(self::PRESIDENT_ROLE);
  }

  /**
   * Return true if the logged in or given user has a dedicated
   * administrative role for the orchestra.
   */
  public function isDedicatedBoardMember(string $role, $uid = null, bool $allowGroupAccess = false)
  {
    empty($uid) && $uid = $this->userId();
    $musicianId = $this->getConfigValue($role.'Id', null);
    if (empty($musicianId)) {
      return false;
    }
    $roleUid = $this->getConfigValue($role.'UserId', null);
    if ($this->inGroup($roleUid) && $roleUid === $uid) {
      return true;
    }
    if (!$allowGroupAccess) {
      return false;
    }
    // check for group-membership
    $groupId = $this->getConfigValue($role.'UserGroupId', null);
    return !empty($groupId) && $this->inGroup($uid, $groupId);
  }

  /**
   * Return true if the logged in user is the treasurer.
   */
  public function isTreasurer($uid = null, $allowGroupAccess = false)
  {
    return $this->isDedicatedBoardMember(self::TREASURER_ROLE, $uid, $allowGroupAccess);
  }

  /**
   * Return true if the logged in user is the secretary.
   */
  public function isSecretary($uid = null, $allowGroupAccess = false)
  {
    return isDedicatedBoardMember(self::SECRETARY_ROLE, $uid, $allowGroupAccess);
  }

  /**
   * Return true if the logged in user is the president.
   */
  public function isPresident($uid = null, $allowGroupAccess = false)
  {
    return isDedicatedBoardMember(self::PRESIDENT_ROLE, $uid, $allowGroupAccess);
  }

  /**
   * Return true if the logged in user is in the treasurer group.
   */
  public function inTreasurerGroup($uid = null)
  {
    return $this->isTreasurer($uid, true);
  }

  /**
   * Return true if the logged in user is in the secretary group.
   */
  public function inSecretaryGroup($uid = null)
  {
    return $this->isSecretary($uid, true);
  }

  /**
   * Return true if the logged in user is in the president group.
   */
  public function inPresidentGroup($uid = null)
  {
    return $this->isPresident($uid, true);
  }

  /**
   * Contact information for the treasurer.
   *
   * @return array ```[ 'name' => USER, 'email' => EMAIL ]```.
   */
  public function treasurerContact()
  {
    return $this->dedicatedBoardMemberContact(self::TREASURER_ROLE);
  }

  /**
   * Contact information for the secretary.
   *
   * @return array ```[ 'name' => USER, 'email' => EMAIL ]```.
   */
  public function secretaryContact()
  {
    return $this->dedicatedBoardMemberContact(self::SECRETARY_ROLE);
  }

  /**
   * Contact information for the president.
   *
   * @return array ```[ 'name' => USER, 'email' => EMAIL ]```.
   */
  public function presidentContact()
  {
    return $this->dedicatedBoardMemberContact(self::PRESIDENT_ROLE);
  }

  /**
   * Check for overall-adminess
   */
  public function isCloudAdmin($uid = null)
  {
    empty($uid) && $uid = $this->userId();
    return $this->groupManager()->isAdmin($uid);
  }

  /**
   * Contact information for the overall admins.
   */
  public function cloudAdminContact(bool $implode = false)
  {
    return $this->getCloudAdminContacts($this->groupManager(), $implode);
  }

  public function adminContact(bool $implode = false)
  {
    return $this->cloudAdminContact($implode);
  }

  /**
   * Check for overall-adminess
   */
  public function isGroupAdmin($uid = null)
  {
    return $this->isSubAdminOfGroup($uid);
  }

  /**
   * Contact information for the group admins.
   */
  public function groupAdminContact()
  {
    $group = $this->group();
    $users = $group->getUsers();
    $contacts = [];
    foreach ($users as $user) {
      if ($this->groupManager()->isSubAdminofGroup($user, $group)) {
        $contacts[] = [ $adminUser->getDisplayName(), $adminUser->getEmailAddress() ];
      }
    }
    return $contacts;
  }

  /**
   * Get all the sub-admins of the dedicated group
   *
   * @return IUser[]
   */
  public function getGroupAdmins():array
  {
    return $this->getGroupSubAdmins();
  }

  /**
   * Check whether the given user is a club-member
   */
  public function isClubMember(string $userId):bool
  {
    $clubMembersProjectId = $this->getClubMembersProjectId();
    if (empty($clubMembersProjectId)) {
      return false;
    }
    /** @var CloudUserConnectorService $cloudService */
    $cloudService = $this->di(CloudUserConnectorService::class);
    $clubMembersGid = $cloudService->projectGroupId($clubMembersProjectId);

    return $this->inGroup($userId, $clubMembersGid);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
