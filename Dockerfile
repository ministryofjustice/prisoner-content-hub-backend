FROM drupal:8.9.2-apache

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

# Install dependencies
RUN composer install \
  --ignore-platform-reqs \
  --no-ansi \
  --no-dev \
  --no-autoloader \
  --no-interaction \
  --no-scripts \
  --prefer-dist

# Copy Project
COPY modules/custom modules/custom
COPY sites/ sites/

# Remove write persmissions for added security
RUN chmod u-w sites/default/settings.php \
  && chmod u-w sites/default/services.yml

COPY ./apache/ /etc/apache2/

# Update autoloads
RUN composer dump-autoload --optimize

# Remove composer cache
RUN composer clear-cache

# Update permisions
RUN chown -R www-data:www-data /var/www/html/

USER www-data
