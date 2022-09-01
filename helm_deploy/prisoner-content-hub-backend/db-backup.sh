#!/bin/bash
set -ue

whoami
echo "[mysqldump]" > ${HOME}/.my.cnf
echo "user=${HUB_DB_ENV_MYSQL_USER}" >> ${HOME}/.my.cnf
echo "password=${HUB_DB_ENV_MYSQL_USER}" >> ${HOME}/.my.cnf

filename="db_backup_$(date +"%F-%H%M%S").sql"
mysqldump ${HUB_DB_ENV_MYSQL_DATABASE} > "${filename}"

echo "[default]" > ${HOME}/.aws/credentials
echo "aws_access_key_id=${DB_BACKUP_S3_KEY}" >> ${HOME}/.aws/credentials
echo "aws_secret_access_key=${DB_BACKUP_S3_SECRET}" >> ${HOME}/.aws/credentials

aws s3 mv ${filename} s3://${DB_BACKUP_S3_BUCKET}/${filename} --region=${DB_BACKUP_S3_REGION}
