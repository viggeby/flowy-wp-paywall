FROM wordpress:latest

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

RUN apt-get update && \
	apt-get install -y  --no-install-recommends ssl-cert && \
	rm -r /var/lib/apt/lists/* && \
	a2enmod ssl && \
	a2ensite default-ssl

EXPOSE 80
EXPOSE 443