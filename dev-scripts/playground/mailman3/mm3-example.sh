#!/bin/bash

# from httplib2 import Http
# >>> headers = {
# ...     'Content-Type': 'application/x-www-form-urlencode',
# ...     'Authorization': 'Basic cmVzdGFkbWluOnJlc3RwYXNz',
# ...     }
# >>> url = 'http://localhost:9001/3.0/system/versions'
# >>> response, content = Http().request(url, 'GET', None, headers)
# >>> print(response.status)

LISTNAME="resttest@lists.renovation.cafev.de"
LISTID="resttest.lists.renovation.cafev.de"

# list of mailing lists
# curl \
#     --user restadmin:n3G/cCyKXknkLLbRFPN0NSf5gBsbOH3NLGKPzy7M0MLQhmwM \
#     -X GET \
#     http://localhost:8001/3.1/lists

#    http://localhost:8001/3.1/system/configuration


# create if not extists
# curl \
#     --user restadmin:n3G/cCyKXknkLLbRFPN0NSf5gBsbOH3NLGKPzy7M0MLQhmwM \
#     -X POST \
#     -d "fqdn_listname=$LISTNAME" \
#     http://localhost:8001/3.1/lists

# list of mailing lists
# curl \
#     --user restadmin:n3G/cCyKXknkLLbRFPN0NSf5gBsbOH3NLGKPzy7M0MLQhmwM \
#     -X GET \
#     http://localhost:8001/3.1/lists



# get configuration for a list
# curl \
#     --user restadmin:n3G/cCyKXknkLLbRFPN0NSf5gBsbOH3NLGKPzy7M0MLQhmwM \
#     -X GET \
#     "http://localhost:8001/3.1/lists/$LISTNAME/config"

RESOURCE=display_name

# get display name
# curl \
#     --user restadmin:n3G/cCyKXknkLLbRFPN0NSf5gBsbOH3NLGKPzy7M0MLQhmwM \
#     -X GET \
#     "http://localhost:8001/3.1/lists/$LISTNAME/config/$RESOURCE"

# set display name
# curl \
#     --user restadmin:n3G/cCyKXknkLLbRFPN0NSf5gBsbOH3NLGKPzy7M0MLQhmwM \
#     -X PUT \
#     -d "$RESOURCE=BlahBlubBlah" \
#     "http://localhost:8001/3.1/lists/$LISTNAME/config/$RESOURCE"

# get display name
# curl \
#     --user restadmin:n3G/cCyKXknkLLbRFPN0NSf5gBsbOH3NLGKPzy7M0MLQhmwM \
#     -X GET \
#     "http://localhost:8001/3.1/lists/$LISTNAME/config/$RESOURCE"

# attributes we want:

# archive_policy: private
# dmarc_mitigate_action: munge_from
# advertised: false
# max_message_size: 0 no limit
# max_num_recipients: 10 or 0 no limit?
# subject_prefix: [BLA]
# acceptable_aliases: needed after renaming the list
# preferred_language: de

# get configuration for a list
# curl \
#     --user restadmin:n3G/cCyKXknkLLbRFPN0NSf5gBsbOH3NLGKPzy7M0MLQhmwM \
#     -X GET \
#     "http://localhost:8001/3.1/lists/vorstand@lists.renovation.cafev.de/config"

# echo
# echo
# echo '*********'
# echo

# curl \
#     --user restadmin:n3G/cCyKXknkLLbRFPN0NSf5gBsbOH3NLGKPzy7M0MLQhmwM \
#     -X GET \
#     "http://localhost:8001/3.1/lists/$LISTNAME/config"

# subscribing to a list ...

SUBSCRIBE_JSON=$(cat<<EOF
{
 "list_id": "$LISTID",
 "subscriber": "himself@claus-justus-heine.de",
 "display_name": "CJH RestTestDisplayName",
 "pre_verified": "True",
 "pre_confirmed": "True",
 "pre_approved": "True"
}
EOF
)

# "send_welcome_message": "True"


echo "$SUBSCRIBE_JSON"

curl \
    --user restadmin:n3G/cCyKXknkLLbRFPN0NSf5gBsbOH3NLGKPzy7M0MLQhmwM \
    --header "Content-Type: application/json" \
    -X POST \
    --data "$SUBSCRIBE_JSON" \
    http://localhost:8001/3.1/members

# list members

curl \
    --user restadmin:n3G/cCyKXknkLLbRFPN0NSf5gBsbOH3NLGKPzy7M0MLQhmwM \
    -X POST \
    -d "list_id=$LISTID" \
    "http://localhost:8001/3.1/members/find"

# delivery addreses can then be changed via "self-link"

# unsubscribe works via method DELETE to "self_link".
