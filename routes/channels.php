<?php

use Illuminate\Support\Facades\Broadcast;

// Public channels — the Next.js frontend subscribes to these
// Authentication is handled via Sanctum token on the API side,
// not on the channel level (these are public channels)
Broadcast::channel('dashboard', function () {
    return true;
});

Broadcast::channel('audit-logs', function () {
    return true;
});

Broadcast::channel('health', function () {
    return true;
});
