#!/bin/bash
set -ue

filename="database_backup_$(date +"%F %T").sql"
pathToFile="~/${filename}"
drush sql-dump --result-file="${pathToFile}"

echo "[default]" > ~/.aws/credentials
echo "aws_access_key_id=${DATABASE_BACKUP_S3_KEY}"
echo "aws_secret_access_key=${DATABASE_BACKUP_S3_SECRET}"

aws s3 mv ${pathToFile} s3://${DATABASE_BACKUP_S3_BUCKET}/${filename} --region=${DATABASE_BACKUP_S3_REGION}
