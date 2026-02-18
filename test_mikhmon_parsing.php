<?php
require_once 'includes/functions.php';

// Mock function until it's implemented in functions.php
if (!function_exists('parseHotspotProfileComment')) {
    function parseHotspotProfileComment($comment)
    {
        // Placeholder for the logic we will implement
        if (strpos($comment, 'parent:') !== false) {
            return (int) str_replace('parent:', '', $comment);
        }
        // Try to find price pattern
        if (preg_match('/(?:^|\s)(\d{3,})(?:\s|$)/', $comment, $matches)) {
            return (int) $matches[1];
        }
        // Fallback or more complex logic
        return 0;
    }
}

echo "=== Testing parseMikhmonComment (Users) ===\n\n";

$userTestCases = [
    // Standard Mikhmon format (comment often includes price and validity)
    'Feb/16/2026 10:00:00 - voucher123 - 5000 - Default - 1d',
    'Feb/16/2026 10:00:00 - voucher123 - 5K - Default - 1d',

    // Another common format
    'vc-user-160226 5000 1d',

    // Current supported format
    'price:5000,validity:1d,profile:Default',

    // Empty comment
    '',

    // Just price and validity without date
    '5000 1d',

    // Real world example from Mikhmon
    'Feb/12/2026 09:12:44 - mikhmon - 3000 - Default - 5h'
];

foreach ($userTestCases as $comment) {
    echo "Comment: \"$comment\"\n";
    $result = parseMikhmonComment($comment);
    echo "Parsed: " . json_encode($result) . "\n";
    echo "----------------------------------------\n";
}

echo "\n=== Testing parseHotspotProfileComment (Profiles) ===\n\n";

$profileTestCases = [
    'parent:5000',      // Current format
    'Rp. 5.000',        // Human readable
    '5000',             // Just number
    'harian 5000',      // Text + number
    '5000 12h',
    'Gratis',           // No price
    ''
];

foreach ($profileTestCases as $comment) {
    echo "Comment: \"$comment\"\n";
    $result = parseHotspotProfileComment($comment);
    echo "Parsed Price: " . $result . "\n";
    echo "----------------------------------------\n";
}
