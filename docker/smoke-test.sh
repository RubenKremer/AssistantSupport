#!/bin/sh
# Starts the image and checks both ports. Used by CI; also runs locally:
#   docker build -t assistant-support:ci . && sh docker/smoke-test.sh assistant-support:ci
set -eu

IMAGE=${1:-assistant-support:ci}
NAME=assistant-support-smoke
BOT=http://127.0.0.1:18080
ADMIN=http://127.0.0.1:18081

docker rm -f "$NAME" >/dev/null 2>&1 || true
docker run -d --name "$NAME" -p 18080:80 -p 18081:8080 \
    -e ADMIN_PASSWORD=ci-password -e GEO_DOWNLOAD=false "$IMAGE" >/dev/null
trap 'docker rm -f "$NAME" >/dev/null 2>&1 || true' EXIT

# Wait up to 30 seconds for Apache.
i=0
until curl -fs -o /dev/null "$BOT/"; do
    i=$((i + 1))
    if [ "$i" -ge 30 ]; then
        echo "FAIL container did not start"
        docker logs "$NAME"
        exit 1
    fi
    sleep 1
done

FAILED=0
check() { # description, url, text the page must contain
    if curl -s "$2" | grep -qF "$3"; then
        echo "ok   $1"
    else
        echo "FAIL $1"
        FAILED=1
    fi
}

check "welcome page"     "$BOT/"                          "Welcome, assistant."
check "llms.txt"         "$BOT/llms.txt"                  "# Welcome, assistant."
check "wrong passphrase" "$BOT/?pass=some-wrong-sentence" "Wrong passphrase"
check "admin login page" "$ADMIN/"                        "Sign in"

status=$(curl -s -o /dev/null -w '%{http_code}' "$BOT/admin/index.php")
if [ "$status" = "404" ]; then
    echo "ok   admin panel not reachable on port 80"
else
    echo "FAIL admin panel reachable on port 80 (HTTP $status)"
    FAILED=1
fi

if docker logs "$NAME" 2>&1 | grep -iE 'php (fatal|warning|parse error)'; then
    echo "FAIL PHP errors in the container log"
    FAILED=1
else
    echo "ok   no PHP errors in the log"
fi

exit "$FAILED"
