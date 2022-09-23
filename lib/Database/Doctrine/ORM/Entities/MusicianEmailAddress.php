<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use \Mail_RFC822;
use OCA\CAFEVDB\Common\PHPMailer;
use InvalidArgumentException;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event;

/**
 * InstrumentInsurance
 *
 * @ORM\Table(name="MusicianEmailAddresses")
 * @ORM\Entity
 */
class MusicianEmailAddress implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\TimestampableEntity;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=254, nullable=false, options={"collation"="ascii_general_ci"})
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="NONE")
   */
  private $address;

  /**
   * @var Musician
   *
   * @ORM\ManyToOne(targetEntity="Musician", inversedBy="emailAddresses", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $musician;

  /**
   * @param null|string $address
   *
   * @param null|Musician $musician
   */
  public function __construct(string $address = null, Musician $musician = null)
  {
    $this->arrayCTOR();
    $this->musician = $musician;
    $this->setAddress($address);
  }

  /**
   * @return string
   */
  public function getAddress():string
  {
    return $this->address;
  }

  /**
   * @param null|string $address
   *
   * @return MusicianEmailAddress
   */
  public function setAddress(?string $address):MusicianEmailAddress
  {
    if (!empty($address)) {
      if (!self::validateAddress($address)) {
        throw new InvalidArgumentException('Email-address "' . $address . '" fails validation.');
      }
      $this->address = $address;
    }
    return $this;
  }

  /**
   * @return Musician
   */
  public function getMusician():Musician
  {
    return $this->musician;
  }

  /**
   * @param int|Musician $musican
   *
   * @return MusicianEmailAddress
   */
  public function setMusician($musician):MusicianEmailAddress
  {
    $this->musician = $musician;
    return $this;
  }

  /**
   * Check whether this is the primary address of $this->musician.
   *
   * @return bool
   */
  public function isPrimaryAddress():bool
  {
    return $this->musician->getEmailAddress() == $this->address;
  }

  /**
   * Validate the given email address.
   *
   * @param string $address
   *
   * @return bool
   */
  public static function validateAddress(string $address):bool
  {
    $phpMailer = new PHPMailer(exceptions: true);
    $parser = new Mail_RFC822(null, null, null, false);
    $parsedAddresses = $parser->parseAddressList($address);
    $parseError = $parser->parseError();
    if ($parseError !== false) {
      return false;
    }
    if (count($parsedAddresses) !== 1) {
      return false;
    }
    $emailRecord = reset($parsedAddresses);
    $email = $emailRecord->mailbox.'@'.$emailRecord->host;
    if ($emailRecord->host == 'localhost') {
      return false;
    } elseif (!$phpMailer->validateAddress($email)) {
      return false;
    }
    return true;
  }
}
