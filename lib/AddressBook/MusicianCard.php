<?php
declare(strict_types=1);
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 *
 */

namespace OCA\CAFEVDB\AddressBook;

use Sabre\CardDAV\ICard;
use Sabre\DAV\Exception\NotImplemented;
use Sabre\VObject\Component\VCard;

class MusicianCard implements ICard {

  /** @var VCard */
  private $vCard;

  public function __construct(VCard $vCard) {
    $this->vCard = $vCard;
  }

  /**
   * @inheritDoc
   */
  public function put($data) {
    throw new NotImplemented();
  }

  /**
   * @inheritDoc
   */
  public function get() {
    $data = $this->vCard->serialize();
    $trace = debug_backtrace();
    $shift = 2;
    $caller = $trace[$shift];
    $file = $caller['file'];
    $line = $caller['line'];
    $caller = $trace[$shift+1];
    $class = $caller['class'];
    $method = $caller['function'];

    $prefix = $file.':'.$line.': '.$class.'::'.$method.': ';

    // \OCP\Util::writeLog('cafevdb', $prefix.': '.$data, \OCP\Util::INFO);
    return $data;
  }

  /**
   * Non-interface method, return the card as plain array.
   */
  public function getData(): array {
    return $this->vCard->children();
  }

  /**
   * @inheritDoc
   */
  public function getContentType() {
    return 'text/vcard; charset=utf-8';
  }

  /**
   * @inheritDoc
   */
  public function getETag() {
    return null;
  }

  /**
   * @inheritDoc
   */
  public function getSize() {
    return \strlen($this->get());
  }

  /**
   * @inheritDoc
   */
  public function delete() {
    throw new NotImplemented();
  }

  /**
   * @inheritDoc
   */
  public function getName() {
    return $this->vCard->URI;
  }

  /**
   * @inheritDoc
   */
  public function setName($name) {
    throw new NotImplemented();
  }

  /**
   * @inheritDoc
   */
  public function getLastModified() {
    return null;
  }
}
