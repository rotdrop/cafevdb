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

use OCP\ILogger;

/**
 * Instrumente
 *
 * @ORM\Table(name="Instruments")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\InstrumentsRepository")
 */
class Instrument implements \ArrayAccess
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
     * @var string
     *
     * @ORM\Column(name="Instrument", type="string", length=64, nullable=false)
     */
    private $instrument;

    /**
     * @var int
     *
     * @ORM\Column(name="Sortierung", type="smallint", nullable=false, options={"comment"="Orchestersortierung"})
     */
    private $sortierung;

    /**
     * @var bool
     *
     * @ORM\Column(name="Disabled", type="boolean", nullable=false, options={"default"="0"})
     */
    private $disabled = false;

    /**
     * @ORM\ManyToMany(targetEntity="Musician", mappedBy="instruments", fetch="EXTRA_LAZY")
     */
    private $musicians;

    /**
     * @ORM\ManyToMany(targetEntity="Project", mappedBy="instrumentation", fetch="EXTRA_LAZY")
     */
    private $projects;

    /**
     * @ORM\ManyToMany(targetEntity="InstrumentFamily", inversedBy="instruments", fetch="EXTRA_LAZY")
     * @ORM\JoinTable(
     *   name="instrument_family",
     *   joinColumns={@ORM\JoinColumn(name="instrument_id", referencedColumnName="Id", onDelete="CASCADE")},
     *   inverseJoinColumns={@ORM\JoinColumn(name="family_id", referencedColumnName="id", onDelete="CASCADE")}
     * )
     */
    private $families;

    public function __construct() {
        \OCP\Util::writeLog('cafevdb', __METHOD__, ILogger::INFO);
        $this->arrayCTOR();
        $this->musicians = new ArrayCollection();
        $this->projects = new ArrayCollection();
        $this->families = new ArrayCollection();
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
     * Set instrument.
     *
     * @param string $instrument
     *
     * @return Instrumente
     */
    public function setInstrument($instrument)
    {
        $this->instrument = $instrument;

        return $this;
    }

    /**
     * Get instrument.
     *
     * @return string
     */
    public function getInstrument()
    {
        return $this->instrument;
    }

    /**
     * Set familie.
     *
     * @param array $familie
     *
     * @return Instrumente
     */
    public function setFamilies($families)
    {
        $this->families = $families;

        return $this;
    }

    /**
     * Get familie.
     *
     * @return array
     */
    public function getFamilies()
    {
        return $this->families;
    }

    /**
     * Set sortierung.
     *
     * @param int $sortierung
     *
     * @return Instrumente
     */
    public function setSortierung($sortierung)
    {
        $this->sortierung = $sortierung;

        return $this;
    }

    /**
     * Get sortierung.
     *
     * @return int
     */
    public function getSortierung()
    {
        return $this->sortierung;
    }

    /**
     * Set disabled.
     *
     * @param bool $disabled
     *
     * @return Instrumente
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

    public function usage()
    {
        return $this->musicians->count()
            + $this->projcts->count()
            + $this->families->count();
    }
}
