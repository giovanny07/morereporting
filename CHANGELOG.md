# Changelog

All notable changes to this module are documented here. Versions follow the `version` field in `manifest.json` (semver: MINOR per new report/feature, PATCH per fix, MAJOR reserved for a stable 1.0.0).

## 0.2.0 - Phase 1 (in progress)

### Added
- `Item percentiles` report (`morereporting.percentiles`): p50/p90/p95/p99 over raw history values for numeric items, filterable by host group and item name pattern, with a native SVG trend graph (p95 overlay) rendered through Zabbix's own `Widgets\SvgGraph` engine.
- `includes/NativeGraph.php`: reusable wrapper around `CSvgGraphHelper` for embedding native-looking graphs in report views.
- `Trigger availability` report (`morereporting.availability`): availability % (SLI) vs a configurable SLO threshold and downtime per trigger over a time window, using Zabbix's own `calculateAvailability()` so results match the classic Availability report.
- Catalog page (`morereporting.list`) now lists available reports instead of a placeholder.

### Tooling
- `scripts/http_smoke.sh`: end-to-end smoke test (login + hit every action, assert HTTP 200 and no PHP errors/warnings in the response).
- PHPUnit unit tests (`tests/`) for pure computation logic (`ItemPercentilesReport::compute()`), run with `vendor/bin/phpunit`.

## 0.1.0 - Phase 0

### Added
- Module scaffold: `manifest.json` (manifest_version 2.0), `Module.php` registering a "MoreReporting" entry under the Reports menu.
- `includes/ReportType.php`: base contract for report types (getData/compute/render), shared by all future reports.
- Verified end-to-end against the local Zabbix 7.0.26 test instance.
