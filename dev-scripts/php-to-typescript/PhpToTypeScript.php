<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023, 2024, 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\DevScripts\PhpToTypeScript;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Spatie\TypeScriptTransformer\TypeScriptTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfig;
use Spatie\TypeScriptTransformer\Structures\TransformedType;

/**
 * Runner for the PHP to typescript conversion.
 */
class PhpToTypeScript extends Command
{
  private const OPTION_OUTPUT_PREFIX = 'output-prefix';
  private const OPTION_SOURCE_PREFIX = 'source-prefix';
  private const OPTION_CONSTANTS = 'constants';
  private const OPTION_CONSTANTS_AS_CONSTANTS = 'constants';
  private const OPTION_CONSTANTS_AS_PROPERTIES = 'properties';
  private const OPTION_NS_PREFIX = 'ns-prefix';
  private const OPTION_AS_MODULES = 'as-modules';
  private const OPTION_SOURCES = 'sources';
  private const OPTION_OUTPUTS = 'outputs';
  private const OPTION_HELP = 'help';
  private const OPTION_VERBOSE = 'verbose';
  private const OPTION_QUIET = 'quiet';
  private const OUTPUT_SUFFIX = '.d.ts';
  private const VERBOSITY_MAX = 3;
  private const PHP_PREFIX = 'php-';
  private const LINE_SEPARATOR = "\r\n";
  private const NS_DECLARATION = 'declare namespace';

  /** {@inheritdoc} */
  public function __construct(
    protected array $configInfo,
    protected array $excludes,
  ) {
    parent::__construct();
  }

  /** {@inheritdoc} */
  protected function configure()
  {
    parent::configure();
    $this
      ->setName('PhpToTypeScript')
      ->setDescription('Generate TypeScript types from selected PHP sources.')
      ->addOption(
        self::OPTION_OUTPUT_PREFIX,
        'p',
        InputOption::VALUE_REQUIRED,
        'The path to the output directory. Required.',
      )
      ->addOption(
        self::OPTION_SOURCE_PREFIX,
        's',
        InputOption::VALUE_REQUIRED,
        'The path to the source directory. Required.',
      )
      ->addOption(
        self::OPTION_CONSTANTS,
        null,
        InputOption::VALUE_REQUIRED,
        'Emit constants as'
        . ' either literal type typed constants (--constants=' . self::OPTION_CONSTANTS_AS_CONSTANTS . ')'
        . ' or literal type typed properties (--constants=' . self::OPTION_CONSTANTS_AS_PROPERTIES . ').',
      )
      ->addOption(
        self::OPTION_NS_PREFIX,
        null,
        InputOption::VALUE_REQUIRED,
        'Specify a namespace prefix to remove, either in PHP notation or in TS notation.',
      )
      ->addOption(
        self::OPTION_AS_MODULES,
        null,
        InputOption::VALUE_NONE,
        'Convert the single-file namespace declaration to a multi-file module structure.',
      )
      ->addOption(
        self::OPTION_OUTPUTS,
        null,
        InputOption::VALUE_NONE,
        'Output the list of outputs.',
      )
      ->addOption(
        self::OPTION_SOURCES,
        null,
        InputOption::VALUE_NONE,
        'Output the list of sources.',
      )
      ->addOption(
        self::OPTION_HELP,
        'h',
        InputOption::VALUE_NONE,
        'Display help',
      )
      ->addOption(
        self::OPTION_HELP,
        'h',
        InputOption::VALUE_NONE,
        'Display help',
      )
      ->addOption(
        self::OPTION_VERBOSE,
        'v|vv...',
        InputOption::VALUE_OPTIONAL|InputOption::VALUE_IS_ARRAY,
        'Set or increase verbosity level.',
        [],
      )
      ->addOption(
        self::OPTION_QUIET,
        'q',
        InputOption::VALUE_NONE,
        'Only emit output on errors.',
      )
      ;
  }

  /** {@inheritdoc} */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    if ($input->getOption(self::OPTION_HELP)) {
      $output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
      $help = new HelpCommand();
      $help->setCommand($this);
      $help->run($input, $output);
      return Command::SUCCESS;
    }
    $verbose = $input->getOption(self::OPTION_VERBOSE);
    $verbosity = 0;
    if ($verbose !== []) {
      $verbosity = array_reduce(
        $verbose,
        function(int $carry, null|int|string $level) {
          if (is_string($level)) {
            $level = strlen($level) + 1;
          }
          return $carry + ($level ?? 1);
        },
        0,
      );
      $verbosity = max($verbosity, self::VERBOSITY_MAX);
    }
    if ($input->getOption(self::OPTION_QUIET)) {
      $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
    } else {
      $output->setVerbosity(OutputInterface::VERBOSITY_NORMAL << $verbosity);
    }

    if ($input->getOption(self::OPTION_SOURCES)) {
      $sources = [];
      foreach ($this->configInfo as $outputName => $outputInfo) {
        $sources = array_merge($sources, $outputInfo['paths']);
      }
      $output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
      $output->writeln(implode(' ', $sources));
      return COMMAND::SUCCESS;
    }
    if ($input->getOption(self::OPTION_OUTPUTS)) {
      $outputs = [];
      foreach ($this->configInfo as $outputName => $outputInfo) {
        $outputs[] = self::PHP_PREFIX . $outputName . self::OUTPUT_SUFFIX;
      }
      $output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
      $output->writeln(implode(' ', $outputs));
      return COMMAND::SUCCESS;
    }

