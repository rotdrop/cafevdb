<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, 2023 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Command;

use Throwable;

use Psr\Log\LoggerInterface as ILogger;

use OCP\IL10N;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\AppFramework\IAppContainer;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Question\Question;

use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\Finance\ReceivablesGeneratorFactory;
use OCA\CAFEVDB\Service\Finance\IRecurringReceivablesGenerator as ReceivablesGenerator;
use OCA\CAFEVDB\Traits\ConfigTrait;

/**
 * Test-command in order to see if the abstract framework is functional.
 *
 * @var string $appName
 * @var IL10N $l
 * @var IUserManager $userManager
 * @var IUserSession $userSession
 * @var IAppContainer $appContainer
 *
 * @todo Remove variable doc when ac-php supports constructor property
 * promotion.
 */
class RecurringReceivables extends Command
{
  use AuthenticatedCommandTrait, ConfigTrait {
    AuthenticatedCommandTrait::logger insteadof ConfigTrait;
  }

  /** {@inheritdoc} */
  public function __construct(
    protected string $appName,
    protected IL10N $l,
    protected IUserManager $userManager,
    protected IUserSession $userSession,
    protected IAppContainer $appContainer,
    protected ILogger $logger,
  ) {
    parent::__construct();
  }

  /** {@inheritdoc} */
  protected function configure()
  {
    $this
      ->setName('cafevdb:projects:participants:receivables')
      ->setDescription('Manage recurring receivables like insurance fees.')
      ->addOption(
        'project',
        'p',
        InputOption::VALUE_REQUIRED,
        $this->l->t('Work on the given project. This option is mandatory.'),
      )
      ->addOption(
        'receivable',
        'r',
        InputOption::VALUE_REQUIRED,
        $this->l->t('Work on the given receivable field. This option is mandatory.'),
      )
      ->addOption(
        'instance',
        'i',
        InputOption::VALUE_REQUIRED,
        $this->l->t(
          'Restrict the operation to the given instance (e.g. for the a specific year). '
          . 'The instance name may be given incomplete (e.g. "2018" instead of "Instrument Insurance 2018").'),
      )
      ->addOption(
        'user',
        'u',
        InputOption::VALUE_REQUIRED,
        $this->l->t('Restrict the operation to the given user-id. The default is to work on the receivables for all users.'),
      )
      ->addOption(
        'list',
        'l',
        InputOption::VALUE_NONE,
        $this->l->t('List the existing receivable instances for the given project, receivable field (and optionally receivable instance and user).'),
      )
      ->addOption(
        'generate',
        'g',
        InputOption::VALUE_NONE,
        $this->l->t('Generate all neccessary receivable instances for the given receivable field.'),
      )
      ->addOption(
        'recompute',
        'c',
        InputOption::VALUE_NONE,
        $this->l->t('Recompute the amounts and supporting documents for the given project, receivable field (and optionally receivable instance and user).'),
      )
      ->addOption(
        'update-strategy',
        's',
        InputOption::VALUE_REQUIRED,
        $this->l->t(
          'Conflict resolution strategy if the newly comuputed value differ from already existing values, one of %s.',
          implode(
            ', ',
            array_map(
              fn(string $strategy) => '"' . $this->l->t($strategy) . '" (' . $strategy . ')',
              ReceivablesGenerator::UPDATE_STRATEGIES
            ),
          )
        )
      )
      ;
  }

  /** {@inheritdoc} */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $projectName = $input->getOption('project');
    $fieldName = $input->getOption('receivable');
    $receivableLabel = $input->getOption('instance');
    $memberUserId = $input->getOption('user');
    $list = $input->getOption('list');
    $generate = $input->getOption('generate');
    $recompute = $input->getOption('recompute');
    $strategyOption = $input->getOption('update-strategy');

    $optionCheck = true;

    if (empty($projectName)) {
      $output->writeln('<error>' . $this->l->t('Please specify the project-name to work on.') . '</error>');
      $optionCheck = false;
    }
    if (empty($fieldName)) {
      $output->writeln('<error>' . $this->l->t('Please specify the name of the receivable field to work on.') . '</error>');
      $optionCheck = false;
    }

