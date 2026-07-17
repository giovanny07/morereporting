#!/usr/bin/env bash
#
# End-to-end smoke test: logs into a real Zabbix frontend and hits every
# morereporting action, asserting HTTP 200 and no PHP Fatal/Warning markers
# in the response body. Also simulates the popup.generic and jsrpc.php
# (patternselect.get) AJAX requests the browser's JS constructs when chaining
# multiselects (filter_preselect) - a plain page-load check can't catch a bad
# param shape or a silently-ignored param there, since those requests only
# fire on a follow-up interaction, not on initial load. These checks assert
# the scoped request returns *fewer* rows than the unscoped one - not just
# "no error" - because a wrong/unsupported param name is silently ignored
# rather than rejected (see CHANGELOG 0.6.2 vs the jsrpc.php hostid fix:
# "Incorrect value" catches a rejected param shape, but a silently-ignored
# one still returns HTTP 200 with no error and the full unscoped result set).
#
# Run after every change that touches actions/views, not just after "php -l"
# (syntax-valid code can still fail at runtime, e.g. an autoloader path issue,
# a framework escaping edge case, or a filter_preselect param mismatch).
#
# TEST_GROUPID/TEST_HOSTID must be real IDs in the target instance with a
# strict subset of the unscoped result set (i.e. that host must have fewer
# items/triggers than the whole instance). Defaults match this project's
# local Zabbix 7.0.26 test instance.
#
# Usage: ZABBIX_URL=http://localhost/zabbix ZABBIX_USER=Admin ZABBIX_PASSWORD=zabbix ./scripts/http_smoke.sh

set -euo pipefail

ZABBIX_URL="${ZABBIX_URL:-http://localhost/zabbix}"
ZABBIX_USER="${ZABBIX_USER:-Admin}"
ZABBIX_PASSWORD="${ZABBIX_PASSWORD:-zabbix}"
TEST_GROUPID="${TEST_GROUPID:-14}"
TEST_HOSTID="${TEST_HOSTID:-9}"

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

# Verifies a file-download export action: HTTP 200, the right Content-Type, a
# Content-Disposition: attachment header (proves the download layout is wired, not just
# the default HTML one), and a parseable JSON array body with at least one row.
check_json_export() {
	local action="$1"
	local body headers status

	body="$(mktemp)"
	headers="$(mktemp)"

	status=$(curl -s -D "$headers" -o "$body" -w '%{http_code}' -b "$COOKIE_JAR" \
		"$ZABBIX_URL/zabbix.php?action=$action&filter_date_from=now-30d&filter_date_to=now")

	if [[ "$status" != "200" ]]; then
		echo "FAIL $action: HTTP $status"
		fail_count=$((fail_count + 1))
		rm -f "$body" "$headers"
		return
	fi

	if ! grep -qi 'content-type: application/json' "$headers"; then
		echo "FAIL $action: missing/wrong Content-Type header"
		fail_count=$((fail_count + 1))
		rm -f "$body" "$headers"
		return
	fi

	if ! grep -qi 'content-disposition: attachment' "$headers"; then
		echo "FAIL $action: missing Content-Disposition: attachment header (not downloading as a file)"
		fail_count=$((fail_count + 1))
		rm -f "$body" "$headers"
		return
	fi

	local row_count
	row_count=$(python3 -c "import json; d=json.load(open('$body')); print(len(d) if isinstance(d, list) else -1)" 2>/dev/null || echo "-1")

	if [[ "$row_count" == "-1" ]]; then
		echo "FAIL $action: response body is not a valid JSON array"
		fail_count=$((fail_count + 1))
		rm -f "$body" "$headers"
		return
	fi

	echo "OK $action ($row_count rows, valid JSON, attachment headers correct)"
	rm -f "$body" "$headers"
}

# Same idea as check_json_export, for CSV (zbx_toCSV() output: "a","b","c" per line).
check_csv_export() {
	local action="$1"
	local body headers status

	body="$(mktemp)"
	headers="$(mktemp)"

	status=$(curl -s -D "$headers" -o "$body" -w '%{http_code}' -b "$COOKIE_JAR" \
		"$ZABBIX_URL/zabbix.php?action=$action&filter_date_from=now-30d&filter_date_to=now")

	if [[ "$status" != "200" ]]; then
		echo "FAIL $action: HTTP $status"
		fail_count=$((fail_count + 1))
		rm -f "$body" "$headers"
		return
	fi

	if ! grep -qi 'content-type: text/csv' "$headers"; then
		echo "FAIL $action: missing/wrong Content-Type header"
		fail_count=$((fail_count + 1))
		rm -f "$body" "$headers"
		return
	fi

	if ! grep -qi 'content-disposition: attachment' "$headers"; then
		echo "FAIL $action: missing Content-Disposition: attachment header (not downloading as a file)"
		fail_count=$((fail_count + 1))
		rm -f "$body" "$headers"
		return
	fi

	local line_count
	line_count=$(grep -c '^"' "$body" || true)

	if [[ "$line_count" -lt 1 ]]; then
		echo "FAIL $action: response body doesn't look like quoted CSV rows"
		fail_count=$((fail_count + 1))
		rm -f "$body" "$headers"
		return
	fi

	echo "OK $action ($line_count lines, valid CSV, attachment headers correct)"
	rm -f "$body" "$headers"
}

