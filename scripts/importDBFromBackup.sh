#!/bin/bash
set -ue
files=( db-backups/*.sql )
drush sql-cli < ${files[0]}
