<?php

namespace App\Enums;

/**
 * The 11 sections of Drugs-for-Dentistry.pdf (§5.1 drugs, FR-J.1).
 */
enum DrugCategory: string
{
    case MouthCleaning = 'mouth_cleaning';
    case SensitiveToothpaste = 'sensitive_toothpaste';
    case VitaminC = 'vitamin_c';
    case AntiInflammatory = 'anti_inflammatory';
    case Antibiotic = 'antibiotic';
    case AnalgesicSedative = 'analgesic_sedative';
    case Antifungal = 'antifungal';
    case Calcium = 'calcium';
    case CodLiverOil = 'cod_liver_oil';
    case LocalAnesthetic = 'local_anesthetic';
    case Other = 'other';
}
