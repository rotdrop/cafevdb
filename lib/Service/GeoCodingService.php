<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
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

namespace OCA\CAFEVDB\Service;

class GeoCodingService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  public function __construct(
      ConfigService $configService
  ) {
      $this->configService = $configService;
  }

  /**Get the array of languages supported in the database tables. */
  public function languages($force = false)
  {
    trigger_error("Unimplemented");
    return [ 'en' ];
  }

  /**Export the table of countries as key => name array w.r.t. the
   * current locale or the requested language.
   */
  public function countryNames($language = null) {
    trigger_error("Unimplemented");
    return [ $language => $language ];
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
