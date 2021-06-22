#!/bin/bash

# from httplib2 import Http
# >>> headers = {
# ...     'Content-Type': 'application/x-www-form-urlencode',
# ...     'Authorization': 'Basic cmVzdGFkbWluOnJlc3RwYXNz',
# ...     }
# >>> url = 'http://localhost:9001/3.0/system/versions'
# >>> response, content = Http().request(url, 'GET', None, headers)
# >>> print(response.status)

curl \
    --user restadmin:n3G/cCyKXknkLLbRFPN0NSf5gBsbOH3NLGKPzy7M0MLQhmwM \
    -X GET \
    http://localhost:8001/3.1/lists

#    http://localhost:8001/3.1/system/configuration



curl \
    --user restadmin:n3G/cCyKXknkLLbRFPN0NSf5gBsbOH3NLGKPzy7M0MLQhmwM \
    -X POST \
    -d 'fqdn_listname=resttest@lists.renovation.cafev.de' \
    http://localhost:8001/3.1/lists

curl \
    --user restadmin:n3G/cCyKXknkLLbRFPN0NSf5gBsbOH3NLGKPzy7M0MLQhmwM \
    -X GET \
    http://localhost:8001/3.1/lists
    
