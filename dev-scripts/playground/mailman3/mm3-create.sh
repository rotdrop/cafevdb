#!/bin/bash

# from httplib2 import Http
# >>> headers = {
# ...     'Content-Type': 'application/x-www-form-urlencode',
# ...     'Authorization': 'Basic cmVzdGFkbWluOnJlc3RwYXNz',
# ...     }
# >>> url = 'http://localhost:9001/3.0/system/versions'
# >>> response, content = Http().request(url, 'GET', None, headers)
# >>> print(response.status)

DOMAIN="lists.renovation.cafev.de"
LISTNAME="baf11f55-d1ee-4d36-acec-a455979172c3@$DOMAIN"
LISTID="baf11f55-d1ee-4d36-acec-a455979172c3.$DOMAIN"

#    http://localhost:8001/3.1/system/configuration

# create if not extists
curl \
    --user restadmin:n3G/cCyKXknkLLbRFPN0NSf5gBsbOH3NLGKPzy7M0MLQhmwM \
    -X POST \
    -d "fqdn_listname=$LISTNAME" \
    http://localhost:8001/3.1/lists

ALIASES_JSON=$(cat<<EOF
{
  "acceptable_aliases": [ "barfoo@blah.bar", "foobar@blah.bar" ]
}
EOF
)

curl \
    --user restadmin:n3G/cCyKXknkLLbRFPN0NSf5gBsbOH3NLGKPzy7M0MLQhmwM \
    -H "Content-Type: application/json" \
    -X PATCH \
    --data "$ALIASES_JSON" \
    http://localhost:8001/3.1/lists/$LISTNAME/config/acceptable_aliases

NAME_JSON=$(cat<<EOF
{
  "list_name": "blahblah"
}
EOF
)

curl \
    --user restadmin:n3G/cCyKXknkLLbRFPN0NSf5gBsbOH3NLGKPzy7M0MLQhmwM \
    -H "Content-Type: application/json" \
    -X PATCH \
    --data "$NAME_JSON" \
    http://localhost:8001/3.1/lists/$LISTNAME/config/list_name

curl \
    --user restadmin:n3G/cCyKXknkLLbRFPN0NSf5gBsbOH3NLGKPzy7M0MLQhmwM \
    -X GET \
    "http://localhost:8001/3.1/lists/$LISTNAME/config" \
    | json_pp

# # set display name
# curl \
#      --user restadmin:n3G/cCyKXknkLLbRFPN0NSf5gBsbOH3NLGKPzy7M0MLQhmwM \
#      -X PUT \
#      -d "$RESOURCE=$NEWVALUE" \
#      "http://localhost:8001/3.1/lists/$LISTNAME/config/$RESOURCE"

# # get display name
# curl \
#     --user restadmin:n3G/cCyKXknkLLbRFPN0NSf5gBsbOH3NLGKPzy7M0MLQhmwM \
#      -X GET \
#      "http://localhost:8001/3.1/lists/$LISTNAME/config/$RESOURCE"

# delete mailing list
curl \
    --user restadmin:n3G/cCyKXknkLLbRFPN0NSf5gBsbOH3NLGKPzy7M0MLQhmwM \
    -X DELETE \
    http://localhost:8001/3.1/lists/$LISTID