    if (empty($strategyOption)) {
      $updateStrategy = ReceivablesGenerator::UPDATE_STRATEGY_EXCEPTION;
    } else {
      $updateStrategy = null;
      foreach (ReceivablesGenerator::UPDATE_STRATEGIES as $strategy) {
        if ($strategy == $updateStrategy || $this->l->t($strategy) == $strategyOption) {
          $updateStrategy = $strategy; // normalize
          break;
        }
      }
      if ($updateStrategy === null) {
        $output->writeln(
          '<error>'
          . $this->l->t(
            'Unknown update strategy "%1$s", must be one of "%2$s".', [
              $strategyOption,
              implode('", "', array_map(fn($s) => $this->l->t($s), ReceivablesGenerator::UPDATE_STRATEGIES)),
            ]
          )
          . '</error>');
        $optionCheck = false;
      }
    }

    if (!$optionCheck) {
      $output->writeln('');
      (new DescriptorHelper)->describe($output, $this);
      return 1;
    }

    $result = $this->authenticate($input, $output);
    if ($result != 0) {
      return $result;
    }

    $this->configService = $this->appContainer->get(ConfigService::class);
    $appLocale = $this->appLocale();

    /** @var Entities\Project $project */
    $project = $this->getDatabaseRepository(Entities\Project::class)->findOneBy([ 'name' => $projectName ]);
    if (empty($project)) {
      if (empty($project)) {
        $output->writeln('<error>' . $this->l->t('Unable to find the project with name "%s".', $projectName) . '</error>');
        return 1;
      }
    }

    if (empty($memberUserId)) {
      $participants = $project->getParticipants();
    } else {
      $participants = $project->getParticipants()->matching(DBUtil::criteriaWhere([ 'musician.userIdSlug' => $memberUserId ]));
      if (count($participants) == 0) {
        $output->writeln('<error>' . $this->l->t('Unable to find the participant with user-id "%s".', $memberUserId) . '</error>');
        return 1;
      }
    }

    /** @var Entities\ProjectParticipantField $field */
    $field = $this->getDatabaseRepository(Entities\ProjectParticipantField::class)->findOneBy([ 'name' => $fieldName ]);
    if (empty($field)) {
      if (empty($field)) {
        $output->writeln('<error>' . $this->l->t('Unable to find the receivable field with name "%s".', $fieldName) . '</error>');
        $fieldNames = $project->getParticipantFields()
          ->filter(
            fn(Entities\ProjectParticipantField $entity) => $entity->getDataType() == FieldType::RECEIVABLES && $entity->getMultiplicity() == FieldMultiplicity::RECURRING
          )
          ->map(fn(Entities\ProjectParticipantField $entity) => $entity->getName())
          ->toArray();
        if (count($fieldNames) == 0) {
          $output->writeln('<error>' . $this->l->t('Project "%s" has no recurring receivables.', $projectName) . '</error>');
        } else {
          $output->writeln('<error>' . $this->l->t('Project "%1$s" has only the following recurring receivables: %2$s.', [
            $projectName, implode(', ', $fieldNames)
          ]) . '</error>');
        }
        return 1;
      }
    }

    $allReceivables = $field->getSelectableOptions();
    if (empty($receivableLabel)) {
      $receivables = $allReceivables;
    } else {
      $receivables = $receivables->filter(
        fn(Entities\ProjectParticipantFieldDataOption $entity) => str_contains($entity->getLabel(), $receivableLabel)
      );
      if (count($receivables) == 0) {
        $output->writeln('<error>' . $this->l->t('Field "%1$s" has no receivable instance with label "%2$s", only the following options are available: %3$s.', [
          $fieldName, $receivableLabel, implode(
            ', ',
            $field->getSelectableOptions()->map(fn(Entities\ProjectParticipantFieldDataOption $entity) => $entity->getLabel())->toArray()
          )
        ]) . '</error>');
      }
    }

