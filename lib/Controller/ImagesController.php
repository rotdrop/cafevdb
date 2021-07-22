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
use OCP\ICache;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Storage\UserStorage;

class ImagesController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const IMAGE_ID_ANY = -1;
  const IMAGE_ID_PLACEHOLDER = 0;

  const UPLOAD_NAME = 'imagefile';

  /** @var RequestParameterService */
  private $parameterService;

  /** @var IL10N */
  protected $l;

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
    , ICache $fileCache
  ) {

    parent::__construct($appName, $request);

    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->rootFolder = $rootFolder;
    $this->fileCache = $fileCache;
    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   */
  public function get($joinTable, $ownerId, $imageId = self::IMAGE_ID_ANY, $imageSize = -1)
  {
    $this->logDebug("table: ".$joinTable.", owner: ".$ownerId. ", image: ".$imageId);

    if ($imageId === self::IMAGE_ID_PLACEHOLDER) {
      // placeholder reguested
      return $this->getPlaceHolder($joinTable, $imageSize);
    }

    $image = null;
    if ($joinTable == 'cache') {
      $cacheKey = $ownerId;
      $imageData = $this->fileCache->get($cacheKey);

      $this->logDebug("Requested cache file with key ".$cacheKey);

      $image = new \OCP\Image();
      $image->loadFromData($imageData);
      $fileName = $cacheKey;
    } else {
      list($image, $fileName) = $this->getImage($joinTable, $ownerId, $imageId);
    }

    if (empty($image)) {
      return $this->getPlaceHolder($joinTable, $imageSize);
    }

    return new Http\DataDownloadResponse($image->data(), $fileName, $image->mimeType());
  }

  /**
   * @NoAdminRequired
   */
  public function post($operation, $joinTable, $ownerId, $imageId = self::IMAGE_ID_ANY, $imageSize = -1)
  {
    if (empty($joinTable)) {
      return self::grumble($this->l->t("Relation between image and object missing"));
    }

    if (!is_numeric($ownerId) || $ownerId <= 0) {
      return self::grumble($this->l->t("Image owner not given"));
    }

    $imagesRepository = $this->getDatabaseRepository(Entities\Image::class);
    $joinTable = Util::dashesToCamelCase($joinTable, true);
    $joinTableClass = $imagesRepository->joinTableClass($joinTable);
    $joinTableRepository =$this->getDatabaseRepository($joinTableClass);

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

      if (!$this->cacheTemporaryImage($tmpKey, $image, $imageSize))  {
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

      $imageData = $this->fileCache->get($tmpKey);
      if (empty($imageData)) {
        return self::grumble($this->l->t('Unable to load image with cache key %s', [ $tmpKey ]));
      }
      $this->fileCache->remove($tmpKey);
      $this->logDebug("Image data for key " . $tmpKey . " of size " . strlen($imageData));

      $image = new \OCP\Image();
      if (!$image->loadFromData($imageData)) {
        return self::grumble($this->l->t('Unable to generate image from temporary storage (%s)', [ $tmpKey ]));
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

      if ($imageSize > 0 && ($image->width() > $imageSize || $image->height() > $imageHeight)) {
        if (!$image->resize($imageSize)) {
          return self::grumble($this->l->t('Unable to resize temporary image %s to size %s', [$tmpKey, $imageSize]));
        }
      }

      try {

        if ($imageId > self::IMAGE_ID_PLACEHOLDER) {
          $findBy = [ 'ownerId' => $ownerId, 'imageId' => $imageId, ];
          $joinTableEntity = $joinTableRepository->findOneBy($findBy);
          /** @var Entities\Image $dbImage */
          $dbImage = $joinTableEntity->getImage();
          $dbImage->setFileName($fileName);
          $dbImage->setMimeType($image->mimeType());
          $dbImage->getFileData()->setData($image->data(), 'binary');
          $this->flush();
        } else  {
          $imagesRepository = $this->getDatabaseRepository(Entities\Image::class);
          $dbImage = $imagesRepository->persistForEntity($joinTable, $ownerId, $image, $fileName);
        }
      } catch (\Throwable $t) {
        $this->logException($t);
        return self::grumble($this->exceptionChainData($t));
      }

      $responseData['imageId']  = $dbImage->getId();

      return self::dataResponse($responseData);
    case 'edit':
      // fetch the image, create a temporary copy and return a link to it

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
        $fileName = $dbImage->getFileName();
        $imageData = $dbImage->getFileData()->getData('binary');

        $image = new \OCP\Image();
        if (!$image->loadFromData($imageData)) {
          return self::grumble($this->l->t("Unable to create temporary image for %s@%s", [$ownerId, $joinTable]));
        }

        $tmpKeyBase = $this->appName() . '-inline-image-' . $joinTable . '-' . $ownerId . '-' . $imageId;
        if (empty($fileName)) {
          $fileName = $tmpKeyBase;
        }
        $tmpKey = $tmpKeyBase . '-' . $this->generateRandomBytes();

        if (!$this->cacheTemporaryImage($tmpKey, $image, $imageSize)) {
        return self::grumble($this->l->t(
          "Unable to save image data of size %s to temporary storage with key %s",
          [ strlen($image->data()), $tmpKey ]));
        }
      } catch (\Throwable $t) {
        $this->logException($t);
        return self::grumble($this->exceptionChainData($t));
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
        $this->logInfo('FOUND IMAGES '.print_r($findBy, true).' / '.count($joinTableEntities));
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
  private function getPlaceHolder($joinTable, $imageSize)
  {
    $placeHolderName = 'placeholder/'.Util::camelCaseToDashes($joinTable).'.svg';
    try {
      $placeHolderUrl = $this->urlGenerator()->imagePath($this->appName(), $placeHolderName);
    } catch (\Throwable $t) {
      //$this->logException($t);
      $imageData = $this->fallbackPlaceholder($imageSize);
      $imageFileName = 'placeholder.svg';
      $imageMimeType = 'image/svg+xml';
      return new Http\DataDownloadResponse($imageData, $imageFileName, $imageMimeType);
    }
    return new Http\RedirectResponse($placeHolderUrl);
  }

  private function fallbackPlaceholder($imageSize)
  {
    if (empty($imageSize) || $imageSize < 0) {
      $imageSize = '240pt';
    } else {
      $imageSize .= 'px';
    }
    $data =<<<'EOT'
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<svg
    xmlns:svg="http://www.w3.org/2000/svg"
    xmlns="http://www.w3.org/2000/svg"
EOT;
    $data .= '
    width="'.$imageSize.'"
    height="'.$imageSize.'"';
    $data .=<<<'EOT'
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
    if($imageSize > 0 && ($image->width() > $imageSize || $image->height() > $imageSize)) {
      $image->resize($imageSize); // Prettier resizing than with browser and saves bandwidth.
    }
    if(!$image->fixOrientation()) { // No fatal error so we don't bail out.
      $this->logDebug("Unable to fix orientation of uploaded image");
    }

    return $this->fileCache->set($tmpKey, $image->data(), 600);
  }

  /**
   * Fetch a real image (no placeholders, no cache) from an image storage.
   */
  private function getImage($joinTable, $ownerId, $imageId = self::IMAGE_ID_ANY) {
    $image = null;

    try {

      switch ($joinTable) {
      case 'UserStorage':
      case 'AppStorage':
        // $ownerId is a directory, $imageId a file in that directory
        if ($joinTable == 'UserStorage') {
          /** @var UserStorage $storage */
          $storage = $this->ci(UserStorage::class);
          $directory = $storage->getFolder($ownerId);
          /** @var \OCP\Files\File $file */
          if ($imageId == self::IMAGE_ID_ANY) {
            /** @var \OCP\Files\Node $node */
            foreach ($directory->getDirectoryListing() as $node) {
              if ($node->getType() == \OCP\Files\Node::TYPE_FILE) {
                $file = $node;
                break;
              }
            }
          } else {
            $file = $directory->get($imageId);
          }
        } else {
          /** @var AppStorage $storage */
          $storage = $this->ci(AppStorage::class);
          $directory = $storage->getFolder($ownerId);
          /** @var \OCP\Files\SimpleFS\ISimpleFile $file */
          if ($imageId == self::IMAGE_ID_ANY) {
            $file = array_shift($directory->getDirectoryListing());
          } else {
            $file = $directory->getFile($imageId);
          }
        }
        if (empty($file)) {
          break;
        }
        $image = new \OCP\Image();
        $image->loadFromData($file->getContent());
        $fileName = $file->getName();
        break;
      default: // data-base
        $imagesRepository = $this->getDatabaseRepository(Entities\Image::class);

        $joinTableClass = $imagesRepository->joinTableClass($joinTable);
        $this->logDebug("cooked table: ".$joinTableClass);

        $joinTableRepository = $this->getDatabaseRepository($joinTableClass);
        $findBy =  [ 'ownerId' => $ownerId ];
        if ($imageId > self::IMAGE_ID_PLACEHOLDER) {
          $findBy['image'] = $imageId;
        }

        $joinTableEntity = $joinTableRepository->findOneBy($findBy);
        if ($joinTableEntity == null) {
          $this->logInfo('NOT FOUND ' . $findBy);
          break;
        }

        /** @var Entities\Image $dbImage */
        $dbImage = $joinTableEntity->getImage();
        $fileName = $dbImage->getFileName();
        if (empty($fileName)) {
          $fileName = $joinTable . '-' . $ownerId . '-' . $dbImage->getId();
        }

        // otherwise return image blob as download

        $imageMimeType = $dbImage->getMimeType();
        $imageData = $dbImage->getFileData()->getData('binary');

        $image = new \OCP\Image();
        $image->loadFromData($imageData);

        if ($image->mimeType() !== $imageMimeType) {
          $this->logError("Mime-types stored / computed: ".$imageMimeType." / ".$image->mimeType());
          $this->logError('Trying to correct cached mime-type for image id ' . $dbImage->getId() . '.');
          $dbImage->setMimeType($image->mimeType());
          $this->flush();
        }
        break;
      }

    } catch (\Throwable $t) {
      $this->logException($t);
      return [];
    }

    return [ $image, $fileName ]; // maybe null
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
