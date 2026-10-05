<?php

namespace App\Enums;

enum HistoryType: string
{
    case Condition = 'condition';
    case Allergy = 'allergy';
    case Surgery = 'surgery';
    case Medication = 'medication';
    case Note = 'note';
}
