#!/bin/bash
set -ue

aws s3 sync  \
  s3://"${S3_SOURCE_BUCKET_TEMP}" \
  s3://"${S3_DESTINATION_BUCKET_TEMP}" \
  --source-region "${S3_SOURCE_REGION_TEMP}" \
  --region "${S3_DESTINATION_REGION_TEMP}" \
