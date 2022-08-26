#!/bin/bash
set -ue

# Install AWS cli.
# This isn't exactly optimal as we have to run it every time.
# But it's better than adding it into the main Dockerfile (I think).
curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64-2.1.27.zip" -o "awscliv2.zip"
unzip awscliv2.zip
./aws/install

echo "[default]" > ~/.aws/credentials
echo "aws_access_key_id=${DATABASE_BACKUP_S3_KEY}"
echo "aws_secret_access_key=${DATABASE_BACKUP_S3_SECRET}"

OBJECT="$(aws s3 ls --profile $BUCKET --recursive | sort | tail -n 1 | awk '{print $4}')"
aws s3 cp s3://${DATABASE_BACKUP_S3_BUCKET}/$OBJECT $OBJECT

drush sql-drop -y
drush sql-cli < $OBJECT
make deploy
