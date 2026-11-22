#!/usr/bin/env bash
# One-command backend setup. Requires PHP 8.2+, Composer, MySQL (database "campus_events" must exist).
set -e
cd "$(dirname "$0")"
if [ ! -d backend ]; then composer create-project laravel/laravel backend; fi
cd backend
composer require laravel/sanctum --no-interaction || true
php artisan install:api --no-interaction || true
cp -R ../backend-overlay/. .            # our migrations, models, controllers, routes, tests
sed -i '/^#\? \?DB_/d' .env
cat >> .env <<'ENV'
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=campus_events
DB_USERNAME=root
DB_PASSWORD=
ENV
php artisan migrate:fresh --seed --force
echo "Backend ready. Run:  cd backend && php artisan serve"
echo "Tests:               cd backend && php artisan test"
echo "Frontend:            cd frontend && npm install && npm run dev"
