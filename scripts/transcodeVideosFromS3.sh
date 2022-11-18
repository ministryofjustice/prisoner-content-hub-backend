#!/bin/bash
set -ue
# Transcode videos script.
# This script is intended to be run from a local environment, outside of Docker.

# Requirements:
# - The Drupal site running locally with Docker compose
# - Aws cli installed, (`brew install aws-cli`) and configured with a --profile=dev
#   (Note the dev S3 profile has access to copying files from the live bucket)
# - HandBrakeCli installed (`brew install handbrake-cli`)
# - Mediainfo cli installed (`brew install mediainfo`)

# This script will:
# 1. Check the Drupal DB for the latest video files (ensure you have an up-to-date first, by running `make sync`)
# 2. Download the videos from S3 (from the live bucket).
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
                          ORDER BY node_field_data.created;' > video_urls.txt
files=$(more video_urls.txt)
IFS=$'\n'       # make newlines the only separator

n=0

echo "" > video_transcoded.txt
echo "" > video_untranscoded.txt
for line in $files
do
  file=$(echo $line | cut -d "," -f 1)
  id=$(echo $line | cut -d "," -f 2)
  echo $file
  echo $id
  unTranscodedFilename="videos_untranscoded/"$file
  transcodedFilename="videos_transcoded/"$file
  newS3Filename=${file/"videos"/"videos_transcoded"}
  dir="$(dirname "${transcodedFilename}")"
  mkdir -p $dir
  aws s3 cp "s3://cloud-platform-5e5f7ac99afe21a0181cbf50a850627b/"$file $unTranscodedFilename --profile=dev
  mediaInfo=$(mediaInfo $unTranscodedFilename --Output=JSON)
  if [ $(echo "$mediaInfo" | jq '.media.track[1].BitRate|tonumber') -gt 1000000 ] || [ $(echo "$mediaInfo" | jq '.media.track[1].Height|tonumber') -gt 480 ] || [ $(echo "$mediaInfo" | jq '.media.track[1].FrameRate|tonumber|floor') -gt 30 ]
  then
    HandBrakeCli --preset-import-file scripts/content-hub-handbrake-preset.json --preset="content-hub-handbrake-preset" -i "$unTranscodedFilename" -o "$transcodedFilename"
    untranscodedFileSize=$(stat -f%z $unTranscodedFilename)
    transcodedFileSize=$(stat -f%z $transcodedFilename)
    if [ $transcodedFileSize -lt $untranscodedFileSize ]
    then
      echo "$transcodedFilename, $id, reduced by "$((($untranscodedFileSize-$transcodedFileSize)/1000000))"MB" >> video_transcoded.txt
      aws s3 cp $transcodedFilename "s3://cloud-platform-5e5f7ac99afe21a0181cbf50a850627b/"$newS3Filename --profile=dev
    else
      echo "$transcodedFilename, $id, increased by "$((($transcodedFileSize-$untranscodedFileSize)/1000000))"MB" >> video_untranscoded.txt
    fi
    rm $transcodedFilename
  else
    echo "$transcodedFilename, $id, not transcoded due to mediainfo" >> video_untranscoded.txt
  fi
  ((n++))
  echo "Processed $n videos."
  rm $unTranscodedFilename
done
