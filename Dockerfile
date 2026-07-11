# OmniFactuur / InvoiceShelf Insider 3.0.0-alpha.1
FROM invoiceshelf/invoiceshelf:3.0.0-alpha.1

USER root
WORKDIR /var/www/html

COPY --chown=www-data:www-data . /var/www/html

RUN rm -f /var/www/html/.env     && chmod +x /var/www/html/artisan     && mkdir -p         /var/www/html/storage/framework/cache         /var/www/html/storage/framework/sessions         /var/www/html/storage/framework/views         /var/www/html/storage/logs         /var/www/html/bootstrap/cache         /var/www/html/Modules     && chown -R www-data:www-data         /var/www/html/storage         /var/www/html/bootstrap/cache         /var/www/html/Modules     && chmod -R ug+rwX         /var/www/html/storage         /var/www/html/bootstrap/cache         /var/www/html/Modules

USER www-data
