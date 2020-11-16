<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Projekte
 *
 * @ORM\Table(name="Projects", uniqueConstraints={@ORM\UniqueConstraint(name="Name", columns={"Name"})})
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectsRepository")
 */
class Project
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @var int
   *
   * @ORM\Column(name="Id", type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var int
   *
   * @ORM\Column(name="Jahr", type="integer", nullable=false, options={"unsigned"=true})
   */
  private $year;

  /**
   * @var string
   *
   * @ORM\Column(name="Name", type="string", length=64, nullable=false)
   */
  private $name;

  /**
   * @var enumprojecttemporaltype
   *
   * @ORM\Column(name="Art", type="enumprojecttemporaltype", nullable=false, options={"default"="temporary"})
   */
  private $art = 'temporary';

  /**
   * @var array|null
   *
   * @ORM\Column(name="Besetzung", type="simple_array", length=0, nullable=true, options={"comment"="obsolete"})
   */
  private $besetzung;

  /**
   * @var string
   *
   * @ORM\Column(name="Unkostenbeitrag", type="decimal", precision=7, scale=2, nullable=false, options={"default"="0.00"})
   */
  private $unkostenbeitrag = '0.00';

  /**
   * @var string
   *
   * @ORM\Column(name="Anzahlung", type="decimal", precision=7, scale=2, nullable=false, options={"default"="0.00"})
   */
  private $anzahlung = '0.00';

  /**
   * @var string
   *
   * @ORM\Column(name="ExtraFelder", type="text", length=65535, nullable=false, options={"comment"="Extra-Datenfelder"})
   */
  private $extrafelder;

  /**
   * @var bool
   *
   * @ORM\Column(name="Disabled", type="boolean", nullable=false, options={"default"="0"})
   */
  private $disabled = '0';

  /**
   * @var \DateTime|null
   *
   * @ORM\Column(name="Aktualisiert", type="datetime", nullable=true)
   */
  private $aktualisiert;

  /**
   * @ORM\ManyToMany(targetEntity="Instrument", inversedBy="projects", orphanRemoval=true, fetch="EXTRA_LAZY")
   * @ORM\JoinTable(
   *   name="project_instrument",
   *   joinColumns={@ORM\JoinColumn(name="project_id", referencedColumnName="Id", onDelete="CASCADE")},
   *   inverseJoinColumns={@ORM\JoinColumn(name="instrument_id", referencedColumnName="Id", onDelete="CASCADE")}
   * )
   */
  private $instrumentation;

  /**
   * @ORM\OneToMany(targetEntity="ProjectPoster", mappedBy="owner")
   */
  private $posters;

  /**
   * @ORM\OneToMany(targetEntity="ProjectFlyer", mappedBy="owner")
   */
  private $flyers;

  public function __construct() {
    $this->arrayCTOR();
    $this->instrumentation = new ArrayCollection();
    $this->posters = new ArrayCollection();
    $this->flyers = new ArrayCollection();
  }

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
   * Set jahr.
   *
   * @param int $jahr
   *
   * @return Projekte
   */
  public function setJahr($jahr)
  {
    $this->jahr = $jahr;

    return $this;
  }

  /**
   * Get jahr.
   *
   * @return int
   */
  public function getJahr()
  {
    return $this->jahr;
  }

  /**
   * Set name.
   *
   * @param string $name
   *
   * @return Projekte
   */
  public function setName($name)
  {
    $this->name = $name;

    return $this;
  }

  /**
   * Get name.
   *
   * @return string
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * Set art.
   *
   * @param enumprojecttemporaltype $art
   *
   * @return Projekte
   */
  public function setArt($art)
  {
    $this->art = $art;

    return $this;
  }

  /**
   * Get art.
   *
   * @return enumprojecttemporaltype
   */
  public function getArt()
  {
    return $this->art;
  }

  /**
   * Set besetzung.
   *
   * @param array|null $besetzung
   *
   * @return Projekte
   */
  public function setBesetzung($besetzung = null)
  {
    $this->besetzung = $besetzung;

    return $this;
  }

  /**
   * Get besetzung.
   *
   * @return array|null
   */
  public function getBesetzung()
  {
    return $this->besetzung;
  }

  /**
   * Set unkostenbeitrag.
   *
   * @param string $unkostenbeitrag
   *
   * @return Projekte
   */
  public function setUnkostenbeitrag($unkostenbeitrag)
  {
    $this->unkostenbeitrag = $unkostenbeitrag;

    return $this;
  }

  /**
   * Get unkostenbeitrag.
   *
   * @return string
   */
  public function getUnkostenbeitrag()
  {
    return $this->unkostenbeitrag;
  }

  /**
   * Set anzahlung.
   *
   * @param string $anzahlung
   *
   * @return Projekte
   */
  public function setAnzahlung($anzahlung)
  {
    $this->anzahlung = $anzahlung;

    return $this;
  }

  /**
   * Get anzahlung.
   *
   * @return string
   */
  public function getAnzahlung()
  {
    return $this->anzahlung;
  }

  /**
   * Set extrafelder.
   *
   * @param string $extrafelder
   *
   * @return Projekte
   */
  public function setExtrafelder($extrafelder)
  {
    $this->extrafelder = $extrafelder;

    return $this;
  }

  /**
   * Get extrafelder.
   *
   * @return string
   */
  public function getExtrafelder()
  {
    return $this->extrafelder;
  }

  /**
   * Set disabled.
   *
   * @param bool $disabled
   *
   * @return Projekte
   */
  public function setDisabled($disabled)
  {
    $this->disabled = $disabled;

    return $this;
  }

  /**
   * Get disabled.
   *
   * @return bool
   */
  public function getDisabled()
  {
    return $this->disabled;
  }

  /**
   * Set aktualisiert.
   *
   * @param \DateTime|null $aktualisiert
   *
   * @return Projekte
   */
  public function setAktualisiert($aktualisiert = null)
  {
    $this->aktualisiert = $aktualisiert;

    return $this;
  }

  /**
   * Get aktualisiert.
   *
   * @return \DateTime|null
   */
  public function getAktualisiert()
  {
    return $this->aktualisiert;
  }
}
