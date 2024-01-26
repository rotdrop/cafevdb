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

namespace OCA\CAFEVDB\Service;

use Throwable;
use DateTimeImmutable;
use DateTimeInterface;

use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use OCP\Files\Node as FileSystemNode;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface as ILogger;

/**
 * Support class for the creating cloud shared, currently only web-links can
 * be generated.
 */
class SimpleSharingService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** {@inheritdoc} */
  public function __construct(
    protected ConfigService $configService,
    private IShareManager $shareManager,
    private IURLGenerator $urlGenerator,
  ) {
    $this->l = $this->l10n();
  }

  /**
   * Create a link-share for the given file-system node. If the node is
   * already shared with the requested permissions then just return the old
   * share.
   *
   * @param FileSystemNode $node The cloud file-system node which shall be shared.
   *
   * @param null|string $shareOwner User-id of the owner.
   *
   * @param int $sharePerms Permissions for the link. Defaults to PERMISSION_CREATE.
   *
   * @param null|false|DateTimeInterface $expirationDate Optional expiration
   * date for the link. If null then create a link which does not expire. If
   * \false then just ignore the expiration date. Otherwise do an exact match
   * on the given date. Default to null.
   *
   * @param bool $noCreate Do not create a new share, but return an existing
   * share if it exists.
   *
   * @return null|string The absolute URL for the share or null.
   */
  public function linkShare(
    FileSystemNode $node,
    ?string $shareOwner = null,
    int $sharePerms = \OCP\Constants::PERMISSION_CREATE,
    mixed $expirationDate = null,
    bool $noCreate = false,
  ) {
    $this->logDebug('shared folder id ' . $node->getId());

    $shareType = IShare::TYPE_LINK;

    if (empty($shareOwner)) {
      $shareOwner = $this->userId();
    }

    if ($expirationDate instanceof DateTimeInterface) {
      // make sure it is UTC midnight
      $expirationDate = new DateTimeImmutable($expirationDate->format('Y-m-d'));
    }
    $expirationTimeStamp = empty($expirationDate) ? -1 : $expirationDate->getTimestamp();

    /** @var IShare $share */
    foreach ($this->shareManager->getSharesBy($shareOwner, $shareType, $node, false, -1) as $share) {
      // check permissions
      if ($share->getPermissions() !== $sharePerms) {
        continue;
      }

      if ($expirationDate !== false) {
        // check expiration time
        $shareExpirationDate = $share->getExpirationDate();

        $shareExpirationStamp = empty($shareExpirationDate) ? -1 : $shareExpirationDate->getTimestamp();

        if ($shareExpirationStamp != $expirationTimeStamp) {
          continue;
        }
      }

      // check permissions
      if ($share->getPermissions() === $sharePerms) {
        $url = $this->urlGenerator()->linkToRouteAbsolute('files_sharing.sharecontroller.showShare', ['token' => $share->getToken()]);
        $this->logInfo('Reuse existing link-share ' . $url);
        return $url;
      }
    }

    if ($noCreate) {
      return null;
    }

    // None found, generate a new one
    /** @var IShare $share */
    $share = $this->shareManager->newShare();
    $share->setNode($node);
    $share->setPermissions($sharePerms);
    $share->setShareType($shareType);
    $share->setShareOwner($shareOwner);
    $share->setSharedBy($shareOwner);
    if ($expirationDate !== false) {
      $share->setExpirationDate($expirationDate);
    }

    if (!$this->shareManager->createShare($share)) {
      return null;
    }

    $url = $this->urlGenerator()->linkToRouteAbsolute('files_sharing.sharecontroller.showShare', ['token' => $share->getToken()]);

    $this->logInfo('Created new link-share ' . $url);

    return $url;
  }

  /**
   * Expire all shares of the respective user of the respective type of the given file-system node by
   * setting their expiration time to the current or the given time.
   *
   * @param FileSystemNode $node
   *
   * @param null|string $shareOwner
   *
   * @param null|DateTimeInterface $expirationDate Optional expiration date for the link.
   *
   * @param int $shareType Defaults to IShare::TYPE_LINK.
   *
   * @return int The number of changed shares
   */
  public function expire(
    FileSystemNode $node,
    ?string $shareOwner = null,
    ?\DateTimeInterface $expirationDate = null,
    int $shareType = IShare::TYPE_LINK,
  ):int {
    if (empty($shareOwner)) {
      $shareOwner = $this->userId();
    }

    if (empty($expirationDate)) {
      $expirationDate = new DateTimeImmutable;
    }

    $numChanged = 0;

    /** @var IShare $share */
    foreach ($this->shareManager->getSharesBy($shareOwner, $shareType, $node, false, -1) as $share) {
      $shareExpirationDate = $share->getExpirationDate();
      if (empty($shareExpirationDate) || $shareExpirationDate > $expirationDate) {
        $share->setExpirationDate($expirationDate);
        $this->shareManager->updateShare($share);
        ++$numChanged;
      }
    }
    return $numChanged;
  }

  /**
   * Delete all shares of the respective user of the respective type of the given file-system node by
   * setting their expiration time to the current time.
   *
   * @param FileSystemNode $node
   *
   * @param null|string $shareOwner
   *
   * @param int $shareType Defaults to IShare::TYPE_LINK.
   *
   * @return int The number of deleted shares
   */
  public function delete(FileSystemNode $node, ?string $shareOwner, int $shareType = IShare::TYPE_LINK)
  {
    if (empty($shareOwner)) {
      $shareOwner = $this->userId();
    }

    $numDeleted = 0;

    /** @var IShare $share */
    foreach ($this->shareManager->getSharesBy($shareOwner, $shareType, $node, false, -1) as $share) {
      $this->shareManager->deleteShare($share);
      ++$numDeleted;
    }
    return $numDeleted;
  }

  /**
   * Delete the given share.
   *
   * @param string $token The share token or the web-url to the share which
   * carries the token as last path component.
   *
   * @return bool Execution status.
   */
  public function deleteLinkShare(string $token)
  {
    $share = $this->getShareFromUrl($token);
    if (empty($share)) {
      return false;
    }
    $this->shareManager->deleteShare($share);
    return true;
  }

  /**
   * Expire just the given share.
   *
   * @param string $token The share token or the web-url to the share which
   * carries the token as last path component.
   *
   * @param null|DateTimeInterface $expirationDate Optional expiration date
   * for the link. If null then it is only made sure the share is expired from
   * the current date on, if non-null the expiration date is actually set to
   * the requested value.
   *
   * @return null|DateTimeInterface The actual expiration date of the share or
   * null if the share cannot be found.
   */
  public function expireLinkShare(string $token, ?\DateTimeInterface $expirationDate = null):?DateTimeInterface
  {
    $share = $this->getShareFromUrl($token);
    if (empty($share)) {
      return null;
    }

    if (empty($expirationDate)) {
      $now = new DateTimeImmutable;
      $shareExpirationDate = $share->getExpirationDate();
      if (empty($shareExpirationDate) || $shareExpirationDate > $now) {
        $expirationDate = $now;
      }
    }

    if (!empty($expirationDate)) {
      $shareExpirationDate = $expirationDate;
      $share->setExpirationDate($expirationDate);
      $this->shareManager->updateShare($share);
    }

    return $shareExpirationDate;
  }

  /**
   * @param string $token The share token or the web-url to the share which
   * carries the token as last path component.
   *
   * @return null|false|\DateTimeInterface The expiration date of the given share or null.
   */
  public function getLinkExpirationDate(string $token):mixed
  {
    $share = $this->getShareFromUrl($token);
    if (empty($share)) {
      return false;
    }
    $shareExpirationDate = $share->getExpirationDate();

    return $shareExpirationDate;
  }

  /**
   * @param string $url Share token or the url to it.
   *
   * @return null|IShare The share if is found, null otherwise.
   */
  private function getShareFromUrl(string $url):?IShare
  {
    $token = basename($url); // allow an URL.

    $share = $this->shareManager->getShareByToken($token);

    return empty($share) ? null : $share;
  }

  /**
   * Create a simple group-share (default read-only)
   *
   * @param FileSystemNode $node
   *
   * @param string $groupId
   *
   * @param null|string $target The targer location in the file-space of the
   * consumer.
   *
   * @param int $permissions Default read-only.
   *
   * @param bool $sharedByOwner Pretend this was shared by the owner of the
   * node instead of the currently logged in user.
   *
   * @return bool
   */
  public function groupShareNode(
    FileSystemNode $node,
    string $groupId,
    ?string $target = null,
    int $permissions = \OCP\Constants::PERMISSION_READ,
    bool $sharedByOwner = false,
  ):bool {
    $owner = $node->getOwner();
    if (empty($owner)) {
      $ownerId = $this->userId();
    } else {
      $ownerId = $owner->getUID();
    }
    $sharedById = $sharedByOwner ? $ownerId : $this->userId();
    /** @var IShare $share */
    foreach ($this->shareManager->getSharesBy(userId: $ownerId, shareType: IShare::TYPE_GROUP, reshares: true) as $share) {
      if ($share->getNodeId() === $node->getId() && $share->getSharedWith() == $groupId) {
        // check permissions
        if ($share->getPermissions() !== $permissions) {
          $share->setPermissions($permissions);
        }
        if ($share->getSharedBy() !== $sharedById) {
          $share->setSharedBy($sharedById);
        }
        if ($share->getShareOwner() !== $ownerId) {
          $share->setShareOwner($ownerId);
        }
        $this->shareManager->updateShare($share);
        return $share->getPermissions() === $permissions
          && $share->getShareOwner() === $ownerId
          && $share->getSharedBy() === $sharedById;
      }
    }

    // Otherwise it should be legal to attempt a new share ...
    $share = $this->shareManager->newShare();
    $share->setNode($node);
    $share->setSharedWith($groupId);
    $share->setPermissions($permissions);
    $share->setShareType(IShare::TYPE_GROUP);
    $share->setShareOwner($ownerId);
    $share->setSharedBy($sharedById);

    try {
      $this->shareManager->createShare($share);
      return true;
    } catch (Throwable $t) {
      $this->logException($t);
      return false;
    }
  }
}
