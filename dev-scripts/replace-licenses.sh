#!/bin/bash

for f in $(fgrep -lr  'You should have received a copy of the GNU Lesser General Public' tests/ ) ; do perl -0 -i -pe '$a = `cat /tmp/broken-license.txt`; $b = `cat /tmp/good-license.txt`; s#\Q$a\E#$b#s' $f ; done
