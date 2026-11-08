<?php

return [
    'async_export_threshold' => (int) env('INVENTORY_ASYNC_EXPORT_THRESHOLD', 500),
    'export_retention_days' => (int) env('INVENTORY_EXPORT_RETENTION_DAYS', 7),
];
