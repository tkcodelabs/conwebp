<?php
require_once('../../../wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/admin.php');

$sizes = get_intermediate_image_sizes();
$useful = array();
global $_wp_additional_image_sizes;
foreach ($sizes as $s) {
    if (in_array($s, ['thumbnail', 'medium', 'medium_large', 'large'])) {
        $w = get_option($s . '_size_w');
        $h = get_option($s . '_size_h');
        $useful[] = "$s ({$w}x{$h})";
    } elseif (isset($_wp_additional_image_sizes[$s])) {
        $useful[] = "$s ({$_wp_additional_image_sizes[$s]['width']}x{$_wp_additional_image_sizes[$s]['height']})";
    }
}
$json = json_encode($useful);
echo "JSON:\n" . $json . "\n";
echo "JS Syntax:\nconst usefulSizes = " . $json . " || [];\n";

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON Error: " . json_last_error_msg();
}
