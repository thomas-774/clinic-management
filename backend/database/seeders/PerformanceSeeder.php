<?php

namespace Database\Seeders;

use App\Enums\HistoryType;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Drug;
use App\Models\User;
use App\Support\ClinicContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * The 5-year dataset of NFR-P.1 (T11-09): 10,000 patients and five years of
 * appointments and visits at up to 50 a day, with payments in installments,
 * history entries and prescriptions. Medical text is encrypted like the app
 * stores it, since decrypting it is part of the response time.
 *
 * Never part of `db:seed`. Run it on its own database:
 *   php artisan migrate:fresh --force
 *   php artisan db:seed --class=PerformanceSeeder --force
 *
 * Rows are bulk-inserted with their ids set here, so no row has to be read
 * back; the whole set takes about 15 seconds.
 */
class PerformanceSeeder extends Seeder
{
    /** Patients to create. */
    public int $patients = 10_000;

    /** Days of history before today. */
    public int $days = 5 * 365;

    /** Days after today with booked appointments (the booking window). */
    public int $futureDays = 30;

    /** Most visits on one day (appointments and walk-ins). */
    public int $maxPerDay = 50;

    /**
     * ANALYZE the tables at the end. Right after a bulk insert MySQL's index
     * statistics are stale and it picks slow plans (reports 16 → 82 ms, the
     * payments table 12 → 430 ms). Off in tests: ANALYZE commits at once.
     */
    public bool $analyze = true;

    private const TABLES = ['users', 'patients', 'medical_history_entries', 'appointments', 'visits', 'payments', 'prescriptions', 'prescription_items'];

    /** Rows per INSERT. */
    private const CHUNK = 1000;

    /** 15-minute slots, 10:00–22:00: 48 slots a day. */
    private const SLOT_MINUTES = 15;

    private const DAY_START = '10:00:00';

    private const DAY_END = '22:00:00';

    private const SLOTS_PER_DAY = 48;

    private const AMOUNTS = [300, 500, 750, 1000, 1500, 2000, 3000, 5000];

    private const WORK_DONE = [
        'حشو كمبوزيت للضرس السفلي الأيمن',
        'تنظيف جير وتلميع',
        'خلع ضرس العقل العلوي الأيسر',
        'علاج عصب - الجلسة الأولى',
        'علاج عصب - الجلسة الثانية وحشو نهائي',
        'تركيب طربوش زيركون',
        'Scaling and polishing, oral hygiene instructions',
        'Composite filling, lower left first molar',
        'Root canal treatment, upper right premolar',
        'Extraction of lower right third molar, sutures placed',
    ];

    private const ILLNESSES = [
        'ألم في الضرس السفلي منذ أسبوع',
        'نزيف من اللثة عند التفريش',
        'حساسية من البارد والساخن',
        'Swelling of the lower jaw for two days',
        'Broken filling, upper left molar',
    ];

    private const HISTORY = [
        'condition' => ['سكر من النوع الثاني', 'ضغط مرتفع', 'Asthma', 'Hypothyroidism'],
        'allergy' => ['حساسية من البنسلين', 'Allergy to amoxicillin', 'Latex allergy'],
        'surgery' => ['استئصال الزائدة الدودية', 'Knee replacement 2019'],
        'medication' => ['Metformin 500 mg twice a day', 'أسبرين 81 مجم يومياً'],
        'note' => ['يخاف من الحقن', 'Prefers evening appointments'],
    ];

    private const HISTORY_DETAILS = [
        'تحت المتابعة مع الطبيب الباطني',
        'Controlled with medication, last check-up three months ago',
        'Rash and itching after the last course',
        null,
    ];

    private const INSTRUCTIONS = [
        'قرص كل 8 ساعات بعد الأكل لمدة 5 أيام',
        'كبسولة كل 12 ساعة لمدة أسبوع',
        'مضمضة مرتين يومياً لمدة أسبوع',
        '1 tablet every 12 hours after meals for 5 days',
        '1 capsule every 8 hours for 3 days',
    ];

