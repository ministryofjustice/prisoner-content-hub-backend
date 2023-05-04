#!/bin/bash
set -ue

mkdir ~/.aws/
echo "[default]" > ~/.aws/credentials
echo "aws_access_key_id=${S3_DESTINATION_KEY_TEMP}" >> ~/.aws/credentials
echo "aws_secret_access_key=${S3_DESTINATION_SECRET_TEMP}" >> ~/.aws/credentials

aws s3 sync  \
  s3://"${S3_SOURCE_BUCKET_TEMP}" \
  s3://"${S3_DESTINATION_BUCKET_TEMP}" \
  --source-region "${S3_SOURCE_REGION_TEMP}" \
  --region "${S3_DESTINATION_REGION_TEMP}" \
