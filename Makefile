build:
	docker build --target optimised-build -t prisoner-content-hub-backend .

push:
	@docker login -u="${QUAYIO_USERNAME}" -p="${QUAYIO_PASSWORD}" quay.io
	docker tag prisoner-content-hub-backend quay.io/hmpps/prisoner-content-hub-backend:$(APP_VERSION)
	docker tag prisoner-content-hub-backend quay.io/hmpps/prisoner-content-hub-backend:latest
	docker push quay.io/hmpps/prisoner-content-hub-backend:$(APP_VERSION)
	docker push quay.io/hmpps/prisoner-content-hub-backend:latest

push-preview:
	@docker login -u="${QUAYIO_USERNAME}" -p="${QUAYIO_PASSWORD}" quay.io
	docker tag prisoner-content-hub-backend quay.io/hmpps/prisoner-content-hub-backend:$(APP_VERSION)
	docker push quay.io/hmpps/prisoner-content-hub-backend:$(APP_VERSION)

install-drupal:
	vendor/bin/drush site-install prisoner_content_hub_profile --existing-config -y

run-tests:
	echo "Running tests on existing site"
	vendor/bin/phpunit --testsuite=existing-site --log-junit ~/phpunit/junit-existing-site.xml --verbose

deploy:
	echo "Enabling maintenance and readonly mode"
	drush state-set readonlymode_active 1
	drush state-set system.maintenance_mode 1
	echo "Running deploy commands"
	drush deploy
	echo "Disabling maintenance and readonly mode"
	drush state-set readonlymode_active 0
	drush state-set system.maintenance_mode 0
