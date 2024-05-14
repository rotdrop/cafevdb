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
 *
 * @ORM\Table(
 *   name="GnuCashSplits",
 *   indexes={
 *     @ORM\Index(name="splits_tx_guid_index", columns={"tx_guid"}),
 *     @ORM\Index(name="splits_account_guid_index", columns={"account_guid"})
 *   }
 * )
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository")
 * @ORM\HasLifecycleCallbacks
 */
class GnuCashSplit implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;

  // CREATE TABLE `splits` (
  //   `guid` varchar(32) NOT NULL,
  //   `tx_guid` varchar(32) NOT NULL,
  //   `account_guid` varchar(32) NOT NULL,
  //   `memo` varchar(2048) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  //   `action` varchar(2048) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  //   `reconcile_state` varchar(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  //   `reconcile_date` datetime DEFAULT '1970-01-01 00:00:00',
  //   `value_num` bigint(20) NOT NULL,
  //   `value_denom` bigint(20) NOT NULL,
  //   `quantity_num` bigint(20) NOT NULL,
  //   `quantity_denom` bigint(20) NOT NULL,
  //   `lot_guid` varchar(32) DEFAULT NULL
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
   * @ORM\ManyToOne(targetEntity="GnuCashTransaction", inversedBy="splits", fetch="EXTRA_LAZY")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="tx_guid", referencedColumnName="guid", nullable=false)
   * )
   */
  private GnuCashTransaction $transaction;

  /**
   * @var string
   *
   * @ORM\ManyToOne(targetEntity="GnuCashAccount", fetch="EXTRA_LAZY")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="account_guid", referencedColumnName="guid", nullable=false)
   * )
   */
  private GnuCashAccount $account;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2028, nullable=false)
   */
  private string $memo;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2028, nullable=false)
   */
  private string $action;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=1, nullable=false, options={"fixed": true, "collation"="ascii_general_ci"})
   */
  private string $reconcileState;

  /**
   * @var \DateTimeImmutable
   * @ORM\Column(type="datetime_immutable", nullable=true)
   */
  protected $reconcileDate;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", length=20, nullable=false)
   */
  private int $valueNum;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", length=20, nullable=false)
   */
  private int $valueDenom;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", length=20, nullable=false)
   */
  private int $quantityNum;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", length=20, nullable=false)
   */
  private int $quantityDenom;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=32, nullable=true, options={"default": null, "fixed": true, "collation"="ascii_general_ci"})
   */
  private string $lotGuid;

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
   * @return GnuCashSplit $this
   */
  public function setGuid(string $guid):GnuCashSplit
  {
    $this->guid = $guid;

    return $this;
  }

  /**
   * @return GnuCashTransaction
   */
  public function getTransaction():GnuCashTransaction
  {
    return $this->transaction;
  }

  /**
   * @param GnuCashTransaction $transaction
   *
   * @return GnuCashSplit $this
   */
  public function setTransaction(GnuCashTransaction $transaction):GnuCashSplit
  {
    $this->transaction = $transaction;

    return $this;
  }

  /**
   * @return GnuCashAccount
   */
  public function getAccount():GnuCashAccount
  {
    return $this->account;
  }

  /**
   * @param GnuCashAccount $account
   *
   * @return GnuCashSplit $this
   */
  public function setAccount(GnuCashAccount $account):GnuCashSplit
  {
    $this->account = $account;

    return $this;
  }

  /**
   * @return string Memo.
   *
   * @todo Clarify the meaning.
   */
  public function getMemo():string
  {
    return $this->memo;
  }

  /**
   * @param string $memo
   *
   * @return GnuCashAccount $this
   */
  public function setMemo(string $memo):GnuCashAccount
  {
    $this->memo = $memo;

    return $this;
  }

  /**
   * @return string Action.
   *
   * @todo Clarify the meaning.
   */
  public function getAction():string
  {
    return $this->action;
  }

  /**
   * @param string $action
   *
   * @return GnuCashAccount $this
   */
  public function setAction(string $action):GnuCashAccount
  {
    $this->action = $action;

    return $this;
  }

  /**
   * @return string ReconcileState.
   *
   * @todo Clarify the meaning.
   */
  public function getReconcileState():string
  {
    return $this->reconcileState;
  }

  /**
   * @param string $reconcileState
   *
   * @return GnuCashAccount $this
   */
  public function setReconcileState(string $reconcileState):GnuCashAccount
  {
    $this->reconcileState = $reconcileState;

    return $this;
  }

  /**
   * @return int VALUENUM.
   */
  public function getValueNum():int
  {
    return $this->valueNum;
  }

  /**
   * @param int $valueNum
   *
   * @return GnuCashSlot $this
   */
  public function setValueNum(int $valueNum):GnuCashSlot
  {
    $this->valueNum = $valueNum;

    return $this;
  }

  /**
   * @return int VALUEDENOM.
   */
  public function getValueDenom():int
  {
    return $this->valueDenom;
  }

  /**
   * @param int $valueDenom
   *
   * @return GnuCashSlot $this
   */
  public function setValueDenom(int $valueDenom):GnuCashSlot
  {
    $this->valueDenom = $valueDenom;

    return $this;
  }

  /**
   * @return int VALUENUM.
   */
  public function getQuantityNum():int
  {
    return $this->quantityNum;
  }

  /**
   * @param int $quantityNum
   *
   * @return GnuCashSlot $this
   */
  public function setQuantityNum(int $quantityNum):GnuCashSlot
  {
    $this->quantityNum = $quantityNum;

    return $this;
  }

  /**
   * @return int QUANTITYDENOM.
   */
  public function getQuantityDenom():int
  {
    return $this->quantityDenom;
  }

  /**
   * @param int $quantityDenom
   *
   * @return GnuCashSlot $this
   */
  public function setQuantityDenom(int $quantityDenom):GnuCashSlot
  {
    $this->quantityDenom = $quantityDenom;

    return $this;
  }

  /**
   * @return string LotGuid.
   *
   * @todo Clarify the meaning.
   */
  public function getLotGuid():string
  {
    return $this->lotGuid;
  }

  /**
   * @param string $lotGuid
   *
   * @return GnuCashAccount $this
   */
  public function setLotGuid(string $lotGuid):GnuCashAccount
  {
    $this->lotGuid = $lotGuid;

    return $this;
  }
}
