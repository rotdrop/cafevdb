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

/**
 * GeoCountries
 *
 * @ORM\Table(name="GeoCountries")
 * @ORM\Entity
 */
class GeoCountry implements \ArrayAccess
{
    use CAFEVDB\Traits\ArrayTrait;
    use CAFEVDB\Traits\FactoryTrait;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=2, nullable=false, options={"fixed":true})
     * @ORM\Id
     */
    private $iso;

    /**
     * @var string
     * @ORM\Id
     *
     * @ORM\Column(type="string", length=2, nullable=false, options={"fixed":true})
     */
    private $target;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=1024, nullable=false)
     */
    private $data;

    public function __construct() {
        $this->arrayCTOR();
    }

    /**
     * Set iso.
     *
     * @param string $iso
     *
     * @return GeoCountries
     */
    public function setIso($iso)
    {
        $this->iso = $iso;

        return $this;
    }
    /**
     * Get iso.
     *
     * @return string
     */
    public function getIso()
    {
        return $this->iso;
    }

    /**
     * Set continent.
     *
     * @param string $target
     *
     * @return GeoCountries
     */
    public function setTarget($target)
    {
        $this->target = $target;

        return $this;
    }

    /**
     * Get target.
     *
     * @return string
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * Set data.
     *
     * @param string $data
     *
     * @return GeoCountries
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Get data.
     *
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }
}
