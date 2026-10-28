#!/bin/sh
set -e

if [ ! -f .env ]; then
  echo "Belum ada .env, copy dari .env.example..."
  cp .env.example .env
fi

if ! grep -q "^APP_KEY=base64" .env 2>/dev/null; then
  echo "APP_KEY belum ada, generate..."
  php artisan key:generate --force
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force --isolated

  SEED_MARKER="storage/app/.seeded"
  if [ ! -f "$SEED_MARKER" ]; then
    echo "Belum pernah di-seed, jalanin seeder..."
    php artisan db:seed --force
    touch "$SEED_MARKER"
  fi
fi

exec "$@"