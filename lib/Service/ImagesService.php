<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2024 Claus-Justus Heine
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

use RuntimeException;
use Imagick;
use ImagickPixel;

use OCP\ICache;
use OCP\IPreview;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Image as CloudImage;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Storage\UserStorage;

/**
 * Service routines for fetching photos and images.
 *
 * Bug in phpmd if full qualified class name has the same length as the alias name:
 *
 * @SuppressWarnings(PHPMD.MissingImport)
 */
class ImagesService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const USER_STORAGE = 'UserStorage';
  const APP_STORAGE = 'AppStorage';
  const TMP_STORAGE = 'cache';
  const DATABASE_STORAGE = 'DatabaseStorage';

  const IMAGE_ID_ANY = -1;
  const IMAGE_ID_PLACEHOLDER = 0;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected ConfigService $configService,
    protected EntityManager $entityManager,
    private ICache $fileCache,
    private IPreview $previewGenerator,
  ) {
    $this->l = $this->l10n();
  }
  // phpcs:enable

  /**
   * Generate a fall-back place-holder image of the given size.
   *
   * @param int $imageSize
   *
   * @return string
   */
  public function fallbackPlaceholder(int $imageSize):string
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

  /**
   * @param string $tmpKey
   *
   * @param CloudImage $image
   *
   * @param int $imageSize
   *
   * @return bool
   */
  public function cacheTemporaryImage(string $tmpKey, CloudImage $image, int $imageSize):bool
  {
    if ($imageSize > 0 && ($image->width() > $imageSize || $image->height() > $imageSize)) {
      $image->resize($imageSize); // Prettier resizing than with browser and saves bandwidth.
    }
    if (!$image->fixOrientation()) { // No fatal error so we don't bail out.
      $this->logDebug("Unable to fix orientation of uploaded image");
    }

    return $this->fileCache->set($tmpKey, $image->data(), 600);
  }

  /**
   * @param string $tmpKey
   *
   * @return void
   */
  public function deleteTemporaryImage(string $tmpKey):void
  {
    $this->fileCache->remove($tmpKey);
  }

  /**
   * Generate a preview image. Redirect to self::getImage() if the image is not file-based.
   *
   * @param string $joinTable
   *
   * @param mixed $ownerId
   *
   * @param mixed $imageId
   *
   * @param int $width
   *
   * @param int $height
   *
   * @return array
   */
  public function getPreviewImage(
    string $joinTable,
    mixed $ownerId,
    mixed $imageId = self::IMAGE_ID_ANY,
    int $width = -1,
    int $height = -1
  ):array {
    if ($joinTable != self::USER_STORAGE) {
      return $this->getImage($joinTable, $ownerId, $imageId);
    }

    $image = null;
    $fileName = null;

    try {
      // $ownerId is a directory, $imageId a file in that directory
      /** @var UserStorage $storage */
      $storage = $this->di(UserStorage::class);
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
      if (empty($file)) {
        throw new RuntimeException($this->l->t('File "%s" not found.', $imageId));
      }

      /** @var ISimpleFile $previewFile */
      $previewFile = $this->previewGenerator->getPreview($file, $width, $height);
      if (empty($previewFile)) {
        throw new RuntimeException($this->l->t('Failed to generate preview for file "%s".', $imageId));
      }

      $image = new CloudImage();
      $image->loadFromData($previewFile->getContent());
      $fileName = $file->getName();

    } catch (\Throwable $t) {
      $this->logException($t);
    }

    return [ $image, $fileName ];
  }

  /**
   * Fetch a real image (no placeholders, no cache) from an image storage.
   *
   * The function catches all exceptions and return an empty array on error.
   *
   * @param string $joinTable
   *
   * @param mixed $ownerId
   *
   * @param mixed $imageId
   *
   * @return array
   */
  public function getImage(string $joinTable, mixed $ownerId, mixed $imageId = self::IMAGE_ID_ANY):array
  {
    $image = null;
    $fileName = null;

    try {
      switch ($joinTable) {
        case self::TMP_STORAGE:
          $cacheKey = $ownerId;
          $imageData = $this->fileCache->get($cacheKey);

          $this->logDebug("Requested cache file with key ".$cacheKey);

          $image = new CloudImage();
          $image->loadFromData($imageData);
          $fileName = $cacheKey;
          break;
        case self::USER_STORAGE:
        case self::APP_STORAGE:
          // $ownerId is a directory, $imageId a file in that directory
          if ($joinTable == self::USER_STORAGE) {
            /** @var UserStorage $storage */
            $storage = $this->di(UserStorage::class);
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
            $storage = $this->di(AppStorage::class);
            $directory = $storage->getFolder($ownerId);
            /** @var \OCP\Files\SimpleFS\ISimpleFile $file */
            if ($imageId == self::IMAGE_ID_ANY) {
              $file = array_shift($directory->getDirectoryListing());
            } else {
              $file = $directory->getFile($imageId);
            }
          }
          if (empty($file)) {
            $this->logInfo('NO FILE FOR ' . $imageId);
            break;
          }
          $image = new CloudImage();
          $image->loadFromData($file->getContent());
          $fileName = $file->getName();
          break;
        default: // data-base
          $imagesRepository = $this->getDatabaseRepository(Entities\Image::class);

          if ($joinTable == self::DATABASE_STORAGE) {
            // plain data-base id without join table
            $dbImage = $imagesRepository->find($imageId);
            if (empty($dbImage)) {
              $this->logInfo('Unable to load image with id ' . $imageId);
            }
          } else {
            $joinTableClass = $imagesRepository->joinTableClass($joinTable);
            $this->logDebug("cooked table: ".$joinTableClass);

            $joinTableRepository = $this->getDatabaseRepository($joinTableClass);
            $findBy =  [ 'ownerId' => $ownerId ];
            if ($imageId > self::IMAGE_ID_PLACEHOLDER) {
              $findBy['image'] = $imageId;
            }

            $joinTableEntity = $joinTableRepository->findOneBy($findBy);
            if ($joinTableEntity == null) {
              $this->logDebug('NOT FOUND ' . print_r($findBy, true));
              break;
            }

            /** @var Entities\Image $dbImage */
            $dbImage = $joinTableEntity->getImage();
          }

          $fileName = $dbImage->getFileName();
          if (empty($fileName)) {
            $fileName = $joinTable . '-' . $ownerId . '-' . $dbImage->getId();
          }

          // otherwise return image blob as download

          $imageMimeType = $dbImage->getMimeType();
          $imageData = $dbImage->getFileData()->getData('binary');

          if (empty($imageData)) {
            $this->logInfo('EMPTY IMAGE DATA FOR FILE ID ' . $dbImage->getId());
            break;
          }

          $image = new CloudImage();
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

  /**
   * Fetch all available images for the given storage and ownerId.
   *
   * @param string $joinTable Either a database join-table or one of
   * self::USER_STORAGE, self::APP_STORAGE.
   *
   * @param mixed $ownerId Some kind of unique identifier. For the
   * file-storage options this is the path to a sub-directory holding
   * the image files.
   *
   * @return array
   * ```
   * [ id0, id1, id, ... ]
   *
   * ```
   */
  public function getImageIds(string $joinTable, mixed $ownerId):array
  {
    $result = [];
    try {
      switch ($joinTable) {
        case self::USER_STORAGE:
          // $ownerId is a directory, $imageId a file in that directory
          if (empty($ownerId)) {
            throw new RuntimeException($this->l->t('Images path must not be empty.'));
          }
          /** @var UserStorage $storage */
          $storage = $this->di(UserStorage::class);
          $directory = $storage->getFolder($ownerId);
          /** @var \OCP\Files\Node $node */
          foreach ($directory->getDirectoryListing() as $node) {
            if ($node->getType() == \OCP\Files\Node::TYPE_FILE && $node->getMimePart() == 'image') {
              $result[] = $node->getName();
            }
          }
          break;
        case self::APP_STORAGE:
          // $ownerId is a directory, $imageId a file in that directory
          /** @var AppStorage $storage */
          $storage = $this->di(AppStorage::class);
          $directory = $storage->getFolder($ownerId);
          /** @var \OCP\Files\SimpleFS\ISimpleFile $file */
          foreach ($directory->getDirectoryListing() as $file) {
            if (strpos($file->getMimeType(), 'image/') === '0') {
              $result[] = $file->getName();
            }
          }
          break;
        default: // data-base
          $imagesRepository = $this->getDatabaseRepository(Entities\Image::class);
          $joinTableClass = $imagesRepository->joinTableClass($joinTable);
          $joinTableRepository = $this->getDatabaseRepository($joinTableClass);
          $findBy =  [ 'ownerId' => $ownerId ];
          foreach ($joinTableRepository->findBy($findBy) as $joinTableEntity) {
            $result[] = $joinTableEntity->getImageId();
          }
          break;
      }
    } catch (\Throwable $t) {
      $this->logException($t);
    }
    return $result;
  }

  /**
   * Store the given image in the storage deduced from the parameters
   * $joinTable and $ownerId. Overwrite the image given by $imageId or
   * create a new one.
   *
   * @param CloudImage $image
   *
   * @param string $joinTable
   *
   * @param mixed $ownerId
   *
   * @param string $fileName
   *
   * @param int $imageId
   *
   * @return mixed The id of the stored image.
   *
   * @throws \Throwable
   */
  public function storeImage(
    CloudImage $image,
    string $joinTable,
    mixed $ownerId,
    string $fileName,
    int $imageId = self::IMAGE_ID_ANY,
  ) {
    switch ($joinTable) {
      case self::USER_STORAGE:
      case self::APP_STORAGE:
        // $ownerId is a directory, $imageId a file in that directory
        if ($imageId != self::IMAGE_ID_PLACEHOLDER && $imageId != $fileName) {
          throw new RuntimeException($this->l->t('Image-id "%s" must be identical to the file-name "%s" or %d.', [ $imageId, $fileName, self::IMAGE_ID_PLACEHOLDER ]));
        }
        if ($joinTable == self::USER_STORAGE) {
          /** @var UserStorage $storage */
          $storage = $this->di(UserStorage::class);
          $directory = $storage->getFolder($ownerId);
          $existsMethod = 'nodeExists';
        } else {
          /** @var AppStorage $storage */
          $storage = $this->di(AppStorage::class);
          $directory = $storage->getFolder($ownerId);
          $existsMethod = 'fileExists';
        }

        if ($imageId === self::IMAGE_ID_PLACEHOLDER && $directory->$existsMethod($fileName)) {
          $this->logInfo(sprintf('File %s / %s exists, generate versioned file-name.', $imageId, $fileName));
          $base = pathinfo($fileName, PATHINFO_FILENAME);
          $ext = pathinfo($fileName, PATHINFO_EXTENSION);
          $cnt = 0;
          do {
            $cnt++;
            $newFileName = sprintf('%s-%d.%s', $base, $cnt, $ext);
          } while ($directory->$existsMethod($newFileName));
          $fileName = $newFileName;
        }
        // just create a new file
        $directory->newFile($fileName, $image->data());
        return $fileName; // id == file-name
      default:
        // data-base
        $imagesRepository = $this->getDatabaseRepository(Entities\Image::class);
        $joinTable = Util::dashesToCamelCase($joinTable, true);
        $joinTableClass = $imagesRepository->joinTableClass($joinTable);
        $joinTableRepository =$this->getDatabaseRepository($joinTableClass);
        if ($imageId > self::IMAGE_ID_PLACEHOLDER) {
          $findBy = [ 'ownerId' => $ownerId, 'imageId' => $imageId, ];
          $joinTableEntity = $joinTableRepository->findOneBy($findBy);
          /** @var Entities\Image $dbImage */
          $dbImage = $joinTableEntity->getImage();
          $dbImage->setFileName($fileName);
          $dbImage->setMimeType($image->mimeType());
          $dbImage->getFileData()->setData($image->data(), 'binary');
          $this->flush();
        } else {
          $imagesRepository = $this->getDatabaseRepository(Entities\Image::class);
          $dbImage = $imagesRepository->persistForEntity($joinTable, $ownerId, $image, $fileName);
        }
        break;
    }

    return $dbImage->getId();
  }

  /**
   * If ownerId only is given, delete all images, if ownerId and
   * imageId is given delete only the given image
   * and join-table entry.
   *
   * @param string $joinTable
   *
   * @param mixed $ownerId
   *
   * @param int $imageId
   *
   * @return void
   */
  public function deleteImage(string $joinTable, mixed $ownerId, int $imageId):void
  {
    switch ($joinTable) {
      case self::USER_STORAGE:
        // $ownerId is a directory, $imageId a file in that directory
        /** @var UserStorage $storage */
        $storage = $this->di(UserStorage::class);
        $directory = $storage->getFolder($ownerId);
        if ($imageId == self::IMAGE_ID_ANY) {
          /** @var \OCP\Files\Node $node */
          foreach ($directory->getDirectoryListing() as $node) {
            if ($node->getType() == \OCP\Files\Node::TYPE_FILE) {
              $node->delete();
            }
          }
        } else {
          $directory->get($imageId)->delete();
        }
        break;
      case self::APP_STORAGE:
        /** @var AppStorage $storage */
        $storage = $this->di(AppStorage::class);
        $directory = $storage->getFolder($ownerId);
        /** @var \OCP\Files\SimpleFS\ISimpleFile $file */
        if ($imageId == self::IMAGE_ID_ANY) {
          foreach ($directory->getDirectoryListing() as $file) {
            $file->delete();
          }
        } else {
          $directory->getFile($imageId)->delete();
        }
        break;
      default: // data-base
        $imagesRepository = $this->getDatabaseRepository(Entities\Image::class);

        if ($joinTable == self::DATABASE_STORAGE) {
          $joinTableEntities = [ $imagesRepository->find($imageId) ];
        } else {
          $joinTableClass = $imagesRepository->joinTableClass($joinTable);
          $this->logDebug("cooked table: ".$joinTableClass);

          $joinTableRepository = $this->getDatabaseRepository($joinTableClass);

          $findBy =  [ 'ownerId' => $ownerId ];
          if ($imageId > self::IMAGE_ID_ANY) {
            $findBy['imageId'] = $imageId;
          }

          $joinTableEntities = $joinTableRepository->findBy($findBy);
        }

        if (count($joinTableEntities) < 1) {
          throw new RuntimeException(
            $this->l->t('Unable to find image link for ownerId/imageId "%s/%s" in join-table "%s".', [ $ownerId, $imageId, $joinTable ])
          );
        }
        foreach ($joinTableEntities as $joinTableEntity) {
          $this->remove($joinTableEntity);
        }
        $this->flush();
        break;
    }
  }

  /**
   * @param string $imageData
   *
   * @param int $maxX
   *
   * @param int $maxY
   *
   * @param string $mimeType
   *
   * @return null|CloudImage
   */
  public static function rasterize(string $imageData, int $maxX, int $maxY = -1, string $mimeType = 'image/png'):?CloudImage
  {
    if ($maxY < 0) {
      $maxY = $maxX;
    }

    $svg = new Imagick();
    $svg->setBackgroundColor(new ImagickPixel('transparent'));
    $svg->setResolution(300, 300);
    $svg->readImageBlob($imageData);
    $svg->setImageFormat('png32');

    //new image object
    $image = new CloudImage();
    $image->loadFromData($svg);

    //check if image object is valid
    if ($image->valid()) {
      $image->scaleDownToFit($maxX, $maxY);
      return $image;
    }
    return null;
  }
}
