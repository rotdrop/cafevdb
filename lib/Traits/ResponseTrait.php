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

//WIP
namespace OCA\CAFEVDB\Traits;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

use OCA\CAFEVDB\Service\SessionService;

trait ResponseTrait
{

  private function exceptionChainData(\Throwable $t, bool $top = true)
  {
    $previous = $t->getPrevious();
    return [
      'message' => ($top
                    ? $this->l->t('Error, caught an exception')
                    : $this->l->t('Caused by previous exception')),
      'exception' => $t->getFile().':'.$t->getLine().' '.$t->getMessage(),
      'trace' => $t->getTraceAsString(),
      'previous' => empty($previous) ? null : $this->exceptionChainData($previous, false),
    ];
  }

  static private function dataResponse($data, $status = Http::STATUS_OK)
  {
    return new DataResponse($data, $status);
  }

  static private function valueResponse($value, $message = '', $status = Http::STATUS_OK)
  {
    return self::dataResponse(['message' => $message, 'value' => $value], $status);
  }

  static private function response($message, $status = Http::STATUS_OK)
  {
    return self::dataResponse(['message' => $message], $status);
  }

  static private function grumble($message, $value = null, $status = Http::STATUS_BAD_REQUEST)
  {
    if (isset($message['message']) && empty($value)) {
      return self::dataResponse($message, $status);
    } else {
      return self::valueResponse($value, $message, $status);
    }
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
