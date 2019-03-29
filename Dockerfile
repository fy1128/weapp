FROM wodby/php:7.3

WORKDIR /var/www/html
RUN set -ex; \
    \
    composer create-project slim/slim-skeleton /var/www/html; \
    composer require overtrue/wechat:~4.1; \
    \
    composer clear-cache; \
    \
    rm -rf logs; \
    files_link logs;

COPY src/* public/

#ENTRYPOINT ["composer", "start"]`
