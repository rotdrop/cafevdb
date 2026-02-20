<?php
declare(strict_types=1);
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022, 2024 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This file based on ldap_contacts_backend, copyright 2020 Arthur Schiwon
 * <blizzz@arthur-schiwon.de>
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

use Sabre\CardDAV\ICard;
use Sabre\DAV\Exception\NotImplemented;
use Sabre\VObject\Component\VCard;

/** ICard implementation linking to the musicians database. */
class MusicianCard implements ICard
{
  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    private string $uri,
    private ?int $lastModified,
    private VCard $vCard
  ) {
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function put($data)
  {
    throw new NotImplemented();
  }

  /** {@inheritdoc} */
  public function get()
  {
    return $this->vCard->serialize();
  }

  /**
   * Non-interface method, return the underlying vCard.
   *
   * @return VCard
   */
  public function getVCard():VCard
  {
    return $this->vCard;
  }

  /** {@inheritdoc} */
  public function getContentType()
  {
    return 'text/vcard; charset=utf-8';
  }

  /** {@inheritdoc} */
  public function getETag()
  {
    return md5((string)$this->getLastModified());
  }

  /** {@inheritdoc} */
  public function getSize()
  {
    return \strlen($this->get());
  }

  /** {@inheritdoc} */
  public function delete()
  {
    throw new NotImplemented();
  }

  /** {@inheritdoc} */
  public function getName()
  {
    return $this->uri;
  }

  /** {@inheritdoc} */
  public function setName($name)
  {
    throw new NotImplemented();
  }

  /** {@inheritdoc} */
  public function getLastModified()
  {
    return $this->lastModified;
  }
}
