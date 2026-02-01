#!/bin/bash

# Exchange the OCA\RotDrop\Toolkit namespace prefix by a custom
# OCA\CUSTOMPREFIX\Toolkit prefix. This is in order decouple different
# apps using the toolkit from each other, a little bit in the spirit
# of PHP scoper, but much simpler as we have full control over our own
# sources ;)

SRC_DIR=$(realpath "$(dirname "$0")"/..)
DST_DIR=$([ -n "$1" ] && realpath "$1")
NS_PREFIX=$2

# use OCA\RotDrop\Database\Doctrine\ORM as EntityParentNamespace;
DEFAULT_ENTITY_PARENT_NAMESPACE=$(grep -m1 -r EntityParentNamespace "$SRC_DIR"/Doctrine/ORM\
 |head -n1\
 |cut -d\  -f2\
 |sed -E -e 's/OCA\\RotDrop\\//g' -e 's/\\/\\\\/g')
ENTITY_PARENT_NAMESPACE=${3:-$DEFAULT_ENTITY_PARENT_NAMESPACE}

if [ -z "$DST_DIR" ] || [ -z "$NS_PREFIX" ]; then
    cat <<EOF
Usage: $(basename "$0") DST_DIR NAMESPACE_PREFIX [ENTITY_PARENT_NAMESPACE]
EOF
    exit 1
fi

mkdir -p "$DST_DIR"/.
rsync -a --delete "$SRC_DIR"/[A-Z]* "$DST_DIR"/.
find "$DST_DIR" -name '*.php' -exec sed -Ei\
 -e 's/([\]?)OCA\\RotDrop\\'"$DEFAULT_ENTITY_PARENT_NAMESPACE"'/\1OCA\\'"$NS_PREFIX"'\\'"$ENTITY_PARENT_NAMESPACE"'/g'\
 -e 's/([\]?)OCA\\RotDrop\\Toolkit/\1OCA\\'"$NS_PREFIX"'\\Toolkit/g'\
 {} \;
find "$DST_DIR" -exec touch {} \;
