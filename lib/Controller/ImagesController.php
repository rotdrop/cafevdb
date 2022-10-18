<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Controller;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

use OCP\Files\IRootFolder;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IL10N;
use OCP\AppFramework\Http\Response;
use OCP\Image as NextCloudImage;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ImagesService;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Storage\UserStorage;

/** AJAX end-point for fetch per-user photos. */
class ImagesController extends Controller
{
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

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    IRequest $request,
    RequestParameterService $parameterService,
    ConfigService $configService,
    ImagesService $imagesService,
    IRootFolder $rootFolder,
  ) {

    parent::__construct($appName, $request);

    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->imagesService = $imagesService;
    $this->rootFolder = $rootFolder;
    $this->l = $this->l10N();
  }
  // phpcs:enable

  /**
   * @param string $joinTable
   *
   * @param mixed $ownerId
   *
   * @param int $imageId
   *
   * @param int $imageSize
   *
   * @param null|int $previewWidth
   *
   * @return Http\Response
   *
   * @NoAdminRequired
   */
  public function get(
    string $joinTable,
    mixed $ownerId,
    int $imageId = ImagesService::IMAGE_ID_ANY,
    int $imageSize = -1,
    ?int $previewWidth = null,
  ):Http\Response {
    $ownerId = urldecode($ownerId);

    if ((string)$imageId == (string)ImagesService::IMAGE_ID_PLACEHOLDER) {
      // placeholder reguested
      return $this->getPlaceHolder($joinTable, $imageSize);
    }

    $image = null;
    if (!empty($previewWidth)) {
      list($image, $fileName) = $this->imagesService->getPreviewImage($joinTable, $ownerId, $imageId, $previewWidth);
    } else {
      list($image, $fileName) = $this->imagesService->getImage($joinTable, $ownerId, $imageId);
    }

    if (empty($image)) {
      return $this->getPlaceHolder($joinTable, $imageSize);
    }

    return $this->dataDownloadResponse($image->data(), $fileName, $image->mimeType());
  }

  /**
   * @param string $operation
   *
   * @param string $joinTable
   *
   * @param mixed $ownerId
   *
   * @param int $imageId
   *
   * @param int $imageSize
   *
   * @return Response
   *
   * @NoAdminRequired
   *
   * @SuppressWarnings(PHPMD.ShortVariable)
   */
  public function post(
    string $operation,
    string $joinTable,
    mixed $ownerId,
    int $imageId = ImagesService::IMAGE_ID_ANY,
    int $imageSize = -1,
  ):Http\Response {

    if (empty($joinTable)) {
      return self::grumble($this->l->t("Relation between image and object missing"));
    }

    switch ($joinTable) {
      case ImagesService::APP_STORAGE:
      case ImagesService::USER_STORAGE:
        $ownerId = urldecode($ownerId);
        $imageId = urldecode($imageId);
        break;
      default:
        if (!is_numeric($ownerId) || $ownerId <= 0) {
          return self::grumble($this->l->t("Image owner not given"));
        }
        break;
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
        $image = new NextCloudImage();
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
                $this->l->t(
                  "File `%s' not found in user's %s cloud storage.",
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

        if (!$this->imagesService->cacheTemporaryImage($tmpKey, $image, $imageSize)) {
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
          [projectId] => null
          [musicianId] => null
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

        /** @var NextCloudImage $image */
        list($image,) = $this->imagesService->getImage('cache', $tmpKey);
        if (empty($image)) {
          return self::grumble($this->l->t('Unable to load image with cache key %s', [ $tmpKey ]));
        }
        $this->imagesService->deleteTemporaryImage($tmpKey);
        $this->logDebug("Image data for key " . $tmpKey . " of size " . strlen($image->data()));

        $x1 = (int)$this->parameterService['x1'];
        $y1 = (int)$this->parameterService['y1'];
        $w  = (int)$this->parameterService['w'];
        $h  = (int)$this->parameterService['h'];

        $w = ($w > 0 ? $w : $image->width());
        $h = ($h > 0 ? $h : $image->height());
        $this->logDebug('CROP  x: '.$x1.' y: '.$y1.' w: '.$w.' h: '.$h);
        if ($x1 != 0 || $y1 != 0 || $w != $image->width() || $h != $image->height()) {
          $this->logDebug('Cropping image');
          if (!$image->crop($x1, $y1, $w, $h)) {
            return self::grumble(
              $this->l->t(
                'Unable to crop temporary image %s to [%s, %s, %s, %s]',
                [ $tmpKey, $x1, $y1, $x1 + $w - 1, $y1 + $h -1 ]));
          }
        }

        if ($imageSize > 0 && ($image->width() > $imageSize || $image->height() > $imageSize)) {
          $this->logDebug('Resizing image');
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

        if (empty($fileName)) {
          $fileName = $this->appName() . '-inline-image-' . $joinTable . '-' . $ownerId . '-' . $imageId;
        }

        $tmpKey = implode('-', [
          $this->appName(),
          'inline-image',
          $joinTable,
          md5($ownerId),
          md5($imageId),
          $this->generateRandomBytes(),
        ]);

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
   *
   * @param string $joinTable
   *
   * @param int $imageSize
   *
   * @return Http\RedirectResponse
   */
  private function getPlaceHolder(string $joinTable, int $imageSize):Http\RedirectResponse
  {
    $placeHolderName = 'placeholder/'.Util::camelCaseToDashes($joinTable).'.svg';
    try {
      $placeHolderUrl = $this->urlGenerator()->imagePath($this->appName(), $placeHolderName);
    } catch (\Throwable $t) {
      //$this->logException($t);
      $imageData = $this->imagesService->fallbackPlaceholder($imageSize);
      $imageFileName = 'placeholder.svg';
      $imageMimeType = 'image/svg+xml';
      return $this->dataDownloadResponse($imageData, $imageFileName, $imageMimeType);
    }
    return new Http\RedirectResponse($placeHolderUrl);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
