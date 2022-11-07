# Digital Hub Backend

The backend CMS for the Digital Hub service using Drupal

## Getting started

### Prerequisites
Docker

### Running the application

To run this application locally, use the docker-compose setup from http://github.com/ministryofjustice/prisoner-content-hub

### Custom Modules

The application is built using Docker, using a Drupal base image.

All custom code specific to the Digital Hub project is implemented as Drupal modules, these are located in

    ./docroot/modules/custom

### Configuration
Drupal's configuration is stored inside the `config/sync` directory.
This is imported during the deployment process, to simulate this on your local environment run the following:
```
vendor/bin/drush deploy
```
Please note that any configuration that has been modified on the environment you are importing to, will be wiped.

To make any configuration changes, make the change on your local environment, and run `drush config-export`, then push
the changes to git.

## Sync local environment with production

### Prerequisites
Kubectl setup and authenticated with cloud platform.
See https://user-guide.cloud-platform.service.justice.gov.uk/documentation/getting-started/kubectl-config.html#connecting-to-the-cloud-platform-39-s-kubernetes-cluster

### Sync command
Run `make sync` from the root of this repo.
This command does the following actions:
- Copies your kubectl config to the prisoner-content-hub-backend Docker container.
- Runs the kubectl and aws cli to download the latest database backup (the database is backed up once a day from prod).
- Imports this into your local environment.
