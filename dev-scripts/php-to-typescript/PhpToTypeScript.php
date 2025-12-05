<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

use DateTime;
use DateTimeImmutable;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

use Spatie\TypeScriptTransformer\Collectors\EnumCollector;
use Spatie\TypeScriptTransformer\Structures\TransformedType;
use Spatie\TypeScriptTransformer\TypeScriptTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfig;
use Spatie\TypeScriptTransformer\Types\TypeScriptType;

use OCA\CAFEVDB\Wrapped\Carbon;
use OCA\CAFEVDB\Wrapped\Ramsey\Uuid\UuidInterface;

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
  private const LINE_BUFFER_SIZE = 4096;
  private const NS_DECLARATION = 'declare namespace';
  private const TYPE_DECLARATION = 'export type';
  private const ROOT_NS = 'ROOT';
  private const ROOT_MODULE = self::ROOT_NS . '.ts';
  private const TS_MODULES_DIR = 'php-modules';

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

      $excludes = array_merge($this->excludes, $outputInfo['excludes'] ?? []);

      $config = ClassConstantsTransformerConfig::create()
        // path where your PHP classes are
        ->autoDiscoverTypes(...array_map(fn(string $path) => $sourcePrefix . $path, $outputInfo['paths']))
        ->autoDiscoverExcludePaths(...array_map(fn(string $path) => $sourcePrefix . $path, $excludes))
        ->autoDiscoverExcludeRegExp('/.*~$|\\/\\.#.+/')
        ->nullToOptional(true)
        // ->transformToNativeEnums(true)
        // list of transformers
        ->transformers($outputInfo['transformers'])
        ->collectors([
          // transform all abstract DTOs
          DTOCollector::class,
          // transform all native enums
          EnumCollector::class,
          // transform all MyClabs enums used in the DB entities
          DatabaseEnumCollector::class,
          // transfrom all database entities
          DatabaseEntityCollector::class,
        ])
        // try inject default TypeScriptTransformer
        ->defaultTypeReplacements([
          // Carbon actually just by default emits a simple strings
          // Carbon\CarbonImmutable::class => new TypeScriptType('{ date: string, timezone_type: number, timezone: string }'),
          // Carbon\Carbon::class => new TypeScriptType('{ date: string, timezone_type: number, timezone: string }'),
          Carbon\CarbonImmutable::class => new TypeScriptType('string'),
          Carbon\Carbon::class => new TypeScriptType('string'),
          DateTime::class => new TypeScriptType('{ date: string, timezone_type: number, timezone: string }'),
          DateTimeImmutable::class => new TypeScriptType('{ date: string, timezone_type: number, timezone: string }'),
          UuidInterface::class => new TypeScriptType('string'),
        ])
        // try inject default TypeScriptTransformer
        ->defaultInlineTypeReplacements([
          // 'mixed' => 'unknown',
          // 'array' => new TypeScriptType('Record<string|number, unknown>'),
          // Carbon\CarbonImmutable::class => new TypeScriptType('{ date: string, timezone_type: number, timezone: string }'),
          // Carbon\Carbon::class => new TypeScriptType('{ date: string, timezone_type: number, timezone: string }'),
          // UuidInterface::class => new TypeScriptType('string'),
        ])
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

      if (!empty($tsNameSpacePrefix)) {
        $output->writeln('<info>' . 'Stripping namespace ' . $tsNameSpacePrefix . '</>');
        $this->fixupTypeScriptTransformer($tsNameSpacePrefix, $outputFile, $output);
      }

      if ($input->getOption(self::OPTION_AS_MODULES)) {
        $metadataGenerator = new GenerateEntityMetadata(
          phpNameSpacePrefix: $input->getOption(self::OPTION_NS_PREFIX),
          outputPrefix: $outputPrefix . self::TS_MODULES_DIR,
          output: $output,
        );
        $metadataGenerator->generateSparseMetadata();
        $entityMapNameSpace = $metadataGenerator->exportEntityMap();
        $tsData = file_get_contents($outputFile);
        $tsData = $entityMapNameSpace . "\n" . $tsData;
        file_put_contents($outputFile, $tsData);

        $this->generateTypeScriptModules($outputPrefix, $outputFile, $output);

        $metadataGenerator->dumpTypeScriptData();
      }
    }


    if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
      $output->writeln('');
      $output->writeln('<info> *** ' . $outputName . ' *** </info>');
      /** @var TransformedType $type */
      foreach ($types as $class => $type) {
        $output->writeln('<info>' . $class . ' -> ' . $type->getTypeScriptName() . '</info>');
      }
      $output->writeln('---------------------------------------------');
      $output->writeln('');
    }

    return Command::SUCCESS;
  }

  /**
   * @param string $tsNameSpacePrefix
   *
   * @param string $outputFile
   *
   * @return void
   */
  private function fixupTypeScriptTransformer(
    string $tsNameSpacePrefix,
    string $outputFile,
    ConsoleOutputInterface $output,
  ): void {
    // strip the top-level namespace as requested and record "root" data types.
    $tsData = null;
    $tsData = str_replace(
      [
        self::NS_DECLARATION . ' ' . $tsNameSpacePrefix,
        ': ' . $tsNameSpacePrefix,
        '<' . $tsNameSpacePrefix,
      ],
      [
        self::NS_DECLARATION . ' ',
        ': ',
        '<',
      ],
      file_get_contents($outputFile),
    );
    // fixup [key: EnumType]
    //
    // TS error 1337 "... consider using a mapped type instead"
    //
    // This is difficult to handle inside Spatie\TypeScriptTransformer ... we
    // exploit the fact that our Enum-types have an "Enum" in their name. This
    // is kludgy, but should work.
    //
    // e.g.
    // insuranceRates: { [key: Types.EnumGeographicalScope]: InsuranceRate };
    $tsFile = fopen('php://temp', 'r+');
    fputs($tsFile, $tsData);
    rewind($tsFile);
    $line = fgets($tsFile, self::LINE_BUFFER_SIZE);
    $tsData = '';
    while ($line !== false) {
      $line = preg_replace('/\\[([[:alnum:]]+):\s*([^]]*Enum[^]]*)\\]/', '[$1 in $2]', $line);
      $tsData .= $line . "\n";
      $line = fgets($tsFile, self::LINE_BUFFER_SIZE);
    }
    fclose($tsFile);
    file_put_contents($outputFile, $tsData);
  }

  /**
   * @param string $outputPrefix
   *
   * @param string $outputFile
   *
   * @param OutputInterface|ConsoleSectionOutput $output
   *
   * @return void
   */
  private function generateTypeScriptModules(
    string $outputPrefix,
    string $outputFile,
    ConsoleOutputInterface $output,
  ): void {
    $headerSection = $output->section();
    $textSection = $output->section();
    $textSection->setMaxHeight(5);
    $progressSection = $output->section();
    $generator = basename(__FILE__);
    $modulesDir = $outputPrefix . '/' . self::TS_MODULES_DIR . '/';
    mkdir($modulesDir);
    $tsData = file_get_contents($outputFile);
    $topLevelTypes = [];
    $currentModule = null;
    $currentFullNS = null;
    $allNameSpaces = [];
    $headerData = [];
    $currentData = null;
    $templateString = false;
    $tsFile = fopen('php://temp', 'r+');
    fputs($tsFile, $tsData);
    // First scan: collect all namespaces
    $numberOfLines = substr_count($tsData, PHP_EOL);
    $headerSection->writeln('<info>' . 'Scanning for namespaces ...' . '</>');
    $progressBar = new ProgressBar($progressSection);
    $progressBar->start($numberOfLines);
    rewind($tsFile);
    $line = fgets($tsFile, self::LINE_BUFFER_SIZE);
    while ($line !== false) {
      $progressBar->advance();
      $line = rtrim($line, PHP_EOL);
      $backticksCount = substr_count($line, '`');
      if (!$templateString && $backticksCount == 0) {
        if (str_starts_with($line, self::NS_DECLARATION)) {
          $nameSpaces = explode('.', trim(substr($line, strlen(self::NS_DECLARATION)), ' {'));
          $currentFullNS = implode('.', $nameSpaces);
          $textSection->writeln('Current FQ NameSpace ' . $currentFullNS, options: OutputInterface::VERBOSITY_NORMAL);
          $allNameSpaces[] = $currentFullNS;
          $allNameSpaces = array_values(array_unique($allNameSpaces));
        } elseif (str_starts_with($line, self::TYPE_DECLARATION)) {
          [,, $type] = explode(' ', $line);
          [, $typeDefinition] = explode('=', $line);
          $topLevelTypes[$type] = $typeDefinition;
        }
      } elseif ($templateString) {
        $templateString = $backticksCount % 2 == 0;
      } else {
        $templateString = $backticksCount % 2 == 1;
      }
      $line = fgets($tsFile, self::LINE_BUFFER_SIZE);
    }
    if (!empty($topLevelTypes)) {
      $allNameSpaces[] = self::ROOT_NS;
    }
    // Second run: emit typedefs, replace namespaces as appropriate
    $templateString = false;
    $currentFullNS = null;

    $headerSection->writeln('<info>' . 'Adjusting namespace references ...' . '</>');
    $progressBar->start($numberOfLines);
    rewind($tsFile);
    $line = fgets($tsFile, self::LINE_BUFFER_SIZE);
    while ($line !== false) {
      $progressBar->advance();
      $line = rtrim($line, PHP_EOL);
      $backticksCount = substr_count($line, '`');
      if (!$templateString && $backticksCount == 0) {
        if (str_starts_with($line, self::NS_DECLARATION)) {
          $nameSpaces = explode('.', trim(substr($line, strlen(self::NS_DECLARATION)), ' {'));
          $currentFullNS = implode('.', $nameSpaces);
          $modulesPath = $modulesDir;
          while (!empty($nameSpaces)) {
            $currentNs = array_shift($nameSpaces);
            if (!empty($nameSpaces)) {
              // emit trampoline modules
              $nextNs = reset($nameSpaces);
              $currentModule = $modulesPath . $currentNs . '.ts';
              $newData = "export * as {$nextNs} from './{$currentNs}/{$nextNs}.ts';";
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
              // emit the current's namespace module
              $currentModule = $modulesPath . $currentNs . '.ts';
              $currentData = '';
              $headerData[] = <<<EOF
// Automatically generated by {$generator}, do not edit!


EOF;
            }
          }
        } elseif ($currentFullNS && $line == '}') {
          if ($currentData && $currentModule) {
            $headerData = array_values(array_unique($headerData));
            sort($headerData);
            $currentData = implode(PHP_EOL, $headerData) . PHP_EOL . PHP_EOL .  $currentData;
            file_put_contents($currentModule, $currentData);
            $currentData = $currentModule = null;
          }
          $currentFullNS = null;
          $headerData = [];
        } else {
          [,$typeSpec] = explode(':', $line);
          foreach ($topLevelTypes as $type => $definition) {
            if (preg_match('/[[:^alnum:]]' . $type . '[[:^alnum:]]/', $typeSpec)) {
              $line = str_replace($type, self::ROOT_NS . '.' . $type, $line);
            }
          }
          $line = str_replace($currentFullNS . '.', '', $line);
          [,$typeSpec] = explode(':', $line, 2);
          foreach ($allNameSpaces as $existingNameSpace) {
            // if (preg_match('/[[:^alnum:]]' . preg_quote($existingNameSpace . '.') . '/', $typeSpec)) {
            if (preg_match('/(([^:]+):|export type)(.*)([[:^alnum:]])(' . preg_quote($existingNameSpace . '.') . ')/', $line)) {
              $selfNS = explode('.', $currentFullNS);
              $refNS = explode('.', $existingNameSpace);
              $textSection->writeln('CROSSREF ' . $currentFullNS . ' ' . $existingNameSpace, options: OutputInterface::VERBOSITY_NORMAL);
              $prefix = [];
              // Database.Doctrine.ORM.EntityMetadata
              // Database.Doctrine.ORM.Util
              // Database.Doctrine.ORM.EntityMetadata.EntityMap
              //
              //  Wrapped.Carbon.CarbonImmutable
              //  Database.Doctrine.ORM.Entities
              while (!empty($selfNS) && !empty($refNS) && reset($selfNS) == reset($refNS)) {
                array_shift($selfNS);
                $importNameSpace = array_shift($refNS);
                $prefix[] = $importNameSpace;
              }
              // do {
              //   array_shift($selfNS);
              //   $importNameSpace = array_shift($refNS);
              //   $prefix[] = $importNameSpace;
              // } while (!empty($selfNS) && !empty($refNS) && reset($selfNS) == reset($refNS));
              // EntityMetadata
              // Util
              // prefix = [ Database, Doctrine, ORM ]
              //
              //
              if (!empty($selfNS) && !empty($refNS)) {
                // can move one level further down.
                array_shift($selfNS);
                $importNameSpace = array_shift($refNS);
              } else {
                array_pop($prefix);
              }
              if (empty($prefix)) {
                $erasePrefix = '';
              } else {
                $erasePrefix = implode('.', $prefix) . '.';
              }
              $up = str_repeat('../', count($selfNS));
              $importPath = "./{$up}{$importNameSpace}";
              while (!empty($refNS)) {
                $erasePrefix .= $importNameSpace . '.';
                $importNameSpace = array_shift($refNS);
                $importPath .= '/' . $importNameSpace;
              }
              $line = str_replace($erasePrefix . $importNameSpace . '.', $importNameSpace . '.', $line);
              $headerData[] = "import type * as {$importNameSpace} from '{$importPath}.ts';";
            }
          }
          $currentData .= $line . PHP_EOL;
        }
      } elseif ($templateString) {
        // just write the line as is
        $currentData .= $line . PHP_EOL;
        $templateString = $backticksCount % 2 == 0;
      } else {
        $currentData .= substr($line, 2) . PHP_EOL;
        $templateString = $backticksCount % 2 == 1;
      }
      $line = fgets($tsFile, self::LINE_BUFFER_SIZE);
    }
    fclose($tsFile);
    if (!empty($currentData)) {
      // Top-level types. We assume these come last, if this changes
      // _this_ code thas to be adjusted.
      file_put_contents($modulesDir . self::ROOT_MODULE, $currentData);
    }
  }
}
