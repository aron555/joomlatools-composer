#!/bin/bash

set -e

echo "** Installing test extension"
composer require -v --working-dir=$DOCUMENTROOT/testsite --no-interaction joomlatools/composer-helloworld:dev-testbranch

# Verify if component file is present
FILE="$DOCUMENTROOT/testsite/administrator/components/com_helloworld/helloworld.php"
if [ "$REPO" == "platform" ]; then
  FILE="$DOCUMENTROOT/testsite/app/administrator/components/com_helloworld/helloworld.php"
fi

[ -f $DOCUMENTROOT/testsite/administrator/components/com_helloworld/helloworld.php ] && true || false
grep -q "echo 'Hello World\!'" $DOCUMENTROOT/testsite/administrator/components/com_helloworld/helloworld.php && true || false

# Test if the row exists in the database
DB="sites_testsite.j_extensions"
if [ "$REPO" == "platform" ]; then
  DB="sites_testsite.extensions"
fi

COUNT=$(mysql -uroot -s -N -e "SELECT COUNT(extension_id) FROM sites_testsite.j_extensions WHERE element = 'com_helloworld';")
echo "Matched $COUNT rows"
[ $COUNT -gt 0 ] && true || false
