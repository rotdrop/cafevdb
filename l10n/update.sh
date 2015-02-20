#! /bin/bash

APP=cafevdb

perl ./l10n.pl read ${APP}
msgmerge -vU --previous --backup=numbered de/${APP}.po  templates/${APP}.pot
perl ./l10n.pl write ${APP}

