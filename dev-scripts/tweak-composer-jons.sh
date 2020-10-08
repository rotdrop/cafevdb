jq '.packages[]|{(.name): .version}' < ../../3rdparty/composer.lock|sed -e 's/[{}]//g' -e '/^$/d' 

+

#!/bin/bash

for (( i=0;; ++i )) ; do
  if cat ../../3rdparty/composer.lock |jq .packages[$i].name|grep -qs  doctrine/dbal; then
    VERSION=$i
    break
  fi
done

     cat ../../3rdparty/composer.lock |jq .packages[$$DBAL_INDEX].version;\
  echo $$DBAL_INDEX
