#!/bin/bash
set -ue

filename="db_backup_$(date +"%F %T").sql"
pathToFile="~/${filename}"
drush sql-dump --result-file="${pathToFile}"

echo "[default]" > ~/.aws/credentials
echo "aws_access_key_id=${DB_BACKUP_S3_KEY}"
echo "aws_secret_access_key=${DB_BACKUP_S3_SECRET}"

aws s3 mv ${pathToFile} s3://${DB_BACKUP_S3_BUCKET}/${filename} --region=${DB_BACKUP_S3_REGION}
