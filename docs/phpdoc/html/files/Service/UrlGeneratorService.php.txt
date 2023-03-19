<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service;

use OCP\IURLGenerator;
use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;

/**
 * Helper which injects the name of this app into all methods of the
 * IURLGenerator which need it.
 */
class UrlGeneratorService implements IURLGenerator
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  /** @var IURLGenerator */
  private $urlGenerator;

  // phpcs:disabled Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    IURLGenerator $urlGenerator,
    IL10N $l10n,
    ILogger $logger,
  ) {
    $this->urlGenerator = $urlGenerator;
    $this->logger = $logger;
    $this->l = $l10n;
  }
  // phpcs:enable

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