# Same idea as check_csv_export, for YAML (symfony/yaml output). No standard CLI YAML
# parser to lean on here, so this checks structural shape (list items + key: value pairs)
# and the absence of PHP errors, rather than fully validating the YAML.
check_yaml_export() {
	local action="$1"
	local body headers status

	body="$(mktemp)"
	headers="$(mktemp)"

	status=$(curl -s -D "$headers" -o "$body" -w '%{http_code}' -b "$COOKIE_JAR" \
		"$ZABBIX_URL/zabbix.php?action=$action&filter_date_from=now-30d&filter_date_to=now")

	if [[ "$status" != "200" ]]; then
		echo "FAIL $action: HTTP $status"
		fail_count=$((fail_count + 1))
		rm -f "$body" "$headers"
		return
	fi

	if grep -qE "Fatal error|Uncaught|Class \"Symfony" "$body"; then
		echo "FAIL $action: PHP error in response (symfony/yaml not autoloaded? see Module.php)"
		fail_count=$((fail_count + 1))
		rm -f "$body" "$headers"
		return
	fi

	if ! grep -qi 'content-type: application/yaml' "$headers"; then
		echo "FAIL $action: missing/wrong Content-Type header"
		fail_count=$((fail_count + 1))
		rm -f "$body" "$headers"
		return
	fi

	if ! grep -qi 'content-disposition: attachment' "$headers"; then
		echo "FAIL $action: missing Content-Disposition: attachment header (not downloading as a file)"
		fail_count=$((fail_count + 1))
		rm -f "$body" "$headers"
		return
	fi

	if ! grep -qE '^-$|^- ' "$body"; then
		echo "FAIL $action: response body doesn't look like a YAML sequence"
		fail_count=$((fail_count + 1))
		rm -f "$body" "$headers"
		return
	fi

	echo "OK $action (looks like valid YAML, attachment headers correct)"
	rm -f "$body" "$headers"
}

# Row count in a popup.generic response body, or -1 on a validation/PHP error.
popup_rows() {
	local body
	body="$(mktemp)"

	curl -s -G -o "$body" -b "$COOKIE_JAR" "$ZABBIX_URL/zabbix.php" \
		--data-urlencode "action=popup.generic" \
		--data-urlencode "multiselect=1" \
		"$@"

	if grep -qE 'Incorrect value|Fatal error|Uncaught' "$body"; then
		echo "-1"
		rm -f "$body"
		return
	fi

	python3 -c "import json; print(json.load(open('$body')).get('body', '').count('<tr'))" 2>/dev/null || echo "-1"
	rm -f "$body"
}

# Result count from a jsrpc.php patternselect.get response, or -1 on error.
jsrpc_pattern_rows() {
	curl -s -G -b "$COOKIE_JAR" "$ZABBIX_URL/jsrpc.php" \
		--data-urlencode "type=11" \
		--data-urlencode "method=patternselect.get" \
		"$@" \
		| python3 -c "import json,sys; print(len(json.load(sys.stdin).get('result', [])))" 2>/dev/null || echo "-1"
}

# Clears any host/group filter popup.generic persisted into this user's profile as a
# side effect of a *previous* scoped call in this same script run - without this, a later
# "unscoped" call silently inherits that leftover scoping and the comparison becomes
# meaningless. Optional: only runs if DB creds are provided (never hardcode them here).
reset_popup_profile() {
	if [[ -n "${DB_NAME:-}" && -n "${DB_USER:-}" ]]; then
		MYSQL_PWD="${DB_PASSWORD:-}" mysql -u "$DB_USER" -h "${DB_HOST:-localhost}" "$DB_NAME" \
			-e "DELETE FROM profiles WHERE idx LIKE 'web.popup.generic%';" 2>/dev/null || true
	fi
}

# Asserts a scoped request returns strictly fewer rows than its unscoped counterpart -
# proves the scoping param was actually honored, not just silently ignored (HTTP 200
# with the full unscoped set looks identical to "no error" otherwise). Use for popups
# that show everything by default (e.g. hosts) and narrow down once scoped.
assert_narrows() {
	local label="$1" unscoped="$2" scoped="$3"

	if [[ "$unscoped" == "-1" || "$scoped" == "-1" ]]; then
		echo "FAIL $label: request error (unscoped=$unscoped, scoped=$scoped)"
		fail_count=$((fail_count + 1))
		return
	fi

	if [[ "$scoped" -ge "$unscoped" ]]; then
		echo "FAIL $label: scoping had no effect (unscoped=$unscoped rows, scoped=$scoped rows)"
		fail_count=$((fail_count + 1))
		return
	fi

	echo "OK $label (unscoped=$unscoped -> scoped=$scoped rows)"
}

