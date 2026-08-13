<?php

declare(strict_types=1);

return [
    'employees' => 'Employés',
    'employee' => 'Employé',
    'departments' => 'Départements',
    'department' => 'Département',
    'leave_requests' => 'Demandes de congé',
    'leave_request' => 'Demande de congé',
    'payroll' => 'Paie',
    'job_positions' => 'Postes',
    'job_position' => 'Poste',
    'leave_types' => 'Types de congé',
    'leave_type' => 'Type de congé',

    'fields' => [
        'employee_number' => 'Numéro d\'employé',
        'first_name' => 'Prénom',
        'last_name' => 'Nom de famille',
        'email' => 'E-mail',
        'phone' => 'Téléphone',
        'gender' => 'Genre',
        'date_of_birth' => 'Date de naissance',
        'hire_date' => 'Date d\'embauche',
        'department' => 'Département',
        'job_position' => 'Poste',
        'manager' => 'Responsable',
        'employment_type' => 'Type d\'emploi',
        'status' => 'Statut',
        'start_date' => 'Date de début',
        'end_date' => 'Date de fin',
        'days' => 'Jours',
        'reason' => 'Motif',
    ],

    'statuses' => [
        'active' => 'Actif',
        'on_probation' => 'En période d\'essai',
        'on_leave' => 'En congé',
        'inactive' => 'Inactif',
        'terminated' => 'Licencié',
    ],

    'employment_types' => [
        'full_time' => 'Temps plein',
        'part_time' => 'Temps partiel',
        'contract' => 'Contrat',
        'intern' => 'Stagiaire',
    ],

    'leave_statuses' => [
        'pending' => 'En attente',
        'approved' => 'Approuvé',
        'rejected' => 'Rejeté',
    ],

    'genders' => [
        'male' => 'Homme',
        'female' => 'Femme',
        'other' => 'Autre',
    ],

    'recruitment' => [
        'title' => 'Recrutement',
        'job_postings' => 'Offres d\'emploi',
        'applications' => 'Candidatures',
        'new_posting' => 'Nouvelle offre',
        'publish' => 'Publier',
        'status_draft' => 'Brouillon',
        'status_published' => 'Publiée',
        'status_closed' => 'Fermée',
    ],

    'training' => [
        'title' => 'Formation & Compétences',
        'skills_catalog' => 'Catalogue des compétences',
        'courses' => 'Formations',
        'skills_matrix' => 'Matrice des compétences',
        'enroll' => 'Inscrire',
        'new_course' => 'Nouvelle formation',
        'add_skill' => 'Ajouter une compétence',
    ],

    'attendance' => [
        'title' => 'Pointages',
        'clock_in' => 'Pointer l\'arrivée',
        'clock_out' => 'Pointer le départ',
        'clocked_in' => 'En cours',
        'not_clocked_in' => 'Non pointé',
        'request_leave' => 'Demande de congé',
        'weekly' => 'Hebdomadaire',
        'monthly' => 'Mensuel',
        'hours_worked' => 'Heures travaillées',
    ],

    'payroll_export' => [
        'title' => 'Export Paie',
        'export_silae' => 'Exporter SILAE',
        'export_dsn' => 'Exporter DSN',
        'export_csv' => 'Exporter CSV',
    ],

    'self_service' => [
        'title' => 'Mon espace RH',
        'my_profile' => 'Mon profil',
        'my_payslips' => 'Mes bulletins',
        'my_attendance' => 'Mes pointages',
        'my_leaves' => 'Mes congés',
        'edit_profile' => 'Modifier mon profil',
        'leave_balance' => 'Solde de congés',
        'request_leave' => 'Demander un congé',
        'download_payslip' => 'Télécharger le bulletin',
    ],
];
