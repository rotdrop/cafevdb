#!/bin/bash

PYTHON=/usr/bin/python
FORMAIL=$(which formail)
MUNPACK=$(which munpack)

# Vereinsmitglieder
MEMBERS_PROJECT_ID=18

function decodeHeader()
{
    ${PYTHON} -c "from email.header import decode_header;
import sys;
decodedHeader = '';
for text, encoding in decode_header(sys.stdin.read()):
    if not isinstance(text, str):
        if encoding == None:
            encoding = 'utf-8';
        text = text.decode(encoding);
    decodedHeader += text;
print(decodedHeader.strip())"
}

function extractHeader()
{
    local HEADER="$1"
    ${FORMAIL} -c -x ${HEADER}:|decodeHeader
}

function escapeCSVData()
{
    local DATA="$1"
    DATA=$(echo -n "$DATA"|sed 's/"/""/g')
    echo -n "$DATA"
}

echo "message_id","reference_id","project_id","created_by","created","bulk_recipients","bulk_recipients_hash","cc","bcc","subject","subject_hash","html_body","html_body_hash"

for i in *.eml ; do
    TO=$(extractHeader To < "$i")
    TO_HASH=$(echo -n $TO|md5sum|awk '{ print $1; }')
    MSG_ID=$(extractHeader Message-ID < "$i")
    REF=$(extractHeader References < "$i")
    if [ $(echo -n "$REF"|wc -w) -gt 1 ]; then
        REF=""
    fi
    DATE=$(date -u +'%Y-%m-%d %H:%M:%S' --date "$(extractHeader Date < "$i")")
    PROJECT="$MEMBERS_PROJECT_ID"
    CREATED_BY="claus-justus.heine"
    CREATED="$DATE"
    CC=""
    BCC=""
    SUBJECT=$(extractHeader Subject < "$i")
    SUBJECT_HASH=$(echo -n $SUBJECT|md5sum|awk '{ print $1; }')
    #
    FS_MSG_ID=$(echo -n $MSG_ID|sed -e 's/>//g' -e 's/<//g')
    rm -rf $FS_MSG_ID
    mkdir $FS_MSG_ID
    while read FILE MIME; do
        if [ "$MIME" = '(text/html)' ]; then
            HTML_PART=$FS_MSG_ID/$FILE
        fi
    done < <(${MUNPACK} -C "$FS_MSG_ID" -r_ -t -f "../$i")
    if [ -f "$HTML_PART" ]; then
        BODY=$(cat $HTML_PART|tr -d '\r')
    fi
    BODY_HASH=$(md5sum $HTML_PART|awk '{ print $1; }')
    rm -rf $FS_MSG_ID
    #
    # FIXME: where is this command line tool to properly esacpe CSV fields and stuff them into a data-line?
    SUBJECT=$(escapeCSVData "$SUBJECT")
    BODY=$(escapeCSVData "$BODY")
    TO=$(escapeCSVData "$TO")
    #
    echo "\"$MSG_ID\",\"$REF\",\"$PROJECT\",\"$CREATED_BY\",\"$CREATED\",\"$TO\",\"$TO_HASH\",\"$CC\",\"$BCC\",\"$SUBJECT\",\"$SUBJECT_HASH\",\"$BODY\",\"$BODY_HASH\""
done
