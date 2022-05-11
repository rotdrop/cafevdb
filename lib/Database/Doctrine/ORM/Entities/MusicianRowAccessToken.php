<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
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
 *
 */

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event;

/**
 * MusicianRowAccessToken
 *
 * A security token which grants the musician access to rows with its own
 * data. The idea is to encrypt a 256 bit random token with the users public
 * key. When the user logs in it can decrypt the token and get access to
 * selected data of the musician matching the associated id when in addition
 * the user-id matches also.
 *
 * @ORM\Table(name="MusicianRowAccessTokens")
 * @ORM\Entity
 */
class MusicianRowAccessToken implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\TimestampableEntity;

  const HASH_LENGTH = 512;

  /**
   * @var Musician
   * @ORM\OneToOne(targetEntity="Musician", inversedBy="rowAccessToken", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $musician;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, unique=true, nullable=true, options={"collation"="ascii_bin"})
   */
  private $userId;

  /**
   * @var string
   *
   * This field stores a SHA-512 hashed random token. The user supplies the
   * un-hashed token from its encrypted config-space, the user-token is
   * hashed, if the hashes and the user-id match then the user gets access to
   * its data. Storage is a hex-string
   *
   * @ORM\Column(type="string", length=128, unique=true, options={"fixed"=true,"collation"="ascii_bin"})
   */
  private $accessTokenHash;

  public function __construct(?Musician $musician = null, ?string $token = null) {
    $this->arrayCTOR();
    $this->musician = $musician;
    $this->userId = empty($musician) ? null : $musician->getUserIdSlug();
    $this->accessTokenHash = empty($token) ? null : $this->computeHash($token);
    if (!empty($musician)) {
      $musician->setRowAccessToken($this);
    }
  }

  /**
   * Set musician.
   *
   * @param Musician $musician
   *
   * @return MusicianRowAccessToken
   */
  public function setMusician(Musician $musician):MusicianRowAccessToken
  {
    $this->musician = $musician;

    return $this;
  }

  /**
   * Get musician.
   *
   * @return Musician
   */
  public function getMusician():Musician
  {
    return $this->musician;
  }

  /**
   * Set userId.
   *
   * @param string|null $userId
   *
   * @return MusicianRowAccessToken
   */
  public function setUserId(?string $userId):MusicianRowAccessToken
  {
    $this->userId = $userId;

    return $this;
  }

  /**
   * Get userId.
   *
   * @return string
   */
  public function getUserId():?string
  {
    return $this->userId;
  }

  /**
   * Set accessToken.
   *
   * @param string $accessToken Unhashed binary token, only a sha-hash is stored
   *
   * @return MusicianRowAccessToken
   */
  public function setAccessToken(string $accessToken):MusicianRowAccessToken
  {
    $this->accessTokenHash = $this->computeHash($accessToken);

    return $this;
  }

  /**
   * Set accessTokenHash.
   *
   * @param string|null $accessTokenHash
   *
   * @return MusicianRowAccessToken
   */
  public function setAccessTokenHash(?string $accessTokenHash):MusicianRowAccessToken
  {
    $this->accessTokenHash = $accessTokenHash;

    return $this;
  }

  /**
   * Get accessTokenHash.
   *
   * @return string
   */
  public function getAccessTokenHash():?string
  {
    return $this->accessTokenHash;
  }

  private function computeHash(string $value):string
  {
    return \hash('sha' . self::HASH_LENGTH, $value, binary: false);
  }
}
