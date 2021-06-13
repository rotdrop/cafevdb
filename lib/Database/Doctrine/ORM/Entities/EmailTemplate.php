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

/**
 * EmailTemplate
 *
 * @ORM\Table(name="EmailTemplates")
 * @Gedmo\TranslationEntity(class="TableFieldTranslation")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EmailTemplatesRepository")
 */
class EmailTemplate implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\TimestampableEntity;
  use \OCA\CAFEVDB\Wrapped\Gedmo\Blameable\Traits\BlameableEntity;
  use CAFEVDB\Traits\TranslatableTrait;

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
   * @Gedmo\Translatable
   * @ORM\Column(type="string", length=128, unique=true, nullable=false)
   */
  private $tag;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=1024, nullable=false)
   */
  private $subject;

  /**
   * @var string|null
   *
   * @ORM\Column(type="text", length=0, nullable=true)
   */
  private $contents;

  /**
   * Get id.
   *
   * @return int
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * Set tag.
   *
   * @param string $tag
   *
   * @return EmailTemplate
   */
  public function setTag($tag):EmailTemplate
  {
    $this->tag = $tag;

    return $this;
  }

  /**
   * Get tag.
   *
   * @return string
   */
  public function getTag()
  {
    return $this->tag;
  }

  /**
   * Set subject.
   *
   * @param string $subject
   *
   * @return EmailTemplate
   */
  public function setSubject($subject):EmailTemplate
  {
    $this->subject = $subject;

    return $this;
  }

  /**
   * Get subject.
   *
   * @return string
   */
  public function getSubject()
  {
    return $this->subject;
  }

  /**
   * Set contents.
   *
   * @param string|null $contents
   *
   * @return EmailTemplate
   */
  public function setContents($contents = null):EmailTemplate
  {
    $this->contents = $contents;

    return $this;
  }

  /**
   * Get contents.
   *
   * @return string|null
   */
  public function getContents():?string
  {
    return $this->contents;
  }
}
