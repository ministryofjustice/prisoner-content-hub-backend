#!/bin/bash
set -ue

# Transcode videos script.
# This script is intended to be run from a local environment, outside of Docker.

# Requirements:
# - The Drupal site running locally with Docker compose
# - Aws cli installed, (`brew install aws-cli`) and configured with a --profile=dev
#   (Note the dev S3 profile has access to copying files from the live bucket)
# - HandBrakeCli installed (`brew install handbrake-cli`)

# This script will:
# 1. Check the Drupal DB for the latest video files (ensure you have an up-to-date first, by running `make sync`)
# 2. Download the videos from S3 (from the live bucket).
# 3. Transcode them using handbrake (with profile "Vimeo YouTube 720p30")
# 4. Check whether the transcoded version is a smaller size than the original
# 5. If the new file has a smaller size than the original, it gets uploaded to S3, and added to the file videos_transcoded.txt
# 6. If the new file has a greater size than the original, it does not get uploaded to S3, and the file is added to videos_untranscoded.txt

docker-compose exec drupal drush sql-query 'SELECT REPLACE(file_managed.uri, "s3://", "") FROM
                          node_field_data
                          JOIN node__field_video on node__field_video.entity_id = node_field_data.nid
                          JOIN file_managed on file_managed.fid = node__field_video.field_video_target_id
                          WHERE node_field_data.status = 1
                          ORDER BY node_field_data.created DESC
                          LIMIT 2;' > video_urls.txt
files=$(more video_urls.txt)
IFS=$'\n'       # make newlines the only separator

n=0
for file in $files
do
  echo $file
  unTranscodedFilename="videos_untranscoded/"$file
  transcodedFilename="videos_transcoded/"$file
  newS3Filename=${file/"videos"/"videos_transcoded"}
  dir="$(dirname "${transcodedFilename}")"
  mkdir -p dir
  aws s3 cp "s3://cloud-platform-5e5f7ac99afe21a0181cbf50a850627b/"$file $unTranscodedFilename --profile=dev
  HandBrakeCli --preset="Vimeo YouTube 720p30" -i "$unTranscodedFilename" -o "$transcodedFilename" > /dev/null
  untranscodedFileSize=$(stat -f%z $unTranscodedFilename)
  transcodedFileSize=$(stat -f%z $transcodedFilename)
  if [ $transcodedFileSize -lt $untranscodedFileSize ]
  then
    touch video_transcoded.txt
    echo "$transcodedFilename reduced by "$((($untranscodedFileSize-$transcodedFileSize)/1000000))"MB" >> video_transcoded.txt
    aws s3 mv $transcodedFilename "s3://cloud-platform-5e5f7ac99afe21a0181cbf50a850627b/"$newS3Filename --profile=dev
  else
    touch video_untranscoded.txt
    echo "$transcodedFilename increased by "$((($transcodedFileSize-$untranscodedFileSize)/1000000))"MB" >> video_untranscoded.txt
  fi
  ((n++))
  echo "Processed $n videos."

done
