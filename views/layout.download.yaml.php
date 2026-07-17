<?php

/**
 * Minimal download layout for YAML exports - same idea as layout.download.json.php,
 * since core has no built-in YAML layout to reuse.
 *
 * @var CView $this
 * @var array $data
 */

header('Content-Type: application/yaml; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$data['page']['file'].'"');

echo $data['main_block'];
