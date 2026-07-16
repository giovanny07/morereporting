# Changelog

All notable changes to this module are documented here. Versions follow the `version` field in `manifest.json` (semver: MINOR per new report/feature, PATCH per fix, MAJOR reserved for a stable 1.0.0).

## 0.3.0 - Phase 2

### Added
- Report builder: save a report definition (name, type, host groups, item/trigger name pattern, SLO where applicable) and reuse/schedule it later instead of re-filtering every time.
  - `morereporting.report.edit` / `morereporting.report.update` / `morereporting.report.delete`: create, edit and delete saved definitions.
  - `includes/ReportStorage.php`: persistence for definitions in a new `morereporting_report` table, installed lazily on first use (no separate bootstrap step). MySQL only for now.
  - `includes/ReportTypeRegistry.php`: maps a saved definition's `report_type` to the action that runs it, so new report types register themselves without touching the builder.
  - Saved definitions are shared across all users with module access (matches native Scheduled reports); the data each user sees when running one still respects their own host group permissions.
  - `morereporting.percentiles` and `morereporting.availability` now accept a `reportid` to run from a saved definition (scope locked, date range still adjustable), alongside their original ad-hoc filter mode.
- Catalog page (`morereporting.list`) now lists saved report definitions with Run/Edit/Delete actions and a "Create report" button.

## 0.2.0 - Phase 1

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
