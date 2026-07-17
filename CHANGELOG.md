# Changelog

All notable changes to this module are documented here. Versions follow the `version` field in `manifest.json` (semver: MINOR per new report/feature, PATCH per fix, MAJOR reserved for a stable 1.0.0).

## 0.6.1 - Chain scope filters together

### Fixed
- Host groups, Hosts, and Item/Trigger name patterns now actually filter each other, using the same `filter_preselect` mechanism native pages (e.g. `trigger.list.php`) use to chain multiselects: picking a Host group narrows the Hosts popup/autosuggest to that group, and picking a Host narrows the item/trigger pattern popup to that host. Applied to the builder and both run pages' ad-hoc filters.

## 0.6.0 - Native item/trigger pattern picker

### Changed
- Replaced the plain-text name pattern field with `CPatternSelect` - the same multi-value, wildcard-aware, autocomplete-driven picker Zabbix's own Graph widget uses for its "Item pattern" dataset mode. Item percentiles gets an "Item name patterns" picker (browses `items`), Trigger availability gets a separate "Trigger name patterns" picker (browses `triggers`) - shown/hidden by report type using the same toggle mechanism as the SLO field.
- A definition can now hold **multiple** patterns (`config.patterns`, an array), matched with OR semantics (`searchByAny`) - e.g. `CPU*` and `*disk*` together, not just one pattern at a time.
- `filter_pattern` (single string) renamed to `filter_patterns[]` (array) across both run pages' ad-hoc filters and the builder; `ReportType::getData()` implementations updated accordingly.

## 0.5.1 - Report builder mechanism fixes

### Fixed
- The SLO field no longer shows for report types that don't use it (e.g. Item percentiles); it now toggles based on the selected report type, mirroring the `CViewSwitcher` pattern native forms (e.g. item edit) use for type-dependent fields. `ReportTypeRegistry` now declares which optional fields each type uses.
- Name pattern matching now supports real wildcards (`searchWildcardsEnabled` on the underlying `API::Item()`/`API::Trigger()` calls): `*` matches any number of characters (e.g. `CPU*`, `*disk*`), while plain text without `*` still does a simple contains-match as before. Field labels/placeholders updated to make this explicit.

## 0.5.0 - Phase 2 polish

### Added
- Report edit form reorganized into tabs (`CTabView`: General / Scope / Default period), matching how native Zabbix forms (e.g. host edit) organize multi-section forms instead of one long flat form.
- `Hosts` multiselect alongside `Host groups` in both the builder and the ad-hoc filters, matching the Host groups + Hosts convention used elsewhere in core (e.g. Top 100 triggers).
- Saved report definitions now store a default **period** (`config.period`), editable with quick-range presets (Yesterday, Last 7 days, Last 30 days, Last 3 months, Last year) or free-form relative/absolute expressions (`now-7d`, exact dates), reusing Zabbix's own `CRangeTimeParser` instead of a home-grown absolute-only date range.
- The percentiles/availability run pages gained the same quick-range presets and now accept relative expressions in their From/To filter fields.

### Changed
- Item/trigger scope resolution (`ItemPercentilesReport`, `AvailabilityReport`) now also filters by `hostids`, not just `groupids`+pattern.

## 0.4.0 - Phase 2 (status & filters)

### Added
- `status` (Enabled/Disabled) on saved report definitions, with a one-click toggle from the list (`morereporting.report.status`) and a lazy schema migration (`ALTER TABLE ... ADD COLUMN status`) for installations created before this existed.
- List page filter: name search, report type, status (Any/Enabled/Disabled), and "Created by me" vs "All" - persisted via `CProfile` like native list pages.
- "Created by" column resolving the definition's owner via `API::User()->get()` + `getUserFullname()`.

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
