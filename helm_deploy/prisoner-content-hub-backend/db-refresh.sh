#!/bin/bash
set -ue

mkdir ~/.aws
echo "[default]" > ~/.aws/credentials
echo "aws_access_key_id=${DB_BACKUP_S3_KEY}" >> ~/.aws/credentials
echo "aws_secret_access_key=${DB_BACKUP_S3_SECRET}" >> ~/.aws/credentials

# Find the most recent file in the S3 bucket.
filename="$(aws s3 ls ${DB_BACKUP_S3_BUCKET} --region=${DB_BACKUP_S3_REGION} --recursive | grep '.sql' | sort | tail -n 1 | awk '{print $4}')"
if [ -z "$filename" ]
then
  echo "No database backup files found.  Unable to perform database refresh."
  # Exit with error.
  exit 1
fi

aws s3 cp s3://${DB_BACKUP_S3_BUCKET}/$filename ~/{$filename}

echo "[mysql]" > ~/.my.cnf
echo "user=${HUB_DB_ENV_MYSQL_USER}" >> ~/.my.cnf
echo "password=${HUB_DB_ENV_MYSQL_PASSWORD}" >> ~/.my.cnf
echo "host=${HUB_DB_PORT_3306_TCP_ADDR}" >> ~/.my.cnf

mysql ${HUB_DB_ENV_MYSQL_DATABASE} < ~/{$filename}

echo "Successfully imported database ${filename}"
