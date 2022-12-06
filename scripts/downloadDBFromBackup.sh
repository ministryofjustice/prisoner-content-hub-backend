#!/bin/bash
set -ue

aws configure set aws_access_key_id $(kubectl -n prisoner-content-hub-development get secret db-backups-s3 --template={{.data.access_key_id}} | base64 --decode) --profile drupal-db-backups
aws configure set aws_secret_access_key $(kubectl -n prisoner-content-hub-development get secret db-backups-s3 --template={{.data.secret_access_key}} | base64 --decode) --profile drupal-db-backups
aws configure set region "eu-west-2" --profile drupal-db-backups

# Find the most recent file in the S3 bucket.
filename="$(aws s3 ls $(kubectl -n prisoner-content-hub-development get secret db-backups-s3 --template={{.data.bucket_name}} | base64 --decode) --profile=drupal-db-backups --recursive | grep '.sql.gz' | sort | tail -n 1 | awk '{print $4}')"
if [ -z "$filename" ]
then
  echo "No database backup files found.  Unable to perform database refresh."
  # Exit with error.
  exit 1
fi

filenameExtracted=$(basename $filename .gz)
# If file doesn't exist, download it.
if [ ! -f "db-backups/$filenameExtracted" ]
then
  # Clear out any old db dumps
  rm -rf db-backups/
  mkdir db-backups
  aws s3 cp --profile=drupal-db-backups s3://$(kubectl -n prisoner-content-hub-development get secret db-backups-s3 --template={{.data.bucket_name}} | base64 --decode)/$filename db-backups/$filename
  gzip -d db-backups/$filename

else
  echo "Latest backup already previously downloaded.  Using existing copy."
fi


