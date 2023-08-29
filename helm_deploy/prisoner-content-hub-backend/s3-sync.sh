#!/bin/bash
set -ue

aws s3 sync  \
  s3://${S3_SOURCE_BUCKET} \
  s3://${S3_DESTINATION_BUCKET} \
  --source-region ${S3_SOURCE_REGION} \
  --region ${S3_DESTINATION_REGION} \
