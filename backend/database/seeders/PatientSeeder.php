<?php

namespace Database\Seeders;

use App\Models\MedicalHistoryEntry;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

class PatientSeeder extends Seeder
{
    /**
     * Create 10 fake patients (password "password"), each with a few history entries.
     */
    public function run(): void
    {
        Patient::factory()
            ->count(10)
            ->has(
                MedicalHistoryEntry::factory()
                    ->count(3)
                    ->state(new Sequence(['patient_visible' => true], ['patient_visible' => false])),
            )
            ->create();
    }
}
