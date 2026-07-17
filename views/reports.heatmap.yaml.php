<?php

use Symfony\Component\Yaml\Yaml;

/**
 * @var CView $this
 * @var array $data
 */

echo Yaml::dump($data['rows'], 2, 4);
