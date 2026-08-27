--- nesbot/carbon/src/Carbon/Traits/Mixin.php.orig	2025-01-08 21:10:23.000000000 +0100
+++ nesbot/carbon/src/Carbon/Traits/Mixin.php	2026-08-27 18:07:49.509790369 +0200
@@ -89,7 +89,9 @@
                 continue;
             }
 
-            $method->setAccessible(true);
+            if (PHP_VERSION_ID < 80100) {
+                $method->setAccessible(true);
+            }
 
             static::macro($method->name, $method->invoke($mixin));
         }
