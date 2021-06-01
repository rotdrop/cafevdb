#!/usr/bin/php
<?php

$text = '<p>Liebe Musiker,</p> <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p> <p>Mit den besten Gr&uuml;&szlig;en,</p> <p>Euer Camerata Vorstand (Claus-Justus, Frodo und Bilbo)</p> <p>P.s.: Sie erhalten diese Email, weil Sie schon einmal mit dem Orchester Camerata Academica Freiburg musiziert haben. Wenn wir Sie aus unserer Datenbank l&ouml;schen sollen, teilen Sie uns das bitte kurz mit, indem Sie entsprechend auf diese Email antworten. Wir entschuldigen uns in diesem Fall f&uuml;r die St&ouml;rung.</p>';

echo md5($text).PHP_EOL;
echo hash('md5', $text).PHP_EOL;
