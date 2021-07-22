<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

use OCP\Files\IRootFolder;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IL10N;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ImagesService;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Storage\UserStorage;

class ImagesController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;

  const UPLOAD_NAME = 'imagefile';

  /** @var RequestParameterService */
  private $parameterService;

  /** @var IL10N */
  protected $l;

  /** @var IRootFolder */
  private $rootFolder;

  /** @var ImagesService */
  private $imagesService;

  public function __construct(
    $appName
    , IRequest $request
    , RequestParameterService $parameterService
    , ConfigService $configService
    , ImagesService $imagesService
    , IRootFolder $rootFolder
  ) {

    parent::__construct($appName, $request);

    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->imagesService = $imagesService;
    $this->rootFolder = $rootFolder;
    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   */
  public function get($joinTable, $ownerId, $imageId = ImagesService::IMAGE_ID_ANY, $imageSize = -1)
  {
    $this->logDebug("table: ".$joinTable.", owner: ".$ownerId. ", image: ".$imageId);

    if ($imageId == ImagesService::IMAGE_ID_PLACEHOLDER) {
      // placeholder reguested
      return $this->getPlaceHolder($joinTable, $imageSize);
    }

    $image = null;
    list($image, $fileName) = $this->imagesService->getImage($joinTable, $ownerId, $imageId);

    if (empty($image)) {
      return $this->getPlaceHolder($joinTable, $imageSize);
    }

    return new Http\DataDownloadResponse($image->data(), $fileName, $image->mimeType());
  }

  /**
   * @NoAdminRequired
   */
  public function post($operation, $joinTable, $ownerId, $imageId = ImagesService::IMAGE_ID_ANY, $imageSize = -1)
  {
    if (empty($joinTable)) {
      return self::grumble($this->l->t("Relation between image and object missing"));
    }

    if (!is_numeric($ownerId) || $ownerId <= 0) {
      return self::grumble($this->l->t("Image owner not given"));
    }

    // response data skeleton, augmented by sub-topics.
    $responseData = [
      'ownerId' => $ownerId,
      'joinTable' => $joinTable,
    ];

    switch ($operation) {
    case 'fileupload':
    case 'dragndrop':
    case 'cloud':
      $image = new \OCP\Image();
      switch ($operation) {
      case 'cloud':
        $path = $this->parameterService['path'];
        if (empty($path)) {
          return self::grumble($this->l->t('No image path was submitted'));
        }

        $this->logDebug($path);

        $userFolder = $this->rootFolder->getUserFolder($this->userId());

        try {
          $file = $userFolder->get($path);
        } catch (\Throwable $t) {
          $this->logException($t);
          return self::grumble(
            $this->l->t("File `%s' not found in user's %s cloud storage.",
                        [$this->userId(), $path]));
        }

        $tmpKey = $this->appName() . '-inline-image-' . md5($path) . '-' . $this->generateRandomBytes();
        $fileName = $file->getName();
        if (!$image->loadFromData($file->getContent())) {
          return self::grumble($this->l->t("Unable to validate cloud image file %s", [$path]));
        }
        break;
      case 'dragndrop':
        if (empty($this->parameterService->server['HTTP_X_FILE_NAME'])) {
          return self::grumble($this->l->t("Drag'n drop filename not submitted"));
        }
        $fileName = $this->parameterService->server['HTTP_X_FILE_NAME'];
        $tmpKey = $this->appName() . '-inline-image-' . md5($fileName) . '-' . $this->generateRandomBytes();
        if (!$image->loadFromData(file_get_contents('php://input'))) {
          return self::grumble($this->l->t("Unable to validate uploaded image data"));
        }
        break;
      case 'fileupload':
        $upload = $this->parameterService->getUpload(self::UPLOAD_NAME);
        if (empty($upload)) {
          return self::grumble($this->l->t("Image has not been uploaded"));
        }

        $tmpName = $upload['tmp_name'];
        $fileName = $upload['name'];
        if (!file_exists($tmpName)) {
          return self::grumble($this->l->t("Uploaded file seems to have vanished on server"));
        }

        $tmpKey = $this->appName() . '-inline-image-' . md5(basename($tmpName));
        if (!$image->loadFromFile($tmpName)) {
          return self::grumble($this->l->t("Unable to validate uploaded image data"));
        }
        break;
      }

      if (!$this->imagesService->cacheTemporaryImage($tmpKey, $image, $imageSize))  {
        return self::grumble($this->l->t(
          "Unable to save image data of size %s to temporary storage with key %s",
          [ strlen($image->data()), $tmpKey ]));
      }

      $this->logDebug("Stored cache file as " . $tmpKey);

      $responseData = [
        'tmpKey' => $tmpKey,
        'fileName' => $fileName,
      ];
      return self::dataResponse($responseData);
    case 'save':
      $this->logDebug('crop data: '.print_r($this->parameterService->getParams(), true));
      /*
        Array (
        [renderAs] => user
        [projectName] =>
        [projectId] => -1
        [musicianId] => -1
        [ownerId] => 2
        [joinTable] => MusicianPhoto
        [imageSize] => 400
        [tmpKey] => cafevdb-inline-image-033d31253582819aa5eb7b7fbcee03f2
        [x1] => 384
        [y1] => 168
        [x2] => 897
        [y2] => 684
        [w] => 513
        [h] => 516
        [opeation] => save
        [_route] => cafevdb.images.post )
      */

      $tmpKey = $this->parameterService['tmpKey'];
      if (empty($tmpKey)) {
        return self::grumble($this->l->t('Missing cache-key for temporay image file'));
      }
      $fileName = $this->parameterService['fileName'];
      if (empty($fileName)) {
        $this->logInfo('Empty file-name for temporary image file "' . $tmpKey . '".');
        $fileName = $tmpKey;
      }

      list($image,) = $this->imagesService->getImage('cache', $tmpKey);
      if (empty($image)) {
        return self::grumble($this->l->t('Unable to load image with cache key %s', [ $tmpKey ]));
      }
      $this->imagesService->deleteTemporaryImage($tmpKey);
      $this->logDebug("Image data for key " . $tmpKey . " of size " . strlen($imageData));

      $x1 = (int)$this->parameterService['x1'];
      $y1 = (int)$this->parameterService['y1'];
      $w  = (int)$this->parameterService['w'];
      $h  = (int)$this->parameterService['h'];

      $w = ($w > 0 ? $w : $image->width());
      $h = ($h > 0 ? $h : $image->height());
      $this->logDebug('savecrop.php, x: '.$x1.' y: '.$y1.' w: '.$w.' h: '.$h);
      if (!$image->crop($x1, $y1, $w, $h)) {
        return self::grumble(
          $this->l->t('Unable to crop temporary image %s to [%s, %s, %s, %s]',
                      [ $tmpKey, $x1, $y1, $x1 + $w - 1, $y1 + $h -1 ]));
      }

      if ($imageSize > 0 && ($image->width() > $imageSize || $image->height() > $imageHeight)) {
        if (!$image->resize($imageSize)) {
          return self::grumble($this->l->t('Unable to resize temporary image %s to size %s', [$tmpKey, $imageSize]));
        }
      }

      try {
        $imageId = $this->imagesService->storeImage($image, $joinTable, $ownerId, $fileName, $imageId);
      } catch (\Throwable $t) {
        $this->logException($t);
        return self::grumble($this->exceptionChainData($t));
      }

      $responseData['imageId']  = $imageId;

      return self::dataResponse($responseData);
    case 'edit':
      // fetch the image, create a temporary copy and return a link to it
      list($image, $fileName) = $this->imagesService->getImage($joinTable, $ownerId, $imageId);

      $tmpKeyBase = $this->appName() . '-inline-image-' . $joinTable . '-' . $ownerId . '-' . $imageId;
      if (empty($fileName)) {
        $fileName = $tmpKeyBase;
      }
      $tmpKey = $tmpKeyBase . '-' . $this->generateRandomBytes();

      if (!$this->imagesService->cacheTemporaryImage($tmpKey, $image, $imageSize)) {
        return self::grumble($this->l->t(
          "Unable to save image data of size %s to temporary storage with key %s",
          [ strlen($image->data()), $tmpKey ]));
      }

      $responseData = [
        'tmpKey' => $tmpKey,
        'fileName' => $fileName,
      ];

      return self::dataResponse($responseData);
    case 'delete':
      // if ownerId only is given, delete all images, if ownerId and
      // imageId is given (not yet used), delete only the given image
      // and join-table entry.

      $findBy =  [ 'ownerId' => $ownerId ];
      if ($imageId > ImagesService::IMAGE_ID_ANY) {
        $findBy['imageId'] = $imageId;
      }

      try {
        $this->imagesService->deleteImage($joinTable, $ownerId, $imageId);
      } catch (\Throwable $t) {
        $this->logException($t);
        return self::grumble($this->exceptionChainData($t));
      }

      return self::dataResponse('');

      break;
    default:
      break;
    }
    return self::grumble($this->l->t('Unknown Request'));
  }

  /**
   * Redirect to a place-holder image.
   */
  private function getPlaceHolder($joinTable, $imageSize)
  {
    $placeHolderName = 'placeholder/'.Util::camelCaseToDashes($joinTable).'.svg';
    try {
      $placeHolderUrl = $this->urlGenerator()->imagePath($this->appName(), $placeHolderName);
    } catch (\Throwable $t) {
      //$this->logException($t);
      $imageData = $this->imagesService->fallbackPlaceholder($imageSize);
      $imageFileName = 'placeholder.svg';
      $imageMimeType = 'image/svg+xml';
      return new Http\DataDownloadResponse($imageData, $imageFileName, $imageMimeType);
    }
    return new Http\RedirectResponse($placeHolderUrl);
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
