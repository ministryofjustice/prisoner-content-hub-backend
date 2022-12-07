#!/bin/bash
set -ue

mkdir ~/.aws/
echo "[default]" > ~/.aws/credentials
echo "aws_access_key_id=${S3_DESTINATION_KEY}" >> ~/.aws/credentials
echo "aws_secret_access_key=${S3_DESTINATION_SECRET}" >> ~/.aws/credentials

aws s3 sync  \
  s3://${S3_SOURCE_BUCKET} \
  s3://${S3_DESTINATION_BUCKET} \
  --source-region ${S3_SOURCE_REGION} \
  --region ${S3_DESTINATION_REGION} \
