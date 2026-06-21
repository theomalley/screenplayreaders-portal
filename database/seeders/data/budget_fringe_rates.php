<?php

// Fringe benefit rates — sourced from step-02-budget-calculations.js lines 2326-2370
// Last verified: 2026-02-11

return [
    ['slug' => 'fica',           'name' => 'FICA',            'rate' => 0.062000, 'ceiling' => 8853.60,   'hourly_addon' => null,  'sort_order' => 1],
    ['slug' => 'medicare',       'name' => 'Medicare',         'rate' => 0.014500, 'ceiling' => null,      'hourly_addon' => null,  'sort_order' => 2],
    ['slug' => 'fui',            'name' => 'FUI',              'rate' => 0.060000, 'ceiling' => 7000.00,   'hourly_addon' => null,  'sort_order' => 3],
    ['slug' => 'payroll',        'name' => 'Payroll Admin',    'rate' => 0.050000, 'ceiling' => null,      'hourly_addon' => null,  'sort_order' => 4],
    ['slug' => 'wga_pension',    'name' => 'WGA Pension',      'rate' => 0.112500, 'ceiling' => 225000.00, 'hourly_addon' => null,  'sort_order' => 5],
    ['slug' => 'wga_health',     'name' => 'WGA Health',       'rate' => 0.130000, 'ceiling' => 250000.00, 'hourly_addon' => null,  'sort_order' => 6],
    ['slug' => 'dga_pension',    'name' => 'DGA Pension',      'rate' => 0.087500, 'ceiling' => 250000.00, 'hourly_addon' => null,  'sort_order' => 7],
    ['slug' => 'dga_health',     'name' => 'DGA Health',       'rate' => 0.112500, 'ceiling' => 400000.00, 'hourly_addon' => null,  'sort_order' => 8],
    ['slug' => 'sag',            'name' => 'SAG-AFTRA',        'rate' => 0.210000, 'ceiling' => 232000.00, 'hourly_addon' => null,  'sort_order' => 9],
    ['slug' => 'iatse',          'name' => 'IATSE',            'rate' => 0.140000, 'ceiling' => null,      'hourly_addon' => 10.60, 'sort_order' => 10],
    ['slug' => 'teamsters',      'name' => 'Teamsters',        'rate' => 0.157190, 'ceiling' => null,      'hourly_addon' => 10.60, 'sort_order' => 11],
];
