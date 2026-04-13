#!/usr/bin/env sh
# Carregue com: . docker/prompt-public-url.sh (a partir da raiz do repositório)
# Define GETFY_WEBHOOK_PUBLIC_URL e GETFY_APP_URL (se ainda vazio) para o Docker/stack.env.
# Sobrescreva exportando GETFY_WEBHOOK_PUBLIC_URL antes do source para pular o prompt.

GETFY_DEFAULT_PUBLIC_URL="${GETFY_DEFAULT_PUBLIC_URL:-http://getfy-gateway.test}"

if [ -z "${GETFY_WEBHOOK_PUBLIC_URL:-}" ]; then
  if [ -t 0 ] 2>/dev/null; then
    echo ""
    echo "URL pública do gateway (APP_URL e GETFY_WEBHOOK_PUBLIC_URL — postbacks dos adquirentes)."
    printf 'Digite a URL [%s]: ' "$GETFY_DEFAULT_PUBLIC_URL"
    read -r GETFY_PUBLIC_URL_READ || GETFY_PUBLIC_URL_READ=""
    GETFY_WEBHOOK_PUBLIC_URL="${GETFY_PUBLIC_URL_READ:-$GETFY_DEFAULT_PUBLIC_URL}"
  else
    GETFY_WEBHOOK_PUBLIC_URL="$GETFY_DEFAULT_PUBLIC_URL"
  fi
  export GETFY_WEBHOOK_PUBLIC_URL
fi

if [ -z "${GETFY_APP_URL:-}" ]; then
  export GETFY_APP_URL="$GETFY_WEBHOOK_PUBLIC_URL"
fi
