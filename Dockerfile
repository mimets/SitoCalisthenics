FROM php:8.3-cli

WORKDIR /var/www/html

COPY . /var/www/html

RUN mkdir -p /var/www/html/messaggi

EXPOSE 10000

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} -t /var/www/html"]
