<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library se Doctrine\ORM\Tools\Setup;is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

/**
 * EmailAttachments
 *
 * @ORM\Table(name="EmailAttachments")
 * @ORM\Entity
 */
class EmailAttachment implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\TimestampableEntity;
  use \OCA\CAFEVDB\Wrapped\Gedmo\Blameable\Traits\BlameableEntity;

  /**
   * @var string
   * @ORM\Column(type="string", length=512, nullable=false)
   * @ORM\Id
   */
  private $fileName;

  /**
   * @var EmailDraft
   * @ORM\ManyToOne(targetEntity="EmailDraft", inversedBy="fileAttachments")
   * @ORM\JoinColumn(onDelete="CASCADE")
   */
  private $draft;

  /**
   * Set messageId.
   *
   * @param int|EmailDraft $draft
   *
   * @return EmailAttachment
   */
  public function setDraft($draft):EmailAttachment
  {
    $this->draft = $draft;

    return $this;
  }

  /**
   * Get email-draft
   *
   * @return null|EmailDraft
   */
  public function getDraft()
  {
    return $this->draft;
  }

  /**
   * Set fileName.
   *
   * @param string $fileName
   *
   * @return EmailAttachment
   */
  public function setFileName(string $fileName):EmailAttachment
  {
    $this->fileName = $fileName;

    return $this;
  }

  /**
   * Get fileName.
   *
   * @return string
   */
  public function getFileName():string
  {
    return $this->fileName;
  }
}
