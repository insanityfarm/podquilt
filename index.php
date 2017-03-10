<?php

require_once('Podquilt.php');

$podquilt = new \Podquilt\Podquilt;

header("Content-Type: application/rss+xml; charset=utf-8");
header("Content-Disposition: inline");

echo $podquilt->sortItemsByPubDate()->toXml();