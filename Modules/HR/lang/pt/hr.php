<?php

declare(strict_types=1);

return [
    'employees' => 'Funcionários',
    'employee' => 'Funcionário',
    'departments' => 'Departamentos',
    'department' => 'Departamento',
    'leave_requests' => 'Pedidos de Licença',
    'leave_request' => 'Pedido de Licença',
    'payroll' => 'Folha de Pagamento',
    'job_positions' => 'Cargos',
    'job_position' => 'Cargo',
    'leave_types' => 'Tipos de Licença',
    'leave_type' => 'Tipo de Licença',

    'fields' => [
        'employee_number' => 'Número do Funcionário',
        'first_name' => 'Nome',
        'last_name' => 'Sobrenome',
        'email' => 'E-mail',
        'phone' => 'Telefone',
        'gender' => 'Gênero',
        'date_of_birth' => 'Data de Nascimento',
        'hire_date' => 'Data de Admissão',
        'department' => 'Departamento',
        'job_position' => 'Cargo',
        'manager' => 'Gestor',
        'employment_type' => 'Tipo de Contrato',
        'status' => 'Status',
        'start_date' => 'Data de Início',
        'end_date' => 'Data de Término',
        'days' => 'Dias',
        'reason' => 'Motivo',
    ],

    'statuses' => [
        'active' => 'Ativo',
        'on_probation' => 'Em Período de Experiência',
        'on_leave' => 'De Licença',
        'inactive' => 'Inativo',
        'terminated' => 'Demitido',
    ],

    'employment_types' => [
        'full_time' => 'Tempo Integral',
        'part_time' => 'Meio Período',
        'contract' => 'Contrato',
        'intern' => 'Estagiário',
    ],

    'leave_statuses' => [
        'pending' => 'Pendente',
        'approved' => 'Aprovado',
        'rejected' => 'Rejeitado',
    ],

    'genders' => [
        'male' => 'Masculino',
        'female' => 'Feminino',
        'other' => 'Outro',
    ],

    'recruitment' => [
        'title' => 'Recrutamento',
        'job_postings' => 'Vagas',
        'applications' => 'Candidaturas',
        'new_posting' => 'Nova Vaga',
        'publish' => 'Publicar',
        'status_draft' => 'Rascunho',
        'status_published' => 'Publicado',
        'status_closed' => 'Encerrado',
    ],

    'training' => [
        'title' => 'Treinamento e Competências',
        'skills_catalog' => 'Catálogo de Competências',
        'courses' => 'Cursos',
        'skills_matrix' => 'Matriz de Competências',
        'enroll' => 'Inscrever',
        'new_course' => 'Novo Curso',
        'add_skill' => 'Adicionar Competência',
    ],

    'attendance' => [
        'title' => 'Presença',
        'clock_in' => 'Registrar Entrada',
        'clock_out' => 'Registrar Saída',
        'clocked_in' => 'Trabalhando',
        'not_clocked_in' => 'Sem Registro',
        'request_leave' => 'Solicitar Licença',
        'weekly' => 'Semanal',
        'monthly' => 'Mensal',
        'hours_worked' => 'Horas Trabalhadas',
    ],

    'payroll_export' => [
        'title' => 'Exportar Folha',
        'export_silae' => 'Exportar SILAE',
        'export_dsn' => 'Exportar DSN',
        'export_csv' => 'Exportar CSV',
    ],

    'self_service' => [
        'title' => 'Meu Portal de RH',
        'my_profile' => 'Meu Perfil',
        'my_payslips' => 'Meus Holerites',
        'my_attendance' => 'Minha Presença',
        'my_leaves' => 'Minhas Licenças',
        'edit_profile' => 'Editar Perfil',
        'leave_balance' => 'Saldo de Licenças',
        'request_leave' => 'Solicitar Licença',
        'download_payslip' => 'Baixar Holerite',
    ],
];
