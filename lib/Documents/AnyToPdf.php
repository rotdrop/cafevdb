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

/**
 * A class which can convert "any" (read: some) file-data to PDF format.
 * Currently anything supported by LibreOffice via unoconv and .eml via
 * mhonarc will work.
 */
class AnyToPdf
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const CONVERTERS = [
    'message/rfc822' => [ 'mhonarc', 'unoconv', ],
    'default' => [ 'unoconv', ],
  ];

  /** @var IMimeTypeDetector */
  protected $mimeTypeDetector;

  /** @var IL10N */
  protected $l;

  public function __construct(
    IMimeTypeDetector $mimeTypeDetector
    , ILogger $logger
    , IL10N $l
  ) {
    $this->mimeTypeDetector = $mimeTypeDetector;
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
      $method = $converter . 'Convert';
      $data = $this->$method($data);
    }

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
    $converter->setInput($data);
    return $converter->getOutput();
  }
}
