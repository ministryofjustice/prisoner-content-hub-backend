FROM drupal:8.7.8-apache

# Install Composer and it's dependencies
RUN apt-get update && apt-get install curl -y && apt-get install git-core unzip -y

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php composer-setup.php --install-dir=/bin --filename=composer
RUN php -r "unlink('composer-setup.php');"

# Set Timezone
RUN echo "date.timezone = Europe/London" > /usr/local/etc/php/conf.d/timezone_set.ini

COPY composer.json composer.lock /var/www/html/

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

COPY ./apache/ /etc/apache2/

# Update autoloads
RUN composer dump-autoload --optimize

# Remove composer cache
RUN composer clear-cache

# Update permisions
RUN chown -R www-data:www-data /var/www/html/

USER www-data
