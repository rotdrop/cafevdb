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
use OCA\CAFEVDB\PageRenderer\ProjectParticipantFields as Renderer;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Service\Finance\ReceivablesGeneratorFactory;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Service\Finance\IRecurringReceivablesGenerator as ReceivablesGenerator;

class ProjectParticipantFieldsController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const REQUEST_TOPIC_GENERATOR = 'generator';
  const REQUEST_TOPIC_OPTION = 'option';
  const REQUEST_SUB_TOPIC_DEFINE = 'define';
  const REQUEST_SUB_TOPIC_RUN = 'run';
  const REQUEST_SUB_TOPIC_RUN_ALL = 'run-all';
  const REQUEST_SUB_TOPIC_REGENERATE = 'regenerate';

  const REQUEST_TOPIC_PROPERTY = 'property';
  const REQUEST_SUB_TOPIC_GET = 'get';

  /** @var PHPMyEdit */
  protected $pme;

  /** @var EntityManager */
  protected $entityManager;

  /** @var RequestParameterService */
  private $parameterService;

  /** @var FuzzyInputService */
  private $fuzzyInput;

  /** @var ProjectParticipantFieldsService */
  private $participantFieldsService;

  public function __construct(
    $appName
    , IRequest $request
    , RequestParameterService $parameterService
    , ConfigService $configService
    , EntityManager $entityManager
    , Renderer $renderer
    , PHPMyEdit $phpMyEdit
    , FuzzyInputService $fuzzyInput
    , ProjectParticipantFieldsService $participantFieldsService
  ) {

    parent::__construct($appName, $request);

    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->renderer = $renderer;
    $this->pme = $phpMyEdit;
    $this->fuzzyInput = $fuzzyInput;
    $this->participantFieldsService = $participantFieldsService;
    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   */
  public function serviceSwitch($topic, $subTopic, $data = null)
  {
    $projectValues = $this->parameterService->getPrefixParams($this->pme->cgiDataName());
    switch ($topic) {
    case self::REQUEST_TOPIC_PROPERTY:
      foreach (['fieldId', 'property'] as $parameter) {
        if (empty($this->parameterService[$parameter])) {
          return self::grumble($this->l->t('Missing parameters in request "%s": "%s".',
                                           [ $topic, $parameter ]));
        }
      }
      switch ($subTopic) {
      case self::REQUEST_SUB_TOPIC_GET:
        // fetch the field
        $fieldId = $this->parameterService['fieldId'];
        /** @var Entities\ProjectParticipantField $field */
        $field = $this->getDatabaseRepository(Entities\ProjectParticipantField::class)->find($fieldId);
        if (empty($field)) {
          return self::grumble($this->l->t('Unable to fetch field with id "%d".', $fieldId));
        }

        $property = $this->parameterService['property'];

        // remap special cases
        switch ($property) {
        case 'defaultDeposit':
          $fieldProperty = 'defaultValue';
          break;
        default:
          $fieldProperty = $property;
          break;
        }

        try {
          $propertyValue = $field[$fieldProperty];
        } catch (\Throwable $t) {
          $this->logException($t);
          return self::grumble(
            $this->l->t('Unable to retrieve property "%s" from field "%s".',
                        [ $property, $field->getName() ]));
        }

        // handle special cases, in particular the default value and deposit
        switch ($property) {
        case 'defaultValue':
          /** @var Entities\ProjectParticipantFieldDataOption $propertyValue */
          $propertyValue = $propertyValue->getData();
          break;
        case 'defaultDeposit':
          /** @var Entities\ProjectParticipantFieldDataOption $propertyValue */
          $propertyValue = $propertyValue->getDeposit();
        default:
          $propertyValue = (string)$propertyValue;
          break;
        }

        return self::dataResponse([
          'message' => $this->l->t('Request successful.'),
          'fieldId' => $fieldId,
          'property' => $property,
          'value' =>  $propertyValue,
        ]);
        break;
      default:
        break;
      }
      break;
    case self::REQUEST_TOPIC_GENERATOR:
      switch ($subTopic) {
      case self::REQUEST_SUB_TOPIC_DEFINE:
        if (empty($data)) {
          return self::grumble($this->l->t('Missing parameters in request "%s".', $topic));
        }
        $used = $data['used'] === 'used';
        $dataOptions = $projectValues['data_options'];
        $dataOptions = array_values($dataOptions); // get rid of -1 index
        if (count($dataOptions) !== 1) {
          return self::grumble($this->l->t('No or too many items available: "%s".',
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

        $generatorClass = null;
        try {
          $generatorClass = $this->participantFieldsService->resolveReceivableGenerator($item['data']);
        } catch (\Throwable $t) {
          $this->logException($t);
        }
        if (empty($generatorClass)) {
          return self::grumble($this->l->t('Generator "%s" could not be instantiated.', $item['data']));
        }

        return self::dataResponse([
          'message' => $this->l->t('Generator "%s" successfully mapped to PHP-class "%s".', [ $item['data'], $generatorClass, ]),
          'value' => $generatorClass,
        ]);
      case self::REQUEST_SUB_TOPIC_RUN:
        foreach (['fieldId', 'startDate'] as $parameter) {
          if (empty($data[$parameter])) {
            return self::grumble($this->l->t('Missing parameters in request "%s": "%s".',
                                             [ $topic, $parameter ]));
          }
        }

        // fetch the field
        $fieldId = $data['fieldId'];
        /** @var Entities\ProjectParticipantField $field */
        $field = $this->getDatabaseRepository(Entities\ProjectParticipantField::class)->find($fieldId);
        if (empty($field)) {
          return self::grumble($this->l->t('Unable to fetch field with id "%d".', $fieldId));
        }

        /** @var Entities\ProjectParticipantFieldDataOption $managementOption */
        $managementOption = $field->getManagementOption();
        if (empty($managementOption)) {
          return self::grumble(
            $this->l->t('No management option in field "%s".', $field->getName()));
        }

        // if we have a start date, then set it as time-stampe into
        // the limit-field of the management option
        if (!empty($data['startDate'])) {
          /** @var \DateTimeInterface $managementDate */
          $managementDate = Util::convertToDateTime($data['startDate']);
          $this->logInfo('DATE '.$managementDate->format('Y-m-d'));
          $managementOption->setLimit($managementDate->getTimestamp());
        }

        /** @var OCA\CAFEVDB\Service\Finance\IRecurringReceivablesGenerator $generator */
        $generator = $this->di(ReceivablesGeneratorFactory::class)->getGenerator($field);
        if (empty($generator)) {
          return self::grumble(
            $this->l->t('Unable to load generator for recurring receivables "%s".',
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
          'message' => $this->l->t("Request \"%s/%s\" successful", [ $topic, $subTopic, ]),
          'startDate' => $this->dateTimeFormatter()->formatDate(
            $managementOption->getLimit(), 'medium'),
          'dataOptionFormInputs' => $inputRows,
        ]);
      case self::REQUEST_SUB_TOPIC_RUN_ALL:
        // for the given project (re-)generate all generated receivables
        foreach (['projectId'] as $parameter) {
          if (empty($data[$parameter])) {
            return self::grumble($this->l->t('Missing parameters in request "%s": "%s".',
                                             [ $topic, $parameter ]));
          }
        }

        /**  @var Entities\Project $project */
        $projectId = $data['projectId'];
        $project = $this->getDatabaseRepository(Entities\Project::class)->find($projectId);
        if (empty($project)) {
          return self::grumble($this->l->t('Unable to find a project with id %d.', $projectId));
        }
        $generatedFields = $this->participantFieldsService->generatedFields($project);
        if ($generatedFields->count() == 0) {
          return self::response(
            $this->l->t('Project "%s" has no generated fields.', $project->getName()));
        }

        $fieldsAffected = 0;
        $messages = [];
        $this->entityManager->beginTransaction();
        try {
          foreach ($generatedFields as $field) {

            /** @var OCA\CAFEVDB\Service\Finance\IRecurringReceivablesGenerator $generator */
            $generator = $this->di(ReceivablesGeneratorFactory::class)->getGenerator($field);
            if (empty($generator)) {
              throw new \RuntimeException(
                $this->l->t('Unable to load generator for recurring receivables "%s".',
                            $field->getName()));
            }

            $generator->generateReceivables();
            $this->flush();

            /** @todo Make strategy selectable from UI */
            list('added' => $added, 'removed' => $removed, 'changed' => $changed) =
                         $generator->updateAll(ReceivablesGenerator::UPDATE_STRATEGY_EXCEPTION);
            $this->flush();

            $messages[] = $this->l->t(
              'Field "%s", options addded/removed/changed: %d/%d/%d.',
              [ $field->getName(), $added, $removed, $changed ]);
            $fieldsAffected += $added + $removed + $changed;
          }

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

        return self::dataResponse([
          'message' => $messages,
          'fieldsAffected' => $fieldsAffected,
        ]);
      default:
        break;
      }
      break;
    case self::REQUEST_TOPIC_OPTION:
      switch ($subTopic) {
      case self::REQUEST_SUB_TOPIC_DEFINE:
        if (empty($data)) {
          return self::grumble($this->l->t('Missing parameters in request %s', $topic));
        }
        $default = $data['default'];
        $index = $data['index'];
        $used  = $data['used'] === 'used';
        $dataOptions = $projectValues['data_options'];

        $dataOptions = array_values($dataOptions); // get rid of -1 index

        // sanitize and potentially add missing keys
        $dataOptions = $this->participantFieldsService->explodeDataOptions(
          $this->participantFieldsService->implodeDataOptions($dataOptions),
          false);

        if (count($dataOptions) !== 1) {
          return self::grumble($this->l->t('No or too many items available: %s',
                                           print_r($dataOptions, true) ));
        }

        $item = array_shift($dataOptions);

        // remove dangerous html
        $item['tooltip'] = $this->fuzzyInput->purifyHTML($item['tooltip']);

        switch ($data['data-type']) {
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
          if (!empty($item['deposit'])) {
            $parsed = $this->fuzzyInput->currencyValue($item['deposit']);
            if ($parsed === false) {
              return self::grumble($this->l->t('Could not parse number: "%s"', [ $item['deposit'] ]));
            }
            $item['deposit'] = $parsed;
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
          'message' => $this->l->t("Request \"%s/%s\" successful", [ $topic, $subTopic, ]),
          'dataOptionFormInputs' => $input,
          'dataOptionSelectOption' => $options,
        ]);
      case self::REQUEST_SUB_TOPIC_REGENERATE:
        if (empty($data['fieldId']) || (empty($data['key']) && empty($data['musicianId']))) {
          return self::grumble($this->l->t('Missing parameters in request "%s/%s"',
                                           [ $topic, $subTopic, ]));
        }

        $fieldId = $data['fieldId'];
        /** @var Entities\ProjectParticipantField $field */
        $field = $this->getDatabaseRepository(Entities\ProjectParticipantField::class)->find($fieldId);
        if (empty($field)) {
          return self::grumble($this->l->t('Unable to fetch field with id "%s".', $fieldId));
        }

        /** @var Entities\ProjectParticipant $participant */
        $participant = null;
        if (!empty($data['musicianId']) && $data['musicianId'] > 0) {
          $participant = $this->getDatabaseRepository(Entities\ProjectParticipant::class)->find([
            'project' => $field->getProject(),
            'musician' => $data['musicianId'],
          ]);
          if (empty($participant)) {
            return self::grumble($this->l->t('Unable to find musician with id "%d" in project "%s".',
                                             [ $data['musicianId'], $field->getProject()->getName(), ]));
          }
        }

        $receivable = null;
        if (!empty($data['key'])) {
          $receivable = $field->getDataOption($data['key']);
          if (empty($receivable)) {
            return self::grumble($this->l->t('Unable to fetch receivable with key "%s".', $data['key']));
          }
        }

        /** @var OCA\CAFEVDB\Service\Finance\IRecurringReceivablesGenerator $generator */
        $generator = $this->di(ReceivablesGeneratorFactory::class)->getGenerator($field);
        if (empty($generator)) {
          return self::grumble($this->l->t('Unable to load generator for recurring receivables "%s".',
                                           $field->getName()));
        }

        $this->entityManager->beginTransaction();
        try {
          if (!empty($receivable)) {
            $generator->updateReceivable($receivable, $participant);
            foreach ($receivable->getFieldData() as $receivableDatum) {
              // unfortunately cascade does not work with multiple
              // "complicated" associations.
              $this->persist($receivableDatum);
            }
          } else {
            $generator->updateParticipant($participant, $receivable);
            /** @var Entities\ProjectParticipantFieldDatum $datum */
            foreach ($participant->getParticipantFieldsData() as $datum) {
              if ($datum->getField()->getId() == $fieldId) {
                $this->persist($datum);
              }
            }
          }
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

        $receivableAmounts = [];
        if (!empty($participant) && !empty($receivable)) {
          $participantFieldsData = $participant->getParticipantFieldsData();
          $receivableData = $participantFieldsData->matching(self::criteriaWhere(['optionKey' => $receivable->getKey()]));
          $receivableAmounts[$participant->getMusician()->getId()] = $receivableData->first()->getOptionValue();
        } else if (!empty($receivable)) {
          /** @var Entities\ProjectParticipantFieldDatum $datum */
          foreach ($receivable->getFieldData() as $datum) {
            $receivableAmounts[$datum->getMusician()->getId()] = $datum->getOptionValue();
          }
        }

        return self::dataResponse([
          'message' => $this->l->t("Request \"%s/%s\" successful", [ $topic, $subTopic, ]),
          'amounts' => $receivableAmounts,
        ]);
      default:
        break;
      }
      break;
    default:
      break;
    }
    return self::grumble($this->l->t('Unknown Request "%s/%s"', [ $topic, $subTopic ]));
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
