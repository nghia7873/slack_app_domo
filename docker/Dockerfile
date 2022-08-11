FROM php:7.4-fpm

RUN apt-get update && apt-get install -y \
        libpng-dev \
        zlib1g-dev \
        libxml2-dev \
        libzip-dev \
        libonig-dev \
        zip \
        curl \
        unzip \
    && docker-php-ext-configure gd \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-install mysqli \
    && docker-php-ext-install zip \
    && docker-php-ext-install mbstring \
    && docker-php-ext-install bcmath

RUN pecl install xdebug && docker-php-ext-enable xdebug

RUN groupadd -r nginx \
 && useradd -r -s /sbin/nologin -d /dev/null -g nginx nginx

RUN curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer && \
    chmod a+x /usr/local/bin/composer && \
    echo "alias composer='php /usr/local/bin/composer'" >> ~/.bashrc

RUN curl -LsS https://phar.phpunit.de/phpunit-7.phar -o /usr/local/bin/phpunit && \
    chmod a+x /usr/local/bin/phpunit && \
    echo "alias phpunit='php /usr/local/bin/phpunit'" >> ~/.bashrc && \
    ## PHP loc
    curl -LsS https://phar.phpunit.de/phploc.phar -o /usr/local/bin/phploc && \
    chmod a+x /usr/local/bin/phploc && \
    ## PHPCS
    curl -LsS https://squizlabs.github.io/PHP_CodeSniffer/phpcs.phar -o /usr/local/bin/phpcs && \
    chmod a+x /usr/local/bin/phpcs && \
    ## PHPMD
    curl -LsS https://phpmd.org/static/latest/phpmd.phar -o /usr/local/bin/phpmd && \
    chmod a+x /usr/local/bin/phpmd && \
    ## PHPCPD
    curl -LsS https://phar.phpunit.de/phpcpd.phar -o /usr/local/bin/phpcpd && \
    chmod a+x /usr/local/bin/phpcpd

SHELL ["/bin/bash", "-c", "source ~/.bashrc"]

EXPOSE 9000
ADD ./entrypoint.sh /
ENTRYPOINT ["sh", "/entrypoint.sh"]
