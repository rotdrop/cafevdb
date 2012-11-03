#! /bin/sh

set -x

HOST=server.example.eu
USER=DBLUSER
PASSWORD=XXXXXXXX
DATABASE=DBNAME 
OPTS="--add-drop-table --lock-tables --default-character-set=utf8"

OUTDIR=$HOME/camerata/webdings/backup
OUTFILE=$OUTDIR/$DATABASE-$(date +'%Y%m%d-%H:%M:%S')

exec > $OUTFILE.log 2>&1

mysqldump --host=$HOST --user=$USER --password=$PASSWORD $OPTS $DATABASE > $OUTFILE.sql
