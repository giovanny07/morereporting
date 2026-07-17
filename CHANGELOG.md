# Changelog

All notable changes to this module are documented here. Versions follow the `version` field in `manifest.json` (semver: MINOR per new report/feature, PATCH per fix, MAJOR reserved for a stable 1.0.0).

## 0.12.0 - Phase 4.3: YAML export

### Added
- "Export YAML" link next to JSON/CSV on both report run pages. Uses `symfony/yaml` (`^5.4`, chosen over the latest 8.x for PHP 8.0 compatibility - Zabbix 7.0's own floor - since symfony/yaml 8.x requires PHP >=8.4.1) as the module's own dependency rather than reusing the copy already vendored inside Zabbix's own `vendor/`, which would silently break if Zabbix ever updates/drops it.
- `views/layout.download.yaml.php`: same idea as the JSON download layout, since core has no built-in YAML layout.

### Changed
- **New operational requirement**: this module now needs `composer install` to actually *run* (not just to test) - `Module.php` requires Composer's autoloader (`vendor/autoload.php`) at file scope, since Zabbix's own `CAutoloader` only knows this module's own namespace and has no idea about third-party dependencies like `symfony/yaml`. Every action still works without this *except* the new `.yaml` export ones. Worth calling out clearly for Phase 7 packaging/deployment docs.

### Fixed/Verified
- Validated actual YAML output with a real parser (Python's `yaml.safe_load`), not just a structural heuristic - parses correctly with all fields (including Phase 3's MTTR/MTBF) intact.

## 0.11.0 - Phase 4.2: CSV export

### Added
- "Export CSV" link next to "Export JSON" on both report run pages, same filter carry-over. Human-readable formatting (severity name instead of raw int, durations via `convertUnitsS()`, "N/A" for zero-episode MTTR/MTBF) rather than the raw JSON export's unformatted values, since CSV/Excel is meant to be read directly, not re-parsed by another program.
- Reuses core's own `layout.csv` and `zbx_toCSV()` (`include/func.inc.php`) - unlike JSON, no custom layout was needed since core's CSV layout already sets `Content-Disposition: attachment`.
- `scripts/http_smoke.sh` gained a matching CSV export check (Content-Type, Content-Disposition, quoted-CSV-shaped body).

## 0.9.0 - Phase 4.1: JSON export

### Added
- "Export JSON" link on both report run pages, carrying over the current filter (scope, patterns, period, saved reportid if any). Downloads the same rows the interactive view computed, as a `.json` file (`Content-Disposition: attachment`).
- Reuses the existing `ReportsPercentiles`/`ReportsAvailability` controllers for the export actions (`morereporting.percentiles.json`, `morereporting.availability.json`) rather than duplicating the filter/scope logic - same pattern core uses for `actionlog.list` vs `actionlog.csv` (one controller, two action registrations pointing at different view+layout). Skips the SVG graph and period comparison work for export requests, since neither is used in the JSON output.
- `views/layout.download.json.php`: small module-owned layout (`Content-Type: application/json` + `Content-Disposition: attachment`) - core's own `layout.json` is meant for inline AJAX/RPC responses and doesn't set the download header.

### Fixed
- View/layout names can only contain lowercase letters and dots (`CView`'s name regex rejects hyphens) - `layout.download-json` failed with "Invalid view name" until renamed to `layout.download.json`.

## 0.8.0 - Phase 3: generic period-over-period comparison

### Added
- "Compare with previous period" checkbox on both report run pages (percentiles and availability). When checked, the report runs twice - the selected period and the immediately preceding period of equal length (e.g. last 7 days vs the 7 days before that) - and shows the headline metric (P95 for percentiles, Availability % for availability) from both periods side by side with a colored delta (green/red/grey).
- `includes/ReportComparison.php`: generic pairing helper (`pair()` matches two result sets by key field like `itemid`/`triggerid`; `previousPeriod()` computes the preceding equal-length window) that any `ReportType` can reuse for this - not specific to one report. Covered by unit tests (pure logic, no framework dependency).
- This closes Phase 3 (MVP gerencial) from the roadmap: availability %, MTTR/MTBF, and period-over-period comparison are all in place.

## 0.7.0 - Phase 3: MTTR/MTBF

### Added
- `Trigger availability` report gained MTTR (mean time to repair) and MTBF (mean time between failures) columns, computed from the number of problem episodes per trigger in the window (counted via `API::Event()->get()`, same aggregation approach as native "Top 100 triggers") - added to the existing report rather than a separate one, since it's the same trigger scope/data. Shows "N/A" for triggers with zero episodes in the period.

## 0.6.3 - Fix item/trigger pattern autosuggest not scoped by host

### Fixed
- Choosing a host, then typing in the Item/Trigger name pattern field, kept suggesting items/triggers from every host - the host selection was silently ignored. Root cause: the as-you-type autosuggest for `CPatternSelect` goes through a completely different endpoint than the "browse" popup (`jsrpc.php?method=patternselect.get`, not `zabbix.php?action=popup.generic`), and that endpoint's handler for `object_name=items` only reads a singular `hostid` parameter - never the plural `hostids` our `filter_preselect` chain was sending (silently ignored, not rejected, so it never surfaced as an error). Changed `submit_as` from `hostids` to `hostid` (and dropped `multiple: true`, for the same reason as the groupid fix in 0.6.2) on every Hosts -> pattern chain, in the builder and both run pages.
- Also fixed the same underlying param-name mismatch on the item/trigger "browse" popup itself, which happened to not error but also wasn't truly scoped (verified by comparing actual result content, not just HTTP status - see Tooling below).
- **Known native limitation, not a bug here**: `jsrpc.php`'s `patternselect.get` only supports `object_name` in `{hosts, items, graphs}` - `triggers` isn't handled at all. The Trigger name patterns field's autosuggest can never show live suggestions from real trigger names; typing a pattern by hand still works correctly, and its "browse" popup button is properly host-scoped.

### Tooling
- Rewrote the `filter_preselect` smoke checks to assert actual result-set narrowing/widening (via row counts), not just "no error" - the previous version passed even with the exact `hostids` vs `hostid` bug above, because an unrecognized param is silently ignored rather than rejected, so "no error" alone proves nothing. Also added a check against `jsrpc.php`'s `patternselect.get` directly, the endpoint the original bug report actually came from. Discovered and worked around a related testing gotcha: `popup.generic` persists whatever host/group scope you pass it into the user's profile (`web.popup.generic.filter_hostid`/`filter_groupid`), so a later "unscoped" check in the same run can be silently pre-scoped by an earlier check's leftover state - the script now resets that profile state before each comparison.

## 0.6.2 - Fix "Incorrect value for groupid field"

### Fixed
- Selecting a host group then trying to pick a host failed with "Incorrect value for \"groupid\" field". Caused by wrongly setting `multiple: true` on the Host groups -> Hosts `filter_preselect` chain: the popup's `groupid` parameter only accepts a single value (`db hstgrp.groupid`, unlike `hostids` which accepts an array), so submitting it as an array failed validation. Removed `multiple` from that specific chain (matches native `trigger.list.php`, which also only preselects the first selected group) - the Hosts -> pattern chain (`hostids`, plural) is unaffected and keeps `multiple: true`.

### Tooling
- `scripts/http_smoke.sh` now also simulates the `popup.generic` AJAX requests the browser's JS constructs for each `filter_preselect` chain (host groups -> hosts, hosts -> item/trigger patterns). Neither PHPUnit nor the existing page-load checks could have caught this bug - the request only fires on a follow-up interaction, never during a plain page load. Verified the new check genuinely fails against the broken (array-shaped `groupid[]`) request before confirming it passes against the fix.

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
