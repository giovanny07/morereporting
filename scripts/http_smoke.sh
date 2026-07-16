#!/usr/bin/env bash
#
# End-to-end smoke test: logs into a real Zabbix frontend and hits every
# morereporting action, asserting HTTP 200 and no PHP Fatal/Warning markers
# in the response body. Run after every change that touches actions/views,
# not just after "php -l" (syntax-valid code can still fail at runtime, e.g.
# an autoloader path issue or a framework escaping edge case).
#
# Usage: ZABBIX_URL=http://localhost/zabbix ZABBIX_USER=Admin ZABBIX_PASSWORD=zabbix ./scripts/http_smoke.sh

set -euo pipefail

ZABBIX_URL="${ZABBIX_URL:-http://localhost/zabbix}"
ZABBIX_USER="${ZABBIX_USER:-Admin}"
ZABBIX_PASSWORD="${ZABBIX_PASSWORD:-zabbix}"

COOKIE_JAR="$(mktemp)"
trap 'rm -f "$COOKIE_JAR"' EXIT

fail_count=0

log_in() {
	curl -s -c "$COOKIE_JAR" "$ZABBIX_URL/index.php" -o /dev/null

	local status
	status=$(curl -s -o /dev/null -w '%{http_code}' \
		-b "$COOKIE_JAR" -c "$COOKIE_JAR" \
		--data-urlencode "name=$ZABBIX_USER" \
		--data-urlencode "password=$ZABBIX_PASSWORD" \
		--data-urlencode "autologin=1" \
		--data-urlencode "enter=Sign in" \
		"$ZABBIX_URL/index.php")

	if [[ "$status" != "302" ]]; then
		echo "FAIL login: expected 302, got $status"
		exit 1
	fi

	echo "OK login"
}

check_action() {
	local action="$1"
	local body
	body="$(mktemp)"

	local status
	status=$(curl -s -o "$body" -w '%{http_code}' -b "$COOKIE_JAR" \
		"$ZABBIX_URL/zabbix.php?action=$action")

	if [[ "$status" != "200" ]]; then
		echo "FAIL $action: HTTP $status"
		fail_count=$((fail_count + 1))
		rm -f "$body"
		return
	fi

	if grep -qE "Fatal error|Uncaught|PHP Warning|PHP Notice|PHP Deprecated" "$body"; then
		echo "FAIL $action: PHP error/warning found in response"
		grep -oE "Fatal error[^\\\\]*|Uncaught[^\\\\]*" "$body" | head -1
		fail_count=$((fail_count + 1))
		rm -f "$body"
		return
	fi

	echo "OK $action (HTTP $status)"
	rm -f "$body"
}

log_in
check_action "morereporting.list"
check_action "morereporting.percentiles"
check_action "morereporting.availability"

if [[ "$fail_count" -gt 0 ]]; then
	echo "$fail_count check(s) failed"
	exit 1
fi

echo "All checks passed"
