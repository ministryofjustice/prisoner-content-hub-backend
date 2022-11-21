#!/bin/bash
set -ue
# Check current video encodings.
# This script is intended to be run from a local environment, outside of Docker.

# Requirements:
# - The Drupal site running locally with Docker compose
# - Aws cli installed, (`brew install aws-cli`) and configured with a --profile=dev
#   (Note the dev S3 profile has access to copying files from the live bucket)
# - Mediainfo cli installed (`brew install mediainfo`)

# This script will:
# 1. Check the Drupal DB for the latest video files (ensure you have an up-to-date first, by running `make sync`)
# 2. Generate a report
# 3. Check whether the video has a higher bitrate/fps/height than what we will be transcoding to.
# 3. Transcode them using handbrake (with custom profile "content-hub-handbrake-preset", which is essentially the
# same as "Vimeo YouTube 720p30" but with a 1250kb variable bitrate).
# 4. Check whether the transcoded version is a smaller size than the original
# 5. If the new file has a smaller size than the original, it gets uploaded to S3, and added to the file videos_transcoded.txt
# 6. If the new file has a greater size than the original, it does not get uploaded to S3, and the file is added to videos_untranscoded.txt

docker-compose exec drupal drush sql-query 'SELECT REPLACE(file_managed.uri, "s3://", ""), node_field_data.nid FROM
                          node_field_data
                          JOIN node__field_video on node__field_video.entity_id = node_field_data.nid
                          JOIN file_managed on file_managed.fid = node__field_video.field_video_target_id
                          WHERE node_field_data.status = 1
                          ORDER BY node_field_data.created;' --result-file=../check-video-encodings-query-results.txt
files=$(more check-video-encodings-query-results.txt)
IFS=$'\n'       # make newlines the only separator

n=0

echo "uri,id,BitRate,Height,FrameRate" > video-encodings-results.csv
for line in $files
do
  file=$(echo $line | cut -d $'\t' -f 1)
  id=$(echo $line | cut -d $'\t' -f 2)
  echo $file
  echo $id
  presignedUrl=$(aws s3 presign "s3://cloud-platform-5e5f7ac99afe21a0181cbf50a850627b/"$file --profile=dev --region eu-west-1)
  mediaInfo=$(mediaInfo $presignedUrl --Output=JSON)
  bitrate=$(echo "$mediaInfo" | jq '.media.track[1].BitRate|tonumber')
  height=$(echo "$mediaInfo" | jq '.media.track[1].Height|tonumber')
  framerate=$(echo "$mediaInfo" | jq '.media.track[1].FrameRate|tonumber|floor')

  echo "$file, $id, $bitrate, $height, $framerate" >> video-encodings-results.csv
  ((n++))
  echo "Processed $n videos."
done
