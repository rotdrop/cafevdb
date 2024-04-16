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
 * Link to the GnuCash commodities table.
 *
 * @ORM\Table(name="GnuCashCommodities")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository")
 * @ORM\HasLifecycleCallbacks
 */
class GnuCashCommodity implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;

// CREATE TABLE `commodities` (
//   `guid` varchar(32) NOT NULL,
//   `namespace` varchar(2048) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
//   `mnemonic` varchar(2048) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
//   `fullname` varchar(2048) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
//   `cusip` varchar(2048) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
//   `fraction` int(11) NOT NULL,
//   `quote_flag` int(11) NOT NULL,
//   `quote_source` varchar(2048) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
//   `quote_tz` varchar(2048) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL
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
   * @ORM\Column(type="string", length=2024, nullable=false, options={"collation"="ascii_general_ci"})
   */
  private string $namespace;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2028, nullable=false)
   */
  private string $mnemonic;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2028)
   */
  private string $fullname;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2028)
   */
  private string $cusip;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   */
  private int $fraction;

  /**
   * @var int
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private bool $quoteFlag;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2028, nullable=false, options={"collation"="ascii_general_ci"})
   */
  private string $quoteSource;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2028, nullable=false, options={"collation"="ascii_general_ci"})
   */
  private string $quoteTz;

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
   * @return GnuCashCommodity $this
   */
  public function setGuid(string $guid):GnuCashCommodity
  {
    $this->guid = $guid;

    return $this;
  }

  /**
   * @return string Namespace.
   *
   * @todo Clarify the meaning.
   */
  public function getNamespace():string
  {
    return $this->namespace;
  }

  /**
   * @param string $namespace
   *
   * @return GnuCashCommodity $this
   */
  public function setNamespace(string $namespace):GnuCashCommodity
  {
    $this->namespace = $namespace;

    return $this;
  }

  /**
   * @return string Mnemonic.
   *
   * @todo Clarify the meaning.
   */
  public function getMnemonic():string
  {
    return $this->mnemonic;
  }

  /**
   * @param string $mnemonic
   *
   * @return GnuCashCommodity $this
   */
  public function setMnemonic(string $mnemonic):GnuCashCommodity
  {
    $this->mnemonic = $mnemonic;

    return $this;
  }

  /**
   * @return string Fullname.
   *
   * @todo Clarify the meaning.
   */
  public function getFullname():string
  {
    return $this->fullname;
  }

  /**
   * @param string $fullname
   *
   * @return GnuCashCommodity $this
   */
  public function setFullname(string $fullname):GnuCashCommodity
  {
    $this->fullname = $fullname;

    return $this;
  }

  /**
   * @return string Cusip.
   *
   * @todo Clarify the meaning.
   */
  public function getCusip():string
  {
    return $this->cusip;
  }

  /**
   * @param string $cusip
   *
   * @return GnuCashCommodity $this
   */
  public function setCusip(string $cusip):GnuCashCommodity
  {
    $this->cusip = $cusip;

    return $this;
  }

  /**
   * @return int Fraction.
   *
   * @todo Clarify the meaning.
   */
  public function getFraction():int
  {
    return $this->fraction;
  }

  /**
   * @param int $fraction
   *
   * @return GnuCashCommodity $this
   */
  public function setFraction(int $fraction):GnuCashCommodity
  {
    $this->fraction = $fraction;

    return $this;
  }

  /**
   * @return bool QuoteFlag.
   *
   * @todo Clarify the meaning.
   */
  public function getQuoteFlag():bool
  {
    return $this->quoteFlag;
  }

  /**
   * @param bool $quoteFlag
   *
   * @return GnuCashCommodity $this
   */
  public function setQuoteFlag(bool $quoteFlag):GnuCashCommodity
  {
    $this->quoteFlag = $quoteFlag;

    return $this;
  }

  /**
   * @return string QuoteSource.
   *
   * @todo Clarify the meaning.
   */
  public function getQuoteSource():string
  {
    return $this->quoteSource;
  }

  /**
   * @param string $quoteSource
   *
   * @return GnuCashCommodity $this
   */
  public function setQuoteSource(string $quoteSource):GnuCashCommodity
  {
    $this->quoteSource = $quoteSource;

    return $this;
  }

  /**
   * @return string QuoteTz.
   *
   * @todo Clarify the meaning.
   */
  public function getQuoteTz():string
  {
    return $this->quoteTz;
  }

  /**
   * @param string $quoteTz
   *
   * @return GnuCashCommodity $this
   */
  public function setQuoteTz(string $quoteTz):GnuCashCommodity
  {
    $this->quoteTz = $quoteTz;

    return $this;
  }
}
