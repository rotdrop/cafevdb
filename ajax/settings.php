<?php
/**
 * Copyright (c) 2011, Frank Karlitschek <karlitschek@kde.org>
 * Copyright (c) 2012, Florian Hülsmann <fh@cbix.de>
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING-README file.
 */

// Check if we are a user
OCP\User::checkLoggedIn();
OCP\JSON::callCheck();

OCP\Config::setSystemValue( 'expertmode', $_POST['expertmode'] );

echo 'true';