    $participants = $participants->toArray();
    usort($participants, fn(Entities\ProjectParticipant $pp1, Entities\ProjectParticipant $pp2) => strcmp($pp1->getPublicName(), $pp2->getPublicName()));

    /** @var OCA\CAFEVDB\Service\Finance\IRecurringReceivablesGenerator $generator */
    $generator = $this->appContainer->get(ReceivablesGeneratorFactory::class)->getGenerator($field);

    $verbosity = $output->getVerbosity();

    if ($generate) {
      $oldReceivables = $allReceivables;
      $this->entityManager->beginTransaction();
      try {
        $allReceivables = $generator->generateReceivables();
        $this->flush();
        $this->entityManager->commit();
      } catch (Throwable $t) {
        $this->logException($t);
        $this->entityManager->rollback();
        throw $t;
      }
      if ($verbosity >= OutputInterface::VERBOSITY_VERBOSE) {
        $output->write('<info>' . $this->l->t('All Receivables:') . '</info>');
      } else {
        $output->write('<info>' . $this->l->t('New Receivables:') . '</info>');
      }
      $newCount = 0;
      /** @var Entities\ProjectParticipantFieldDataOption $receivable */
      foreach ($allReceivables as $key => $receivable) {
        $new = !$oldReceivables->containsKey($key);
        if (!$new || $verbosity < OutputInterface::VERBOSITY_VERBOSE) {
          continue;
        }
        $output->writeln('');
        $output->write('<info>' . $receivable->getLabel() . ': ' . $receivable->getData() . '</info>' . ($new ? '<comment> ' . $this->l->t('new') . '</comment>' : ''));
        $newCount += (int)$new;
      }
      if ($newCount == 0) {
        $output->writeln('<info> ' . $this->l->t('No new receivables.') . '</info>');
      }
    }

