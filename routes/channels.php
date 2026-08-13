<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Tenant-isolated notification channels
Broadcast::channel('notifications.{tenantId}', function ($user, $tenantId) {
    return $user->tenant_id === (int) $tenantId;
});

Broadcast::channel('dashboard.{tenantId}', function ($user, $tenantId) {
    return $user->tenant_id === (int) $tenantId;
});

Broadcast::channel('crm.contacts.{tenantId}', function ($user, $tenantId) {
    return $user->tenant_id === (int) $tenantId;
});

Broadcast::channel('inventory.products.{tenantId}', function ($user, $tenantId) {
    return $user->tenant_id === (int) $tenantId;
});

Broadcast::channel('hr.employees.{tenantId}', function ($user, $tenantId) {
    return $user->tenant_id === (int) $tenantId;
});

Broadcast::channel('accounting.invoices.{tenantId}', function ($user, $tenantId) {
    return $user->tenant_id === (int) $tenantId;
});

Broadcast::channel('queue.{tenantId}', function ($user, $tenantId) {
    return $user->tenant_id === (int) $tenantId;
});

Broadcast::channel('websocket.{tenantId}', function ($user, $tenantId) {
    return $user->tenant_id === (int) $tenantId;
});
