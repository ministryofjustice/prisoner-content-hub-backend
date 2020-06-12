build-deps:
	composer clear-cache
	composer install --no-dev --no-ansi --no-scripts --prefer-dist --ignore-platform-reqs --no-interaction --no-autoloader

build:
	docker build -t digital-hub-be .

clean:
	rm -rf modules/contrib

push:
	@docker login -u $(DOCKER_USERNAME) -p $(DOCKER_PASSWORD)
	docker tag digital-hub-be mojdigitalstudio/digital-hub-be:build-$(CIRCLE_BUILD_NUM)
	docker tag digital-hub-be mojdigitalstudio/digital-hub-be:latest
	docker push mojdigitalstudio/digital-hub-be:build-$(CIRCLE_BUILD_NUM)
	docker push mojdigitalstudio/digital-hub-be:latest
