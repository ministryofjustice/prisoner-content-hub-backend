# Digital Hub Backend

The backend CMS for the Digital Hub service using Drupal

For the frontend, see https://github.com/ministryofjustice/prisoner-content-hub-frontend

## Getting started

### Prerequisites
Docker

### Running the application
Start the application with:

>`-d` starts the services up in the background

```
docker-compose up -d
```

Once all the services have started, you can access them at:

http://localhost:11001

When you first launch the application, you should see the Drupal install screen.
This means the setup was successfully.  To get a fully working site, follow the steps below to sync the database.

## Sync local database with production

### Prerequisites
- The application is running locally though docker compose (see above)
- Kubectl setup and authenticated with cloud platform.
  See https://user-guide.cloud-platform.service.justice.gov.uk/documentation/getting-started/kubectl-config.html#connecting-to-the-cloud-platform-39-s-kubernetes-cluster.
  I.e. you should be able to run `kubectl -n prisoner-content-hub-development get pods` without any errors.

### Sync command
Run `make sync` from the root of this repo.  Run this from your host machine (not inside the container).
This command does the following actions:
- Runs the kubectl and aws cli to download the latest database backup (the database is backed up once a day from prod).
- Imports this into your local environment.

## Files in S3
Drupal is configured to store its files in S3 (e.g. images, pdfs, videos and audio files).
The docker-compose.yml file on this project comes with a local s3 environment, via localstack.
However, if you want to use real files from production, it's best to update your prisoner-content-hub-backend-local.env
file with the s3 credentials for the development S3 bucket.

To obtain the s3 credentials, you can run the following commands:
- FLYSYSTEM_S3_KEY

  `kubectl -n prisoner-content-hub-development get secret drupal-s3 --template={{.data.access_key_id}} | base64 --decode`
- FLYSYSTEM_S3_SECRET

  `kubectl -n prisoner-content-hub-development get secret drupal-s3 --template={{.data.secret_access_key}} | base64 --decode`
- FLYSYSTEM_S3_BUCKET

  `kubectl -n prisoner-content-hub-development get secret drupal-s3 --template={{.data.bucket_name}} | base64 --decode`
- FLYSYSTEM_S3_REGION=eu-west-2
