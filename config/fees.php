<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Invoice Due Day
    |--------------------------------------------------------------------------
    |
    | The day of the month a generated invoice falls due. Monthly generation
    | stamps due_date as this day of the invoice's month/year. This is the
    | default consulted by the SettingsRepository stub until the settings table
    | arrives in Task 14.1.
    |
    */

    'invoice_due_day' => 10,

    /*
    |--------------------------------------------------------------------------
    | Partial Payment
    |--------------------------------------------------------------------------
    |
    | When false, a counter (cash) payment must settle the full outstanding
    | amount of an invoice; when true, a partial amount (0 < amount <=
    | outstanding) is accepted, leaving the invoice `partial`. Default consulted
    | by the SettingsRepository stub until the settings table arrives in 14.1.
    |
    */

    'partial_payment_enabled' => false,

];
