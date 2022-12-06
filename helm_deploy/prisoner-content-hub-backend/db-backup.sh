#!/bin/bash
set -ue

echo "[mysqldump]" > ~/.my.cnf
echo "user=${HUB_DB_ENV_MYSQL_USER}" >> ~/.my.cnf
echo "password=${HUB_DB_ENV_MYSQL_PASSWORD}" >> ~/.my.cnf
echo "host=${HUB_DB_PORT_3306_TCP_ADDR}" >> ~/.my.cnf
echo "column-statistics=0" >> ~/.my.cnf

echo "[client]" >> ~/.my.cnf
echo "user=${HUB_DB_ENV_MYSQL_USER}" >> ~/.my.cnf
echo "password=${HUB_DB_ENV_MYSQL_PASSWORD}" >> ~/.my.cnf
echo "host=${HUB_DB_PORT_3306_TCP_ADDR}" >> ~/.my.cnf

# Make 8 maximum attempts to connect to the database.  This mitigates intermittent DNS issues.
# See https://mojdt.slack.com/archives/C57UPMZLY/p1664264969450269
# See https://mojdt.slack.com/archives/C57UPMZLY/p1666708074467369
attempts=8
while ! mysql ${HUB_DB_ENV_MYSQL_DATABASE} -e "SELECT 1" &> /dev/null
do
  ((attempts--))
  if [ $attempts -eq 0 ]
  then
    echo "Unable to connect to database instance.  Possibly route53 DNS is not yet available. (Tried 8 times before failing)."
    exit 1
  fi
done

filename="db_backup_$(date +"%F-%H%M%S").sql.gz"
mysqldump ${HUB_DB_ENV_MYSQL_DATABASE} | gzip -9 -c > ~/${filename}

mkdir ~/.aws/
echo "[default]" > ~/.aws/credentials
echo "aws_access_key_id=${DB_BACKUP_S3_KEY}" >> ~/.aws/credentials
echo "aws_secret_access_key=${DB_BACKUP_S3_SECRET}" >> ~/.aws/credentials

aws s3 mv ~/${filename} s3://${DB_BACKUP_S3_BUCKET}/${filename} --region=${DB_BACKUP_S3_REGION}

rm ~/${filename}
echo "Successfully backed up database ${filename}"
