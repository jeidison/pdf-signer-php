FROM kooldev/php:8.2

RUN sed -i '/^default = default_sect/a legacy = legacy_sect' /etc/ssl/openssl.cnf
RUN sed -i '/^\[default_sect\]/a activate = 1' /etc/ssl/openssl.cnf
RUN printf "[legacy_sect]\nactivate = 1" >> /etc/ssl/openssl.cnf

CMD [ "php-fpm" ]