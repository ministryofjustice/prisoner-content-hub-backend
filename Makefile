build-deps:
	composer clear-cache
	composer install --no-dev --no-ansi --no-scripts --prefer-dist --ignore-platform-reqs --no-interaction --no-autoloader

build:
	docker build -t prisoner-content-hub-backend .

clean:
	rm -rf modules/contrib

push:
	@docker login -u="${QUAYIO_USERNAME}" -p="${QUAYIO_PASSWORD}" quay.io
	docker tag prisoner-content-hub-backend hmpps/prisoner-content-hub-backend:$(APP_VERSION)
	docker tag prisoner-content-hub-backend hmpps/prisoner-content-hub-backend:latest
	docker push hmpps/prisoner-content-hub-backend:$(APP_VERSION)
	docker push hmpps/prisoner-content-hub-backend:latest

push-preview:
	@docker login -u="${QUAYIO_USERNAME}" -p="${QUAYIO_PASSWORD}" quay.io
	docker tag prisoner-content-hub-backend hmpps/prisoner-content-hub-backend:$(APP_VERSION)
	docker push hmpps/prisoner-content-hub-backend:$(APP_VERSION)

install-drupal:
	vendor/bin/drush site-install prisoner_content_hub_profile --existing-config -y

run-tests:
	echo "Running tests on existing site"
	vendor/bin/phpunit --testsuite=existing-site --log-junit ~/phpunit/junit-existing-site.xml --verbose
	echo "Running Javascript tests on existing site"
	vendor/bin/phpunit --testsuite=existing-site-javascript --log-junit ~/phpunit/junit-existing-site-javascript.xml --verbose
