###########################################################################################
# Copy Dockerhub Drupal image
# https://github.com/docker-library/drupal/blob/master/8.9/php7.4/apache-buster/Dockerfile
#
# We copy over the first part of the Drupal dockerhub image.  We don't want the steps
# that come after this (e.g. composer create-project).
###########################################################################################
FROM php:8.1.11-apache-buster AS base

# install the PHP extensions we need
RUN set -eux; \
	\
	if command -v a2enmod; then \
		a2enmod rewrite; \
	fi; \
	\
	savedAptMark="$(apt-mark showmanual)"; \
	\
	apt-get update; \
	apt-get install -y --no-install-recommends \
		libfreetype6-dev \
		libjpeg-dev \
		libpng-dev \
		libpq-dev \
		libzip-dev \
	; \
	\
	docker-php-ext-configure gd \
		--with-freetype \
		--with-jpeg=/usr \
	; \
	\
	docker-php-ext-install -j "$(nproc)" \
		gd \
		opcache \
		pdo_mysql \
		pdo_pgsql \
		zip \
	; \
	\
# reset apt-mark's "manual" list so that "purge --auto-remove" will remove all build dependencies
	apt-mark auto '.*' > /dev/null; \
	apt-mark manual $savedAptMark; \
	ldd "$(php -r 'echo ini_get("extension_dir");')"/*.so \
		| awk '/=>/ { print $3 }' \
		| sort -u \
		| xargs -r dpkg-query -S \
		| cut -d: -f1 \
		| sort -u \
		| xargs -rt apt-mark manual; \
	\
	apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
	rm -rf /var/lib/apt/lists/*

# set recommended PHP.ini settings
# see https://secure.php.net/manual/en/opcache.installation.php
RUN { \
		echo 'opcache.memory_consumption=128'; \
		echo 'opcache.interned_strings_buffer=8'; \
		echo 'opcache.max_accelerated_files=4000'; \
		echo 'opcache.revalidate_freq=60'; \
		echo 'opcache.fast_shutdown=1'; \
	} > /usr/local/etc/php/conf.d/opcache-recommended.ini
###########################################################################################
# Finish copy Dockerhub Drupal image
###########################################################################################
###########################################################################################
# Custom build steps
###########################################################################################
RUN apt-get update && apt-get install -y \
  curl \
  git-core \
  mediainfo \
  unzip \
  && rm -rf /var/lib/apt/lists/*

RUN pecl install uploadprogress \
    && docker-php-ext-enable uploadprogress

RUN pecl install redis \
  && docker-php-ext-enable redis

# Enable apache modules that are used in Drupal's htaccess.
RUN a2enmod expires headers

# Set Timezone
RUN echo "date.timezone = Europe/London" > /usr/local/etc/php/conf.d/timezone_set.ini
# Set no memory limit (for PHP running as cli only).
RUN echo 'memory_limit = -1' >> /usr/local/etc/php/php-cli.ini

###########################################################################################
# Install composer as non-root and copy repository files
###########################################################################################

RUN chown -R www-data:www-data /var/www
# Set to www-data user.
# Have to use uid instead username, as otherwise we are forced to use a securityContext
# See https://stackoverflow.com/a/66367783
USER 33
WORKDIR /var/www/html

RUN mkdir -p /var/www/.local/bin
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
  && php composer-setup.php --install-dir=$HOME/.local/bin --filename=composer --version=2.4.2 \
  && php -r "unlink('composer-setup.php');"

# Add the composer bin directory to the path.
ENV PATH="/var/www/html/vendor/bin:/var/www/.local/bin:$PATH"

# Copy Project files.
# Copy with chown as otherwise files are owned by root.
COPY --chown=www-data:www-data composer.json composer.lock Makefile ./
COPY --chown=www-data:www-data patches patches
COPY --chown=www-data:www-data docroot docroot
COPY --chown=www-data:www-data config config

COPY ./apache/ /etc/apache2/

###########################################################################################
# Create test image
###########################################################################################
FROM base AS test
USER root
# Install mysql cli client, required for running certain drush commands.
RUN apt-get update && apt-get install -y \
  mariadb-client

COPY phpunit.xml phpunit.xml

# Install vim/vi for easier debugging (e.g. from circleci).
RUN apt-get install -y vim

# Set to www-data user.
USER 33

RUN mkdir -p ~/phpunit/browser_output

## Install dependencies (with dev)
RUN composer install \
  --no-ansi \
  --no-interaction \
  --prefer-dist

FROM test as local

COPY scripts/ scripts/

# Install kubectl, required to run `make sync`.
RUN curl -LO "https://dl.k8s.io/release/v1.25.3/bin/linux/amd64/kubectl" \
    && chmod +x kubectl \
    && mkdir -p ~/.local/bin \
    && mv ./kubectl ~/.local/bin/kubectl

# Install aws cli, required to run `make sync`.
RUN curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64-2.8.8.zip" -o "awscliv2.zip" \
    && unzip awscliv2.zip \
    && ./aws/install -i ~/.local/aws-cli -b ~/.local/bin

USER root
RUN pecl install xdebug-3.1.5 \
  && docker-php-ext-enable xdebug

RUN echo 'opcache.enable=0' > /usr/local/etc/php/conf.d/opcache-disable.ini

# Set to www-data user.
USER 33
###########################################################################################
# Create optimised build
#
# This stage should be used in production.
# It has been purposely set as the last stage of this file, so that it becomes the default
# when no build stage has been specified.
###########################################################################################
FROM base as optimised-build

## Install dependencies (without dev)
RUN composer install \
  --no-ansi \
  --no-dev \
  --no-interaction \
  --prefer-dist
