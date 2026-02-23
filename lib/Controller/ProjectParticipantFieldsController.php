<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2026 Claus-Justus Heine
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

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

use DateTime;
use RuntimeException;
use Throwable;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute as CoreAttributes;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicty;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldDataType;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\PageRenderer\ProjectParticipantFields as Renderer;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\Finance\IRecurringReceivablesGenerator as ReceivablesGenerator;
use OCA\CAFEVDB\Service\Finance\ReceivablesGeneratorFactory;
use OCA\CAFEVDB\Service\FuzzyInputService;
use OCA\CAFEVDB\Service\ProgressStatusService;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\Service\ProjectService;

/**
 * Ajax end-point to support the definition of project-participant fields.
 *
 * @see Entities\ProjectParticipantField
 */
#[TSAttributes\TypeScript]
class ProjectParticipantFieldsController extends Controller
{
  use GetPrefixParamsTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  public const END_POINT = 'projects/participant-fields';

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    $appName,
    IRequest $request,
    private FuzzyInputService $fuzzyInput,
    private ProjectParticipantFieldsService $participantFieldsService,
    private Renderer $renderer,
    protected ConfigService $configService,
    protected EntityManager $entityManager,
    protected PHPMyEdit $pme,
  ) {
    parent::__construct($appName, $request);

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
   * @throws Exceptions\EnduserNotificationException
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(verb: 'POST', url: '/' . self::END_POINT . '/{topic}/{subTopic}')]
  public function serviceSwitch(string $topic, ?string $subTopic, ?array $data = null): Response
  {
    $topic = EnumParticipantFieldRequestTopic::get($topic);
    $subTopic = $subTopic ? EnumParticipantFieldRequestSubTopic::get($subTopic) : null;
    $projectValues = $this->getPrefixParams($this->pme->cgiDataName());
    switch ($topic) {
      case EnumParticipantFieldRequestTopic::PROPERTY:
        foreach (['fieldId', 'property'] as $parameter) {
          if (empty($this->request->getParam($parameter))) {
            throw new Exceptions\EnduserNotificationException(
              $this->l->t(
                'Missing parameters in request "%s": "%s".',
                [ $topic, $parameter ]),
            );
          }
        }
        switch ($subTopic) {
          case EnumParticipantFieldRequestSubTopic::GET:
            // fetch the field
            $fieldId = $this->request->getParam('fieldId');
            /** @var Entities\ProjectParticipantField $field */
            $field = $this->getDatabaseRepository(Entities\ProjectParticipantField::class)->find($fieldId);
            if (empty($field)) {
              throw new Exceptions\EnduserNotificationException(
                $this->l->t('Unable to fetch field with id "%d".', $fieldId),
              );
            }

            $property = EnumParticipantFieldPropertyGet::get($this->request->getParam('property'));

            try {
              $propertyValue = $field->getDefaultValue();
            } catch (Throwable $t) {
              throw new Exceptions\EnduserNotificationException(
                $this->l->t(
                  'Unable to retrieve property "%s" from field "%s".',
                  [ $property, $field->getName() ]),
                previous: $t,
              );
            }

            // handle special cases, in particular the default value and deposit
            switch ($property) {
              case EnumParticipantFieldPropertyGet::DEFAULT_VALUE:
                /** @var Entities\ProjectParticipantFieldDataOption $propertyValue */
                $propertyValue = [
                  'data' => $propertyValue->getData(),
                  'key' => $propertyValue->getKey(),
                ];
                break;
              case EnumParticipantFieldPropertyGet::DEFAULT_DEPOSIT:
                /** @var Entities\ProjectParticipantFieldDataOption $propertyValue */
                $propertyValue = $propertyValue->getDeposit();
                break;
              default:
                throw UnexpectedValueException(
                  $this->l->t('Received the unexpected value "%s" for the "property" parameter.', $property->value),
                );
            }

            return DTO\ParticipantFieldPropertyGetResponse::fromArray([
              'messages' => [ $this->l->t('Request successful.') ],
              'fieldId' => $fieldId,
              'property' => $property,
              'value' =>  $propertyValue,
            ])->response();

          default:
            break;
        }
        break;
      case EnumParticipantFieldRequestTopic::GENERATOR:
        switch ($subTopic) {
          case EnumParticipantFieldRequestSubTopic::DEFINE:
            if (empty($data)) {
              new Exceptions\EnduserNotificationException(
                ($this->l->t('Missing parameters in request "%s".', $topic)),
              );
            }
            $used = $data['used'] === 'used';
            $dataOptions = $projectValues['data_options'];
            $dataOptions = array_values($dataOptions); // get rid of -1 index
            if (count($dataOptions) !== 1) {
              new Exceptions\EnduserNotificationException(
                ($this->l->t(
                  'No or too many items available: "%s".',
                  print_r($dataOptions, true) )),
              );
            }
            $item = $dataOptions[0];
            if ($item['label'] != ReceivablesGeneratorFactory::GENERATOR_LABEL) {
              new Exceptions\EnduserNotificationException(
                ($this->l->t(
                  'Generator data must be tagged with "%s" label, got "%s".',
                  [ ReceivablesGeneratorFactory::GENERATOR_LABEL, $item['label'], ])),
              );
            }
            if ($item['key'] != Uuid::NIL) {
              new Exceptions\EnduserNotificationException(
                ($this->l->t('Generator data must be tagged with NIL uuid, got "%s".', $item['key'])),
              );
            }

            $generatorClass = null;
            try {
              $generatorClass = $this->participantFieldsService->resolveReceivableGenerator($item['data']);
            } catch (Throwable $t) {
              $this->logException($t);
            }
            if (empty($generatorClass)) {
              new Exceptions\EnduserNotificationException(
                ($this->l->t('Generator "%s" could not be instantiated.', $item['data'])),
              );
            }

            $operationLabels = $generatorClass::operationLabels();
            foreach ($operationLabels as $slug => $value) {
              if (is_callable($value)) {
                $operationLabels[$slug] = true;
              }
            }

            $updateStrategyChoices = $generatorClass::updateStrategyChoices();

            return new DTO\ParticipantFieldGeneratorDefineResponse(
              messages: [$this->l->t('Generator "%s" successfully mapped to PHP-class "%s".', [ $item['data'], $generatorClass, ])],
              value: $generatorClass,
              slug: $generatorClass::slug(),
              operationLabels: $operationLabels,
              availableUpdateStrategies: $updateStrategyChoices,
            )->response();

          case EnumParticipantFieldRequestSubTopic::RUN:
            foreach (['fieldId', 'startDate',] as $parameter) {
              if (empty($data[$parameter])) {
                new Exceptions\EnduserNotificationException(
                  ($this->l->t(
                    'Missing parameters in request "%s": "%s".',
                    [ $topic, $parameter ])),
                );
              }
            }

            // id for progress-bar
            $progressToken = $data['progressToken'];

            // fetch the field
            $fieldId = $data['fieldId'];
            /** @var Entities\ProjectParticipantField $field */
            $field = $this->getDatabaseRepository(Entities\ProjectParticipantField::class)->find($fieldId);
            if (empty($field)) {
              new Exceptions\EnduserNotificationException(
                ($this->l->t('Unable to fetch field with id "%d".', $fieldId)),
              );
            }

            /** @var Entities\ProjectParticipantFieldDataOption $managementOption */
            $managementOption = $field->getManagementOption();
            if (empty($managementOption)) {
              new Exceptions\EnduserNotificationException(
                (
                  $this->l->t('No management option in field "%s".', $field->getName())),
              );
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
              new Exceptions\EnduserNotificationException(
                (
                  $this->l->t(
                    'Unable to load generator for recurring receivables "%s".',
                    $field->getName())),
              );
            }

            $this->entityManager->beginTransaction();
            try {
              $receivables = $generator->generateReceivables();
              $this->flush();
              $this->entityManager->commit();
            } catch (Throwable $t) {
              $this->logException($t);
              $this->entityManager->rollback();
              throw new Exceptions\EnduserNotificationException(
                $this->l->t('Unable to generate receivables for the field "%1$s".', $field->getName()),
                previous: $t,
                httpStatusCode: Http::STATUS_INTERNAL_SERVER_ERROR,
              );
            }

            // report back all options as HTML fragment
            $index = 0;
            $inputRows = [];
            foreach ($receivables as $receivable) {
              $inputRows[] = $this->renderer->dataOptionInputRowHtml(
                $receivable, $index++, $receivable->usage() > 0, $field->getDataType(),
              );
            }

            return new DTO\ParticipantFieldGeneratorRunResponse(
              messages: [$this->l->t("Request \"%s/%s\" successful", [ $topic, $subTopic, ])],
              startDate: $this->dateTimeFormatter()->formatDate(
                $managementOption->getLimit(),
                'medium',
              ),
              dataOptionFormInputs: $inputRows,
            )->response();

          default:
            break;
        }
        break;
      case EnumParticipantFieldRequestTopic::OPTION:
        switch ($subTopic) {
          case EnumParticipantFieldRequestSubTopic::DEFINE:
            if (empty($data)) {
              new Exceptions\EnduserNotificationException(
                ($this->l->t('Missing parameters in request %s', $topic)),
              );
            }
            $default = $data['default'];
            $index = $data['index'];
            $used  = ($data['used'] ?? null) === 'used';
            $dataOptions = $projectValues['data_options'];

            $dataOptions = array_values($dataOptions); // get rid of -1 index

            // sanitize and potentially add missing keys
            $dataOptions = $this->participantFieldsService->explodeDataOptions(
              $this->participantFieldsService->implodeDataOptions($dataOptions),
              false);

            if (count($dataOptions) !== 1) {
              new Exceptions\EnduserNotificationException(
                ($this->l->t(
                  'No or too many items available: %s',
                  print_r($dataOptions, true) )),
              );
            }

            $item = array_shift($dataOptions);

            if ($item['label'] === Constants::README_NAME) {
              new Exceptions\EnduserNotificationException(
                ($this->l->t(
                  'Using "%1$s" as option-label is not allowed.'
                  . ' The "%2$s"-file is reserved by the app to hold the contents of the tooltip for this field.', [
                    $item['label'], Constants::README_NAME ])),
              );
            }

            // remove dangerous html
            $item['tooltip'] = $this->fuzzyInput->purifyHTML($item['tooltip']);

            // receivable generators make their own use (if any) of the data field.
            if ($data['multiplicity'] != FieldMultiplicty::RECURRING) {
              switch ($data['dataType'] ?? null) {
                case FieldDataType::RECEIVABLES:
                case FieldDataType::LIABILITIES:
                  // see that it is a valid decimal number ...
                  if (!empty($item['data'])) {
                    $parsed = $this->fuzzyInput->currencyValue($item['data']);
                    if ($parsed === false) {
                      throw new Exceptions\EnduserNotificationException(
                        $this->l->t('Could not parse number: "%s"', $item['data']),
                        context: [ 'item' =>  $item ],
                      );
                    }
                    $item['data'] = $parsed;
                  }
                  if (!empty($item['deposit'])) {
                    $parsed = $this->fuzzyInput->currencyValue($item['deposit']);
                    if ($parsed === false) {
                      new Exceptions\EnduserNotificationException(
                        ($this->l->t('Could not parse number: "%s"', [ $item['deposit'] ])),
                      );
                    }
                    $item['deposit'] = $parsed;
                  }
                  break;
                case FieldDataType::CLOUD_FILE:
                  if ($item['label'] === Constants::README_NAME) {
                    new Exceptions\EnduserNotificationException(
                      ($this->l->t(
                        'Using "%1$s" as option-label is not allowed.'
                        . ' The "%2$s"-file is used by the app to hold the contents of the help-text for this field.', [
                          $item['label'], Constants::README_NAME ])),
                    );
                  }
                  break;
                default:
                  break;
              }
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

            return new DTO\ParticipantFieldOptionDefineResponse(
              messages: [$this->l->t("Request \"%s/%s\" successful", [ $topic, $subTopic, ])],
              dataOptionFormInputs: $input,
              dataOptionSelectOptions: $options,
            )->response();

          case EnumParticipantFieldRequestSubTopic::REGENERATE:
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
            if (!empty($missing)) {
              throw new Exceptions\EnduserNotificationException(
                (
                  $this->l->t('Missing parameters in request "%s/%s": "%s".', [
                    $topic, $subTopic, implode('", "', $missing),
                  ])),
              );
            }
            $updateStrategy = $data['updateStrategy'];
            if (array_search($updateStrategy, ReceivablesGenerator::UPDATE_STRATEGIES) === false) {
              new Exceptions\EnduserNotificationException(
                (
                  $this->l->t('Unknown update strategy: "%s".', $this->l->t($updateStrategy ?? 'empty'))),
              );
            }
            $this->logInfo('Update Strategy ' . $updateStrategy);

            $fieldId = $data['fieldId'];
            /** @var Entities\ProjectParticipantField $field */
            $field = $this->getDatabaseRepository(Entities\ProjectParticipantField::class)->find($fieldId);
            if (empty($field)) {
              new Exceptions\EnduserNotificationException(
                ($this->l->t('Unable to fetch field with id "%s".', $fieldId)),
              );
            }

            /** @var Entities\ProjectParticipant $participant */
            $participant = null;
            if (!empty($data['musicianId']) && $data['musicianId'] > 0) {
              $participant = $this->getDatabaseRepository(Entities\ProjectParticipant::class)->find([
                'project' => $field->getProject(),
                'musician' => $data['musicianId'],
              ]);
              if (empty($participant)) {
                new Exceptions\EnduserNotificationException(
                  ($this->l->t(
                    'Unable to find the musician with the id "%d" in project "%s".',
                    [ $data['musicianId'], $field->getProject()->getName(), ])),
                );
              }
            }

            // id for progress-bar, maybe missing as checked above
            $progressToken = $data['progressToken'] ?? null;

            /** @var Entities\ProjectParticipantFieldDataOption $receivable */
            $receivable = null;
            if (!empty($data['key'])) {
              $receivable = $field->getDataOption($data['key']);
              if (empty($receivable)) {
                new Exceptions\EnduserNotificationException(
                  ($this->l->t('Unable to fetch receivable with key "%s".', $data['key'])),
                );
              }
            }

            $progressStatus = $this->di(ProgressStatusService::class)->get($progressToken);

            /** @var OCA\CAFEVDB\Service\Finance\IRecurringReceivablesGenerator $generator */
            $generator = $this->di(ReceivablesGeneratorFactory::class)->getGenerator($field, $progressStatus);
            if (empty($generator)) {
              new Exceptions\EnduserNotificationException(
                ($this->l->t(
                  'Unable to load generator for recurring receivables "%s".',
                  $field->getName())),
              );
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
            } catch (Throwable $t) {
              $this->logException($t);
              $this->entityManager->rollback();
              throw new Exceptions\EnduserNotificationException(
                $this->l->t(
                  'Unable to update receivable "%1$s@%2$s" for the person "%3$s" using the generator "%4$s".', [
                    $receivable?->getLabel() ?? $this->l->t('unknown'),
                    $field->getName(),
                    $participant?->getMusician()?->getPublicName() ?? $this->l->t('unknown'),
                    get_class($generator),
                  ]),
                previous: $t,
                httpStatusCode: Http::STATUS_INTERNAL_SERVER_ERROR,
              );
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
            return DTO\ReceivablesStatistics::fromArray([
              'messages' => $messages,
              'amounts' => $receivableAmounts,
              'added' => $added,
              'removed' => $removed,
              'changed' => $changed,
              'skipped' => $skipped,
              'musicians' => [ $musicianId => $musicianName ],
              'receivables' => $receivables,
            ])->response();
          default:
            break;
        }
        break;
      default:
        break;
    }
    throw new Exceptions\EnduserNotificationException(
      ($this->l->t('Unknown Request "%s/%s"', [ $topic, $subTopic ])),
    );
  }
}
