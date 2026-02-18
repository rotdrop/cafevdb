<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2022, 2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Cloud\Entities;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/** A Db entry modelling a ToS exception. */
class TOSException extends Entity implements JsonSerializable
{
  public $id;

  /**
   * We only override public shares in order to support simple download-links
   * for selected shares.
   */
  protected $shareToken;

  /**
   * Comma separated list of networds in CIDR notation, IPv4 or IPv6.
   */
  protected $ipRanges;

  /** CTOR */
  public function __construct()
  {
    // $this->addType('id', 'integer');
    $this->addType('shareToken', 'string');
    $this->addType('ipRanges', 'string');
  }

  /** {@inheritdoc} */
  public function jsonSerialize():mixed
  {
    return [
      'id' => $this->id,
      'shareToken' => $this->shareToken,
      'ipRanges' => explode(',', $this->ipRanges),
    ];
  }
}
