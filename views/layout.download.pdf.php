<?php

/**
 * Minimal download layout for PDF exports - mirrors layout.download.json.php, but for
 * binary content. PHP output buffering (used by CView to capture main_block) is
 * binary-safe, so the raw PDF bytes echoed by the view pass through untouched.
 *
 * @var CView $this
 * @var array $data
 */

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="'.$data['page']['file'].'"');

echo $data['main_block'];
