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

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IL10N;
use OCP\ICache;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\CalDavService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

class ImagesController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var RequestParameterService */
  private $parameterService;

  /** @var IL10N */
  private $l;

  /** @var CalDavService */
  private $calDavService;

  /** @var EntityManager */
  protected $entityManager;

  /** @var \OCP\ICache */
  private $fileCache;

  public function __construct(
    $appName
    , IRequest $request
    , RequestParameterService $parameterService
    , ConfigService $configService
    , EntityManager $entityManager
    , CalDavService $calDavService
    , ICache $fileCache
  ) {

    parent::__construct($appName, $request);

    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->calDavService = $calDavService; // ? why
    $this->fileCache = $fileCache;
    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   */
  public function get($joinTable, $ownerId)
  {
    $this->logInfo("table: ".$joinTable.", owner: ".$ownerId);
    $imageFileName = "image";
    $imageMimeType = "image/unknown";
    $imageData = '';
    if ($joinTable == 'cache') {
      $cacheKey = $ownerId;
      $imageData = $this->fileCache->get($cacheKey);
      $image = new \OCP\Image();
      $image->loadFromData();
    } else {

      if ($ownerId <= 0) {
        return self::grumble($this->l->t("Owner-ID is missing"));
      }

      try {

        // ownername_imagename
        $joinTable = Util::dashesToCamelCase($joinTable, true);
        $this->logInfo("cooked table: ".$joinTable);

        $imagesRepository = $this->getDatabaseRepository(Entities\Image::class);

        $dbImage = $imagesRepository->findOneForEntity($joinTable, $ownerId);
        if (empty($dbImage)) {
          // @TODO: maybe better throw and catch
          return $this->getPlaceHolder($joinTable);
        }

        $imageMimeType = $dbImage->getMimeType();
        $imageData = $dbImage->getImageData()->getData();

        $image = new \OCP\Image();
        $image->loadFromBase64($imageData);

        $this->logInfo("Image data: ".strlen($imageData)." mime ".$imageMimeType);
        if ($image->mimeType() !== $imageMimeType) {
          $this->logError("Mime-types stored / computed: ".$imageMimeType." / ".$image->mimeType());
        }
      } catch (\Throwable $t) {
        $this->logException($t);
        $message = $this->l->t("Unable to load image data for %s@%s", [$ownerId, $joinTable]);
        $this->logError($message);
        return self::grumble($message);
      }

    }

    $imageData = $image->data();
    $imageMimeType = $image->mimeType();

    return new Http\DataDownloadResponse($imageData, $imageFileName, $imageMimeType);
  }

  /**
   * @NoAdminRequired
   */
  public function post($joinTable, $ownerId)
  {
    return self::grumble($this->l->t('Unknown Request'));
  }

  /**
   * Redirect to a place-holder image.
   */
  private function getPlaceHolder($joinTable)
  {
    $placeHolderName = 'placeholder/'.Util::camelCaseToDashes($joinTable);
    try {
      $placeHolderUrl = $this->urlGenerator()->imagePath($this->appName(), $placeHolderName);
    } catch (\Throwable $t) {
      $this->logException($t);
      try {
        $placeHolderUrl = $this->urlGenerator()->imagePath($this->appName(), 'placeholder/default.svg');
      } catch (\Throwable $t) {

      }
    }
    return new Http\RedirectResponse($placeHolderUrl);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
