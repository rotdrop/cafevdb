<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Documents;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\ExecutableFinder;

use OCP\ILogger;
use OCP\IL10N;
use OCP\Files\IMimeTypeDetector;
use OCP\ITempManager;

/**
 * A class which can convert "any" (read: some) file-data to PDF format.
 * Currently anything supported by LibreOffice via unoconv and .eml via
 * mhonarc will work.
 */
class AnyToPdf
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /**
   * @var string Array of available converters per mime-type. These form a
   * chain. If part of the chain is again an error then the first succeeding
   * sub-converter wins.
   */
  const CONVERTERS = [
    'message/rfc822' => [ 'mhonarc', [ 'wkhtmltopdf', 'unoconv', ], ],
    'application/postscript' => [ 'ps2pdf', ],
    'image/tiff' => [ 'tiff2pdf' ],
    'application/pdf' => [ 'passthrough' ],
    'default' => [ 'unoconv', ],
  ];

  /** @var IMimeTypeDetector */
  protected $mimeTypeDetector;

  /** @var ITempManager */
  protected $tempManager;

  /** @var IL10N */
  protected $l;

  /**
   * @var string
   * @todo Make it configurable
   * Paper size for converters which need it.
   */
  protected $paperSize = 'a4';

  public function __construct(
    IMimeTypeDetector $mimeTypeDetector
    , ITempManager $tempManager
    , ILogger $logger
    , IL10N $l
  ) {
    $this->mimeTypeDetector = $mimeTypeDetector;
    $this->tempManager = $tempManager;
    $this->logger = $logger;
    $this->l = $l;
  }

  public function convertData(string $data, ?string $mimeType = null):string
  {
    if (empty($mimeType)) {
      $mimeType = $this->mimeTypeDetector->detectString($data);
    }

    $converters = self::CONVERTERS[$mimeType] ?? self::CONVERTERS['default'];

    foreach ($converters as $converter) {
      if (!is_array($converter)) {
        $converter = [ $converter ];
      }
      $convertedData = null;
      foreach  ($converter as $tryConverter) {
        try {
          $method = $tryConverter . 'Convert';
          $convertedData = $this->$method($data);
          break;
        } catch (\Throwable $t) {
          $this->logException($t, 'Ignoring failed converter ' . $tryConverter);
        }
      }
      if (empty($convertedData)) {
        throw new \RuntimeException($this->l->t('Converter "%1$s" has failed trying to convert mime-type "%2$s"', [ print_r($converter, true), $mimeType ]));
      }
      $data = $convertedData;
      $convertedData = null;
    }

    return $data;
  }

  protected function passthroughConvert(string $data):string
  {
    return $data;
  }

  protected function unoconvConvert(string $data):string
  {
    $converterName = 'unoconv';
    $converter = (new ExecutableFinder)->find($converterName);
    if (empty($converter)) {
      throw new Exceptions\EnduserNotificationException($this->l->t('Please install the "%s" program on the server.', $converterName));
    }
    $retry = false;
    do {
      $process = new Process([
        $converter,
        '-f', 'pdf',
        '--stdin', '--stdout',
        '-e', 'ExportNotes=False'
      ]);
      $process->setInput($data);
      try  {
        $process->run();
        $retry = false;
      } catch (\Throwable $t) {
        $this->logException($t);
        $this->logError('RETRY');
        $retry = true;
      }
    } while ($retry);

    return $process->getOutput();
  }

  protected function mhonarcConvert(string $data):string
  {
    $converterName = 'mhonarc';
    $converter = (new ExecutableFinder)->find($converterName);
    if (empty($converter)) {
      throw new Exceptions\EnduserNotificationException($this->l->t('Please install the "%s" program on the server.', $converterName));
    }
    $process = new Process([
      $converter,
      '-single',
    ]);
    $process->setInput($data)->run();
    return $process->getOutput();
  }

  protected function ps2pdfConvert(string $data):string
  {
    $converterName = 'ps2pdf';
    $converter = (new ExecutableFinder)->find($converterName);
    if (empty($converter)) {
      throw new Exceptions\EnduserNotificationException($this->l->t('Please install the "%s" program on the server.', $converterName));
    }
    $process = new Process([
      $converter,
      '-', '-',
    ]);
    $process->setInput($data)->run();
    return $process->getOutput();
  }

  protected function wkhtmltopdfConvert(string $data):string
  {
    $converterName = 'wkhtmltopdf';
    $converter = (new ExecutableFinder)->find($converterName);
    if (empty($converter)) {
      throw new Exceptions\EnduserNotificationException($this->l->t('Please install the "%s" program on the server.', $converterName));
    }
    $process = new Process([
      $converter,
      '-', '-',
    ]);
    $process->setInput($data)->run();
    return $process->getOutput();
  }

  protected function tiff2pdfConvert(string $data):string
  {
    $converterName = 'tiff2pdf';
    $converter = (new ExecutableFinder)->find($converterName);
    if (empty($converter)) {
      throw new Exceptions\EnduserNotificationException($this->l->t('Please install the "%s" program on the server.', $converterName));
    }
    $inputFile = $this->tempManager->getTemporaryFile();
    $outputFile = $this->tempManager->getTemporaryFile();
    file_put_contents($inputFile, $data);

    // As of mow tiff2pdf cannot write to stdout.
    $process = new Process([
      $converter,
      '-p', $this->paperSize,
      '-o', $outputFile,
      $inputFile,
    ]);
    $process->run();
    $data = file_get_contents($outputFile);

    unlink($inputFile);
    unlink($outputFile);
    return $data;
  }
}
