#!/bin/bash
set -ue

echo "[mysqldump]" > ~/.my.cnf
echo "user=${HUB_DB_ENV_MYSQL_USER}" >> ~/.my.cnf
echo "password=${HUB_DB_ENV_MYSQL_PASSWORD}" >> ~/.my.cnf
echo "host=${HUB_DB_PORT_3306_TCP_ADDR}" >> ~/.my.cnf
echo "column-statistics=0" >> ~/.my.cnf

filename="db_backup_$(date +"%F-%H%M%S").sql"
mysqldump ${HUB_DB_ENV_MYSQL_DATABASE} > ~/${filename}

mkdir ~/.aws/
echo "[default]" > ~/.aws/credentials
echo "aws_access_key_id=${DB_BACKUP_S3_KEY}" >> ~/.aws/credentials
echo "aws_secret_access_key=${DB_BACKUP_S3_SECRET}" >> ~/.aws/credentials

aws s3 mv ~/${filename} s3://${DB_BACKUP_S3_BUCKET}/${filename} --region=${DB_BACKUP_S3_REGION}

echo "Successfuly backed up database ${filename}"
