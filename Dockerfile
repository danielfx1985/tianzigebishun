FROM php:8.2-apache

WORKDIR /var/www/html

COPY . .

RUN apt-get update && apt-get install -y unzip \
    && rm -rf /var/lib/apt/lists/* \
    && if [ -f bishun_data.zip ]; then unzip -o bishun_data.zip -d . && rm bishun_data.zip; fi

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
