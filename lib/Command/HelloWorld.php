<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Command;

use OCP\IL10N;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class HelloWorld extends Command
{
  /** @var IL10N */
  private $l;

  public function __construct(
    $appName
    , IL10N $l10n
  ) {
    parent::__construct();
    $this->l = $l10n;
    $this->appName = $appName;
    if (empty($l10n)) {
      throw new \RuntimeException('No IL10N :(');
    }
  }

  protected function configure()
  {
    $this
      ->setName('cafevdb:hello-world')
      ->setDescription('Say "Hello!" to the world!')
      ->addOption(
        'only-hello',
        'o',
        InputOption::VALUE_NONE,
        'outputs hello, not world',
      )
      ;
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    if ($input->getOption('only-hello')) {
      $output->writeln($this->l->t('Hello!'));
    } else {
      $output->writeln($this->l->t('Hello World!'));
    }
    return 0;
  }
}
