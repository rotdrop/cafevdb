<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Settings;

use OCP\Settings\IIconSection;
use OCP\IURLGenerator;

/** Personal app-settings. */
class PersonalSection implements IIconSection
{
  /** @var string */
  private $appName;

  /** @var IURLGenerator */
  private $urlGenerator;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    $appName,
    IURLGenerator $urlGenerator,
  ) {
    $this->appName = $appName;
    $this->urlGenerator = $urlGenerator;
  }
  // phpcs:enable

  /**
   * Returns the ID of the section. It is supposed to be a lower case string
   *
   * @return string
   */
  public function getID()
  {
    return $this->appName;
  }

  /**
   * Returns the translated name as it should be displayed, e.g. 'LDAP / AD
   * integration'. Use the L10N service to translate it.
   *
   * @return string
   */
  public function getName()
  {
    // @todo make this configurable
    return 'Camerata DB';
  }

  /**
   * @return The relative path to a an icon describing the section
   */
  public function getIcon()
  {
    return $this->urlGenerator->imagePath($this->appName, $this->appName . '.svg');
  }

  /**
   * @return int whether the form should be rather on the top or bottom of
   * the settings navigation. The sections are arranged in ascending order of
   * the priority values. It is required to return a value between 0 and 99.
   */
  public function getPriority()
  {
    return 50;
  }
}
