#!/bin/bash
set -ue
awslocal s3api create-bucket --bucket localstack-s3
