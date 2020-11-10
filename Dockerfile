FROM drupal:8.9.6-apache AS base

# Install Composer and it's dependencies
RUN apt-get update && apt-get install -y \
  curl \
  git-core \
  mediainfo \
  unzip \
  && rm -rf /var/lib/apt/lists/*
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php composer-setup.php --install-dir=/bin --filename=composer --version=1.10.16
RUN php -r "unlink('composer-setup.php');"

# Set Timezone
RUN echo "date.timezone = Europe/London" > /usr/local/etc/php/conf.d/timezone_set.ini

###########################################################################################
# Run test suite
###########################################################################################

FROM base AS test

# Remove the memory limit for the CLI only.
RUN echo 'memory_limit = -1' > /usr/local/etc/php/php-cli.ini

# Remove the vanilla Drupal ready to install a dev version
RUN rm -rf ..?* .[!.]* *

# Install Drupal 8.x Dev
RUN composer create-project drupal-composer/drupal-project:8.x-dev . --stability dev --no-interaction

# Update autoloads
RUN composer dump-autoload --optimize

# Install custom modules and run PHPUnit
WORKDIR /opt/drupal/web

COPY phpunit.xml core/phpunit.xml
COPY modules/custom modules/custom

RUN ../vendor/bin/phpunit -c core --testsuite unit --debug --verbose

###########################################################################################
# Create runtime image
###########################################################################################

FROM base

WORKDIR /opt/drupal/web

# Copy in Composer configuration
COPY composer.json composer.lock /opt/drupal/web/
# Copy in patches we want to apply to modules in Drupal using Composer
COPY patches/ patches/

# Install dependencies
RUN composer install \
  --ignore-platform-reqs \
  --no-ansi \
  --no-dev \
  --no-autoloader \
  --no-interaction \
  --prefer-dist

# Copy Project
COPY --from=test /opt/drupal/web/modules/custom modules/custom
COPY sites/ sites/

# Remove write permissions for added security
RUN chmod u-w sites/default/settings.php \
  && chmod u-w sites/default/services.yml

COPY ./apache/ /etc/apache2/

# Update autoloads
RUN composer dump-autoload --optimize

# Remove composer cache
RUN composer clear-cache

# Update permisions
RUN chown -R www-data:www-data /opt/drupal/web/

USER www-data
