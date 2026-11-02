<?php

return [
    // Disable after reprinting old Base64 QR posters. Default false prevents guessing table IDs.
    'allow_legacy_table_ids' => (bool) env('QR_ALLOW_LEGACY_TABLE_IDS', false),
];
