<?php

require_once('Podquilt.php');
require __DIR__ . '/vendor/autoload.php';

$podquilt = new \Podquilt\Podquilt;

header("Content-Type: application/rss+xml; charset=utf-8");
header("Content-Disposition: inline");
header("Cache-control: no-cache");

echo $podquilt->sortItemsByPubDate()->toXml();

$podquilt->logExecutionTime();