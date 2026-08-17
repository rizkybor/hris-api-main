<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Employee Code Format
    |--------------------------------------------------------------------------
    |
    | [COUNTRY]JCD[YEAR]-[COMPANY ID][EMPLOYMENT TYPE][5-DIGIT ID]-[CHECK DIGIT]
    | e.g. IDJCD26-001F00125-3
    |
    */

    'country_code' => env('EMPLOYEE_CODE_COUNTRY', 'ID'),
    'group_prefix' => env('EMPLOYEE_CODE_GROUP_PREFIX', 'JCD'),
    'company_id' => env('EMPLOYEE_CODE_COMPANY_ID', '001'),
];
