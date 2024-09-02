<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

/**
 * Link to the Gnucash accounts table.
 */
#[ORM\Table(name: 'GnuCashBooks')]
#[ORM\Entity(repositoryClass: \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository::class)]
#[ORM\HasLifecycleCallbacks]
class GnuCashBook implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;

  //   CREATE TABLE `books` (
  //   `guid` varchar(32) NOT NULL,
  //   `root_account_guid` varchar(32) NOT NULL,
  //   `root_template_guid` varchar(32) NOT NULL
  // ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  /**
   * @var string
   */
  #[ORM\Column(type: 'string', length: 32, nullable: false, options: ['fixed' => true, 'collation' => 'ascii_general_ci'])]
  #[ORM\Id]
  private string $guid;

  /**
   * @var GnuCashAccount
   */
  #[ORM\JoinColumn(name: 'root_account_guid', referencedColumnName: 'guid', nullable: false)]
  #[ORM\OneToOne(targetEntity: GnuCashAccount::class, fetch: 'EXTRA_LAZY')]
  private string $rootAccount;

  /**
   * @var GnuCashAccount
   */
  #[ORM\JoinColumn(name: 'root_template_guid', referencedColumnName: 'guid', nullable: false)]
  #[ORM\OneToOne(targetEntity: GnuCashAccount::class, fetch: 'EXTRA_LAZY')]
  private string $rootTemplate;

  /** {@inheritdoc} */
  public function __construct()
  {
    $this->__wakeup();
  }

  /**
   * @return string GUID (id).
   */
  public function getGuid():string
  {
    return $this->guid;
  }

  /**
   * @param string $guid
   *
   * @return GnuCashBook $this
   */
  public function setGuid(string $guid):GnuCashBook
  {
    $this->guid = $guid;

    return $this;
  }

  /**
   * @return GnuCashAccount RootAccount.
   */
  public function getRootAccount():GnuCashAccount
  {
    return $this->rootAccount;
  }

  /**
   * @param GnuCashAccount $rootAccount
   *
   * @return GnuCashAccount $this
   */
  public function setRootAccount(GnuCashAccount $rootAccount):GnuCashAccount
  {
    $this->rootAccount = $rootAccount;

    return $this;
  }

  /**
   * @return GnuCashAccount RootTemplate.
   */
  public function getRootTemplate():GnuCashAccount
  {
    return $this->rootTemplate;
  }

  /**
   * @param GnuCashAccount $rootTemplate
   *
   * @return GnuCashAccount $this
   */
  public function setRootTemplate(GnuCashAccount $rootTemplate):GnuCashAccount
  {
    $this->rootTemplate = $rootTemplate;

    return $this;
  }
}
