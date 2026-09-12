<?php
// Lightweight source-level checks for CI/review. This file intentionally does not boot WordPress.
$plugin = file_get_contents(__DIR__.'/../bubba-hub-production.php');
$importer = file_get_contents(__DIR__.'/../includes/class-google-import.php');
$checks = [
  strpos($plugin, "class-google-import.php") !== false,
  preg_match("/BUBBAHUB_VERSION',\\s*'4\\.2\\.[0-9]+'/", $plugin) === 1,
  strpos($importer, 'class BubbaHubGoogleImport') !== false,
  strpos($importer, 'manage_bubbahub') !== false,
  strpos($importer, "post_type'=>'bh_group'") !== false,
  strpos($importer, '_bubbahub_google_id') !== false,
];
foreach($checks as $i=>$ok){ if(!$ok) throw new RuntimeException('Stage 8 check failed: '.($i+1)); }
echo "Stage 8 import checks passed\n";
