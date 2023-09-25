<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023 Claus-Justus Heine
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

use DateTime;
use RuntimeException;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\FuzzyInputService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldDataType;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\PageRenderer\ProjectParticipantFields as Renderer;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Service\Finance\ReceivablesGeneratorFactory;
use OCA\CAFEVDB\Service\ProgressStatusService;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Service\Finance\IRecurringReceivablesGenerator as ReceivablesGenerator;

use OCA\CAFEVDB\Constants;

/**
 * Ajax end-point to support the definition of project-participant fields.
 *
 * @see Entities\ProjectParticipantField
 */
class ProjectParticipantFieldsController extends Controller
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const REQUEST_TOPIC_GENERATOR = 'generator';
  const REQUEST_TOPIC_OPTION = 'option';

  /**
   * @var string Resolve a receivables generator with the given user input.
   */
  const REQUEST_SUB_TOPIC_DEFINE = 'define';

  /**
   * @var string Generate (missing) fields.
   */
  const REQUEST_SUB_TOPIC_RUN = 'run';

  /**
   * @var string For the given project (re-)generate all generated
   * receivables. The other operations refer to one specific receivable.
   */
  const REQUEST_SUB_TOPIC_RUN_ALL = 'run-all';

  /**
   * Recompute one or all receivables, given on the request parameters
   * provided.
   */
  const REQUEST_SUB_TOPIC_REGENERATE = 'regenerate';

  const REQUEST_TOPIC_PROPERTY = 'property';
  const REQUEST_SUB_TOPIC_GET = 'get';

  const OPTION_PATCH_FIELDS = [
    'label',
  ];

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

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    $appName,
    IRequest $request,
    RequestParameterService $parameterService,
    ConfigService $configService,
    EntityManager $entityManager,
    Renderer $renderer,
    PHPMyEdit $phpMyEdit,
    FuzzyInputService $fuzzyInput,
    ProjectParticipantFieldsService $participantFieldsService,
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
  // phpcs:enable

  /**
   * @param string $topic
   *
   * @param null|string $subTopic
   *
   * @param null|array $data
   *
   * @return Response
   *
   * @NoAdminRequired
   */
  public function serviceSwitch(string $topic, ?string $subTopic, ?array $data = null):Response
  {
    $projectValues = $this->parameterService->getPrefixParams($this->pme->cgiDataName());
    switch ($topic) {
      case self::REQUEST_TOPIC_PROPERTY:
        foreach (['fieldId', 'property'] as $parameter) {
          if (empty($this->parameterService[$parameter])) {
            return self::grumble($this->l->t(
              'Missing parameters in request "%s": "%s".',
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
                $this->l->t(
                  'Unable to retrieve property "%s" from field "%s".',
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
                // fall through
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
              return self::grumble($this->l->t(
                'No or too many items available: "%s".',
                print_r($dataOptions, true) ));
            }
            $item = $dataOptions[0];
            if ($item['label'] != ReceivablesGeneratorFactory::GENERATOR_LABEL) {
              return self::grumble($this->l->t(
                'Generator data must be tagged with "%s" label, got "%s".',
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

            $operationLabels = $generatorClass::operationLabels();
            foreach ($operationLabels as $slug => $value) {
              if (is_callable($value)) {
                $operationLabels[$slug] = true;
              }
            }

            $updateStrategyChoices = $generatorClass::updateStrategyChoices();

            return self::dataResponse([
              'message' => $this->l->t('Generator "%s" successfully mapped to PHP-class "%s".', [ $item['data'], $generatorClass, ]),
              'value' => $generatorClass,
              'slug' => $generatorClass::slug(),
              'operationLabels' => $operationLabels,
              'availableUpdateStrategies' => $updateStrategyChoices,
            ]);
          case self::REQUEST_SUB_TOPIC_RUN:
            foreach (['fieldId', 'startDate',] as $parameter) {
              if (empty($data[$parameter])) {
                return self::grumble($this->l->t(
                  'Missing parameters in request "%s": "%s".',
                  [ $topic, $parameter ]));
              }
            }

            // id for progress-bar
            $progressToken = $data['progressToken'];

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

            // if we have a start date, then set it as time-stamp into
            // the limit-field of the management option
            if (!empty($data['startDate'])) {
              /** @var \DateTimeInterface $managementDate */
              $managementDate = Util::convertToDateTime($data['startDate']);
              $this->logInfo('DATE '.$managementDate->format('Y-m-d'));
              $managementOption->setLimit($managementDate->getTimestamp());
            }

            $progressStatus = $this->di(ProgressStatusService::class)->get($progressToken);

            /** @var OCA\CAFEVDB\Service\Finance\IRecurringReceivablesGenerator $generator */
            $generator = $this->di(ReceivablesGeneratorFactory::class)->getGenerator($field, $progressStatus);
            if (empty($generator)) {
              return self::grumble(
                $this->l->t(
                  'Unable to load generator for recurring receivables "%s".',
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
              return self::grumble($this->exceptionChainData($t));
            }

            // report back all options as HTML fragment
            $index = 0;
            $inputRows = [];
            foreach ($receivables as $receivable) {
              $inputRows[] = $this->renderer->dataOptionInputRowHtml(
                $receivable, $index++, $receivable->usage() > 0, $field->getDataType(),
              );
            }

            return self::dataResponse([
              'message' => $this->l->t("Request \"%s/%s\" successful", [ $topic, $subTopic, ]),
              'startDate' => $this->dateTimeFormatter()->formatDate(
                $managementOption->getLimit(), 'medium'),
              'dataOptionFormInputs' => $inputRows,
            ]);
          case self::REQUEST_SUB_TOPIC_REGENERATE:
            $missing = [];
            foreach (['fieldId', 'updateStrategy', 'progressToken'] as $parameter) {
              if (empty($data[$parameter])) {
                $missing[] = $parameter;
              }
            }
            if (!empty($missing)) {
              return self::grumble(
                $this->l->t('Missing parameters in request "%s/%s": "%s".', [
                  $topic, $subTopic, implode('", "', $missing),
                ]));
            }
            $updateStrategy = $data['updateStrategy'];
            if (array_search($updateStrategy, ReceivablesGenerator::UPDATE_STRATEGIES) === false) {
              return self::grumble(
                $this->l->t('Unknown update strategy: "%s".', $this->l->t($updateStrategy)));
            }
            $this->logInfo('Update Strategy ' . $updateStrategy);

            // fetch the field
            $fieldId = $data['fieldId'];
            /** @var Entities\ProjectParticipantField $field */
            $field = $this->getDatabaseRepository(Entities\ProjectParticipantField::class)->find($fieldId);
            if (empty($field)) {
              return self::grumble($this->l->t('Unable to fetch field with id "%d".', $fieldId));
            }

            // id for progress-bar
            $progressToken = $data['progressToken'];

            $fieldsAffected = 0;
            $messages = [];
            $this->entityManager->beginTransaction();
            try {
              $progressStatus = $this->di(ProgressStatusService::class)->get($progressToken);

              /** @var OCA\CAFEVDB\Service\Finance\IRecurringReceivablesGenerator $generator */
              $generator = $this->di(ReceivablesGeneratorFactory::class)->getGenerator($field, $progressStatus);
              if (empty($generator)) {
                throw new RuntimeException($this->l->t(
                    'Unable to load generator for recurring receivables "%s".',
                    $field->getName()));
              }

              /** @todo Make strategy selectable from UI */
              list(
                'added' => $added,
                'removed' => $removed,
                'changed' => $changed,
                'skipped' => $skipped,
                'notices' => $notices,
              ) = $generator->updateAll($updateStrategy);
              $this->flush();

              $messages[] = $this->l->t(
                'Field "%s", options addded/removed/changed: %d/%d/%d/%d.',
                [ $field->getName(), $added, $removed, $changed, $skipped ]);
              $fieldsAffected += $added + $removed + $changed;
              $messages += (array)$notices;
              $this->entityManager->commit();
            } catch (\Throwable $t) {
              $this->logException($t);
              $this->entityManager->rollback();
              return self::grumble($this->exceptionChainData($t));
            }

            return self::dataResponse([
              'message' => $messages,
              'fieldsAffected' => $fieldsAffected,
            ]);

            break;
          case self::REQUEST_SUB_TOPIC_RUN_ALL:
            // for the given project (re-)generate all generated receivables
            $missing = [];
            foreach (['projectId', 'updateStrategy', 'progressToken'] as $parameter) {
              if (empty($data[$parameter])) {
                $missing[] = $parameter;
              }
            }
            if (!empty($missing)) {
              return self::grumble(
                $this->l->t('Missing parameters in request "%s/%s": "%s".', [
                  $topic, $subTopic, implode('", "', $missing),
                ]));
            }
            $updateStrategy = $data['updateStrategy'];
            if (array_search($updateStrategy, ReceivablesGenerator::UPDATE_STRATEGIES) === false) {
              return self::grumble(
                $this->l->t('Unknown update strategy: "%s".', $this->l->t($updateStrategy)));
            }
            $this->logInfo('Update Strategy ' . $updateStrategy);

            /**  @var Entities\Project $project */
            $projectId = $data['projectId'];
            $project = $this->getDatabaseRepository(Entities\Project::class)->find($projectId);
            if (empty($project)) {
              return self::grumble($this->l->t('Unable to find the project with the id %d.', $projectId));
            }
            $generatedFields = $this->participantFieldsService->generatedFields($project);
            if ($generatedFields->isEmpty()) {
              return self::response(
                $this->l->t('Project "%s" has no generated fields.', $project->getName()));
            }

            // id for progress-bar
            $progressToken = $data['progressToken'];

            $fieldsAffected = 0;
            $messages = [];
            $this->entityManager->beginTransaction();
            try {
              $progressStatusService = $this->di(ProgressStatusService::class);
              foreach ($generatedFields as $field) {
                $progressStatus = $progressStatusService->get($progressToken);

                /** @var OCA\CAFEVDB\Service\Finance\IRecurringReceivablesGenerator $generator */
                $generator = $this->di(ReceivablesGeneratorFactory::class)->getGenerator($field, $progressStatus);
                if (empty($generator)) {
                  throw new RuntimeException($this->l->t(
                    'Unable to load generator for recurring receivables "%s".',
                    $field->getName()));
                }

                $generator->generateReceivables();
                $this->flush();

                /** @todo Make strategy selectable from UI */
                list(
                  'added' => $added,
                  'removed' => $removed,
                  'changed' => $changed,
                  'skipped' => $skipped,
                  'notices' => $notices,
                ) = $generator->updateAll($updateStrategy);
                $this->flush();

                $messages[] = $this->l->t(
                  'Field "%s", options addded/removed/changed/skipped: %d/%d/%d/%d.',
                  [ $field->getName(), $added, $removed, $changed, $skipped ]);
                $fieldsAffected += $added + $removed + $changed; // $skipped are not affected
                // display further messages if present
                $messages += (array)$notices;
              }

              $this->entityManager->commit();
            } catch (\Throwable $t) {
              $this->logException($t);
              $this->entityManager->rollback();
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
            $used  = ($data['used']??null) === 'used';
            $dataOptions = $projectValues['data_options'];

            $dataOptions = array_values($dataOptions); // get rid of -1 index

            // sanitize and potentially add missing keys
            $dataOptions = $this->participantFieldsService->explodeDataOptions(
              $this->participantFieldsService->implodeDataOptions($dataOptions),
              false);

            if (count($dataOptions) !== 1) {
              return self::grumble($this->l->t(
                'No or too many items available: %s',
                print_r($dataOptions, true) ));
            }

            $item = array_shift($dataOptions);

            if ($item['label'] === Constants::README_NAME) {
              return self::grumble($this->l->t(
                'Using "%1$s" as option-label is not allowed.'
                . ' The "%2$s"-file is reserved by the app to hold the contents of the tooltip for this field.', [
                  $item['label'], Constants::README_NAME ]));
            }

            // remove dangerous html
            $item['tooltip'] = $this->fuzzyInput->purifyHTML($item['tooltip']);

            switch ($data['dataType']??null) {
              case FieldDataType::RECEIVABLES:
              case FieldDataType::LIABILITIES:
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
              case FieldDataType::CLOUD_FILE:
                if ($item['label'] === Constants::README_NAME) {
                  return self::grumble($this->l->t(
                    'Using "%1$s" as option-label is not allowed.'
                    . ' The "%2$s"-file is used by the app to hold the contents of the help-text for this field.', [
                      $item['label'], Constants::README_NAME ]));
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
              $input = $this->renderer->dataOptionInputRowHtml($item, $index, $used, $data['dataType']);
            }
            $options = PageNavigation::selectOptions($options);

            return self::dataResponse([
              'message' => $this->l->t("Request \"%s/%s\" successful", [ $topic, $subTopic, ]),
              'dataOptionFormInputs' => $input,
              'dataOptionSelectOption' => $options,
            ]);
          case self::REQUEST_SUB_TOPIC_REGENERATE:
            // either musicianId or key may be missing
            $missing = [];
            foreach (['fieldId', 'updateStrategy'] as $parameter) {
              if (empty($data[$parameter])) {
                $missing[] = $parameter;
              }
            }
            if (empty($data['key']) && empty($data['musicianId'])) {
              $missing += [ 'key', 'musicianId' ];
            }
            if ((empty($data['key']) || empty($data['musicianId'])) && empty($data['progressToken'])) {
              $missing[] = 'progressToken';
            }
            if (!empty($missing)) {
              return self::grumble(
                $this->l->t('Missing parameters in request "%s/%s": "%s".', [
                  $topic, $subTopic, implode('", "', $missing),
                ]));
            }
            $updateStrategy = $data['updateStrategy'];
            if (array_search($updateStrategy, ReceivablesGenerator::UPDATE_STRATEGIES) === false) {
              return self::grumble(
                $this->l->t('Unknown update strategy: "%s".', $this->l->t($updateStrategy)));
            }
            $this->logInfo('Update Strategy ' . $updateStrategy);

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
                return self::grumble($this->l->t(
                  'Unable to find the musician with the id "%d" in project "%s".',
                  [ $data['musicianId'], $field->getProject()->getName(), ]));
              }
            }

            // id for progress-bar, maybe missing as checked above
            $progressToken = $data['progressToken'] ?? null;

            /** @var Entities\ProjectParticipantFieldDataOption $receivable */
            $receivable = null;
            if (!empty($data['key'])) {
              $receivable = $field->getDataOption($data['key']);
              if (empty($receivable)) {
                return self::grumble($this->l->t('Unable to fetch receivable with key "%s".', $data['key']));
              }
            }

            $progressStatus = $this->di(ProgressStatusService::class)->get($progressToken);

            /** @var OCA\CAFEVDB\Service\Finance\IRecurringReceivablesGenerator $generator */
            $generator = $this->di(ReceivablesGeneratorFactory::class)->getGenerator($field, $progressStatus);
            if (empty($generator)) {
              return self::grumble($this->l->t(
                'Unable to load generator for recurring receivables "%s".',
                $field->getName()));
            }

            $messages = [];
            $this->entityManager->beginTransaction();
            try {
              if (!empty($receivable)) {
                list(
                  'added' => $added,
                  'removed' => $removed,
                  'changed' => $changed,
                  'skipped' => $skipped,
                  'notices' => $notices,
                  'receivables' => $receivables,
                ) = $generator->updateReceivable($receivable, $participant, $updateStrategy);
                foreach ($receivable->getFieldData() as $receivableDatum) {
                  // unfortunately cascade does not work with multiple
                  // "complicated" associations.
                  $this->persist($receivableDatum);
                }
              } else {
                list(
                  'added' => $added,
                  'removed' => $removed,
                  'changed' => $changed,
                  'skipped' => $skipped,
                  'notices' => $notices,
                  'receivables' => $receivables,
                ) = $generator->updateParticipant($participant, null /* $receivable */, $updateStrategy);
                /** @var Entities\ProjectParticipantFieldDatum $datum */
                foreach ($participant->getParticipantFieldsData() as $datum) {
                  if ($datum->getField()->getId() == $fieldId) {
                    $this->persist($datum);
                  }
                }
              }
              $messages += $notices;
              $this->flush();
              $this->entityManager->commit();
            } catch (\Throwable $t) {
              $this->logException($t);
              $this->entityManager->rollback();
              return self::grumble($this->exceptionChainData($t));
            }

            $musician = $participant->getMusician();
            $musicianId = $musician->getId();
            $musicianName = $musician->getPublicName(firstNameFirst: true);
            $receivableAmounts = [];
            if (!empty($receivable)) {
              if (!empty($participant)) {
                $fieldData = $participant
                  ->getParticipantFieldsData()
                  ->matching(self::criteriaWhere(['optionKey' => $receivable->getKey()]));
                if (count($fieldData) > 0) {
                  /** @var Entities\ProjectParticipantFieldDatum $receivableDatum */
                  $receivableDatum = $fieldData->first();
                  $receivableAmounts[$musicianId] = $receivableDatum->getOptionValue();
                  if (!empty($receivableDatum->getSupportingDocument())) {
                    $projectService = $this->di(ProjectService::class);
                    $projectService->ensureParticipantFolder($participant->getProject(), $musician);
                  }
                }
              } else {
                /** @var Entities\ProjectParticipantFieldDatum $datum */
                foreach ($receivable->getFieldData() as $datum) {
                  $receivableAmounts[$musicianId] = $datum->getOptionValue();
                }
              }
            }

            array_unshift($messages, $this->l->t("Request \"%s/%s\" successful", [ $topic, $subTopic, ]));
            return self::dataResponse([
              'message' => $messages,
              'amounts' => $receivableAmounts,
              'added' => $added,
              'removed' => $removed,
              'changed' => $changed,
              'skipped' => $skipped,
              'musician' => [
                'id' => $musicianId,
                'name' => $musicianName,
              ],
              'receivables' => $receivables,
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

  public const GET_OPTIONS = 'options';

  /**
   * Request information about a field given its id.
   *
   * @param int $fieldId
   *
   * @param string $topic Major topic.
   *
   * @param string $subTopic Minor topic.
   *
   * @return DataResponse
   *
   * @NoAdminRequired
   */
  public function get(int $fieldId, string $topic = '', string $subTopic = ''):DataResponse
  {
    /** @var Entities\ProjectParticipantField $field */
    $field = $this->getDatabaseRepository(Entities\ProjectParticipantField::class)->find($fieldId);
    if (empty($field)) {
      return self::grumble($this->l->t('Unable to fetch field with id "%d".', $fieldId));
    }
    switch ($topic) {
      case self::GET_OPTIONS:
        $data = [];
        /** @var Entities\ProjectParticipantFieldDataOption $dataOption */
        foreach ($field->getSelectableOptions() as $dataOption) {
          $data[] = [
            'key' => (string)$dataOption->getKey(),
            'label' => $dataOption->getLabel(),
            'data' => $dataOption->getData(),
            'limit' => $dataOption->getLimit(),
            'untranslatedLabel' => $dataOption->getUntranslatedLabel(),
            'deleted' => $dataOption->getDeleted() === null ? null : $dataOption->getDeleted()->format(DateTime::W3C),
          ];
        }
        return self::dataResponse($data);
      default:
        break;
    }
    return self::grumble($this->l->t('Unknown request: "%1$s / %2$s".', [ $topic, $subTopic ]));
  }


  /**
   * @param string $topic The kind of object to patch. Currently only
   * self::REQUEST_SUB_TOPIC_OPTION is supported.
   *
   * @param int $fieldId The field to work on.
   *
   * @param string $objectId The identifier of the object to patch.
   *
   * @return Response
   *
   * @NoAdminRequired
   */
  public function patch(string $topic, int $fieldId, string $objectId):Response
  {
    /** @var Entities\ProjectParticipantField $field */
    $field = $this->getDatabaseRepository(Entities\ProjectParticipantField::class)->find($fieldId);
    if (empty($field)) {
      return self::grumble($this->l->t('Unable to fetch field with id "%d".', $fieldId));
    }
    switch ($topic) {
      case self::REQUEST_SUB_TOPIC_OPTION:
        $option = $field->getDataOption($objectId);
        if (empty($option)) {
          return self::grumble($this->l->t('Unable to find the option with id "%1$s" for field "%2$s".', [
            $objectId, $field->getName(),
          ]));
        }
        $patchedFields = [];
        foreach (self::OPTION_PATCH_FIELDS as $field) {
          if (isset($this->parameterService[$field])) {
            $value = $this->parameterService[$field];
            $option[$field] = empty($value) ? null : $value;
            $patchedFields[$field] = $value;
          }
        }
        try {
          $this->flush();
        } catch (\Throwable $t) {
          return self::grumble($this->exceptionChainData($t));
        }
        return self::dataResponse([
          'fieldId' => $fieldId,
          'optionKey' => $objectId,
          'patchSet' => $patchedFields,
        ]);
        break;
      default:
        break;
    }
    return self::grumble($this->l->t('Unknown Request "%s/%s"', [ $topic, $objectId ]));
  }
}
