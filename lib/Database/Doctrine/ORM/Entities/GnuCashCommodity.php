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
 * _AT_ORM\Table(name="GnuCashCommodities")
 * _AT_ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository")
 * _AT_ORM\HasLifecycleCallbacks
 */
class GnuCashCommodity implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\TimestampableEntity;

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
   * @ORM\Column(type="string", length=32, nullable=false, options={"fixed": true, "collation"="ascii_general_ci")
   * @ORM\Id
   */
  private string $guid;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2024, nullable=false, options={"collation"="ascii_general_ci")
   */
  private string $namespace;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2028, nullable=false)
   * @ORM\Id
   */
  private string $mnemonic;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2028)
   * @ORM\Id
   */
  private string $fullname;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2028)
   * @ORM\Id
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
}
