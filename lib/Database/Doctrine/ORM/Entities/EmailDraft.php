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

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * EmailDraft
 *
 * @ORM\Table(name="EmailDrafts")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EmailDraftsRepository")
 */
class EmailDraft implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\TimestampableEntity;
  use \Gedmo\Blameable\Traits\BlameableEntity;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=false)
   */
  private $subject;

  /**
   * @var string
   *
   * @ORM\Column(type="json", nullable=false, options={"comment"="Message Data Without Attachments"})
   */
  private $data;

  /**
   * @var EmailAttachment
   * @ORM\OneToMany(targetEntity="EmailAttachment", mappedBy="draft")
   */
  private $fileAttachments;

  public function __construct() {
    $this->arrayCTOR();
    $this->fileAttachments = new ArrayCollection();
  }

  /**
   * Get id.
   *
   * @return int
   */
  public function getId():int
  {
    return $this->id;
  }

  /**
   * Set subject.
   *
   * @param string $subject
   *
   * @return EmailDrafts
   */
  public function setSubject($subject):EmailDraft
  {
    $this->subject = $subject;

    return $this;
  }

  /**
   * Get subject.
   *
   * @return string
   */
  public function getSubject():string
  {
    return $this->subject;
  }

  /**
   * Set data.
   *
   * @param array $data
   *
   * @return EmailDrafts
   */
  public function setData(array $data):EmailDraft
  {
    $this->data = $data;

    return $this;
  }

  /**
   * Get data.
   *
   * @return array
   */
  public function getData():array
  {
    return $this->data;
  }

  /**
   * Set fileAttachments.
   *
   * @param Collection $fileAttachments
   *
   * @return EmailDrafts
   */
  public function setFileAttachments(Collection $fileAttachments):EmailDraft
  {
    $this->fileAttachments = $fileAttachments;

    return $this;
  }

  /**
   * Get fileAttachments.
   *
   * @return Collection
   */
  public function getFileAttachments():Collection
  {
    return $this->fileAttachments;
  }
}
