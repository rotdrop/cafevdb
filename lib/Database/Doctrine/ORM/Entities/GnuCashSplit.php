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
 * _AT_ORM\Table(name="GnuCashSplits")
 * _AT_ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository")
 * _AT_ORM\HasLifecycleCallbacks
 */
class GnuCashSplit implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\TimestampableEntity;

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
   * @ORM\Column(type="string", length=32, nullable=false, options={"fixed": true, "collation"="ascii_general_ci"})
   */
  private string $txGuid;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=32, nullable=false, options={"fixed": true, "collation"="ascii_general_ci"})
   */
  private string $accountGuid;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2028, nullable=false)
   * @ORM\Id
   */
  private string $memo;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2028, nullable=false)
   * @ORM\Id
   */
  private string $action;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=, nullable=false, options={"fixed": true, "collation"="ascii_general_ci"})
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
   * @ORM\Column(type="string", length=32, nullable=false, options={"fixed": true, "collation"="ascii_general_ci"})
   */
  private string $logGuid;
}
