#!/usr/bin/php
<?php

include_once __DIR__ . '/../console-setup.php';

include_once __DIR__ . '/../../../../vendor/autoload.php';

use Html2Text\Html2Text;
use Soundasleep\Html2Text as Html2TextAlt;

$html = file_get_contents('php://stdin');

// $html2Text = new Html2Text;
//
// $html2Text->setHtml($html);
// $html2Text->p();

// $html2Text = new Html2TextAlt;

// echo $html2Text->convert($html) . PHP_EOL;

// use League\HTMLToMarkdown\HtmlConverter;
// use League\HTMLToMarkdown\Converter\TableConverter;

// $converter = new HtmlConverter();
// $converter->getConfig()->setOption('strip_tags', true);
// $converter->getConfig()->setOption('remove_nodes', 'style');
// $converter->getEnvironment()->addConverter(new TableConverter());

// $markdown = $converter->convert($html);

include 'Html2TextLynx.php';


$text = Html2TextLynx::convert($html);

echo $text . PHP_EOL;
