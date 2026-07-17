<?php

/**
 * @var CView $this
 * @var array $data
 */

echo zbx_toCSV(array_merge([$data['export_headers']], $data['export_rows']));
