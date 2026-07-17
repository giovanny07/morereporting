<?php

/**
 * Minimal download layout for JSON exports - mirrors core's layout.csv.php (headers +
 * echo main_block), but core's own layout.json.php is meant for inline AJAX/RPC
 * consumption and doesn't set Content-Disposition, so it won't trigger a file download.
 *
 * @var CView $this
 * @var array $data
 */

header('Content-Type: application/json; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$data['page']['file'].'"');

echo $data['main_block'];
