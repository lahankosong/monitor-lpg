<?php
$base = dirname(__DIR__);
$files = glob($base . '/bootstrap/cache/*.php');
foreach ($files as $f) { unlink($f); echo "Deleted: $f\n"; }
echo "Done!";