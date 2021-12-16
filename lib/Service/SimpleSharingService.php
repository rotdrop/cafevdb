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

namespace OCA\CAFEVDB\Service;

use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use OCP\Files\Node as FileSystemNode;
use OCP\ILogger;

class SimpleSharingService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var IShareManager */
  private $shareManager;

  public function __construct(
    ConfigService $configService,
    IShareManager $shareManager
  )  {
    $this->configService = $configService;
    $this->shareManager = $shareManager;
    $this->urlGenerator = $urlGenerator;
    $this->l = $this->l10n();
  }

  /**
   * Create a link-share for the given file-system node. If the node is
   * already shared with the requested permissions then just return the old
   * share.
   *
   * @param FileSystemNode $node
   *
   * @paran null|string $shareOwner User-id of the owner.
   *
   * @param int $sharePerms Permissions for the link. Defaults to PERMISSION_CREATE.
   *
   * @return null|string The absolute URL for the share or null.
   *
   */
  public function linkShare(
    FileSystemNode $node
    , ?string $shareOwner = null
    , int $sharePerms = \OCP\Constants::PERMISSION_CREATE
    , ?\DateTimeInterface $expirationDate = null
  ) {
    $this->logDebug('shared folder id ' . $node->getId());

    $shareType = IShare::TYPE_LINK;

    if (empty($shareOwner)) {
      $shareOwner = $this->userId();
    }

    if (!empty($expirationDate)) {
      // make sure it is UTC midnight
      $expirationDate = new \DateTimeImmutable($expirationDate->format('Y-m-d'));
    }
    $expirationTimeStamp = empty($expirationDate) ? -1 : $expirationDate->getTimestamp();

    /** @var IShare $share */
    foreach ($this->shareManager->getSharesBy($shareOwner, $shareType, $node, false, -1) as $share) {
      // check permissions
      if ($share->getPermissions() !== $sharePerms) {
        continue;
      }

      // check expiration time
      $shareExpirationDate = $share->getExpirationDate();

      $shareExpirationStamp = empty($shareExpirationDate) ? -1 : $shareExpirationDate->getTimestamp();

      $this->logInfo('SHARE EXPIRATION DATE ' . print_r($shareExpirationDate, true) . ' ' . $shareExpirationStamp);

      if ($shareExpirationStamp != $expirationTimeStamp) {
        continue;
      }

      // check permissions
      if ($share->getPermissions() === $sharePerms) {
        $url = $this->urlGenerator()->linkToRouteAbsolute('files_sharing.sharecontroller.showShare', ['token' => $share->getToken()]);
        $this->logInfo('Reuse existing link-share ' . $url);
        return $url;
      }
    }

    // None found, generate a new one
    /** @var IShare $share */
    $share = $this->shareManager->newShare();
    $share->setNode($node);
    $share->setPermissions($sharePerms);
    $share->setShareType($shareType);
    $share->setShareOwner($shareOwner);
    $share->setSharedBy($shareOwner);
    $share->setExpirationDate($expirationDate);

    if (!$this->shareManager->createShare($share)) {
      return null;
    }

    $url = $this->urlGenerator()->linkToRouteAbsolute('files_sharing.sharecontroller.showShare', ['token' => $share->getToken()]);

    $this->logInfo('Created new link-share ' . $url);

    return $url;
  }

  /**
   * Expire all shares of the respective user of the respective type of the given file-system node by
   * setting their expiration time to the current time.
   *
   * @param FileSystemNode $node
   *
   * @param null|string $shareOwner
   *
   * @param int $shareType Defaults to IShare::TYPE_LINK
   *
   * @return int The number of changed shares
   *
   */
  public function expire(FileSystemNode $node, ?string $shareOwner = null, int $shareType = IShare::TYPE_LINK):int
  {
    if (empty($shareOwner)) {
      $shareOwner = $this->userId();
    }

    $now = new \DateTimeImmutable;

    $numChanged = 0;

    /** @var IShare $share */
    foreach ($this->shareManager->getSharesBy($shareOwner, $shareType, $node, false, -1) as $share) {
      $expirationDate = $share->getExpirationDate() ?? $now;
      if ($expirationDate > $now) {
        $share->setExpirationDate($now);
        $this->shareManager->updateShare($share);
        ++$numChanged;
      }
    }
    return $numChanged;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
