FROM drupal:8.9.6-apache AS base

# Install Composer and it's dependencies
RUN apt-get update && apt-get install -y \
  curl \
  git-core \
  mediainfo \
  unzip \
  && rm -rf /var/lib/apt/lists/*

RUN pecl install uploadprogress \
    && docker-php-ext-enable uploadprogress

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
  && php composer-setup.php --install-dir=/bin --filename=composer --version=1.10.16 \
  && php -r "unlink('composer-setup.php');"

# Set Timezone
RUN echo "date.timezone = Europe/London" > /usr/local/etc/php/conf.d/timezone_set.ini

###########################################################################################
# Create runtime image
###########################################################################################

WORKDIR /opt/drupal/web

# Copy in Composer configuration
COPY composer.json composer.lock ./
# Copy in patches we want to apply to modules in Drupal using Composer
COPY patches/ patches/

# Copy Project
COPY docroot/modules/custom web/docroot/modules/custom
COPY ./apache/ /etc/apache2/
COPY docroot/sites/ docroot/sites/
COPY config/ config/

# Remove write permissions for added security
RUN chmod u-w docroot/sites/default/settings.php \
  && chmod u-w docroot/sites/default/services.yml

###########################################################################################
# Create test image
###########################################################################################

FROM base AS test

# Remove the memory limit for the CLI only.
RUN echo 'memory_limit = -1' > /usr/local/etc/php/php-cli.ini

# Install dependencies (with dev)
RUN composer install \
  --ignore-platform-reqs \
  --no-ansi \
  --dev \
  --no-autoloader \
  --no-interaction \
  --prefer-dist && \
  composer dump-autoload --optimize && \
  composer clear-cache

###########################################################################################
# Create build
###########################################################################################
FROM base as build

# Install dependencies
RUN composer install \
  --ignore-platform-reqs \
  --no-ansi \
  --no-dev \
  --no-autoloader \
  --no-interaction \
  --prefer-dist && \
  composer dump-autoload --optimize && \
  composer clear-cache

# Change ownership of files
RUN chown -R www-data:www-data ./

USER www-data

FROM build as local
USER root
RUN pecl install xdebug-2.9.8 \
  && docker-php-ext-enable xdebug
USER www-data

# Make build (and not local) the default target.
# By ensuring this is the last defined target in the file.
FROM build as production
