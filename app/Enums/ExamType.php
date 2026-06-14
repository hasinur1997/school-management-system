<?php

namespace App\Enums;

/**
 * The three exams held per class per session, stored as VARCHAR(20) on exams.
 * The (session, class, type) tuple is unique — each type occurs once per class
 * per session.
 */
enum ExamType: string
{
    case FirstSemester = 'first_semester';
    case SecondSemester = 'second_semester';
    case Final = 'final';
}
