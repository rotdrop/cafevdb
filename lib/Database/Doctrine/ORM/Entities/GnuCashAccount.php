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
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

/**
 * Link to the Gnucash accounts table.
 *
 * @ORM\Table(name="GnuCashAccounts")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository")
 * @ORM\HasLifecycleCallbacks
 */
class GnuCashAccount implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;

//   CREATE TABLE `accounts` (
//   `guid` varchar(32) NOT NULL,
//   `name` varchar(2048) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
//   `account_type` varchar(2048) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
//   `commodity_guid` varchar(32) DEFAULT NULL,
//   `commodity_scu` int(11) NOT NULL,
//   `non_std_scu` int(11) NOT NULL,
//   `parent_guid` varchar(32) DEFAULT NULL,
//   `code` varchar(2048) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
//   `description` varchar(2048) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
//   `hidden` int(11) DEFAULT NULL,
//   `placeholder` int(11) DEFAULT NULL
// ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=32, nullable=false, options={"fixed": true, "collation"="ascii_general_ci"})
   * @ORM\Id
   */
  private string $guid;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2028, nullable=false)
   */
  private string $name;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2028, nullable=false, options={"collation"="ascii_general_ci"})
   */
  private string $accountType;

  /**
   * @var null|GnuCashCommodity
   *
   * @ORM\ManyToOne(targetEntity="GnuCashCommodity", fetch="EXTRA_LAZY")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="commodity_guid", referencedColumnName="guid", nullable=true)
   * )
   */
  private ?GnuCashCommodity $commodity;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   */
  private int $commodityScu;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   */
  private int $nonStdScu;

  /**
   * @var GnuCashAccount
   *
   * @ORM\ManyToOne(targetEntity="GnuCashAccount", inversedBy="children", fetch="EXTRA_LAZY")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="parent_guid", referencedColumnName="guid", nullable=true)
   * )
   */
  private ?GnuCashAccount $parent;

  /**
   * @var Collection
   *
   * @ORM\OneToMany(targetEntity="GnuCashAccount", mappedBy="parent", fetch="EXTRA_LAZY")
   */
  private string $children;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2028, nullable=false, options={"collation"="ascii_general_ci"})
   */
  private string $code;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2028, nullable=false)
   */
  private string $description;

  /**
   * @var int
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private bool $hidden;

  /**
   * @var int
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private bool $placeholder;

  /** {@inheritdoc} */
  public function __construct()
  {
    $this->__wakeup();
    $this->children = new ArrayCollection;
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
   * @return GnuCashAccount $this
   */
  public function setGuid(string $guid):GnuCashAccount
  {
    $this->guid = $guid;

    return $this;
  }

  /**
   * @return string Name.
   *
   * @todo Clarify the meaning.
   */
  public function getName():string
  {
    return $this->name;
  }

  /**
   * @param string $name
   *
   * @return GnuCashAccount $this
   */
  public function setName(string $name):GnuCashAccount
  {
    $this->name = $name;

    return $this;
  }

  /**
   * @return string AccountType.
   *
   * @todo Clarify the meaning.
   */
  public function getAccountType():string
  {
    return $this->accountType;
  }

  /**
   * @param string $accountType
   *
   * @return GnuCashAccount $this
   */
  public function setAccountType(string $accountType):GnuCashAccount
  {
    $this->accountType = $accountType;

    return $this;
  }

  /**
   * For the purpose of this application the "commodity" is just the currency.
   *
   * @return GnuCashCommodity Commodity.
   */
  public function getCommodity():GnuCashCommodity
  {
    return $this->commodity;
  }

  /**
   * @param GnuCashCommodity $commodity
   *
   * @return GnuCashAccount $this
   */
  public function setCommodity(GnuCashCommodity $commodity):GnuCashAccount
  {
    $this->commodity = $commodity;

    return $this;
  }

  /**
   * @return int CommodityScu.
   *
   * @todo Clarify the meaning.
   */
  public function getCommodityScu():int
  {
    return $this->commodityScu;
  }

  /**
   * @param int $commodityScu
   *
   * @return GnuCashAccount $this
   */
  public function setCommodityScu(int $commodityScu):GnuCashAccount
  {
    $this->commodityScu = $commodityScu;

    return $this;
  }

  /**
   * @return int NonStdScu.
   *
   * @todo Clarify the meaning.
   */
  public function getNonStdScu():int
  {
    return $this->nonStdScu;
  }

  /**
   * @param int $nonStdScu
   *
   * @return GnuCashAccount $this
   */
  public function setNonStdScu(int $nonStdScu):GnuCashAccount
  {
    $this->nonStdScu = $nonStdScu;

    return $this;
  }

  /**
   * @return GnuCashAccount Parent.
   */
  public function getParent():GnuCashAccount
  {
    return $this->parent;
  }

  /**
   * @param GnuCashAccount $parent
   *
   * @return GnuCashAccount $this
   */
  public function setParent(GnuCashAccount $parent):GnuCashAccount
  {
    $this->parent = $parent;

    return $this;
  }

  /**
   * @return GnuCashAccount Children.
   */
  public function getChildren():Collection
  {
    return $this->children;
  }

  /**
   * @param Collection $children
   *
   * @return GnuCashAccount $this
   */
  public function setChildren(Collection $children):GnuCashAccount
  {
    $this->children = $children;

    return $this;
  }

  /**
   * @return string Code.
   *
   * @todo Clarify the meaning.
   */
  public function getCode():string
  {
    return $this->code;
  }

  /**
   * @param string $code
   *
   * @return GnuCashAccount $this
   */
  public function setCode(string $code):GnuCashAccount
  {
    $this->code = $code;

    return $this;
  }

  /**
   * @return string Description.
   *
   * @todo Clarify the meaning.
   */
  public function getDescription():string
  {
    return $this->description;
  }

  /**
   * @param string $description
   *
   * @return GnuCashAccount $this
   */
  public function setDescription(string $description):GnuCashAccount
  {
    $this->description = $description;

    return $this;
  }

  /**
   * @return bool Hidden.
   *
   * @todo Clarify the meaning.
   */
  public function getHidden():bool
  {
    return $this->hidden;
  }

  /**
   * @param bool $hidden
   *
   * @return GnuCashAccount $this
   */
  public function setHidden(bool $hidden):GnuCashAccount
  {
    $this->hidden = $hidden;

    return $this;
  }

  /**
   * @return bool Placeholder.
   *
   * @todo Clarify the meaning.
   */
  public function getPlaceholder():bool
  {
    return $this->placeholder;
  }

  /**
   * @param bool $placeholder
   *
   * @return GnuCashAccount $this
   */
  public function setPlaceholder(bool $placeholder):GnuCashAccount
  {
    $this->placeholder = $placeholder;

    return $this;
  }
}
