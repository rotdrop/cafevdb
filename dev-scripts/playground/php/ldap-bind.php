<?php

$ldap = "cafev.de";
$usr = 'uid=claus-justus.heine,ou=People,dc=cafev,dc=de';
$pwd = "XXXX";

$ds = ldap_connect($ldap);
$ldapbind = false;
if (ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3))
  if (ldap_set_option($ds, LDAP_OPT_REFERRALS, 0))
    if (ldap_start_tls($ds))
      $ldapbind = ldap_bind($ds, $usr, $pwd);
ldap_close($ds);
if (!$ldapbind)
  echo "ERROR" . PHP_EOL;
else
  echo "OK" . PHP_EOL;
