<?php

require_once('Podquilt.php');

$podquilt = new Podquilt;

header("Content-Type: application/rss+xml; charset=utf-8");
header("Content-Disposition: attachment; filename=podquilt.rss");

echo $podquilt->sortItemsByPubDate()->toXml();