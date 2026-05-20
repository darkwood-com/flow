# syntax=docker/dockerfile:1.6.0

#FROM php:8.3-fpm-alpine as php
FROM php:8.3-zts-alpine as php

RUN apk add --update make curl

COPY --from=composer:2.6.2 /usr/bin/composer /usr/bin/composer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN curl "https://github.com/jolicode/castor/releases/latest/download/castor.linux-amd64.phar" -Lso /usr/local/bin/castor && chmod u+x /usr/local/bin/castor

RUN set -eux; \
    install-php-extensions \
		apcu \
        ast \
		intl \
		opcache \
        openswoole \
        pcntl \
        pcov \
		zip \
        parallel \
    ;

WORKDIR /flow

#CMD ["php-fpm"]
CMD ["tail", "-f", "/dev/null"]
