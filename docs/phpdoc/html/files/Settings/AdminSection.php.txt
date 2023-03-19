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

/** Admin settings for the app. */
class AdminSection implements IIconSection
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

  /** {@inheritdoc} */
  public function getID()
  {
    return $this->appName;
  }

  /** {@inheritdoc} */
  public function getName()
  {
    // @todo make this configurable
    return 'Camerata DB';
  }

  /** {@inheritdoc} */
  public function getIcon()
  {
    return $this->urlGenerator->imagePath($this->appName, $this->appName . '.svg');
  }

  /** {@inheritdoc} */
  public function getPriority()
  {
    return 50;
  }
}
