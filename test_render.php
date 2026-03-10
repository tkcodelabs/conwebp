<?php
require_once('../../../wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/admin.php');

// Output the raw JS to see where the syntax error is
ob_start();
conwebp_settings_page();
$content = ob_get_clean();

// Add line numbers to the output
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    echo ($i + 1) . ": " . htmlspecialchars($line) . "<br>";
}