    if ($recompute) {
      if (count($participants) > 1) {
        $section0 = $output->section();
        $progress0 = new ProgressBar($section0, count($participants));
        $output->writeln('<info>' . $this->l->t('Updating receivables for %d participants.', count($participants)) . '</info>');
      }
      /** @var ConsoleSectionOutput $section1 */
      $section1 = $output->section();
      if (count($receivables) > 1) {
        $section2 = $output->section();
        $progress2= new ProgressBar($section2, count($receivables));
      }
      /** @var ConsoleSectionOutput $section3 */
      $section3 = $output->section();
      /** @var ConsoleSectionOutput $section4 */
      $section4 = $output->section();

      $statistics = [];
      $this->entityManager->beginTransaction();
      try {
        if ($progress0) {
          $progress0->start();
        }
        /** @var Entities\ProjectParticipant $participant */
        foreach ($participants as $participant) {
          $participantStatistics = [
            'added' => 0,
            'removed' => 0,
            'changed' => 0,
            'skipped' => 0,
            'notices' => [],
            'receivables' => [],
            'musicians' => [],
          ];
          $subTotals = 0.0;
          $relevant = false;
          $section1->overwrite('<info>' . $this->l->t('Updating receivables for %s.', $participant->getPublicName()) . '</info>');
          if ($progress2) {
            $progress2->start();
          }
          /** @var Entities\ProjectParticipantFieldDataOption $receivable */
          foreach ($receivables as $receivable) {
            $section1->overwrite('<info>' . $this->l->t('Updating receivables for %1$s, working on "%2$s".', [
              $participant->getPublicName(),
              $receivable->getLabel(),
            ]) . '</info>');
            $info = $generator->updateParticipant($participant, $receivable, $updateStrategy);
            foreach (['added', 'removed', 'changed', 'skipped', 'notices', 'receivables', 'musicians'] as $key) {
              if (is_array($participantStatistics[$key])) {
                $participantStatistics[$key] = array_merge($participantStatistics[$key], $info[$key] ?? []);
              } else {
                $participantStatistics[$key] += $info[$key] ?? 0;
              }
            }
            $fieldDatum = $participant->getParticipantFieldsDatum($receivable->getKey());
            if (empty($fieldDatum)) {
              $section3->overwrite('<info>' . $this->l->t('Receivable "%1$s" does not apply for %2$s.', [
                $receivable->getLabel(),
                $participant->getPublicName(),
              ]) . '</info>');
            } else {
              $relevant = true;
              $subAmount = (float)$fieldDatum->getOptionValue();
              $subTotals += $subAmount;
              $section3->overwrite('<info>' . $this->l->t('%1$s: receivable "%2$s" amounts to %3$s.', [
                $participant->getPublicName(),
                $receivable->getLabel(),
                $this->moneyValue($subAmount, $appLocale),
              ]) . '</info>');
            }
            if ($progress2) {
              $progress2->advance();
              $progress2->display();
            }
          }
          /** @var Entities\ProjectParticipantFieldDatum $datum */
          foreach ($participant->getParticipantFieldsData() as $datum) {
            if ($datum->getField()->getId() == $field->getId()) {
              $this->persist($datum);
            }
          }
          if ($relevant) {
            $statistics[$participant->getMusician()->getUserIdSlug()] = $participantStatistics;
            $section4->overwrite('<info>' . $this->l->t('%1$s: added %2$d, removed %3$d, changed %4$d and skipped %5$d receivables, total amount %6$s.', [
              $participant->getPublicName(),
              $participantStatistics['added'],
              $participantStatistics['removed'],
              $participantStatistics['changed'],
              $participantStatistics['skipped'],
              $this->moneyValue($subTotals, $appLocale),
            ]) . '</info>');
          }
          if ($progress2) {
            $progress2->finish();
          }
          if ($progress0) {
            $progress0->advance();
            $progress0->display();
          }
        }
        $this->flush();
        $this->entityManager->commit();
        if ($progress0) {
          $progress0->finish();
        }
        $totalStatistics = [];
        foreach ($statistics as $participantStatistics) {
          foreach (['added', 'removed', 'changed', 'skipped', 'notices', 'receivables', 'musicians'] as $key) {
            if (is_array($participantStatistics[$key])) {
              $totalStatistics[$key] = array_merge($totalStatistics[$key] ?? [], $participantStatistics[$key]);
            } else {
              $totalStatistics[$key] = ($totalStatistics[$key] ?? 0) + $participantStatistics[$key];
            }
          }
        }
        $output->writeln('<info>' . $this->l->t('Added %1$d, removed %2$d, changed %3$d and skipped %4$d receivables.', [
          $totalStatistics['added'],
          $totalStatistics['removed'],
          $totalStatistics['changed'],
          $totalStatistics['skipped'],
        ]) . '</info>');
        if (count($totalStatistics['notices']) > 0) {
          $output->writeln('<info>' . $this->l->t('Informational messages:') . '</info>');
          foreach ($totalStatistics['notices'] as $notice) {
            $output->writeln('<info>' . $notice . '</info>');
          }
        }
      } catch (\Throwable $t) {
        $this->logException($t);
        $this->entityManager->rollback();
        throw new Exceptions\EnduserNotificationException($this->l->t('Receivables update has failed.'), 0, $t);
      }
    }
    if ($list) {
      foreach ($receivables as $receivable) {
        $output->writeln('<info>' . '*** ' . $receivable->getLabel() . ' ***' . '</info>');
        $output->writeln('');
        $totals = 0.0;
        /** @var Entities\ProjectParticipant $participant */
        foreach ($participants as $participant) {
          $fieldDatum = $participant->getParticipantFieldsDatum($receivable->getKey());
          if (empty($fieldDatum)) {
            continue;
          }
          $amount = (float)$fieldDatum->getOptionValue();
          $totals += $amount;
          $output->writeln('<info>  ' . $participant->getPublicName() . ': ' . $this->moneyValue($amount, $appLocale) . '</info>');
        }
        $output->writeln('');
        $output->writeln('<info>  ' . $this->l->t('Total amount for "%s": %s.', [
          $receivable->getLabel(),
          $this->moneyValue($totals, $appLocale),
        ]) . '</info>');
        $output->writeln('');
        $output->writeln('');
      }
    }

    return 0;
  }
}
