build-deps:
	composer clear-cache
	composer install --no-dev --no-ansi --no-scripts --prefer-dist --ignore-platform-reqs --no-interaction --no-autoloader

build:
	docker build -t prisoner-content-hub-backend .

clean:
	rm -rf modules/contrib

push:
	@docker login -u $(DOCKER_USERNAME) -p $(DOCKER_PASSWORD)
	docker tag prisoner-content-hub-backend mojdigitalstudio/prisoner-content-hub-backend:build-$(CIRCLE_BUILD_NUM)
	docker tag prisoner-content-hub-backend mojdigitalstudio/prisoner-content-hub-backend:latest
	docker push mojdigitalstudio/prisoner-content-hub-backend:build-$(CIRCLE_BUILD_NUM)
	docker push mojdigitalstudio/prisoner-content-hub-backend:latest

push-preview:
	@docker login -u $(DOCKER_USERNAME) -p $(DOCKER_PASSWORD)
	docker tag prisoner-content-hub-backend mojdigitalstudio/prisoner-content-hub-backend:preview
	docker push mojdigitalstudio/prisoner-content-hub-backend:preview

install-drupal:
	vendor/bin/drush site-install prisoner_content_hub_profile --existing-config -y

run-tests: run-unit-tests run-functional-tests

run-unit-tests:
	echo "Run rest and jsonapi module unit tests"
	vendor/bin/phpunit docroot/ --filter='Drupal\\Tests\\rest\\Unit\\\
	|Drupal\\Tests\\jsonapi\\Unit' --verbose

run-functional-tests:
	echo "Run selected core functional tests"
	vendor/bin/phpunit docroot/ --filter='Drupal\\Tests\\user\\Functional\\UserLoginTest\
	|Drupal\\Tests\\node\\Functional\\NodeEditFormTest\
	|Drupal\\Tests\\taxonomy\\Functional\\TermTest' --verbose

