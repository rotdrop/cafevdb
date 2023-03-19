<?php
declare(strict_types=1);
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022, 2022 Claus-Justus Heine
 *
 * This file based on ldap_contacts_backend, copyright 2020 Arthur Schiwon
 * <blizzz@arthur-schiwon.de>
 *
 * @license GNU AGPL version 3 or any later version
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\AddressBook;

/** Bizarre single-implementation interface ... */
interface ICardBackend
{
  /** @return string */
  public function getURI():string;

  /** @return string */
  public function getDisplayName():string;

  /**
   * @param string $name
   *
   * @return MusicianCard
   *
   * @throws \Sabre\DAV\Exception\NotFound
   */
  public function getCard(string $name):MusicianCard;

  /**
   * @param string $pattern
   *
   * @param array $properties
   *
   * @return MusicianCard[]
   */
  public function searchCards(string $pattern, array $properties): array;

  /**
   * @return MusicianCard[]
   */
  public function getCards(): array;

  /**
   * Get the time of the last modification for either the address-book
   * or an individual entry.
   *
   * @param string|null $uri
   *
   * @return int Seconds sinc 1970-0-0 of last modification.
   */
  public function getLastModified(?string $uri = null):int;
}