    private const RX_NOTES = ['أكل طري لمدة يومين', 'Avoid hot drinks for 24 hours', null];

    private const FIRST_NAMES = ['Ahmed', 'Mohamed', 'Mahmoud', 'Omar', 'Youssef', 'Mona', 'Sara', 'Nour', 'Hana', 'Mariam', 'محمد', 'أحمد', 'مريم', 'سارة', 'يوسف', 'نور'];

    private const LAST_NAMES = ['Hassan', 'Ibrahim', 'Mostafa', 'Ali', 'Saeed', 'Fathy', 'Kamal', 'حسن', 'إبراهيم', 'مصطفى', 'علي', 'سعيد'];

    /** @var array<string, list<array<string, mixed>>> rows waiting for INSERT, by table */
    private array $buffers = [];

    /** @var array<string, int> next id per table */
    private array $ids = [];

    private string $now;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('PerformanceSeeder fills the database with fake patients; it never runs in production.');
        }

        $this->call([DoctorSeeder::class, AssistantSeeder::class, DrugSeeder::class]);

        $doctor = app(ClinicContext::class)->doctor();
        $assistantId = (int) User::query()->where('role', UserRole::Assistant)->value('id');
        $this->configureSchedule($doctor);

        $this->now = now()->format('Y-m-d H:i:s');
        foreach (self::TABLES as $table) {
            $this->ids[$table] = (int) DB::table($table)->max('id') + 1;
        }

        $patientIds = $this->seedPatients();
        $drugs = Drug::query()->where('is_active', true)->get(['id', 'trade_name', 'form'])->all();

        $today = Carbon::today();
        $nowTime = Carbon::now();
        // Patients holding a future appointment (BR-4: at most one each).
        $holding = [];

        // Days by offset from today, not by comparing Carbons: Egypt's summer
        // time starts at midnight, so such a day's "midnight" is 01:00.
        for ($offset = -$this->days; $offset <= $this->futureDays; $offset++) {
            $day = $today->copy()->addDays($offset);
            if ($day->isFriday()) {
                continue;
            }

            $count = $offset > 0 ? random_int(10, 25) : random_int(30, self::SLOTS_PER_DAY);
            $slots = array_slice($this->shuffled(range(0, self::SLOTS_PER_DAY - 1)), 0, $count);
            sort($slots);
            $visitsToday = 0;

            foreach ($slots as $slot) {
                $start = $day->copy()->setTimeFromTimeString(self::DAY_START)->addMinutes($slot * self::SLOT_MINUTES);

                if ($offset > 0 || ($offset === 0 && $start->gt($nowTime))) {
                    $patientId = $this->freePatient($patientIds, $holding);
                    $this->appointment($doctor->id, $patientId, $start, 'booked');

                    continue;
                }

                $patientId = $patientIds[array_rand($patientIds)];
                $roll = random_int(1, 100);
                $status = $roll <= 85 ? 'completed' : ($roll <= 93 ? 'cancelled' : 'no_show');
                $appointmentId = $this->appointment($doctor->id, $patientId, $start, $status);

                if ($status === 'completed') {
                    $this->visit($patientId, $appointmentId, $day, $today, $doctor->id, $assistantId, $drugs);
                    $visitsToday++;
                }
            }

            // Walk-ins, never past the day's cap.
            if ($offset <= 0) {
                $walkIns = min(random_int(0, 3), $this->maxPerDay - $visitsToday);
                for ($i = 0; $i < $walkIns; $i++) {
                    $this->visit($patientIds[array_rand($patientIds)], null, $day, $today, $doctor->id, $assistantId, $drugs);
                }
            }

            if (count($this->buffers['payments'] ?? []) >= self::CHUNK) {
                $this->flush();
            }
        }

        $this->flush();

        if ($this->analyze && DB::getDriverName() === 'mysql') {
            DB::statement('ANALYZE TABLE '.implode(', ', [...self::TABLES, 'drugs']));
        }
    }

    /**
     * 15-minute slots 10:00–22:00 on six days, so 50 visits fit in a day.
     */
    private function configureSchedule(User $doctor): void
    {
        $doctor->doctorSetting()->update(['slot_duration_minutes' => self::SLOT_MINUTES]);
        $doctor->workingHours()->update(['start_time' => self::DAY_START, 'end_time' => self::DAY_END]);
    }

    /**
     * @return list<int> patient ids
     */
    private function seedPatients(): array
    {
        $password = Hash::make('password');
        $patientIds = [];

        for ($i = 0; $i < $this->patients; $i++) {
            $userId = $this->ids['users']++;
            $this->buffer('users', [
                'id' => $userId,
                'name' => $this->pick(self::FIRST_NAMES).' '.$this->pick(self::LAST_NAMES).' '.$this->pick(self::LAST_NAMES),
                // 0155xxxxxxx: never one of the demo accounts.
                'phone' => '0155'.str_pad((string) $i, 7, '0', STR_PAD_LEFT),
                'email' => random_int(1, 4) === 1 ? "patient{$i}@example.com" : null,
                'password' => $password,
                'role' => UserRole::Patient->value,
                'is_active' => true,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            $patientId = $this->ids['patients']++;
            $patientIds[] = $patientId;
            $illness = random_int(1, 10) <= 7 ? $this->pick(self::ILLNESSES) : null;
            $this->buffer('patients', [
                'id' => $patientId,
                'user_id' => $userId,
                'address' => random_int(1, 200).' شارع التحرير، الجيزة',
                'date_of_birth' => Carbon::today()->subDays(random_int(5 * 365, 80 * 365))->toDateString(),
                'gender' => random_int(0, 1) ? 'male' : 'female',
                'current_illness' => $illness === null ? null : Crypt::encryptString($illness),
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            for ($h = random_int(0, 6); $h > 0; $h--) {
                $type = $this->pick(HistoryType::cases());
                $details = $this->pick(self::HISTORY_DETAILS);
                $this->buffer('medical_history_entries', [
                    'id' => $this->ids['medical_history_entries']++,
                    'patient_id' => $patientId,
                    'type' => $type->value,
                    'title' => Crypt::encryptString($this->pick(self::HISTORY[$type->value])),
                    'details' => $details === null ? null : Crypt::encryptString($details),
                    'patient_visible' => random_int(0, 1) === 1,
                    'recorded_on' => Carbon::today()->subDays(random_int(0, $this->days))->toDateString(),
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);
            }

            if (count($this->buffers['users']) >= self::CHUNK) {
                $this->flush();
            }
        }

        $this->flush();

        return $patientIds;
    }

    private function appointment(int $doctorId, int $patientId, Carbon $start, string $status): int
    {
        $id = $this->ids['appointments']++;
        $this->buffer('appointments', [
            'id' => $id,
            'doctor_id' => $doctorId,
            'patient_id' => $patientId,
            'start_at' => $start->format('Y-m-d H:i:s'),
            'end_at' => $start->copy()->addMinutes(self::SLOT_MINUTES)->format('Y-m-d H:i:s'),
            'status' => $status,
            'checked_in_at' => $status === 'completed' ? $start->format('Y-m-d H:i:s') : null,
            'cancelled_at' => $status === 'cancelled' ? $start->copy()->subDay()->format('Y-m-d H:i:s') : null,
            'created_at' => $start->copy()->subDays(random_int(1, 20))->format('Y-m-d H:i:s'),
            'updated_at' => $start->format('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    /**
     * A visit with its payments: 70 % paid at once, 20 % in 2–3 installments
     * (the later ones up to 60 days after, never in the future), 10 % still
     * owing part. About 30 % come with a prescription of 1–3 drugs.
     *
     * @param  list<Drug>  $drugs
     */
    private function visit(int $patientId, ?int $appointmentId, Carbon $day, Carbon $today, int $doctorId, int $assistantId, array $drugs): void
    {
        $visitId = $this->ids['visits']++;
        $total = $this->pick(self::AMOUNTS);
        $at = $day->copy()->setTime(random_int(10, 21), random_int(0, 59))->format('Y-m-d H:i:s');

        $this->buffer('visits', [
            'id' => $visitId,
            'patient_id' => $patientId,
            'appointment_id' => $appointmentId,
            'visit_date' => $day->toDateString(),
            'work_done' => Crypt::encryptString($this->pick(self::WORK_DONE)),
            'total_amount' => $total,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        $roll = random_int(1, 100);
        $amounts = match (true) {
            $roll <= 70 => [$total],
            $roll <= 90 => $this->installments($total, random_int(2, 3)),
            default => [intdiv($total, 2)],
        };

        foreach ($amounts as $n => $amount) {
            $paidOn = $n === 0 ? $day->copy() : $day->copy()->addDays(random_int(7, 60) * $n);
            if ($paidOn->toDateString() > $today->toDateString()) {
                break;
            }
            $this->buffer('payments', [
                'id' => $this->ids['payments']++,
                'visit_id' => $visitId,
                'amount' => $amount,
                'method' => $this->pick(PaymentMethod::cases())->value,
                'paid_at' => $paidOn->setTime(random_int(10, 21), random_int(0, 59))->format('Y-m-d H:i:s'),
                'recorded_by' => random_int(0, 1) ? $doctorId : $assistantId,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }

        if ($drugs === [] || random_int(1, 10) > 3) {
            return;
        }

        $prescriptionId = $this->ids['prescriptions']++;
        $notes = $this->pick(self::RX_NOTES);
        $this->buffer('prescriptions', [
            'id' => $prescriptionId,
            'patient_id' => $patientId,
            'doctor_id' => $doctorId,
            'visit_id' => $visitId,
            'issued_on' => $day->toDateString(),
            'notes' => $notes === null ? null : Crypt::encryptString($notes),
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        for ($position = 1, $items = random_int(1, 3); $position <= $items; $position++) {
            $drug = $this->pick($drugs);
            $this->buffer('prescription_items', [
                'id' => $this->ids['prescription_items']++,
                'prescription_id' => $prescriptionId,
                'drug_id' => $drug->id,
                'drug_name' => $drug->trade_name,
                'drug_form' => $drug->form,
                'instructions' => Crypt::encryptString($this->pick(self::INSTRUCTIONS)),
                'position' => $position,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }

    /**
     * $total split into $parts whole amounts that add up to it.
     *
     * @return list<int>
     */
    private function installments(int $total, int $parts): array
    {
        $first = intdiv($total, $parts) + $total % $parts;

        return [$first, ...array_fill(0, $parts - 1, intdiv($total, $parts))];
    }

    /**
     * A patient without a future appointment, marked as holding one now.
     *
     * @param  list<int>  $patientIds
     * @param  array<int, true>  $holding
     */
    private function freePatient(array $patientIds, array &$holding): int
    {
        do {
            $id = $patientIds[array_rand($patientIds)];
        } while (isset($holding[$id]) && count($holding) < count($patientIds));

        $holding[$id] = true;

        return $id;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function buffer(string $table, array $row): void
    {
        $this->buffers[$table][] = $row;
    }

    /**
     * INSERT every waiting row, parents before children (foreign keys).
     */
    private function flush(): void
    {
        foreach (self::TABLES as $table) {
            foreach (array_chunk($this->buffers[$table] ?? [], self::CHUNK) as $rows) {
                DB::table($table)->insert($rows);
            }
            $this->buffers[$table] = [];
        }
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $items
     * @return T
     */
    private function pick(array $items): mixed
    {
        return $items[array_rand($items)];
    }

    /**
     * @param  list<int>  $items
     * @return list<int>
     */
    private function shuffled(array $items): array
    {
        shuffle($items);

        return $items;
    }
}
