<?php
/**
 * The is a stripped down version from
 * dav/lib/CardDAV/ImageExportPlugin + PhotoCache.php
 *
 * Original sources copyright to
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Jacob Neplokh <me@jacobneplokh.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\CAFEVDB\AddressBook;

use Sabre\CardDAV\ICard;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

use OCA\CAFEVDB\Service\ConfigService;

class ImageExportPlugin extends ServerPlugin
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var array  */
  public const ALLOWED_CONTENT_TYPES = [
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
    'image/gif' => 'gif',
    'image/vnd.microsoft.icon' => 'ico',
  ];

  /** @var Server */
  protected $server;

  /** @var PhotoCache */
  private $cache;

  /**
   * ImageExportPlugin constructor.
   */
  public function __construct(
    ConfigService $configService
  ) {
    $this->configService = $configService;
    $this->l = $this->l10n();
  }

  /**
   * Initializes the plugin and registers event handlers
   *
   * @param Server $server
   * @return void
   */
  public function initialize(Server $server) {
    $this->server = $server;
    $this->server->on('method:GET', [$this, 'httpGet'], 90);
  }

  /**
   * Intercepts GET requests on addressbook urls ending with ?photo.
   *
   * @param RequestInterface $request
   * @param ResponseInterface $response
   * @return bool
   */
  public function httpGet(RequestInterface $request, ResponseInterface $response) {
    $queryParams = $request->getQueryParameters();
    // TODO: in addition to photo we should also add logo some point in time
    if (!array_key_exists('photo', $queryParams)) {
      return true;
    }

    $size = isset($queryParams['size']) ? (int)$queryParams['size'] : -1;

    $path = $request->getPath();
    $node = $this->server->tree->getNodeForPath($path);

    if (!($node instanceof MusicianCard)) {
      return true;
    }

    $this->server->transactionType = 'carddav-image-export';

    // Checking ACL, if available.
    if ($aclPlugin = $this->server->getPlugin('acl')) {
      /** @var \Sabre\DAVACL\Plugin $aclPlugin */
      $aclPlugin->checkPrivileges($path, '{DAV:}read');
    }

    $photo = $node->getVCard()->PHOTO;
    if (empty($photo)) {
      $response->setStatus(404);
      return false;
    }

    // we know we have only a data uri
    $value = $photo->getValue();
    $parsed = \Sabre\URI\parse($value);

    if (substr_count($parsed['path'], ';') === 1) {
      list($mimeType) = explode(';', $parsed['path']);
    }

    if (empty(self::ALLOWED_CONTENT_TYPES[$mimeType])) {
      $this->logError('Unsupported mime-type '.$mimeType);
      $response->setStatus(404);
      return false;
    }

    $imageData = file_get_contents($value);

    $response->setHeader('Cache-Control', 'private, max-age=3600, must-revalidate');
    $response->setHeader('Etag', $node->getETag());
    $response->setHeader('Pragma', 'public');

    $response->setHeader('Content-Type', $mimeType);
    $fileName = $node->getName() . '.' . self::ALLOWED_CONTENT_TYPES[$mimeType];
    $response->setHeader('Content-Disposition', "attachment; filename=$fileName");
    $response->setStatus(200);

    $response->setBody($imageData);

    return false;
  }
}
