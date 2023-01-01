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

namespace OCA\CAFEVDB\Traits;

use Throwable;
use ReflectionClass;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;

/** Helper trait for the construction of HTTP responses. */
trait ResponseTrait
{
  /**
   * @param string $data
   *
   * @param string $fileName
   *
   * @param string $contentType
   *
   * @return Http\DataDownloadResponse
   */
  private function dataDownloadResponse(string $data, string $fileName, string $contentType):Http\DataDownloadResponse
  {
    $response = new Http\DataDownloadResponse($data, $fileName, $contentType);
    $response->addHeader(
      'Content-Disposition',
      'attachment; '
      . 'filename="' . $this->transliterate($fileName) . '"; '
      . 'filename*=UTF-8\'\'' . rawurlencode($fileName));

    return $response;
  }

  /**
   * @param Throwable $throwable
   *
   * @param string $renderAs
   *
   * @param null|string $method
   *
   * @return TemplateResponse
   */
  private function exceptionResponse(Throwable $throwable, string $renderAs, ?string $method = null):TemplateResponse
  {
    if (empty($method)) {
      $method = __METHOD__;
    }
    $this->logException($throwable, $method);
    if ($renderAs == 'blank') {
      return self::grumble($this->exceptionChainData($throwable));
    }

    $templateParameters = [
      'error' => 'exception',
      'exception' => $throwable->getMessage(),
      'code' => $throwable->getCode(),
      'trace' => $this->exceptionChainData($throwable),
      'debug' => true,
      'admin' => 'bofh@nowhere.com',
    ];

    return new TemplateResponse($this->appName, 'errorpage', $templateParameters, $renderAs);
  }

  /**
   * @param Throwable $throwable
   *
   * @param bool $top
   *
   * @return array
   */
  private function exceptionChainData(Throwable $throwable, bool $top = true):array
  {
    $previous = $throwable->getPrevious();
    $shortException = (new ReflectionClass($throwable))->getShortName();
    return [
      'message' => ($top
                    ? $this->l->t('Error, caught an exception.')
                    : $this->l->t('Caused by previous exception')),
      'exception' => $throwable->getFile().':'.$throwable->getLine().' '.$shortException.': '.$throwable->getMessage(),
      'code' => $throwable->getCode(),
      'trace' => $throwable->getTraceAsString(),
      'previous' => empty($previous) ? null : $this->exceptionChainData($previous, false),
    ];
  }

  /**
   * @param array $data
   *
   * @param int $status
   *
   * @return DataResponse
   */
  private static function dataResponse(array $data, int $status = Http::STATUS_OK):DataResponse
  {
    $response = new DataResponse($data, $status);
    $policy = $response->getContentSecurityPolicy();
    $policy->addAllowedFrameAncestorDomain("'self'");
    return $response;
  }

  /**
   * @param mixed $value
   *
   * @param string $message
   *
   * @param int $status
   *
   * @return DataResponse
   */
  private static function valueResponse(mixed $value, string $message = '', int $status = Http::STATUS_OK):DataResponse
  {
    return self::dataResponse(['message' => $message, 'value' => $value], $status);
  }

  /**
   * @param string $message
   *
   * @param int $status
   *
   * @return DataResponse
   */
  private static function response(string $message, int $status = Http::STATUS_OK):DataResponse
  {
    return self::dataResponse(['message' => $message], $status);
  }

  /**
   * @param string $message
   *
   * @param mixed $value
   *
   * @param int $status
   *
   * @return DataResponse
   */
  private static function grumble(string $message, mixed $value = null, int $status = Http::STATUS_BAD_REQUEST):DataResponse
  {
    $trace = debug_backtrace();
    $caller = array_shift($trace);
    $data = [
      'class' => __CLASS__,
      'file' => $caller['file'],
      'line' => $caller['line'],
      'value' => $value,
    ];
    if (is_array($message)) {
      $data = array_merge($data, $message);
    } else {
      $data['message'] = $message;
    }
    return self::dataResponse($data, $status);
  }

  /**
   * @param DataResponse $response
   *
   * @return array
   */
  private static function getResponseMessages(DataResponse $response):array
  {
    $messages = [];
    $data = $response->getData();
    foreach (['message', 'messages'] as $key) {
      $messageData = $data[$key] ?? [];
      if (!is_array($messageData)) {
        $messageData = [ $messageData ];
      }
      $messages = array_merge($messages, $messageData);
    }
    return $messages;
  }
}
