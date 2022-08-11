#!//usr/bin/env bash
cp docker/hooks/pre-commit .git/hooks/
php-fpm -F