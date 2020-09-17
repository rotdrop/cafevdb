<?php

script($_['appName'], 'config');

print_unescaped("<PRE>");
p($l->t("Hallo"));
foreach($_ as $key => $entry) {
    p("[$key] = ");
    if (is_array($entry)) {
        var_dump($entry);
    } elseif (is_object($entry) && !method_exists($var, '__toString')) {
        p(get_class($entry));
        p("\n");
    } else {
        p($entry);
        p("\n");
    }
}
print_unescaped("</PRE>");
