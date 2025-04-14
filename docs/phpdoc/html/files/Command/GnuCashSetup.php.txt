<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2024 Claus-Justus Heine
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

use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\AppFramework\IAppContainer;

use Symfony\Component\Console\Terminal;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Question\Question;

use OCA\CAFEVDB\Service\Finance\GnuCashConnectorService;
use OCA\CAFEVDB\Constants;

/** GnuCash connectivity. */
class GnuCashSetup extends Command
{
  use AuthenticatedCommandTrait;

  private const OPTION_DRY_RUN = 'dry';
  private const OPTION_GNC_URI = 'gnc-uri';

  /**
   * @var bool
   *
   * Run in simulation mode.
   */
  private bool $dry;

  /** {@inheritdoc} */
  public function __construct(
    protected string $appName,
    protected IL10N $l,
    protected ILogger $logger,
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
      ->setName('cafevdb:gnucash:setup')
      ->setDescription($this->l->t('Establish the connectivity to a given GnuCash MySQL/MariaDB storage.'))
      ->addOption(
        self::OPTION_DRY_RUN,
        'd',
        InputOption::VALUE_NONE,
        $this->l->t('Just report what would have been done but do not take action.'),
      )
      ->addOption(
        self::OPTION_GNC_URI,
        null,
        InputOption::VALUE_REQUIRED,
        $this->l->t(
          'The name of the GnuCash database to tweak. Simple URI-like hosts are support, e.g. "mysql:://USER:PASSWORD@example.com:143/database".'
          . ' If a user is given but no password the command will prompt for the password.',
        ),
      )
      ;
  }

  /** {@inheritdoc} */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $this->dry = $input->getOption(self::OPTION_DRY_RUN);

    $gncUri = $input->getOption(self::OPTION_GNC_URI);
    if (empty($gncUri)) {
      $output->writeln(
        '<error>'
        . $this->l->t('The Gnucash database URI is mandatory and must not been omitted.')
        . '</error>'
      );
      return 1;
    }
    $gncOptions = parse_url($gncUri);
    if (empty($gncOptions['path'])) {
      $output->writeln(
        '<error>'
        . $this->l->t('The Gnucash database must be specifiewd as path component of the database URI.')
        . '</error>'
      );
      return 1;
    }
    list(,$path,) = explode(Constants::PATH_SEP, $gncOptions['path']);
    $gncOptions['path'] = $path;

    print_r($gncOptions);

    $result = $this->authenticate($input, $output);
    if ($result != 0) {
      return $result;
    }

    if (!empty($gncOptions)) {
      if (isset($gncOptions['user']) && !isset($gncOptions['pass'])) {
        $helper = $this->getHelper('question');
        $question = (new Question($this->l->t('GNC DB Password') . ': ', ''))->setHidden(true);
        $gncOptions['pass'] = $helper->ask($input, $output, $question);
      }
    }

    /** @var GnuCashConnectorService $gncService */
    $gncService = $this->appContainer->get(GnuCashConnectorService::class);

    $gncService->copyGnuCashTables(
      gnuCashDatabase: $gncOptions['path'],
      host: $gncOptions['host'] ?? null,
      user: $gncOptions['user'] ?? null,
      password: $gncOptions['pass'] ?? null,
    );

    return 0;
  }
}
