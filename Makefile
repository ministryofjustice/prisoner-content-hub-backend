build:
	docker build -t prisoner-content-hub-backend .

push:
	@docker login -u $(DOCKER_USERNAME) -p $(DOCKER_PASSWORD)
	docker tag prisoner-content-hub-backend mojdigitalstudio/prisoner-content-hub-backend:$(APP_VERSION)
	docker tag prisoner-content-hub-backend mojdigitalstudio/prisoner-content-hub-backend:latest
	docker push mojdigitalstudio/prisoner-content-hub-backend:$(APP_VERSION)
	docker push mojdigitalstudio/prisoner-content-hub-backend:latest

push-preview:
	@docker login -u $(DOCKER_USERNAME) -p $(DOCKER_PASSWORD)
	docker tag prisoner-content-hub-backend mojdigitalstudio/prisoner-content-hub-backend:$(APP_VERSION)
	docker push mojdigitalstudio/prisoner-content-hub-backend:$(APP_VERSION)

install-drupal:
	vendor/bin/drush site-install prisoner_content_hub_profile --existing-config -y

run-tests:
	echo "Running tests on existing site"
	vendor/bin/phpunit --testsuite=existing-site --log-junit ~/phpunit/junit-existing-site.xml --verbose
	echo "Running Javascript tests on existing site"
	vendor/bin/phpunit --testsuite=existing-site-javascript --log-junit ~/phpunit/junit-existing-site-javascript.xml --verbose

deploy:
  echo "Enabling maintenance and readonly mode"
  drush state-set readonlymode_active 1
  drush state-set system.maintenance_mode 1
  echo "Running deploy commands"
  drush deploy
  echo "Disabling maintenance and readonly mode"
  drush state-set readonlymode_active 0
  drush state-set system.maintenance_mode 0
