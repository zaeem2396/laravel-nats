#!/usr/bin/env bash
# Start NATS servers for GitHub Actions with resilient image pulls.
set -euo pipefail

readonly NATS_IMAGE="${NATS_IMAGE:-nats:2.10}"
readonly MAX_PULL_ATTEMPTS="${MAX_PULL_ATTEMPTS:-5}"

pull_nats_image() {
  local attempt=1
  local wait_seconds=5

  while [ "${attempt}" -le "${MAX_PULL_ATTEMPTS}" ]; do
    echo "Pulling ${NATS_IMAGE} (attempt ${attempt}/${MAX_PULL_ATTEMPTS})..."

    if docker pull "${NATS_IMAGE}"; then
      echo "Image ${NATS_IMAGE} is available locally"
      return 0
    fi

    echo "Pull failed; retrying in ${wait_seconds}s..."
    sleep "${wait_seconds}"
    attempt=$((attempt + 1))
    wait_seconds=$((wait_seconds + 5))
  done

  echo "Unable to pull ${NATS_IMAGE} after ${MAX_PULL_ATTEMPTS} attempts"
  return 1
}

start_containers() {
  if [ -f docker-compose.yml ]; then
    echo "Starting NATS via docker compose..."
    docker compose up -d
    return 0
  fi

  echo "docker-compose.yml not found; starting containers directly..."
  docker run -d --name nats -p 4222:4222 -p 8222:8222 "${NATS_IMAGE}" --jetstream
  docker run -d --name nats-secured -p 4223:4222 -p 8223:8222 "${NATS_IMAGE}" --jetstream --user testuser --pass testpass
  docker run -d --name nats-token -p 4224:4222 -p 8224:8222 "${NATS_IMAGE}" --jetstream --auth secret-token-12345
}

pull_nats_image
start_containers

echo "NATS containers started"
