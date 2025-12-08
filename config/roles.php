<?php
/**
 * config/roles.php
 * Definición de roles y permisos del sistema
 */

// Roles
define('ROLE_ADMIN', 'Administrador');
define('ROLE_DOCTOR', 'Médico');
define('ROLE_NURSE', 'Enfermero');
define('ROLE_RECEPTIONIST', 'Recepcionista');
define('ROLE_PHARMACIST', 'Farmacéutico');
define('ROLE_LAB', 'Laboratorio');
define('ROLE_AUDITOR', 'Auditor');
define('ROLE_PATIENT', 'Paciente');

// Matriz de permisos: ['rol' => ['modulo' => ['accion1', 'accion2']]]
$PERMISSIONS = [
    ROLE_ADMIN => [
        'pacientes' => ['create', 'read', 'update', 'delete', 'list'],
        'citas' => ['create', 'read', 'update', 'delete', 'list'],
        'internamiento' => ['create', 'read', 'update', 'delete', 'list'],
        'inventario' => ['create', 'read', 'update', 'delete', 'list'],
        'personal' => ['create', 'read', 'update', 'delete', 'list'],
        'reportes' => ['create', 'read', 'list'],
        'usuarios' => ['create', 'read', 'update', 'delete', 'list'],
        'dashboard' => ['read']
    ],
    ROLE_DOCTOR => [
        'pacientes' => ['read', 'list'],
        'citas' => ['read', 'list', 'update'],
        'internamiento' => ['read', 'list'],
        'historial-clinico' => ['create', 'read', 'update', 'list'],
        'dashboard' => ['read']
    ],
    ROLE_NURSE => [
        'pacientes' => ['read', 'list'],
        'internamiento' => ['read', 'update', 'list'],
        'inventario' => ['read', 'list'],
        'dashboard' => ['read']
    ],
    ROLE_RECEPTIONIST => [
        'pacientes' => ['create', 'read', 'update', 'list'],
        'citas' => ['create', 'read', 'update', 'list'],
        'dashboard' => ['read']
    ],
    ROLE_PHARMACIST => [
        'inventario' => ['read', 'update', 'list'],
        'medicamentos' => ['create', 'read', 'update', 'list'],
        'dashboard' => ['read']
    ],
    ROLE_LAB => [
        'pacientes' => ['read', 'list'],
        'dashboard' => ['read']
    ],
    ROLE_AUDITOR => [
        'reportes' => ['read', 'list'],
        'dashboard' => ['read']
    ],
    ROLE_PATIENT => [
        'pacientes' => ['read'],
        'citas' => ['read', 'list'],
        'dashboard' => ['read']
    ]
];

// Hacer disponible globalmente
global $PERMISSIONS;