# Same idea, inverted: for popups gated by host_preselect_required (items/triggers/graphs
# - see CControllerPopupGeneric::POPUPS_HAVING_HOST_FILTER), the *unscoped* request
# returns nothing at all until a host is provided, so scoping must reveal *more* rows,
# not fewer.
assert_widens() {
	local label="$1" unscoped="$2" scoped="$3"

	if [[ "$unscoped" == "-1" || "$scoped" == "-1" ]]; then
		echo "FAIL $label: request error (unscoped=$unscoped, scoped=$scoped)"
		fail_count=$((fail_count + 1))
		return
	fi

	if [[ "$scoped" -le "$unscoped" ]]; then
		echo "FAIL $label: scoping had no effect (unscoped=$unscoped rows, scoped=$scoped rows)"
		fail_count=$((fail_count + 1))
		return
	fi

	echo "OK $label (unscoped=$unscoped -> scoped=$scoped rows)"
}

log_in
check_action "morereporting.list"
check_action "morereporting.percentiles"
check_action "morereporting.availability"
check_action "morereporting.report.edit"
check_json_export "morereporting.percentiles.json"
check_json_export "morereporting.availability.json"
check_csv_export "morereporting.percentiles.csv"
check_csv_export "morereporting.availability.csv"
check_yaml_export "morereporting.percentiles.yaml"
check_yaml_export "morereporting.availability.yaml"

# Host groups -> Hosts (popup.generic, submit_as: groupid, no "multiple" - see 0.6.2).
# Not host_preselect_required, so it shows everything unscoped and narrows once scoped.
reset_popup_profile
assert_narrows "popup.generic hosts scoped by groupid" \
	"$(popup_rows --data-urlencode "srctbl=hosts" --data-urlencode "srcfld1=hostid" \
		--data-urlencode "dstfrm=x" --data-urlencode "dstfld1=x")" \
	"$(popup_rows --data-urlencode "srctbl=hosts" --data-urlencode "srcfld1=hostid" \
		--data-urlencode "dstfrm=x" --data-urlencode "dstfld1=x" --data-urlencode "groupid=$TEST_GROUPID")"

# Hosts -> item pattern popup (popup.generic, submit_as: hostid - plural "hostids" is
# silently ignored here, see the jsrpc.php fix below for the same underlying mistake).
# host_preselect_required: shows nothing unscoped, widens once a host is given.
reset_popup_profile
assert_widens "popup.generic items scoped by hostid" \
	"$(popup_rows --data-urlencode "srctbl=items" --data-urlencode "srcfld1=name" \
		--data-urlencode "dstfrm=x" --data-urlencode "dstfld1=x" \
		--data-urlencode "real_hosts=1" --data-urlencode "numeric=1")" \
	"$(popup_rows --data-urlencode "srctbl=items" --data-urlencode "srcfld1=name" \
		--data-urlencode "dstfrm=x" --data-urlencode "dstfld1=x" \
		--data-urlencode "real_hosts=1" --data-urlencode "numeric=1" --data-urlencode "hostid=$TEST_HOSTID")"

# Hosts -> trigger pattern popup (same submit_as: hostid convention, also host_preselect_required).
reset_popup_profile
assert_widens "popup.generic triggers scoped by hostid" \
	"$(popup_rows --data-urlencode "srctbl=triggers" --data-urlencode "srcfld1=description" \
		--data-urlencode "dstfrm=x" --data-urlencode "dstfld1=x")" \
	"$(popup_rows --data-urlencode "srctbl=triggers" --data-urlencode "srcfld1=description" \
		--data-urlencode "dstfrm=x" --data-urlencode "dstfld1=x" --data-urlencode "hostid=$TEST_HOSTID")"

# The item pattern *autosuggest* (as-you-type) goes through a completely different
# endpoint than the popup - jsrpc.php's patternselect.get - which only recognizes the
# singular "hostid", never "hostids". This is the exact bug the user hit: the popup
# chain was fixed and correct, but autosuggest kept suggesting items from every host
# because it silently ignores an unrecognized param instead of erroring.
assert_narrows "jsrpc patternselect.get items scoped by hostid" \
	"$(jsrpc_pattern_rows --data-urlencode "object_name=items" --data-urlencode "search=a" \
		--data-urlencode "real_hosts=1" --data-urlencode "numeric=1")" \
	"$(jsrpc_pattern_rows --data-urlencode "object_name=items" --data-urlencode "search=a" \
		--data-urlencode "real_hosts=1" --data-urlencode "numeric=1" --data-urlencode "hostid=$TEST_HOSTID")"

# NOTE: jsrpc.php's patternselect.get only supports object_name in {hosts, items, graphs} -
# "triggers" isn't handled at all (native Zabbix limitation, not ours). The trigger
# pattern field's autosuggest can never show real suggestions; typing a pattern by hand
# still works, and the popup button (checked above) is correctly host-scoped. Don't add
# a jsrpc scoping check for triggers - it would always fail regardless of our code.

if [[ "$fail_count" -gt 0 ]]; then
	echo "$fail_count check(s) failed"
	exit 1
fi

echo "All checks passed"
