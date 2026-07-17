<?php

/**
 * Test bootstrap: requires source files directly by path rather than relying on
 * PSR-4 autoloading, because Zabbix's own module autoloader expects lowercase
 * directory segments (e.g. includes/reports/) that don't match the PascalCase
 * namespace segments Composer's PSR-4 autoloader would look for on a case-sensitive
 * filesystem. See the module gotchas notes for details.
 */

require_once __DIR__.'/../includes/ReportType.php';
require_once __DIR__.'/../includes/ReportComparison.php';
require_once __DIR__.'/../includes/reports/ItemPercentilesReport.php';
require_once __DIR__.'/../includes/reports/AnomalyReport.php';
