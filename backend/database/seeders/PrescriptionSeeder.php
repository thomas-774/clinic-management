<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Drug;
use App\Models\Patient;
use App\Models\User;
use App\Services\PrescriptionService;
use Illuminate\Database\Seeder;

class PrescriptionSeeder extends Seeder
{
    /**
     * One demo prescription for each of the first two patients (T9-14):
     * catalogue lines by seed_key plus a free-text line.
     *
     * @var list<array{days_ago: int, notes: string|null, items: list<array{seed_key?: string, drug_name?: string, instructions: string}>}>
     */
    public const DEMO = [
        [
            'days_ago' => 3,
            'notes' => 'أكل طري لمدة يومين، ولا مشروبات ساخنة اليوم.',
            'items' => [
                ['seed_key' => 'augmentin-1g-tabs', 'instructions' => 'قرص كل 12 ساعة بعد الأكل لمدة 7 أيام'],
                ['seed_key' => 'brufen-400-tabs', 'instructions' => 'قرص بعد الأكل عند اللزوم، بحد أقصى 3 أقراص يوميا'],
                ['drug_name' => 'Panadol Extra', 'instructions' => '1 tablet when needed, at most 4 a day'],
            ],
        ],
        [
            'days_ago' => 10,
            'notes' => null,
            'items' => [
                ['seed_key' => 'flagyl-500-tabs', 'instructions' => 'قرص كل 8 ساعات بعد الأكل لمدة 5 أيام'],
                ['seed_key' => 'hexitol-mouthwash', 'instructions' => 'مضمضة 3 مرات يوميا بعد غسل الأسنان'],
            ],
        ],
    ];

    /**
     * Written through PrescriptionService, like the app, so the lines get
     * their name / form snapshots (RX-2). Patients that already have a
     * prescription are skipped, so running it again adds nothing.
     */
    public function run(PrescriptionService $prescriptions): void
    {
        $doctor = User::where('role', UserRole::Doctor)->first();
        $patients = Patient::orderBy('id')->take(count(self::DEMO))->get();

        if (! $doctor) {
            return;
        }

        foreach ($patients->values() as $index => $patient) {
            if ($patient->prescriptions()->exists()) {
                continue;
            }

            $demo = self::DEMO[$index];
            $drugIds = Drug::whereIn('seed_key', array_filter(array_column($demo['items'], 'seed_key')))->pluck('id', 'seed_key');

            $items = [];
            foreach ($demo['items'] as $item) {
                if (isset($item['seed_key'])) {
                    // A catalogue line whose drug is missing is left out rather than failing the seed.
                    if ($drugIds->has($item['seed_key'])) {
                        $items[] = ['drug_id' => $drugIds[$item['seed_key']], 'instructions' => $item['instructions']];
                    }
                } else {
                    $items[] = ['drug_name' => $item['drug_name'], 'instructions' => $item['instructions']];
                }
            }

            $prescriptions->save($patient, $doctor, [
                'issued_on' => today()->subDays($demo['days_ago'])->format('Y-m-d'),
                'notes' => $demo['notes'],
                'items' => $items,
            ]);
        }
    }
}
