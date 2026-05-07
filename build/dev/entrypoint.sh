#!/bin/sh
set -e

cd /app/src

# Spustit Vite dev server na pozadí (pokud existuje node_modules)
if [ -d "node_modules" ]; then
    echo "Starting Vite dev server..."
    npm run dev &
fi

# Spustit FrankenPHP
echo "Starting FrankenPHP..."
exec frankenphp run --config /etc/caddy/Caddyfile
