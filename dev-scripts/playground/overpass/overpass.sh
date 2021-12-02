#!/bin/bash

DATA1='
[out:csv("name";false)];
area["ISO3166-1"="DE"]->.country;
rel(area.country)[postal_code="47800"];
map_to_area -> .restricted;
way(area.restricted)[highway][name];
for (t["name"]) ( make x name=_.val; out; );
'

DATA2='
[out:csv("name";false)];
area["ISO3166-1"="DE"]->.country;
area[postal_code="47800"]->.restricted;
way(area.country)(area.restricted)[highway][name];
for (t["name"]) ( make x name=_.val; out; );
'

DATA3='
[out:csv("name";false)];
area["ISO3166-1"="DE"]->.country;
area[postal_code="47800"]->.postalCode;
area[name~"Krefeld"]->.city;
way(area.country)(area.postalCode)(area.city)[highway][name];
for (t["name"]) ( make x name=_.val; out; );
'

OVERPASS_URL='https://overpass-api.de/api/interpreter'

#time -p curl --data-urlencode "data=$DATA1" $OVERPASS_URL

time -p curl --data-urlencode "data=$DATA2" $OVERPASS_URL

#time -p curl --data-urlencode "data=$DATA3" $OVERPASS_URL
