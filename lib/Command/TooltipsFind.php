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

use \RecursiveDirectoryIterator;
use \RecursiveIteratorIterator;
use \RegexIterator;

use OCP\IL10N;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

use OCA\CAFEVDB\Service\ToolTipsService;

class TooltipsFind extends Command
{
  private const PHP_SOURCES = [
    __DIR__ . '/../../templates/' => '$toolTips',
    __DIR__ . '/../' => 'toolTipsService',
  ];
  private const TOOLTIP_SEARCHING = 0;
  private const TOOLTIP_KEYWORD = 1;
  private const TOOLTIP_BRACKET = 2;
  private const TOOLTIP_KEY = 3;

  /** @var IL10N */
  private $l;

  /** @var ToolTipsService */
  protected $toolTipsService;

  public function __construct(
    $appName
    , IL10N $l10n
    , ToolTipsService $toolTipsService
  ) {
    parent::__construct();
    $this->l = $l10n;
    $this->appName = $appName;
    if (empty($l10n)) {
      throw new \RuntimeException('No IL10N :(');
    }
    $this->toolTipsService = $toolTipsService;
  }

  protected function configure()
  {
    $this
      ->setName('cafevdb:tooltips-find')
      ->setDescription('Find used tooltip- tags in the php-sources.');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $tooltipKeys = [];
    foreach (self::PHP_SOURCES as $dir => $instanceName) {
      $directoryIterator = new RecursiveDirectoryIterator($dir);
      $iteratorIterator = new RecursiveIteratorIterator($directoryIterator);
      $regexIterator = new RegexIterator($iteratorIterator, '/^.+\.php$/i', RegexIterator::GET_MATCH);
      foreach ($regexIterator as $phpFile) {

        $phpFile = realpath(reset($phpFile));

        if (dirname($phpFile) == dirname(realpath(__FILE__))) {
          continue;
        }

        $phpSource = file_get_contents($phpFile);
        $phpTokens = token_get_all($phpSource);

        $state = self::TOOLTIP_SEARCHING;
        $tooltipKey = null;
        foreach ($phpTokens as $token) {
          switch ($state) {
          case self::TOOLTIP_SEARCHING:
            if (is_array($token)
                && $token[1] == $instanceName
                && ($token[0] === T_STRING || $token[0] === T_VARIABLE)) {
              $state = self::TOOLTIP_KEYWORD;
            }
            break;
          case self::TOOLTIP_KEYWORD:
            if (is_string($token) && $token == '[') {
              $state = self::TOOLTIP_BRACKET;
            } else if (is_array($token) && $token[0] === T_WHITESPACE) {
              // skip whitespace
              continue 2;
            } else {
              if (is_array($token)) {
                $output->writeln(
                  'UNEXPECTED '
                  . token_name($token[0])
                  . ': "' . $token[1] . '"'
                  . ' ' . $phpFile
                  . ':'. $token[2]);
              } else {
                if ($token !== '=' && $token !== ',') {
                  throw new \RuntimeException(
                    'UNEXPECTED '
                    . ' ' . $phpFile
                    . ': TOKEN '. $token);
                }
              }
              $state = self::TOOLTIP_SEARCHING;
            }
            break;
          case self::TOOLTIP_BRACKET:
            if (is_array($token) && $token[0] == T_CONSTANT_ENCAPSED_STRING) {
              $state = self::TOOLTIP_KEY;
              $tooltipKey = $token[1];
            } else {
              if (is_array($token)) {
                if ($token[0] !== T_VARIABLE) {
                  throw new \RuntimeException(
                    'UNEXPECTED '
                    . token_name($token[0])
                    . ': "' . $token[1] . '"'
                    . ' ' . $phpFile
                    . ':'. $token[2]);
                }
              } else {
                throw new \RuntimeException(
                  'UNEXPECTED '
                  . ' ' . $phpFile
                  . ': TOKEN '. $token);
              }
              $state = self::TOOLTIP_SEARCHING;
            }
            break;
          case self::TOOLTIP_KEY:
            if (is_string($token) && $token == ']') {
              // $output->writeln('TOOLTIPKEY: '.$tooltipKey);
              $tooltipKeys[] = trim($tooltipKey, "'");
            }
            $tooltipKey = null;
            $state = self::TOOLTIP_SEARCHING;
            break;
          }
        }
      }
    }
    sort($tooltipKeys);
    $tooltipKeys = array_values(array_unique($tooltipKeys));
    $tooltipPrincipalKeys = array_values(
      array_unique(
        array_map(function($value) {
          return strtok($value, ':');
        }, $tooltipKeys)));
    //$output->writeln(print_r($tooltipPrincipalKeys, true));

    $unknownTooltipKeys = [];
    foreach ($tooltipKeys as $tooltipKey) {
      // $output->writeln($tooltipKey);
      if (empty($this->toolTipsService[$tooltipKey])) {
        $unknownTooltipKeys[] = $tooltipKey;
      }
    }

    $tooltipServiceKeys = array_keys($this->toolTipsService->toolTips());
    $unusedTooltipKeys = array_values(array_diff($tooltipServiceKeys, $tooltipPrincipalKeys));

    $output->writeln('*** Unknown Tooltip-Keys ***');
    foreach ($unknownTooltipKeys as $tooltipKey) {
      $output->writeln($tooltipKey);
    }
    $output->writeln('');

    $output->writeln('*** First 100 unused Tooltip-Keys ***');
    for ($i = 0; $i < min(100, count($unusedTooltipKeys)); ++$i) {
      $output->writeln($unusedTooltipKeys[$i]);
    }
    $output->writeln('');


    $output->writeln('Found '.count($tooltipKeys).' tool-tip keys.');
    $output->writeln('Found '.count($unknownTooltipKeys).' unknown tool-tip keys.');
    $output->writeln('Found '.count($unusedTooltipKeys).' unused tool-tip keys.');

    return 0;
  }
}
