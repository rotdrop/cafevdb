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
use Gedmo\Mapping\Annotation as Gedmo;

// @todo: recipients, md5 for quick check, message-id to fetch message from imap storage.

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
   * @var int
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
   * @Gedmo\Slug(fields={"bulkRecipients"}, updatable=true, handlers={
   *   @Gedmo\SlugHandler(class="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\HashHandler")
   * })
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
   * @ORM\Column(type="text", length=0, nullable=false)
   */
  private $htmlBody;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=32, nullable=false, options={"fixed"=true, "collation"="ascii_bin"})
   * @Gedmo\Slug(fields={"htmlBody"}, updatable=true, handlers={
   *   @Gedmo\SlugHandler(class="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\HashHandler")
   * })
   */
  private $htmlBodyHash;

  /**
   * Sets messageId.
   *
   * @param string $messageId
   *
   * @return SentEmail $this
   */
  public function setMessageId(string $messageId):self
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
  public function setCreatedBy(string $createdBy):self
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
  public function setBulkRecipients(string $bulkRecipients):self
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
   * Sets md5BulkRecipients.
   *
   * @param string $md5BulkRecipients
   *
   * @return SentEmail $this
   */
  public function setMd5BulkRecipients(string $md5BulkRecipients):self
  {
    $this->md5BulkRecipients = $md5BulkRecipients;

    return $this;
  }

  /**
   * Returns md5BulkRecipients.
   *
   * @return string
   */
  public function getMd5BulkRecipients():string
  {
    return $this->md5BulkRecipients;
  }

  /**
   * Sets htmlBody.
   *
   * @param string $htmlBody
   *
   * @return SentEmail $this
   */
  public function setHtmlBody(string $htmlBody):self
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
   * Sets md5HtmlBody.
   *
   * @param string $md5HtmlBody
   *
   * @return SentEmail $this
   */
  public function setMd5HtmlBody(string $md5HtmlBody):self
  {
    $this->md5HtmlBody = $md5HtmlBody;

    return $this;
  }

  /**
   * Returns md5HtmlBody.
   *
   * @return string
   */
  public function getMd5HtmlBody():string
  {
    return $this->md5HtmlBody;
  }

  /**
   * Sets subject.
   *
   * @param string $subject
   *
   * @return SentEmail $this
   */
  public function setSubject(string $subject):self
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
   * Sets cc.
   *
   * @param string $cc
   *
   * @return SentEmail $this
   */
  public function setCc(string $cc):self
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
  public function setBcc(string $bcc):self
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
}
