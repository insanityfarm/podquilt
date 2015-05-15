<?php

require_once('Podquilt.php');

$podquilt = new Podquilt;

header("Content-Type: application/rss+xml; charset=utf-8");

echo $podquilt->sortItemsByPubDate()->toXml();