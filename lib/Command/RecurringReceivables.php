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

use OCP\IL10N;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\AppFramework\IAppContainer;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;

/** Test-command in order to see if the abstract framework is functional. */
class RecurringReceivables extends Command
{
  use AuthenticatedCommandTrait;

  /** {@inheritdoc} */
  public function __construct(
    protected string $appName,
    protected IL10N $l,
    protected IUserManager $userManager,
    protected IUserSession $userSession,
    protected IAppContainer $appContainer,
  ) {
    parent::__construct();
  }

  /** {@inheritdoc} */
  protected function configure()
  {
    $this
      ->setName('cafevdb:projects:participants:receivables')
      ->setDescription('Manage recurring receivables like insurancs fees.')
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
        $this->l->t('Restrict the operation to the given instance (e.g. for the a specific year). The instance name may be given incomplete (e.g. "2018" instead of "Instrument Insurance 2018").'),
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

    $optionCheck = true;

    if (empty($projectName)) {
      $output->writeln('<error>' . $this->l->t('Please specify the project-name to work on.') . '</error>');
      $optionCheck = false;
    }
    if (empty($fieldName)) {
      $output->writeln('<error>' . $this->l->t('Please specify the name of the receivable field to work on.') . '</error>');
      $optionCheck = false;
    }

    if (!$optionCheck) {
      return 1;
    }

    $result = $this->authenticate($input, $output);
    if ($result != 0) {
      return $result;
    }

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
          $output->writeln('<error>' . $this->l->t('Project "%1$s" has only the following recurring receivables: %2$s', [
            $projectName, implode(', ', $fieldNames)
          ]) . '</error>');
        }
        return 1;
      }
    }

    $receivables = $field->getSelectableOptions();
    if (!empty($receivableLabel)) {
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

    if ($generate) {
    }
    if ($recompute) {
    }
    if ($list) {
    }

    return 0;
  }
}
