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
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

/**
 * Link to the Gnucash accounts table.
 *
 * @ORM\Table(
 *   name="GnuCashTransactions",
 *   indexes={
 *     @ORM\Index(name="tx_post_date_index", columns={"post_date"})
 *   }
 * )
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository")
 * @ORM\HasLifecycleCallbacks
 */
class GnuCashTransaction implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=32, nullable=false, options={"fixed": true, "collation"="ascii_general_ci"})
   * @ORM\Id
   */
  private string $guid;

  /**
   * @var GnuCashCommodity
   *
   * @ORM\ManyToOne(targetEntity="GnuCashCommodity", fetch="EXTRA_LAZY")
   * @ORM\JoinColumns({
   *   @ORM\JoinColumn(name="currency_guid", referencedColumnName="guid", nullable=false)
   * })
   */
  private GnuCashCommodity $currency;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2028, nullable=false)
   *
   * @todo What is this?
   */
  private string $num;

  /**
   * @var \DateTimeImmutable
   * @ORM\Column(type="datetime_immutable", options={"default": "1970-01-01 00:00:00.000000"})
   */
  private $postDate;

  /**
   * @var \DateTimeImmutable
   * @ORM\Column(type="datetime_immutable", options={"default": "1970-01-01 00:00:00.000000"})
   */
  private $enterDate;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2028, nullable=true)
   */
  private string $description;

  /**
   * @var Collection
   *
   * Link back to the splits belonging to this transaction.
   *
   * @ORM\OneToMany(targetEntity="GnuCashSplit", mappedBy="transaction", fetch="EXTRA_LAZY")
   */
  private Collection $splits;

// CREATE TABLE `transactions` (
//   `guid` varchar(32) NOT NULL,
//   `currency_guid` varchar(32) NOT NULL,
//   `num` varchar(2048) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
//   `post_date` datetime DEFAULT '1970-01-01 00:00:00',
//   `enter_date` datetime DEFAULT '1970-01-01 00:00:00',
//   `description` varchar(2048) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL
// ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

  /** {@inheritdoc} */
  public function __construct()
  {
    $this->__wakeup();
    $this->splits = new ArrayCollection;
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
   * @return GnuCashTransaction $this
   */
  public function setGuid(string $guid):GnuCashTransaction
  {
    $this->guid = $guid;

    return $this;
  }

  /**
   * For the purpose of this application the "currency" is just the currency.
   *
   * @return GnuCashCommodity Currency.
   */
  public function getCurrency():GnuCashCommodity
  {
    return $this->currency;
  }

  /**
   * @param GnuCashCommodity $currency
   *
   * @return GnuCashAccount $this
   */
  public function setCurrency(GnuCashCommodity $currency):GnuCashAccount
  {
    $this->currency = $currency;

    return $this;
  }

  /**
   * @return string Num.
   *
   * @todo Clarify the meaning.
   */
  public function getNum():string
  {
    return $this->num;
  }

  /**
   * @param string $num
   *
   * @return GnuCashAccount $this
   */
  public function setNum(string $num):GnuCashAccount
  {
    $this->num = $num;

    return $this;
  }

  /**
   * Get postDate.
   *
   * @return \DateTime|null
   */
  public function getPostDate():?DateTimeInterface
  {
    return $this->postDate;
  }

  /**
   * Set postDate.
   *
   * @param \DateTime|null $postDate
   *
   * @return CompositePayment
   */
  public function setPostDate($postDate = null):CompositePayment
  {
    $this->postDate = self::convertToDateTime($postDate);

    return $this;
  }

  /**
   * Get enterDate.
   *
   * @return \DateTime|null
   */
  public function getEnterDate():?DateTimeInterface
  {
    return $this->enterDate;
  }

  /**
   * Set enterDate.
   *
   * @param \DateTime|null $enterDate
   *
   * @return CompositePayment
   */
  public function setEnterDate($enterDate = null):CompositePayment
  {
    $this->enterDate = self::convertToDateTime($enterDate);

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
   * @return GnuCashAccount Splits.
   */
  public function getSplits():Collection
  {
    return $this->splits;
  }

  /**
   * @param Collection $splits
   *
   * @return GnuCashAccount $this
   */
  public function setSplits(Collection $splits):GnuCashTransaction
  {
    $this->splits = $splits;

    return $this;
  }
}
