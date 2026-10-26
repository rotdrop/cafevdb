<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

use Psr\Container\ContainerInterface;

use OCA\DokuWiki\Service\AuthDokuWiki;

/**
 * Thin adapter around the XML-RPC client of the DokuWiki app. The DokuWiki
 * classes are resolved lazily by name, so that this class can be mocked or
 * stubbed without the DokuWiki app being installed.
 */
class WikiRPC
{
  public const ON_ERROR_THROW = 'throw';
  public const ON_ERROR_RETURN = 'return';

  public const AUTH_NONE = 0;
  public const AUTH_READ = 1;
  public const AUTH_EDIT = 2;
  public const AUTH_CREATE = 4;
  public const AUTH_UPLOAD = 8;
  public const AUTH_DELETE = 16;
  public const AUTH_ADMIN = 255;

  private ?object $rpc = null;

  /** {@inheritdoc} */
  public function __construct(
    private ContainerInterface $appContainer,
  ) {
  }

  /** @return object The DokuWiki RPC service. */
  private function rpc(): object
  {
    return $this->rpc ??= $this->appContainer->get(AuthDokuWiki::class);
  }

  /**
   * @param null|string $how One of self::ON_ERROR_THROW or
   * self::ON_ERROR_RETURN or null (just return the current reporting).
   *
   * @return string Currently active error handling policy.
   */
  public function errorReporting(?string $how = null): string
  {
    return $this->rpc()->errorReporting($how);
  }

  /**
   * @param null|string $userName
   *
   * @param null|string $password
   *
   * @return bool
   */
  public function login(?string $userName = null, ?string $password = null): bool
  {
    return $this->rpc()->login($userName, $password);
  }

  /**
   * @param string $pagename
   *
   * @param string $pagedata
   *
   * @param array $attr
   *
   * @return mixed
   */
  public function putPage(string $pagename, string $pagedata, array $attr = []): mixed
  {
    return $this->rpc()->putPage($pagename, $pagedata, $attr);
  }

  /**
   * @param string $pagename
   *
   * @param null|int $version
   *
   * @return mixed
   */
  public function getPage(string $pagename, ?int $version = null): mixed
  {
    return $this->rpc()->getPage($pagename, $version);
  }

  /**
   * @param string $oldName
   *
   * @param string $newName
   *
   * @return mixed
   */
  public function renamePage(string $oldName, string $newName): mixed
  {
    return $this->rpc()->renamePage($oldName, $newName);
  }

  /**
   * @param string $pagename
   *
   * @return mixed
   */
  public function getPageVersions(string $pagename): mixed
  {
    return $this->rpc()->getPageVersions($pagename);
  }

  /**
   * @param string $pagename
   *
   * @return mixed
   */
  public function getPageInfo(string $pagename): mixed
  {
    return $this->rpc()->getPageInfo($pagename);
  }

  /**
   * @param string $scope
   *
   * @param string $who
   *
   * @param int $what
   *
   * @return mixed
   */
  public function addAcl(string $scope, string $who, int $what): mixed
  {
    return $this->rpc()->addAcl($scope, $who, $what);
  }

  /**
   * @param string $scope
   *
   * @param string $who
   *
   * @return mixed
   */
  public function delAcl(string $scope, string $who): mixed
  {
    return $this->rpc()->delAcl($scope, $who);
  }
}