    $error = false;
    $outputPrefix = $input->getOption(self::OPTION_OUTPUT_PREFIX);
    if (empty($outputPrefix)) {
      $output->writeln('<error>' . 'The "--' . self::OPTION_OUTPUT_PREFIX . '" option is mandatory.' . '</error>', OutputInterface::VERBOSITY_QUIET);
      $error = true;
    }
    $sourcePrefix = $input->getOption(self::OPTION_SOURCE_PREFIX);
    if (empty($sourcePrefix)) {
      $output->writeln('<error>' . 'The "--' . self::OPTION_SOURCE_PREFIX . '" option is mandatory.' . '</error>', OutputInterface::VERBOSITY_QUIET);
      $error = true;
    }
    if ($error) {
      $output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
      $help = new HelpCommand();
      $help->setCommand($this);
      $output->writeln('');
      $help->run($input, $output);
      return Command::INVALID;
    }

    if (!str_ends_with($outputPrefix, '/')) {
      $outputPrefix .= '/';
    }
    if (!str_ends_with($sourcePrefix, '/')) {
      $sourcePrefix .= '/';
    }

    $tsNameSpacePrefix = $input->getOption(self::OPTION_NS_PREFIX);
    if (!empty($tsNameSpacePrefix)) {
      // convert from PHP to TypeScript notation.
      $tsNameSpacePrefix = str_replace('\\', '.', $tsNameSpacePrefix);
      if (!str_ends_with($tsNameSpacePrefix, '.')) {
        $tsNameSpacePrefix .= '.';
      }
    }

    foreach ($this->configInfo as $outputName => $outputInfo) {
      $outputFile = $outputPrefix . self::PHP_PREFIX . $outputName . self::OUTPUT_SUFFIX;

      $config = ClassConstantsTransformerConfig::create()
        // path where your PHP classes are
        ->autoDiscoverTypes(...array_map(fn(string $path) => $sourcePrefix . $path, $outputInfo['paths']))
        ->autoDiscoverExclude(...array_map(fn(string $path) => $sourcePrefix . $path, $this->excludes))
        // list of transformers
        ->transformers($outputInfo['transformers'])
        // file where TypeScript type definitions will be written
        ->outputFile($outputFile);

      switch ($input->getOption(self::OPTION_CONSTANTS)) {
        case self::OPTION_CONSTANTS_AS_CONSTANTS:
          $config->constantsAsConstants(true);
          break;
        case self::OPTION_CONSTANTS_AS_PROPERTIES:
          $config->constantsAsProperties(true);
          break;
        default:
          $config->constantsAsConstants(true);
          break;
      }

      $types = TypeScriptTransformer::create($config)->transform();

      $tsData = null;
      if (!empty($tsNameSpacePrefix)) {
        $tsData = str_replace(
          'declare namespace ' . $tsNameSpacePrefix,
          'declare namespace ',
          file_get_contents($outputFile),
        );
        file_put_contents($outputFile, $tsData);
      }

      if ($input->getOption(self::OPTION_AS_MODULES)) {
        $generator = basename(__FILE__);
        $modulesDir = $outputPrefix . '/php-modules/';
        mkdir($modulesDir);
        if ($tsData === null) {
          $tsData = file_get_contents($outputFile);
        }
        $separator = "\r\n";
        $line = strtok($tsData, self::LINE_SEPARATOR);
        $currentModule = null;
        $currentData = null;
        while ($line !== false) {
          if (str_starts_with($line, self::NS_DECLARATION)) {
            $nameSpaces = explode('.', trim(substr($line, strlen(self::NS_DECLARATION)), ' {'));
            $modulesPath = $modulesDir;
            while (!empty($nameSpaces)) {
              $currentNs = array_shift($nameSpaces);
              if (!empty($nameSpaces)) {
                // emit trampoline modules
                $nextNs = reset($nameSpaces);
                $currentModule = $modulesPath . $currentNs . '.ts';
                $newData = "export * as {$nextNs} from './{$currentNs}/${nextNs}.ts';";
                $currentData = file_get_contents($currentModule);
                if (!empty($currentData) && !str_contains($currentData, $newData)) {
                  $currentData .= $newData . PHP_EOL;
                } elseif (empty($currentData)) {
                  $currentData = <<<EOF
// Automatically generated by {$generator}, do not edit!


EOF;
                  $currentData .= $newData . PHP_EOL;
                }
                file_put_contents($currentModule, $currentData);
                $modulesPath .= $currentNs . '/';
                mkdir($modulesPath);
              } else {
                // emit the leaf module
                $currentModule = $modulesPath . $currentNs . '.ts';
                $currentData = <<<EOF
// Automatically generated by {$generator}, do not edit!


EOF;
              }
            }
          } elseif ($line == '}') {
            if ($currentData && $currentModule) {
              file_put_contents($currentModule, $currentData);
              $currentData = $currentModule = null;
            }
          } else {
            $currentData .= substr($line, 2) . PHP_EOL;
          }
          $line = strtok(self::LINE_SEPARATOR);
        }
      }

      if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
        $output->writeln();
        $output->writeln('<info> *** ' . $outputName . ' *** </info>');
        /** @var TransformedType $type */
        foreach ($types as $class => $type) {
          $output->writeln('<info>' . $class . ' -> ' . $type->getTypeScriptName() . '</info>');
        }
        $output->writeln('---------------------------------------------');
        $output->writeln();
      }
    }

    return Command::SUCCESS;
  }
}
