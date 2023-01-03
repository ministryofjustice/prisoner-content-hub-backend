# Content Hub Backend

The backend CMS for the Digital Hub service using Drupal

For the frontend, see https://github.com/ministryofjustice/prisoner-content-hub-frontend

## Getting started

### Prerequisites
Docker

### Running the application for the first time
#### 1. Start the docker environment
>`-d` starts the services up in the background

```
docker-compose up -d
```
#### 2. Build PHP/Drupal dependencies
```
docker-compose exec drupal composer install
```
_(Note that is already run as part of the docker build, but the files will be wiped out by the volume mount, set in
docker-compose.override.yml.  So the command needs to be run again.)_
#### 3. Import the database
For this part you will need to have `kubectl` setup and authenticated with cloud platform. \
See https://user-guide.cloud-platform.service.justice.gov.uk/documentation/getting-started/kubectl-config.html#connecting-to-the-cloud-platform-39-s-kubernetes-cluster. \
I.e. you should be able to run `kubectl -n prisoner-content-hub-development get pods` without any errors.

Now run:
```
make sync
```
This will download the latest database backup and import it (the database is backed up once a day). \
Note this should be run from your host machine (not inside the container).

Alternatively, you can install a "fresh" version of Drupal.
```
docker-compose exec drupal make install-drupal
```
This will have all of the site's configuration, but won't have any content or taxonomy.
#### 4. Access the service
Once all the services have started, you can access them at:

**http://localhost:11001**

#### 5. Logging into Drupal
The `make sync` command brings in all of the Drupal users from production.  So if you already have an account setup there,
you can login with the same username/password on your local environment.

Alternatively, you can login with the admin account by running:
```
docker-compose exec drupal drush user:unblock admin
docker-compose exec drupal drush uli --uri=http://localhost:11001/
```
This will give you a login link to access the site. \
Note this account is blocked on production, and should only be used on local environments.

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
- FLYSYSTEM_S3_CNAME_IS_BUCKET=true
- FLYSYSTEM_S3_CNAME=""
- FLYSYSTEM_S3_ENDPOINT=""
