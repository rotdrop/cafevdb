#! /bin/bash

perl ./l10n.pl read
msgmerge -vU --previous --backup=numbered de/cafevdb.po  templates/cafevdb.pot
perl ./l10n.pl write

