<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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

use \RuntimeException;

use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\AppFramework\IAppContainer;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\DescriptorHelper;

use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Exceptions;

use OCA\CAFEVDB\Maintenance\SanitizerRegistration;

/** Run various databse sanitizers on request. */
class DatabaseSanitizers extends Command
{
  use AuthenticatedCommandTrait;

  /** {@inheritdoc} */
  public function __construct(
    string $appName,
    IL10N $l10n,
    ILogger $logger,
    IUserManager $userManager,
    IUserSession $userSession,
    IAppContainer $appContainer,
  ) {
    parent::__construct();
    $this->appName = $appName;
    $this->logger = $logger;
    $this->l = $l10n;
    $this->userManager = $userManager;
    $this->userSession = $userSession;
    $this->appContainer = $appContainer;
  }

  /** {@inheritdoc} */
  protected function configure()
  {
    $this
      ->setName('cafevdb:database:sanitize')
      ->setDescription('Sanitize Database Entities')
      ->addOption(
        'list',
        'l',
        InputOption::VALUE_NONE,
        'List available sanitizers.',
      )
      ->addOption(
        'run',
        'r',
        InputOption::VALUE_REQUIRED,
        'Run all or the given sanitizer for all entities of the given class-name.'
        . ' The argument is in the format CLASS:SANITIZER.'
        . ' Use "--list" to get a list of available sanitizers. Only the "base-name" of the class needs to be given.',
      )
      ->addOption(
        'test',
        't',
        InputOption::VALUE_REQUIRED,
        'Validate all entities of the given class-name with all or the given sanitizer.'
        . ' The argument is in the format CLASS:SANITIZER.'
        . ' Use "--list" to get a list of available sanitizers. Only the "base-name" of the class needs to be given.',
      )
      ;
  }

  /** {@inheritdoc} */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    if ($input->getOption('list')) {
      $entities = SanitizerRegistration::getClasses();
      foreach ($entities as $entityClass) {
        $sanitizers = SanitizerRegistration::getSanitizers($entityClass);
        if (!empty($sanitizers)) {
          $output->writeln($entityClass);
          foreach ($sanitizers as $name => $sanitizer) {
            $output->writeln('  ' . $name . ': ' . $sanitizer::getDescription($this->l));
          }
        }
      }
    } elseif ($input->getOption('run') || $input->getOption('test')) {
      if ($input->getOption('run')) {
        $option = 'run';
        $method = 'sanitizePersist';
      } else {
        $option = 'test';
        $method = 'validate';
      }
      $which = $input->getOption($option);
      if (strpos($which, ':') !== false) {
        list($entityClass, $sanitizer) = explode(':', $which);
      } else {
        $entityClass = $which;
        $sanitizer = null;
      }
      if (strpos($entityClass, '\\') === false) {
        $classPath = explode('\\', Entities\Musician::class);
        array_pop($classPath);
        $classPath[] = $entityClass;
        $entityClass = implode('\\', $classPath);
      }
      $sanitizers = SanitizerRegistration::getSanitizers($entityClass);
      if (empty($sanitizers)) {
        throw new RuntimeException($this->l->t('There are no sanitizers available for the entity "%s".', array_pop(explode('\\', $entityClass))));
      }
      if (!empty($sanitizer)) {
        if (empty($sanitizers[$sanitizer])) {
          throw new RuntimeException($this->l->t(
            'There is no such sanitizer for the entity "%1$s": "%2$s".', [
              array_pop(explode('\\', $entityClass)),
              $sanitizer,
            ]
          ));
        }
        $sanitizers = [ $sanitizer => $sanitizers[$sanitizer] ];
      }
      $result = $this->authenticate($input, $output);
      if ($result != 0) {
        return $result;
      }

      if (count($sanitizers) > 1) {
        $section0 = $output->section();
        $progress0 = new ProgressBar($section0);
        $progress2 = new ProgressBar($section2);
        $section2 = $output->section();
      }
      $section1 = $output->section();

      $progress1 = new ProgressBar($section1);

      $repository = $this->getDatabaseRepository($entityClass);
      $entities = $repository->findAll();

      if (count($sanitizers) > 1) {
        $progress0->start(count($sanitizers));
        $progress2->start(count($sanitizers) * count($entities));
      }

      $failures = [];
      foreach ($sanitizers as $sanitizerName => $sanitizerClass) {
        $failures[$sanitizerName] = 0;
        $sanitizer = new $sanitizerClass(
          $this->entityManager,
          $this->logger,
        );
        if (empty($sanitizer)) {
          throw new RuntimeException($this->l->t('Unable to construct the sanitizer "%s".', $sanitizerName));
        }
        $output->writeln('Running sanitizer "' . $sanitizerName . '":', OutputInterface::VERBOSITY_VERBOSE);
        $output->writeln('  ' . $sanitizerClass::getDescription($this->l), OutputInterface::VERBOSITY_VERY_VERBOSE);
        $this->entityManager->beginTransaction();
        $progress1->start(count($entities));
        try {
          foreach ($entities as $entity) {
            $sanitizer->setEntity($entity);
            try {
              $failures[$sanitizerName] += (int)($sanitizer->$method() === false);
            } catch (Exceptions\SanitizerNotNeededException $e) {
              // ignore
            } catch (Exceptions\SanitizerNotImplementedException $e) {
              // ignore
            }
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
              $validationMessages = $sanitizer->getValidationMessages();
              foreach ($validationMessages as $level => $messages) {
                foreach ($messages as $message) {
                  $output->writeln('  ' . $message, $level);
                }
              }
            }
            $progress1->advance();
            if (count($sanitizers) > 1) {
              $progress2->advance();
            }
          }
          $this->flush();
          $this->entityManager->commit();
        } catch (\Throwable $t) {
          $this->entityManager->rollback();
          throw $t;
        }
        $progress1->finish();
        if (count($sanitizers) > 1) {
          $progress0->advance();
        }
      }
      if (count($sanitizers) > 1) {
        $progress2->finish();
        $progress0->finish();
      }
      $totalFailures = 0;
      foreach ($failures as $sanitizerName => $failureCount) {
        $totalFailures += $failureCount;
        if ($failureCount > 0) {
          $output->writeln(
            $this->l->t(
              'Sanitizer "%s" reported %d failures.',
              [ $sanitizerName, $failureCount, ],
            ));
        } else {
          $output->writeln($this->l->t('Sanitizer "%s" succeeded with no failurs.', $sanitizerName));
        }
      }
      return $totalFailures > 0 ? 1 : 0;
    } else {
      (new DescriptorHelper)->describe($output, $this);
    }

    return 0;
  }
}
