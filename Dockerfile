FROM drupal:8.9.2-apache AS base

# Install Composer and it's dependencies
RUN apt-get update && apt-get install -y \
  curl \
  git-core \
  mediainfo \
  unzip \
  && rm -rf /var/lib/apt/lists/*

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php composer-setup.php --install-dir=/bin --filename=composer
RUN php -r "unlink('composer-setup.php');"

# Set Timezone
RUN echo "date.timezone = Europe/London" > /usr/local/etc/php/conf.d/timezone_set.ini

COPY composer.json composer.lock /var/www/html/

# Copy in patches we want to apply to modules in Drupal using Composer
COPY patches/ patches/

# Copy Project
COPY modules/custom modules/custom

# Install dependencies
RUN composer install \
  --ignore-platform-reqs \
  --no-ansi \
  --no-dev \
  --optimize-autoloader \
  --no-interaction \
  --prefer-dist \
  && composer clear-cache

COPY sites/ sites/

COPY ./apache/ /etc/apache2/

#
# Run our tests in a separate build which doesn't mind being polluted with
# test dependencies and output
#
FROM base AS test

# Install test dependencies
RUN composer install \
  --ignore-platform-reqs \
  --no-ansi \
  --no-interaction \
  --prefer-dist

# TODO run the tests...

#
# Build our clean image.
# This is handy for installing modules and running tests
#
FROM base AS production_base



#
# Finally lock-down the production image
#
FROM production_base AS production
# Remove write permissions for added security
RUN chmod u-w sites/default/settings.php \
  && chmod u-w sites/default/services.yml
RUN chown -R www-data:www-data /var/www/html/

USER www-data
