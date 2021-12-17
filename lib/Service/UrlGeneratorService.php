<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\IURLGenerator;
use OCP\IL10N;
use OCP\ILogger;

class UrlGeneratorService implements IURLGenerator
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var IURLGenerator */
  private $urlGenerator;

  public function __construct(
    IURLGenerator $urlGenerator
    , IL10N $l10n
    , ILogger $logger
  ) {
    $this->urlGenerator = $urlGenerator;
    $this->logger = $logger;
    $this->l = $l10n;
  }

  /** {@inheritDoc} */
  public function linkToRoute(string $routeName, array $arguments = []): string
  {
    return $this->urlGenerator->linkToRouteAbsolute($routeName, $arguments);
  }

  /** {@inheritDoc} */
  public function linkToRouteAbsolute(string $routeName, array $arguments = []): string
  {
    return $this->urlGenerator->linkToRouteAbsolute($routeName, $arguments);
  }

  /** {@inheritDoc} */
  public function linkToOCSRouteAbsolute(string $routeName, array $arguments = []): string
  {
    return $this->urlGenerator->linkToOCSRouteAbsolute($routeName, $arguments);
  }

  /** {@inheritDoc} */
  public function linkTo(string $appName, string $file, array $args = []): string
  {
    return $this->urlGenerator->linkTo($appName, $file, $args);
  }

  /** {@inheritDoc} */
  public function imagePath(string $appName, string $file): string
  {
    $imagePath = $this->urlGenerator->imagePath($appName, $file);

    $this->logInfo('ImagePath ' . $imagePath);

    return $imagePath;
  }

  /** {@inheritDoc} */
  public function getAbsoluteURL(string $url): string
  {
    return $this->urlGenerator->getAbsoluteURL($url);
  }

  /** {@inheritDoc} */
  public function linkToDocs(string $key): string
  {
    return $this->urlGenerator->linkToDocs($key);
  }

  /** {@inheritDoc} */
  public function getBaseUrl(): string
  {
    return $this->urlGenerator->getBaseUrl();
  }

  /** {@inheritDoc} */
  public function getWebroot(): string
  {
    return $this->urlGenerator->getWebroot();
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
