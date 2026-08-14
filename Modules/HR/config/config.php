<?php

return [
    'name' => 'HR',

    // Fallback days/year when a leave request auto-creates a LeaveType from
    // a free-text `type` string that doesn't match an existing code (was a
    // bare hardcoded 30 in LeaveRequestController::store()).
    'default_leave_days_per_year' => 30,
];
