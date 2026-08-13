<?php

declare(strict_types=1);

return [
    'employees' => 'Empleados',
    'employee' => 'Empleado',
    'departments' => 'Departamentos',
    'department' => 'Departamento',
    'leave_requests' => 'Solicitudes de Permiso',
    'leave_request' => 'Solicitud de Permiso',
    'payroll' => 'Nómina',
    'job_positions' => 'Puestos de Trabajo',
    'job_position' => 'Puesto de Trabajo',
    'leave_types' => 'Tipos de Permiso',
    'leave_type' => 'Tipo de Permiso',

    'fields' => [
        'employee_number' => 'Número de Empleado',
        'first_name' => 'Nombre',
        'last_name' => 'Apellido',
        'email' => 'Correo electrónico',
        'phone' => 'Teléfono',
        'gender' => 'Género',
        'date_of_birth' => 'Fecha de Nacimiento',
        'hire_date' => 'Fecha de Contratación',
        'department' => 'Departamento',
        'job_position' => 'Puesto',
        'manager' => 'Responsable',
        'employment_type' => 'Tipo de Empleo',
        'status' => 'Estado',
        'start_date' => 'Fecha de Inicio',
        'end_date' => 'Fecha de Fin',
        'days' => 'Días',
        'reason' => 'Motivo',
    ],

    'statuses' => [
        'active' => 'Activo',
        'on_probation' => 'En Período de Prueba',
        'on_leave' => 'De Permiso',
        'inactive' => 'Inactivo',
        'terminated' => 'Despedido',
    ],

    'employment_types' => [
        'full_time' => 'Tiempo Completo',
        'part_time' => 'Tiempo Parcial',
        'contract' => 'Contrato',
        'intern' => 'Becario',
    ],

    'leave_statuses' => [
        'pending' => 'Pendiente',
        'approved' => 'Aprobado',
        'rejected' => 'Rechazado',
    ],

    'genders' => [
        'male' => 'Masculino',
        'female' => 'Femenino',
        'other' => 'Otro',
    ],

    'recruitment' => [
        'title' => 'Reclutamiento',
        'job_postings' => 'Ofertas de Empleo',
        'applications' => 'Candidaturas',
        'new_posting' => 'Nueva Oferta',
        'publish' => 'Publicar',
        'status_draft' => 'Borrador',
        'status_published' => 'Publicado',
        'status_closed' => 'Cerrado',
    ],

    'training' => [
        'title' => 'Formación y Competencias',
        'skills_catalog' => 'Catálogo de Competencias',
        'courses' => 'Cursos',
        'skills_matrix' => 'Matriz de Competencias',
        'enroll' => 'Inscribir',
        'new_course' => 'Nuevo Curso',
        'add_skill' => 'Agregar Competencia',
    ],

    'attendance' => [
        'title' => 'Asistencia',
        'clock_in' => 'Registrar Entrada',
        'clock_out' => 'Registrar Salida',
        'clocked_in' => 'En Trabajo',
        'not_clocked_in' => 'Sin Registrar',
        'request_leave' => 'Solicitar Permiso',
        'weekly' => 'Semanal',
        'monthly' => 'Mensual',
        'hours_worked' => 'Horas Trabajadas',
    ],

    'payroll_export' => [
        'title' => 'Exportar Nómina',
        'export_silae' => 'Exportar SILAE',
        'export_dsn' => 'Exportar DSN',
        'export_csv' => 'Exportar CSV',
    ],

    'self_service' => [
        'title' => 'Mi Portal de RRHH',
        'my_profile' => 'Mi Perfil',
        'my_payslips' => 'Mis Nóminas',
        'my_attendance' => 'Mi Asistencia',
        'my_leaves' => 'Mis Permisos',
        'edit_profile' => 'Editar Perfil',
        'leave_balance' => 'Saldo de Permisos',
        'request_leave' => 'Solicitar Permiso',
        'download_payslip' => 'Descargar Nómina',
    ],
];
