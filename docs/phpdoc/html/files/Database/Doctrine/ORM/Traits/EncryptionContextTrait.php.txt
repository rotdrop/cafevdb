<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Traits;

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/** Define the special "encryptionContext" member. */
trait EncryptionContextTrait
{
  /**
   * In memory encryption context to support multi user encryption. This is a
   * multi-field encryption context indexed by the property name.
   */
  #[TSAttributes\Hidden]
  protected array $encryptionContext = [];

   /**
   * Add a user-id or group-id to the list of "encryption identities",
   * i.e. the list of identities which can read and write this entry.
   *
   * @param string $personality
   *
   * @return self
   */
  public function addEncryptionIdentity(string $personality):self
  {
    if (empty($this->encryptionContext)) {
      $this->encryptionContext = [];
    }
    if (!in_array($personality, $this->encryptionContext)) {
      $this->encryptionContext[] = $personality;
    }
    return $this;
  }

  /**
   * Remove a user-id or group-id to the list of "encryption identities",
   * i.e. the list of identities which can read and write this entry.
   *
   * @param string $personality
   *
   * @return self
   */
  public function removeEncryptionIdentity(string $personality):self
  {
    $pos = array_search($personality, $this->encryptionContext??[]);
    if ($pos !== false) {
      unset($this->encryptionContext[pos]);
      $this->encryptionContext = array_values($this->encryptionContext);
    }
    return $this;
  }

  /**
   * Ensure that the encryptionContext contains the user-id of the given musician.
   *
   * @param Musician $musician
   *
   * @return void
   */
  protected function sanitizeEncryptionContext(Musician $musician)
  {
    $userIdSlug = $musician->getUserIdSlug();
    if (!empty($userIdSlug) && !in_array($userIdSlug, $this->encryptionContext ?? [])) {
      $this->encryptionContext[] = $userIdSlug;
    }
  }

}
