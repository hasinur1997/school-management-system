<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Teacher Late Threshold
    |--------------------------------------------------------------------------
    |
    | A teacher who checks in after this time of day (HH:MM, app timezone) is
    | marked `late` instead of `present`. This is the default consulted by the
    | SettingsRepository stub until the settings table arrives in Task 14.1.
    |
    */

    'teacher_late_threshold' => '09:00',

];
