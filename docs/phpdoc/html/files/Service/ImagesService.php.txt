<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

use InvalidArgumentException;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use MathiasReker\PhpSvgOptimizer\Services\SvgOptimizerService;

use OCP\Files\File;
use OCP\Files\IMimeTypeDetector;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\Image;

use OCA\CAFEVDB\Storage\AppStorageDisclosure;
use OCA\CAFEVDB\Exceptions;

/**
 * Some services for icons/images. Optimization, convert to data uris etc.
 */
class ImagesService
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  const CACHE_HASH_ALGORITHM = 'sha256';

  public const SVG_AS_IS = 0;
  public const SVG_OPTIMIZE = (1 << 0);
  public const SVG_TEXT_TO_PATH = (1 << 1);
  public const SVG_OPTIONS = self::SVG_OPTIMIZE|self::SVG_TEXT_TO_PATH;
  public const CONVERT_TO_SVG = (1 << 2);

  private const SVG_FROM_FILE_TAG = 'svg-from-file:';
  private const DATA_URI_TAG = 'data-uri:';
  private const SVG_MIME_TYPE = 'image/svg+xml';

  // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected AppStorageDisclosure $appStorage,
    protected IL10N $l,
    protected IMimeTypeDetector $mimeTypeDetector,
    protected LoggerInterface $logger,
    protected string $appName,
    private ExecutableFinder $executableFinder,
    private ITempManager $tempManager,
    private FilesCacheService $filesCacheService,
  ) {
  }
  // phcs:enable

  /**
   * Generate SVG image data from a given file. If the content of $file is not
   * already an SVG image, then try to generate a pixel image from $file and
   * wrap the resulting image into an SVG-tag. If $file already contains SVG
   * data then optionally try to optimize it and return the optimized SVG, as
   * specified by $options.
   *
   * @param File $file
   *
   * @param int $options Conversion options, defaults to self::SVG_OPTIMIZE.
   *
   * @param string $hash Hash value of the file in order to speed up validation.
   *
   * @return string
   *
   * @throw Exceptions\FilterFailedException
   */
  public function svgFromFile(File $file, int $options = self::SVG_OPTIMIZE, ?string $hash = null):string
  {
    $options &= self::SVG_OPTIONS;
    $data = $file->getContent();
    if ($options === self::SVG_AS_IS) {
      return $data; // just pass through
    }
    $this->filesCacheService->validate($file); // will erase all outdated cache data
    $cacheKey = self::SVG_FROM_FILE_TAG . $options;
    $cachedData = $this->filesCacheService->get($file, $cacheKey);
    if ($cachedData) {
      return $cachedData;
    }
    $data = $this->svgFromImageData($data, $file->getMimeType()); // no-op if already SVG
    $data = $this->svgToSvg($data, $options);
    $this->filesCacheService->set($file, $cacheKey, $data);
    return $data;
  }

  /**
   * Generate a data URI from the given file with caching.
   *
   * @param File $file
   *
   * @param int $options
   *
   * @return string
   */
  public function dataUriFromFile(File $file, int $options = self::SVG_OPTIMIZE):string
  {
    $mimeType = $file->getMimeType();
    // sanitize options in order to have a proper cache key suffix
    if ($mimeType == self::SVG_MIME_TYPE) {
      $options &= ~self::CONVERT_TO_SVG;
    } elseif (!($options & CONVERT_TO_SVG)) {
      $options &= ~SVG_OPTIONS;
    }
    $hash = $this->filesCacheService->validate($file);
    $cacheKey = self::DATA_URI_TAG . $options;
    $cachedData = $this->filesCacheService->get($file, $cacheKey);
    if ($cachedData) {
      return $cachedData;
    }
    if (($options & self::CONVERT_TO_SVG) || $mimeType == self::SVG_MIME_TYPE) {
      $data = $this->svgFromFile($file, $options, $hash);
      $mimeType = self::SVG_MIME_TYPE;
    } else {
      $data = $file->getContent();
    }
    $dataUri = $this->generateDataUri($data, $mimeType);
    $this->filesCacheService->set($file, $cacheKey, $dataUri);
    return $dataUri;
  }

  /**
   * Optimize a given SVG, cache the result and return the optimzed SVG as
   * string.
   *
   * @param string $data
   *
   * @param int $options Conversion flags. Defaults to self::SVG_OPTIMZE.
   *
   * @return string
   *
   * @throw Exceptions\FilterFailedException
   */
  public function svgToSvg(string $data, int $options = self::SVG_OPTIMIZE):string
  {
    $mimeType = $this->mimeTypeDetector->detectString($data);
    if ($mimeType !== self::SVG_MIME_TYPE) {
      throw new InvalidArgumentException($this->l->t('Given image data is not an SVG image. "%s".', $data));
    }
    $throwArgv = null;
    if ($options & self::SVG_TEXT_TO_PATH) {
      // libreoffice has a bug with SVGs which contain text.
      $inkscape = $this->executableFinder->find('inkscape');
      if (!empty($inkscape)) {
        $processArgv = [
          $inkscape,
          '--export-plain-svg',
          '--export-text-to-path',
          '-o', '-',
          '-p',
        ];
        $process = new Process($processArgv);
        try {
          $process->setInput($data)->run();
          $data = $process->getOutput();
        } catch (Throwable $t) {
          $throwArgv = $processArgv;
        }
      }
    }
    if ($options & self::SVG_OPTIMIZE) {
      $svgo = $this->executableFinder->find('svgo');
      if (!empty($svgo)) {
        $svgoConfigFile = $this->tempManager->getTemporaryFile();
        $svgoConfig = <<<'EOD'
export default {
  multipass: true,
  js2svg: {
    indent: 2,
    pretty: true,
  },
  plugins: [
    {
      name: 'preset-default',
      params: {
        overrides: {
          // viewBox is required to resize SVGs with CSS.
          // @see https://github.com/svg/svgo/issues/1128
          removeViewBox: false,
        },
      },
    },
  ],
};
EOD;
        file_put_contents($svgoConfigFile, $svgoConfig);
        $processArgv = [
          $svgo,
          '--input', '-',
          '--output', '-',
          '--config', $svgoConfigFile,
        ];
        $process = new Process($processArgv);
        try {
          $process->setInput($data)->run();
          $data = $process->getOutput();
        } catch (Throwable $t) {
          $throwArgv = $processArgv;
          // provide the config string as the temp file name  has no value
          array_pop($throwArgv);
          array_push($throwArgv, $svgoConfig);
        }
      }
    } else {
      try {
        $svgOptimizer = SvgOptimizerService::fromString($data)->optimize();
        $data = $svgOptimizer->getContent();
      } catch (Throwable $t) {
        $throwArgv = [
          SvgOptimizerService::class,
        ];
      }
    }
    if ($throwArgv) {
      throw new Exceptions\FilterFailedException(
        $processArgv,
        $this->l->t('Conversion of SVG image data failed.'),
        0,
        $t,
      );
    }
    return $data;
  }

  /**
   * Return $data unmodified if it is an SVG-image already, otherwise wrap a
   * given image into an SVG element.
   *
   * @param string $data
   *
   * @param null|string $mimeType
   *
   * @return string
   */
  public function svgFromImageData(string $data, ?string $mimeType = null):string
  {
    if (str_starts_with($data, 'data:')) {
      $data = file_get_contents($data);
      if ($mimeType === null) {
        $mimeType = mime_content_type($data);
      }
    }
    if ($mimeType === null) {
      $mimeType = $this->mimeTypeDetector->detectString($data);
    }
    if ($mimeType == self::SVG_MIME_TYPE) {
      return $data;
    }
    $cloudImage = new Image;
    $cloudImage->loadFromData($data);
    $width = $cloudImage->width();
    $height = $cloudImage->height();
    $dataUri = $this->generateDataUri($data, $mimeType);
    $svg =<<<EOD
<svg xmlns="http://www.w3.org/2000/svg"
     xmlns:xlink="http://www.w3.org/1999/xlink"
     viewBox="0 0 {$width} {$height}"
>
  <image width="{$width}" height="{$height}"
         xlink:href="{$dataUri}"
  />
</svg>
EOD;
    return $svg;
  }

  /**
   * Create a data-uri from a data string. If $data is svg then first convert
   * texts to paths as Libreoffice has a bug with font rendering with svg
   * images.
   *
   * @param string $data
   *
   * @param null|string $mimeType
   *
   * @return string
   */
  private function generateDataUri(string $data, ?string $mimeType = null):string
  {
    if ($mimeType === null) {
      $mimeType = $this->mimeTypeDetector->detectString($data);
    }
    return 'data:' . $mimeType . ';base64,' . base64_encode($data);
  }
}
