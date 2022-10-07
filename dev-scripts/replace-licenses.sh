#!/bin/bash

for f in $(fgrep -lr  ' se Doctrine\ORM\Tools\Setup' lib/) ; do perl -0 -i -pe '$a = `cat /tmp/broken-license.txt`; $b = `cat /tmp/good-license.txt`; s#\Q$a\E#$b#s' $f ; done
