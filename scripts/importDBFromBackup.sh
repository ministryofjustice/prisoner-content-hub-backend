#!/bin/bash
set -ue
files=( db-backups/*.sql )
$(drush sql:connect) < ${files[0]}
drush deploy
