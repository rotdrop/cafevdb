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

use Doctrine\ORM\Mapping as ORM;

use OCP\Files\IRootFolder;
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

  const UPLOAD_NAME = 'imagefile';

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

  /** @var  \OCP\FILES\IRootFolder */
  private $rootFolder;

  public function __construct(
    $appName
    , IRequest $request
    , RequestParameterService $parameterService
    , ConfigService $configService
    , EntityManager $entityManager
    , IRootFolder $rootFolder
    , CalDavService $calDavService
    , ICache $fileCache
  ) {

    parent::__construct($appName, $request);

    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->rootFolder = $rootFolder;
    $this->calDavService = $calDavService; // ? why
    $this->fileCache = $fileCache;
    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   */
  public function get($joinTable, $ownerId, $imageId = -1, $metaData = false)
  {
    $this->logDebug("table: ".$joinTable.", owner: ".$ownerId. ", image: ".$imageId);
    $imageFileName = "image";
    $imageMimeType = "image/unknown";
    $imageData = '';

    $metaData = (bool)filter_var($metaData, FILTER_VALIDATE_BOOLEAN);

    if ($joinTable == 'cache') {
      $cacheKey = $ownerId;
      $imageData = $this->fileCache->get($cacheKey);

      $this->logInfo("Requested cache file with key ".$cacheKey);

      $image = new \OCP\Image();
      $image->loadFromData($imageData);
    } else {

      if ($ownerId <= 0) {
        return self::grumble($this->l->t("Owner-ID is missing"));
      }

      $imagesRepository = $this->getDatabaseRepository(Entities\Image::class);

      // ownername_imagename
      $joinTable = Util::dashesToCamelCase($joinTable, true);
      $joinTableClass = $imagesRepository->joinTableClass($joinTable);
      $this->logInfo("cooked table: ".$joinTableClass);

      $joinTableRepository = $this->getDatabaseRepository($joinTableClass);
      $findBy =  [ 'ownerId' => $ownerId ];
      if ($imageId > 0) {
        $findBy['imageId'] = $imageId;
      }

      try {

        $joinTableEntity = $joinTableRepository->findOneBy($findBy);
        if ($joinTableEntity == null) {
          if ($metaData === true) {
            // no image yet
            return new Http\DataResponse([], Http::STATUS_NOT_FOUND);
          } else {
            // return placeholder if metadata is not requested
            return $this->getPlaceHolder($joinTable);
          }
        }

        $dbImage = $joinTableEntity->getImage();

        if ($metaData === true) {
          return self::dataResponse([
            'joinTable' => $joinTable,
            'ownerId' => $ownerId,
            'imageId' => $dbImage->getId(),
            'mimeType' => $dbImage->getMimeType(),
            'md5' => $dbImage->getMd5(),
            'width' => $dbImage->getWidth(),
            'height' => $dbImage->getHeight(),
          ]);
        }

        // otherwise return image blob as download

        $imageMimeType = $dbImage->getMimeType();
        $imageData = $dbImage->getImageData()->getData('binary');

        $image = new \OCP\Image();
        $image->loadFromData($imageData);

        $this->logInfo("Image data: ".strlen($imageData)." mime ".$imageMimeType);
        if ($image->mimeType() !== $imageMimeType) {
          $this->logError("Mime-types stored / computed: ".$imageMimeType." / ".$image->mimeType());
        }
      } catch (\Throwable $t) {
        $this->logException($t);
        return self::grumble($this->exceptionChainData($t));
      }

    }

    $imageData = $image->data();
    $imageMimeType = $image->mimeType();

    return new Http\DataDownloadResponse($imageData, $imageFileName, $imageMimeType);
  }

  /**
   * @NoAdminRequired
   */
  public function post($action, $joinTable, $ownerId, $imageId = -1, $imageSize = 400)
  {
    if (empty($joinTable)) {
      return self::grumble($this->l->t("Relation between image and object missing"));
    }

    if (!is_numeric($ownerId) || $ownerId <= 0) {
      return self::grumble($this->l->t("Image owner not given"));
    }

    $joinTable = Util::dashesToCamelCase($joinTable, true);

    // response data skeleton, augmented by sub-topics.
    $responseData = [
      'ownerId' => $ownerId,
      'joinTable' => $joinTable,
    ];

    switch ($action) {
    case 'upload':
    case 'dragndrop':
    case 'cloud':
      $image = new \OCP\Image();
      switch ($action) {
      case 'cloud':
        $path = $this->parameterService['path'];
        if (empty($path)) {
          return self::grumble($this->l->t('No image path was submitted'));
        }

        $this->logInfo($path);

        $userFolder = $this->rootFolder->getUserFolder($this->userId());

        try {
          $file = $userFolder->get($path);
        } catch (\Throwable $t) {
          $this->logException($t);
          return self::grumble(
            $this->l->t("File `%s' not found in user's %s cloud storage.",
                        [$this->userId(), $path]));
        }

        $tmpKey = $this->appName().'-inline-image-'.md5($path);
        if (!$image->loadFromData($file->getContent())) {
          return self::grumble($this->l->t("Unable to validate cloud image file %s", [$path]));
        }
        break;
      case 'dragndrop':
        if (empty($this->parameterService->server['HTTP_X_FILE_NAME'])) {
          return self::grumble($this->l->t("Drag'n drop filename not submitted"));
        }
        $fileName = $this->parameterService->server['HTTP_X_FILE_NAME'];
        $tmpKey = $this->appName().'-inline-image-'.md5($fileName);
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
        if (!file_exists($tmpName)) {
          return self::grumble($this->l->t("Uploaded file seems to have vanished on server"));
        }

        $tmpKey = $this->appName().'-inline-image-'.md5(basename($tmpName));
        if (!$image->loadFromFile($tmpName)) {
          return self::grumble($this->l->t("Unable to validate uploaded image data"));
        }
        break;
      }

      if (!$this->cacheTemporaryImage($tmpKey, $image, $imageSize))  {
        return self::grumble($this->l->t(
          "Unable to save image data of size %s to temporary storage with key %s",
          [ strlen($image->data()), $tmpKey ]));
      }

      $this->logInfo("Stored cache file as ".$tmpKey);

      $responseData['tmpKey'] = $tmpKey;

      return self::dataResponse($responseData);
    case 'save':
      $this->logInfo('crop data: '.print_r($this->parameterService->getParams(), true));
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
        [action] => save
        [_route] => cafevdb.images.post )
      */

      $tmpKey = $this->parameterService['tmpKey'];
      if (empty($tmpKey)) {
        return self::grumble($this->l->t('Missing cache-key for temporay image file'));
      }

      $imageData = $this->fileCache->get($tmpKey);
      if (empty($imageData)) {
        return self::grumble($this->l->t('Unable to load image with cache key %s', [$tmpKey]));
      }
      $this->fileCache->remove($tmpKey);
      $this->logInfo("Image data for key ".$tmpKey." of size ".strlen($imageData));

      $image = new \OCP\Image();
      if (!$image->loadFromData($imageData)) {
        return self::grumble($this->l->t('Unable to generate image from temporary storage (%s)', [$tmpKey]));
      }

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

      if ($image->width() > $imageSize || $image->height() > $imageHeight) {
        if (!$image->resize($imageSize)) {
          return self::grumble($this->l->t('Unable to resize temporary image %s to size %s', [$tmpKey, $imageSize]));
        }
      }

      try {
        $imagesRepository = $this->getDatabaseRepository(Entities\Image::class);
        $dbImage = $imagesRepository->persistForEntity($joinTable, $ownerId, $image);
      } catch (\Throwable $t) {
        $this->logException($t);
        return self::grumble($this->exceptionChainData($t));
      }

      $responseData['imageId']  = $dbImage->getId();

      return self::dataResponse($responseData);
    case 'edit':
      // fetch the image, create a temporary copy and return a link to it

      $imagesRepository = $this->getDatabaseRepository(Entities\Image::class);

      // ownername_imagename
      $joinTable = Util::dashesToCamelCase($joinTable, true);
      $joinTableClass = $imagesRepository->joinTableClass($joinTable);
      $this->logInfo("cooked table: ".$joinTableClass);

      $joinTableRepository = $this->getDatabaseRepository($joinTableClass);
      $findBy =  [ 'ownerId' => $ownerId ];
      if ($imageId > 0) {
        $findBy['imageId'] = $imageId;
      }

      try {

        $joinTableEntity = $joinTableRepository->findOneBy($findBy);
        if ($joinTableEntity == null) {
          return self::grumble($this->l->t("Unable to find image to edit for %s@%s", [$ownerId, $joinTable]));
        }

        $dbImage = $joinTableEntity->getImage();
        $imageId = $dbImage->getId();
        $imageData = $dbImage->getImageData()->getData('binary');

        $image = new \OCP\Image();
        if (!$image->loadFromData($imageData)) {
          return self::grumble($this->l->t("Unable to create temporary image for %s@%s", [$ownerId, $joinTable]));
        }

        $tmpKey = $this->appName().'-inline-image-'.$joinTable.'-'.$ownerId.'-'.$imageId.'-'.$dbImage->getMd5();

        if (!$this->cacheTemporaryImage($tmpKey, $image, $imageSize)) {
        return self::grumble($this->l->t(
          "Unable to save image data of size %s to temporary storage with key %s",
          [ strlen($image->data()), $tmpKey ]));
        }
      } catch (\Throwable $t) {
        $this->logException($t);
        return self::grumble($this->exceptionChainData($t));
      }

      $responseData['tmpKey'] = $tmpKey;

      return self::dataResponse($responseData);
    case 'delete':
      // if ownerId only is given, delete all images, if ownerId and
      // imageId is given (not yet used), delete only the given image
      // and join-table entry.

      $imagesRepository = $this->getDatabaseRepository(Entities\Image::class);
      $joinTableClass = $imagesRepository->joinTableClass($joinTable);
      $joinTableRepository =$this->getDatabaseRepository($joinTableClass);

      $findBy =  [ 'ownerId' => $ownerId ];
      if ($imageId > 0) {
        $findBy['imageId'] = $imageId;
      }

      try {

        $joinTableEntities = $joinTableRepository->findBy($findBy);
        if (count($joinTableEntities) < 1) {
          return self::grumble(
            $this->l->t("Unable to find image link for ownerId %s in join-table %s",
                        [ $ownerId, $joinTable ]));
        }
        foreach ($joinTableEntities as $joinTableEntity) {
          $this->remove($joinTableEntity);
        }
        $this->flush();
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
  private function getPlaceHolder($joinTable)
  {
    $placeHolderName = 'placeholder/'.Util::camelCaseToDashes($joinTable).'.svg';
    try {
      $placeHolderUrl = $this->urlGenerator()->imagePath($this->appName(), $placeHolderName);
    } catch (\Throwable $t) {
      $this->logException($t);
      $imageData = $this->fallbackPlaceholder();
      $imageFileName = 'placeholder.svg';
      $imageMimeType = 'image/svg+xml';
      return new Http\DataDownloadResponse($imageData, $imageFileName, $imageMimeType);
    }
    return new Http\RedirectResponse($placeHolderUrl);
  }

  private function fallbackPlaceholder()
  {
    $data =<<<'EOT'
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<svg
    xmlns:svg="http://www.w3.org/2000/svg"
    xmlns="http://www.w3.org/2000/svg"
    width="180pt"
    height="180pt"
    viewBox="0 0 120 120">
  <rect
      x="0" y="0" width="120" height="120"
      style="fill:#BEBEBE;stroke:black;stroke-width:1;fill-opacity:1;stroke-apacity:0.5"
      />
  <svg x="50%" y="50%" width="100%" height="100%" style="overflow:visible">
    <text
        x="0%" y="0%" dominant-baseline="middle" text-anchor="middle"
        font-family="Arial" font-size="12pt" fill="red"
        transform="rotate(45)">
EOT;
    $data .= $this->l->t('Placeholder Image');
    $data .=<<<'EOT'
    </text>
  </svg>
</svg>
EOT;
    return $data;
  }

  private function cacheTemporaryImage(string $tmpKey, \OCP\Image $image, int $imageSize):bool
  {
    if($image->width() > $imageSize || $image->height() > $imageSize) {
      $image->resize($imageSize); // Prettier resizing than with browser and saves bandwidth.
    }
    if(!$image->fixOrientation()) { // No fatal error so we don't bail out.
      $this->logDebug("Unable to fix orientation of uploaded image");
    }

    return $this->fileCache->set($tmpKey, $image->data(), 600);
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
