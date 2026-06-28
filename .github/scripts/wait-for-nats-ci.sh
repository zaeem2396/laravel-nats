#!/usr/bin/env bash
# Wait until all NATS CI ports accept TCP connections.
set -euo pipefail

readonly MAX_ATTEMPTS="${MAX_ATTEMPTS:-120}"
readonly PORTS=(4222 4223 4224)

echo "Waiting for NATS servers on ports: ${PORTS[*]}..."
sleep 3

for ((attempt = 1; attempt <= MAX_ATTEMPTS; attempt++)); do
  ready=true

  for port in "${PORTS[@]}"; do
    if ! nc -z localhost "${port}" 2>/dev/null; then
      ready=false
      break
    fi
  done

  if [ "${ready}" = true ]; then
    echo "All NATS servers are ready"
    exit 0
  fi

  if ((attempt % 10 == 0)); then
    echo "Attempt ${attempt}/${MAX_ATTEMPTS}: still waiting..."
    docker ps -a | grep -E 'nats|laravel-nats' || true
  fi

  sleep 1
done

echo "NATS servers failed to become ready after ${MAX_ATTEMPTS} seconds"
docker ps -a | grep -E 'nats|laravel-nats' || true
netstat -tuln 2>/dev/null | grep -E '4222|4223|4224' || ss -tuln | grep -E '4222|4223|4224' || true
exit 1
