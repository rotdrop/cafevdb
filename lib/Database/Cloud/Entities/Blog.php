<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

use Doctrine\DBAL\Types\Types;

use OCP\AppFramework\Db\Entity;

/** Cloud blog entity. */
class Blog extends Entity
{
  public $id;
  protected $author;
  protected $created;
  protected $editor;
  protected $modified;
  protected $message;
  protected $inReplyTo;
  protected $deleted;
  protected $priority;
  protected $popup;
  protected $reader;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    // $this->addType('id', 'int'); this is default
    $this->addType('author', 'string');
    $this->addType('created', 'int');
    $this->addType('editor', 'string');
    $this->addType('modified', 'int');
    $this->addType('message', 'string');
    $this->addType('inReplyTo', 'int');
    $this->addType('deleted', 'bool');
    $this->addType('priority', 'int');
    $this->addType('popup', 'bool');
    $this->addType('reader', 'string');
  }
  // phpcs:enable
}
