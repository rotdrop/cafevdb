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

use Ramsey\Uuid\Uuid;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\FuzzyInputService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\PageRenderer\ProjectExtraFields as Renderer;
use OCA\CAFEVDB\Service\ProjectExtraFieldsService;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Service\Finance\ReceivablesGeneratorFactory;

class ProjectExtraFieldsController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var \OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit */
  protected $pme;

  /** @var RequestParameterService */
  private $parameterService;

  /** @var \OCA\CAFEVDB\Service\FuzzyInputService */
  private $fuzzyInput;

  /** @var \OCA\CAFEVDB\Service\ProjectExtraFieldsService */
  private $extraFieldsService;

  /** @var EntityManager */
  protected $entityManager;

  public function __construct(
    $appName
    , IRequest $request
    , RequestParameterService $parameterService
    , ConfigService $configService
    , EntityManager $entityManager
    , Renderer $renderer
    , PHPMyEdit $phpMyEdit
    , FuzzyInputService $fuzzyInput
    , ProjectExtraFieldsService $extraFieldsService
  ) {

    parent::__construct($appName, $request);

    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->renderer = $renderer;
    $this->pme = $phpMyEdit;
    $this->fuzzyInput = $fuzzyInput;
    $this->extraFieldsService = $extraFieldsService;
    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   */
  public function serviceSwitch($topic, $data = null)
  {
    $projectValues = $this->parameterService->getPrefixParams($this->pme->cgiDataName());
    switch ($topic) {
      case 'allowed-values-generator-run':
        if (empty($data['fieldId'])) {
          return self::grumble($this->l->t('Missing parameters in request %s', $topic));
        }

        // fetch the field
        $fieldId = $data['fieldId'];
        /** @var Entities\ProjectExtraField $field */
        $field = $this->getDatabaseRepository(Entities\ProjectExtraField::class)->find($fieldId);
        if (empty($field)) {
          return self::grumble($this->l->t('Unable to fetch field with id "%s".', $fieldId));
        }

        // fetch the generator
        $generator = $this->di(ReceivablesGeneratorFactory::class)->getGenerator($field);
        if (empty($generator)) {
          return self::grumble($this->l->t('Unable to load generator for recurring receivables "%s".',
                                           $field->getName()));
        }

        $this->entityManager->beginTransaction();
        try {
          $receivables = $generator->generateReceivables();
          $this->flush();
          $this->entityManager->commit();
        } catch (\Throwable $t) {
          $this->logException($t);
          $this->entityManager->rollback();
          if (!$this->entityManager->isTransactionActive()) {
            $this->entityManager->close();
            $this->entityManager->reopen();
          }
          return self::grumble($this->exceptionChainData($t));
        }

        // report back all options as HTML fragment
        $index = 0;
        $inputRows = [];
        foreach ($receivables as $receivable) {
          $inputRows[] = $this->renderer->dataOptionInputRowHtml(
            $receivable, $index++, $receivable->usage() > 0
          );
        }

        return self::dataResponse([
          'message' => $this->l->t("Request \"%s\" successful", $topic),
          'dataOptionFormInputs' => $inputRows,
        ]);

      case 'allowed-values-generator':
        if (empty($data)) {
          return self::grumble($this->l->t('Missing parameters in request %s', $topic));
        }
        $used = $data['used'] === 'used';
        $dataOptions = $projectValues['allowed_values'];
        $dataOptions = array_values($dataOptions); // get rid of -1 index
        if (count($dataOptions) !== 1) {
          return self::grumble($this->l->t('No or too many items available: %s',
                                           print_r($dataOptions, true) ));
        }
        $item = $dataOptions[0];
        if ($item['label'] != ReceivablesGeneratorFactory::GENERATOR_LABEL) {
          return self::grumble($this->l->t('Generator data must be tagged with "%s" label, got "%s".',
                                           [ ReceivablesGeneratorFactory::GENERATOR_LABEL, $item['label'], ]));
        }
        if ($item['key'] != Uuid::NIL) {
          return self::grumble($this->l->t('Generator data must be tagged with NIL uuid, got "%s".', $item['key']));
        }

        // data should return a PHP class name
        if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*(\\\\[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)*$/', $item['data'])) {
          return self::grumble($this->l->t('Generator "%s" does not appear to be valid PHP class name.', $item['data']));
        }

        // check the generator can be found
        $generator = null;
        try {
          $generator = $this->di($item['data']);
        } catch (\Throwable $t) {
          $this->logException($t);
        }
        if (empty($generator)) {
          return self::grumble($this->l->t('Generator "%s" could not be instantiated.', $item['data']));
        }

        return self::dataResponse([
          'message' => $this->l->t('Generator "%s" successfully validated.', $item['data']),
        ]);
      case 'allowed-values-option':
        if (empty($data)) {
          return self::grumble($this->l->t('Missing parameters in request %s', $topic));
        }
        $default = $data['default'];
        $index = $data['index'];
        $used  = $data['used'] === 'used';
        $dataOptions = $projectValues['allowed_values'];

        $dataOptions = array_values($dataOptions); // get rid of -1 index

        // sanitize and potentially add missing keys
        $dataOptions = $this->extraFieldsService->explodeAllowedValues(
          $this->extraFieldsService->implodeAllowedValues($dataOptions),
          false);

        if (count($dataOptions) !== 1) {
          return self::grumble($this->l->t('No or too many items available: %s',
                                           print_r($dataOptions, true) ));
        }

        $item = $dataOptions[0];

        // remove dangerous html
        $item['tooltip'] = $this->fuzzyInput->purifyHTML($item['tooltip']);

        switch ($data['data-type']) {
        case 'deposit':
        case 'service-fee':
        case 'money':
          // see that it is a valid decimal number ...
          if (!empty($item['data'])) {
            $parsed = $this->fuzzyInput->currencyValue($item['data']);
            if ($parsed === false) {
              return self::grumble($this->l->t('Could not parse number: "%s"', [ $item['data'] ]));
            }
            $item['data'] = $parsed;
          }
          break;
        default:
          break;
        }

        $input = '';
        $options = [];
        if (!empty($item['key'])) {
          $key = $item['key'];
          $options[] = [ 'name' => $item['label'],
                         'value' => $key,
                         'flags' => ($default === $key ? PageNavigation::SELECTED : 0) ];
          $input = $this->renderer->dataOptionInputRowHtml($item, $index, $used);
        }
        $options = PageNavigation::selectOptions($options);

        return self::dataResponse([
          'message' => $this->l->t("Request \"%s\" successful", $topic),
          'dataOptionFormInputs' => $input,
          'dataOptionSelectOption' => $options,
        ]);
      default:
        break;
    }
    return self::grumble($this->l->t('Unknown Request "%s"', $topic));
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
