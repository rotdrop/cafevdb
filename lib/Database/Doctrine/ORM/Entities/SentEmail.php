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
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

/**
 * SentEmail
 *
 * @ORM\Table(name="SentEmails")
 * @ORM\Entity
 */
class SentEmail
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\CreatedAtEntity;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=false, options={"collation"="ascii_bin"})
   * @ORM\Id
   */
  private $messageId;

  /**
   * @var string
   * @Gedmo\Blameable(on="create")
   * @ORM\Column(nullable=true)
   */
  protected $createdBy;

  /**
   * @var string
   *
   * @ORM\Column(type="text", length=0, nullable=false)
   */
  private $bulkRecipients;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=32, nullable=false, options={"fixed"=true, "collation"="ascii_bin"})
   * @Gedmo\Slug(
   *   fields={"bulkRecipients"},
   *   updatable=true,
   *   unique=false,
   *   handlers={
   *     @Gedmo\SlugHandler(
   *       class="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\HashHandler",
   *       options={@Gedmo\SlugHandlerOption(name="algorithm", value="md5")}
   *     )
   *   }
   * )
   */
  private $bulkRecipientsHash;

  /**
   * @var string
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private $cc;

  /**
   * @var string
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private $bcc;

  /**
   * @var string
   *
   * @ORM\Column(type="text", nullable=false)
   */
  private $subject;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=32, nullable=false, options={"fixed"=true, "collation"="ascii_bin"})
   * @Gedmo\Slug(
   *   fields={"subject"},
   *   updatable=true,
   *   unique=false,
   *   handlers={
   *     @Gedmo\SlugHandler(
   *       class="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\HashHandler",
   *       options={@Gedmo\SlugHandlerOption(name="algorithm", value="md5")}
   *     )
   *   }
   * )
   */
  private $subjectHash;

  /**
   * @var string
   *
   * @ORM\Column(type="text", length=0, nullable=false)
   */
  private $htmlBody;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=32, nullable=false, options={"fixed"=true, "collation"="ascii_bin"})
   * @Gedmo\Slug(
   *   fields={"htmlBody"},
   *   updatable=true,
   *   unique=false,
   *   handlers={
   *     @Gedmo\SlugHandler(
   *       class="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\HashHandler",
   *       options={@Gedmo\SlugHandlerOption(name="algorithm", value="md5")}
   *     )
   *   }
   * )
   */
  private $htmlBodyHash;

  /**
   * @var SentEmail
   *
   * @ORM\ManyToOne(targetEntity="SentEmail", inversedBy="referencedBy", cascade={"persist"}, fetch="EXTRA_LAZY")
   * @ORM\JoinColumn(name="reference_id", referencedColumnName="message_id")
   */
  private $referencing;

  /**
   * @var Collection
   *
   * @ORM\OneToMany(targetEntity="SentEmail", mappedBy="referencing", indexBy="messageId", cascade={"persist"}, fetch="EXTRA_LAZY")
   * @ORM\OrderBy({"bulkRecipients" = "ASC"})
   */
  private $referencedBy;

  public function __construct()
  {
    $this->arrayCTOR();
    $this->referencedBy = new ArrayCollection;
  }

  /**
   * Sets messageId.
   *
   * @param string $messageId
   *
   * @return SentEmail $this
   */
  public function setMessageId(string $messageId):SentEmail
  {
    $this->messageId = $messageId;

    return $this;
  }

  /**
   * Returns messageId.
   *
   * @return string
   */
  public function getMessageId():string
  {
    return $this->messageId;
  }

  /**
   * Sets createdBy.
   *
   * @param string $createdBy
   *
   * @return SentEmail $this
   */
  public function setCreatedBy(string $createdBy):SentEmail
  {
    $this->createdBy = $createdBy;

    return $this;
  }

  /**
   * Returns createdBy.
   *
   * @return string
   */
  public function getCreatedBy():string
  {
    return $this->createdBy;
  }

  /**
   * Sets bulkRecipients.
   *
   * @param string $bulkRecipients
   *
   * @return SentEmail $this
   */
  public function setBulkRecipients(string $bulkRecipients):SentEmail
  {
    $this->bulkRecipients = $bulkRecipients;

    return $this;
  }

  /**
   * Returns bulkRecipients.
   *
   * @return string
   */
  public function getBulkRecipients():string
  {
    return $this->bulkRecipients;
  }

  /**
   * Sets bulkRecipientsHash.
   *
   * @param string $bulkRecipientsHash
   *
   * @return SentEmail $this
   */
  public function setBulkRecipientsHash(string $bulkRecipientsHash):SentEmail
  {
    $this->bulkRecipientsHash = $bulkRecipientsHash;

    return $this;
  }

  /**
   * Returns bulkRecipientsHash.
   *
   * @return string
   */
  public function getBulkRecipientsHash():string
  {
    return $this->bulkRecipientsHash;
  }

  /**
   * Sets htmlBody.
   *
   * @param string $htmlBody
   *
   * @return SentEmail $this
   */
  public function setHtmlBody(string $htmlBody):SentEmail
  {
    $this->htmlBody = $htmlBody;

    return $this;
  }

  /**
   * Returns htmlBody.
   *
   * @return string
   */
  public function getHtmlBody():string
  {
    return $this->htmlBody;
  }

  /**
   * Sets htmlBodyHash.
   *
   * @param string $htmlBodyHash
   *
   * @return SentEmail $this
   */
  public function setHtmlBodyHash(string $htmlBodyHash):SentEmail
  {
    $this->htmlBodyHash = $htmlBodyHash;

    return $this;
  }

  /**
   * Returns htmlBodyHash.
   *
   * @return string
   */
  public function getHtmlBodyHash():string
  {
    return $this->htmlBodyHash;
  }

  /**
   * Sets subject.
   *
   * @param string $subject
   *
   * @return SentEmail $this
   */
  public function setSubject(string $subject):SentEmail
  {
    $this->subject = $subject;

    return $this;
  }

  /**
   * Returns subject.
   *
   * @return string
   */
  public function getSubject():string
  {
    return $this->subject;
  }

  /**
   * Sets subjectHash.
   *
   * @param string $subjectHash
   *
   * @return SentEmail $this
   */
  public function setSubjectHash(string $subjectHash):SentEmail
  {
    $this->subjectHash = $subjectHash;

    return $this;
  }

  /**
   * Returns subjectHash.
   *
   * @return string
   */
  public function getSubjectHash():string
  {
    return $this->subjectHash;
  }

  /**
   * Sets cc.
   *
   * @param string $cc
   *
   * @return SentEmail $this
   */
  public function setCc(string $cc):SentEmail
  {
    $this->cc = $cc;

    return $this;
  }

  /**
   * Returns cc.
   *
   * @return string
   */
  public function getCc():string
  {
    return $this->cc;
  }

  /**
   * Sets bcc.
   *
   * @param string $bcc
   *
   * @return SentEmail $this
   */
  public function setBcc(string $bcc):SentEmail
  {
    $this->bcc = $bcc;

    return $this;
  }

  /**
   * Returns bcc.
   *
   * @return string
   */
  public function getBcc():string
  {
    return $this->bcc;
  }

  /**
   * Sets referencing.
   *
   * @param null|SentEmail $referencing
   *
   * @return SentEmail $this
   */
  public function setReferencing(?SentEmail $referencing):SentEmail
  {
    $this->referencing = $referencing;

    return $this;
  }

  /**
   * Returns referencing.
   *
   * @return string|null
   */
  public function getReferencing():?SentEmail
  {
    return $this->referencing;
  }

  /**
   * Sets referencedBy.
   *
   * @param Collection $referencedBy
   *
   * @return SentEmail $this
   */
  public function setReferencedBy(Collection $referencedBy):SentEmail
  {
    $this->referencedBy = $referencedBy;

    return $this;
  }

  /**
   * Returns referencedBy.
   *
   * @return Collection
   */
  public function getReferencedBy():Collection
  {
    return $this->referencedBy;
  }
}
