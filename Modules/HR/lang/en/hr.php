<?php

declare(strict_types=1);

return [
    'employees' => 'Employees',
    'employee' => 'Employee',
    'departments' => 'Departments',
    'department' => 'Department',
    'leave_requests' => 'Leave Requests',
    'leave_request' => 'Leave Request',
    'payroll' => 'Payroll',
    'job_positions' => 'Job Positions',
    'job_position' => 'Job Position',
    'leave_types' => 'Leave Types',
    'leave_type' => 'Leave Type',

    'fields' => [
        'employee_number' => 'Employee Number',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'gender' => 'Gender',
        'date_of_birth' => 'Date of Birth',
        'hire_date' => 'Hire Date',
        'department' => 'Department',
        'job_position' => 'Job Position',
        'manager' => 'Manager',
        'employment_type' => 'Employment Type',
        'status' => 'Status',
        'start_date' => 'Start Date',
        'end_date' => 'End Date',
        'days' => 'Days',
        'reason' => 'Reason',
    ],

    'statuses' => [
        'active' => 'Active',
        'on_probation' => 'On Probation',
        'on_leave' => 'On Leave',
        'inactive' => 'Inactive',
        'terminated' => 'Terminated',
    ],

    'employment_types' => [
        'full_time' => 'Full Time',
        'part_time' => 'Part Time',
        'contract' => 'Contract',
        'intern' => 'Intern',
    ],

    'leave_statuses' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],

    'genders' => [
        'male' => 'Male',
        'female' => 'Female',
        'other' => 'Other',
    ],

    'recruitment' => [
        'title' => 'Recruitment',
        'job_postings' => 'Job Postings',
        'applications' => 'Applications',
        'new_posting' => 'New Job Posting',
        'publish' => 'Publish',
        'status_draft' => 'Draft',
        'status_published' => 'Published',
        'status_closed' => 'Closed',
    ],

    'training' => [
        'title' => 'Training & Skills',
        'skills_catalog' => 'Skills Catalog',
        'courses' => 'Courses',
        'skills_matrix' => 'Skills Matrix',
        'enroll' => 'Enroll',
        'new_course' => 'New Course',
        'add_skill' => 'Add Skill',
    ],

    'attendance' => [
        'title' => 'Attendance',
        'clock_in' => 'Clock In',
        'clock_out' => 'Clock Out',
        'clocked_in' => 'Clocked In',
        'not_clocked_in' => 'Not Clocked In',
        'request_leave' => 'Request Leave',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        'hours_worked' => 'Hours Worked',
    ],

    'payroll_export' => [
        'title' => 'Payroll Export',
        'export_silae' => 'Export SILAE',
        'export_dsn' => 'Export DSN',
        'export_csv' => 'Export CSV',
    ],

    'self_service' => [
        'title' => 'My HR Portal',
        'my_profile' => 'My Profile',
        'my_payslips' => 'My Payslips',
        'my_attendance' => 'My Attendance',
        'my_leaves' => 'My Leaves',
        'edit_profile' => 'Edit Profile',
        'leave_balance' => 'Leave Balance',
        'request_leave' => 'Request Leave',
        'download_payslip' => 'Download Payslip',
    ],
];
