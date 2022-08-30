#!/bin/bash
set -ue

echo "[default]" > ~/.aws/credentials
echo "aws_access_key_id=${DB_BACKUP_S3_KEY}"
echo "aws_secret_access_key=${DB_BACKUP_S3_SECRET}"

OBJECT="$(aws s3 ls --profile $BUCKET --recursive | sort | tail -n 1 | awk '{print $4}')"
aws s3 cp s3://${DB_BACKUP_S3_BUCKET}/$OBJECT $OBJECT

drush sql-drop -y
drush sql-cli < $OBJECT
make deploy
