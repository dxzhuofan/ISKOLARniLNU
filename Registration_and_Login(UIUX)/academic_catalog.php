<?php
declare(strict_types=1);

$catalog = [
    'COE' => [
        'name' => 'College of Education',
        'programs' => [
            'BECEd' => ['name' => 'Bachelor of Early Childhood Education', 'type' => 'Bachelor', 'requires_specialization' => false, 'specializations' => []],
            'BEEd' => ['name' => 'Bachelor of Elementary Education', 'type' => 'Bachelor', 'requires_specialization' => false, 'specializations' => []],
            'BSNEd' => ['name' => 'Bachelor of Special Needs Education', 'type' => 'Bachelor', 'requires_specialization' => false, 'specializations' => []],
            'BPEd' => ['name' => 'Bachelor of Physical Education', 'type' => 'Bachelor', 'requires_specialization' => false, 'specializations' => []],
            'BTLEd' => ['name' => 'Bachelor of Technology and Livelihood Education', 'type' => 'Bachelor', 'requires_specialization' => false, 'specializations' => []],
            'BSEd' => ['name' => 'Bachelor of Secondary Education', 'type' => 'Bachelor', 'requires_specialization' => true, 'specializations' => ['Science', 'Filipino', 'English', 'Social Science', 'Mathematics', 'Values Education']],
            'TCP' => ['name' => 'Teacher Certificate Program', 'type' => 'Certificate', 'requires_specialization' => false, 'specializations' => []],
        ],
    ],
    'CAS' => [
        'name' => 'College of Arts and Sciences',
        'programs' => [
            'BACOMM' => ['name' => 'Bachelor of Arts in Communication', 'type' => 'Bachelor', 'requires_specialization' => false, 'specializations' => []],
            'BAPOLS' => ['name' => 'Bachelor of Arts in Political Science', 'type' => 'Bachelor', 'requires_specialization' => false, 'specializations' => []],
            'BAEL' => ['name' => 'Bachelor of Arts in English Language', 'type' => 'Bachelor', 'requires_specialization' => false, 'specializations' => []],
            'BSSW' => ['name' => 'Bachelor of Science in Social Work', 'type' => 'Bachelor', 'requires_specialization' => false, 'specializations' => []],
            'BSBIO' => ['name' => 'Bachelor of Science in Biology', 'type' => 'Bachelor', 'requires_specialization' => true, 'specializations' => ['Medical Biology', 'Environmental Biology', 'Molecular Biology']],
            'BSIT' => ['name' => 'Bachelor of Science in Information Technology', 'type' => 'Bachelor', 'requires_specialization' => false, 'specializations' => []],
            'BLIS' => ['name' => 'Bachelor of Library and Information Science', 'type' => 'Bachelor', 'requires_specialization' => false, 'specializations' => []],
            'BMUS' => ['name' => 'Bachelor of Music in Music Education', 'type' => 'Bachelor', 'requires_specialization' => false, 'specializations' => []],
        ],
    ],
    'CME' => [
        'name' => 'College of Management and Entrepreneurship',
        'programs' => [
            'BSTM' => ['name' => 'Bachelor of Science in Tourism Management', 'type' => 'Bachelor', 'requires_specialization' => true, 'specializations' => ['Events Management', 'Travel Management']],
            'BSHM' => ['name' => 'Bachelor of Science in Hospitality Management', 'type' => 'Bachelor', 'requires_specialization' => true, 'specializations' => ['Hotel Operations Management', 'Restaurant Operations']],
            'BENTREP' => ['name' => 'Bachelor of Entrepreneurship', 'type' => 'Bachelor', 'requires_specialization' => true, 'specializations' => ['Clothing and Handicraft', 'Food Preparation']],
        ],
    ],
];

return $catalog;
