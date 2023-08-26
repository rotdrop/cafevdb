<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023 Claus-Justus Heine
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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\AppFramework\IAppContainer;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service\Finance\FinanceService;

/** Encryption/Decryption AJAX end-points. */
class CryptoController extends Controller
{
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  const META_DATA_IBAN = 'iban';

  /** @var Transformable\Transformer\TransformerInterface */
  protected $encryptionTransformer;

  /** @var IAppContainer */
  protected $appContainer;

  /** @var FinanceService */
  protected $financeService;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    IRequest $request,
    ILogger $logger,
    IAppContainer $appContainer,
    EntityManager $entityManager,
  ) {
    parent::__construct($appName, $request);

    $this->appContainer = $appContainer;
    $this->logger = $logger;
    $this->encryptionTransformer = $entityManager->getDataTransformer(EntityManager::TRANSFORM_ENCRYPT);
  }
  // phpcs:enable

  /**
   * Unseal the given data provided the currently logged in user is part of
   * the seal context.
   *
   * @param string $sealedData
   *
   * @param null|string $metaData
   *
   * @return Http\DataResponse
   *
   * @NoAdminRequired
   */
  public function unseal(string $sealedData, ?string $metaData):Http\DataResponse
  {
    return self::dataResponse($this->getUnsealedData($sealedData, $metaData));
  }

  /**
   * Unseal the given data provided the currently logged in user is part of
   * the seal context.
   *
   * @param array $sealedData
   *
   * @param null|string $metaData
   *
   * @return Http\DataResponse
   *
   * @NoAdminRequired
   */
  public function batchUnseal(array $sealedData, ?string $metaData):Http\DataResponse
  {
    $start = hrtime(true);
    $resultArray = [];
    foreach ($sealedData as $datum) {
      $resultArray[] = $this->getUnsealedData($datum, $metaData);
    }
    $this->logDebug('DURATION ' . (float)(hrtime(true) - $start) / 1e9);
    return self::dataResponse($resultArray);
  }

  /**
   * Unseal the given data provided the currently logged in user is part of
   * the seal context.
   *
   * @param string $sealedData
   *
   * @param null|string $metaData
   *
   * @return array
   */
  private function getUnsealedData(string $sealedData, ?string $metaData):array
  {
    $context = null;
    $unsealedData = $this->encryptionTransformer->reverseTransform($sealedData, $context);

    switch ($metaData) {
      case self::META_DATA_IBAN:
        $metaData = $this->getIBANMetaData($unsealedData);
        break;
      default:
        $metaData = null;
        break; // silently ignore
    }

    return [
      'hash' => md5($sealedData),
      'data' => $unsealedData,
      'context' => $context,
      'metaData' => $metaData,
    ];
  }

  /**
   * @param string $iban
   *
   * @return null|array
   */
  private function getIBANMetaData(string $iban):?array
  {
    if (empty($this->financeService)) {
      $this->financeService = $this->appContainer->get(FinanceService::class);
    }
    return $this->financeService->getIbanInfo($iban);
  }
}
