<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Services\SlotService;
use App\Support\ClinicContext;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * NFR-P.1 (T11-09): calls each key endpoint through the HTTP kernel as the
 * right role and prints p50 / p95 / p99 in ms against the budget. Meant for
 * the PerformanceSeeder dataset on its own database, with `config:cache` and
 * `route:cache`; see docs in tasks/phase-11-nfr/T11-09-performance-budget.md.
 *
 * Timed: the kernel's handle() + terminate(), i.e. routing, middleware
 * (rate limits included), validation, queries, audit rows, JSON or file.
 * Not timed: the Sanctum token lookup (the user is set on the guard, as in
 * the tests) and the web server in front of PHP.
 *
 * Writes (booking, visit save) are real and committed; the rows they create
 * are deleted after each call so a second run sees the same data.
 */
class RunBenchmark extends Command
{
    protected $signature = 'clinic:bench
        {--iterations=100 : Timed calls per endpoint}
        {--only= : Only endpoints whose name contains this text}';

    protected $description = 'Time the key API endpoints (p50 / p95 / p99) against the NFR-P.1 budget';

    /** NFR-P.1 budget in ms: [p95, p99]; null = no p99 target. */
    private const BUDGET = [300, 800];

    private const EXPORT_BUDGET = [1500, null];

    private const WARM_UP = 3;

    private Kernel $kernel;

    public function handle(Kernel $kernel): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('clinic:bench writes test rows; it never runs in production.');

            return self::FAILURE;
        }

        if (! $this->laravel->configurationIsCached() || ! $this->laravel->routesAreCached()) {
            $this->warn('Config or routes are not cached; run config:cache and route:cache for numbers that count.');
        }

        $this->kernel = $kernel;
        $iterations = max(1, (int) $this->option('iterations'));

        // The limiters stay in the path (they still read and write the cache), only higher.
        config(['clinic.rate_limits.writes' => PHP_INT_MAX, 'clinic.rate_limits.search' => PHP_INT_MAX]);

        $this->line(sprintf(
            'Dataset: %d patients, %d appointments, %d visits, %d payments, %d prescriptions. %d calls per endpoint.',
            Patient::count(), Appointment::count(), Visit::count(), DB::table('payments')->count(), DB::table('prescriptions')->count(), $iterations,
        ));

        $rows = [];
        $failed = false;

        foreach ($this->endpoints($iterations) as $name => $endpoint) {
            if ($this->option('only') && ! str_contains($name, (string) $this->option('only'))) {
                continue;
            }

            try {
                $times = $this->measure($endpoint, $iterations);
            } catch (\RuntimeException $e) {
                $this->error("{$name}: {$e->getMessage()}");
                $failed = true;

                continue;
            }

            [$p95Budget, $p99Budget] = $endpoint['budget'] ?? self::BUDGET;
            [$p50, $p95, $p99] = [self::percentile($times, 50), self::percentile($times, 95), self::percentile($times, 99)];
            $ok = $p95 < $p95Budget && ($p99Budget === null || $p99 < $p99Budget);
            $failed = $failed || ! $ok;

            $rows[] = [
                $name,
                number_format($p50, 1),
                number_format($p95, 1),
                number_format($p99, 1),
                $p95Budget.' / '.($p99Budget ?? '—'),
                $ok ? 'OK' : 'OVER',
            ];
        }

        $this->table(['Endpoint', 'p50 ms', 'p95 ms', 'p99 ms', 'Budget p95 / p99', ''], $rows);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Nearest-rank percentile of $times (ms).
     *
     * @param  list<float>  $times
     */
    public static function percentile(array $times, int $percent): float
    {
        sort($times);

        return $times[max(0, (int) ceil($percent / 100 * count($times)) - 1)];
    }

    /**
     * @param  array{user: User|Closure(int): User, request: Closure(int): array{0: string, 1: string, 2?: array<string, mixed>}, after?: Closure(Response): void, budget?: array{0: int, 1: ?int}}  $endpoint
     * @return list<float>
     */
    private function measure(array $endpoint, int $iterations): array
    {
        $times = [];

        for ($i = -self::WARM_UP; $i < $iterations; $i++) {
            $call = $i + self::WARM_UP;
            [$method, $uri, $body] = $endpoint['request']($call) + [2 => []];
            $user = $endpoint['user'] instanceof Closure ? $endpoint['user']($call) : $endpoint['user'];
            // A fresh user each call, so no relation stays loaded from the last one.
            $user = User::query()->findOrFail($user->getKey());
            $this->laravel['auth']->guard('sanctum')->setUser($user);

            $request = Request::create('/api/v1'.$uri, $method, server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'ar',
                'CONTENT_TYPE' => 'application/json',
            ], content: $body ? json_encode($body) : null);

            $start = hrtime(true);
            $response = $this->kernel->handle($request);
            $this->kernel->terminate($request, $response);
            $ms = (hrtime(true) - $start) / 1e6;

            if (! $response->isSuccessful()) {
                throw new \RuntimeException("{$method} {$uri} answered {$response->getStatusCode()}: ".mb_substr((string) $response->getContent(), 0, 300));
            }

            if (isset($endpoint['after'])) {
                $endpoint['after']($response);
            }

            if ($i >= 0) {
                $times[] = $ms;
            }
        }

        return $times;
    }

    /**
     * The endpoints of NFR-P.1, each with the role that calls it and a
     * request per call number (ids vary, so not one row is served from the
     * database's buffer pool over and over).
     *
     * @return array<string, array<string, mixed>>
     */
    private function endpoints(int $iterations): array
    {
        $calls = $iterations + self::WARM_UP;
        $doctor = app(ClinicContext::class)->doctor();
        $assistant = User::query()->where('role', UserRole::Assistant)->where('is_active', true)->firstOrFail();
        $patients = Patient::query()->whereHas('visits')->inRandomOrder()->limit($calls)->with('user')->get()->values();
        $visits = Visit::query()->inRandomOrder()->limit($calls)->pluck('id')->values();
        $today = today()->toDateString();
        $searches = $patients->flatMap(fn (Patient $p) => [mb_substr($p->user->name, 0, 4), substr($p->user->phone, 4, 5)])->values();
        $drugTerms = ['amo', 'بارا', 'ibu', 'met', 'كلور', 'flu'];
        $at = fn ($list, int $i) => $list[$i % count($list)];
        $bookings = $this->freeSlots($calls);
        $slotDay = Carbon::parse($bookings[0][1] ?? $today)->toDateString();

        return [
            'patients list' => ['user' => $doctor, 'request' => fn (int $i) => ['GET', '/doctor/patients?page='.(1 + $i % 5)]],
            'patients search' => ['user' => $doctor, 'request' => fn (int $i) => ['GET', '/doctor/patients?search='.urlencode($at($searches, $i))]],
            'patient details' => ['user' => $doctor, 'request' => fn (int $i) => ['GET', '/doctor/patients/'.$at($patients, $i)->id]],
            'patient details (assistant)' => ['user' => $assistant, 'request' => fn (int $i) => ['GET', '/assistant/patients/'.$at($patients, $i)->id]],
            'slots for a day' => ['user' => $at($patients, 0)->user, 'request' => fn () => ['GET', '/slots?date='.$slotDay]],
            'booking' => [
                // A different patient each call: one may hold only one future appointment (BR-4).
                'user' => fn (int $i) => $bookings[$i][0]->user,
                'request' => fn (int $i) => ['POST', '/patient/appointments', ['start_at' => $bookings[$i][1]]],
                'after' => fn (Response $r) => Appointment::query()->whereKey(json_decode($r->getContent(), true)['data']['id'])->delete(),
            ],
            'schedule day' => ['user' => $doctor, 'request' => fn () => ['GET', "/doctor/appointments?from={$today}&to={$today}"]],
            'schedule week' => ['user' => $doctor, 'request' => fn () => ['GET', '/doctor/appointments?from='.$today.'&to='.today()->addDays(6)->toDateString()]],
            "today's queue (assistant)" => ['user' => $assistant, 'request' => fn () => ['GET', "/assistant/appointments?from={$today}&to={$today}"]],
            'waiting to pay (assistant)' => ['user' => $assistant, 'request' => fn () => ['GET', '/assistant/visits/unpaid']],
            'visit save' => [
                'user' => $doctor,
                'request' => fn (int $i) => ['POST', '/doctor/visits', [
                    'patient_id' => $at($patients, $i)->id,
                    'work_done' => 'حشو كمبوزيت للضرس السفلي الأيمن',
                    'total_amount' => 750,
                    'paid_now' => 500,
                    'method' => 'cash',
                ]],
                'after' => fn (Response $r) => Visit::query()->whereKey(json_decode($r->getContent(), true)['data']['id'])->delete(),
            ],
            'report day' => ['user' => $doctor, 'request' => fn () => ['GET', '/doctor/reports/summary?period=day']],
            'report week' => ['user' => $doctor, 'request' => fn () => ['GET', '/doctor/reports/summary?period=week']],
            'report month' => ['user' => $doctor, 'request' => fn () => ['GET', '/doctor/reports/summary?period=month']],
            'report payments (month)' => ['user' => $doctor, 'request' => fn () => ['GET', '/doctor/reports/payments?from='.today()->startOfMonth()->toDateString()."&to={$today}"]],
            'report daily revenue' => ['user' => $doctor, 'request' => fn () => ['GET', '/doctor/reports/daily-revenue']],
            'report outstanding' => ['user' => $doctor, 'request' => fn () => ['GET', '/doctor/reports/outstanding']],
            'drug search' => ['user' => $doctor, 'request' => fn (int $i) => ['GET', '/doctor/drugs/search?q='.urlencode($at($drugTerms, $i))]],
            'visit export pdf' => ['user' => $doctor, 'request' => fn (int $i) => ['GET', '/doctor/visits/'.$at($visits, $i).'/export?format=pdf'], 'budget' => self::EXPORT_BUDGET],
            'visit export word' => ['user' => $doctor, 'request' => fn (int $i) => ['GET', '/doctor/visits/'.$at($visits, $i).'/export?format=docx'], 'budget' => self::EXPORT_BUDGET],
        ];
    }

    /**
     * $count pairs of [patient without a future appointment, free slot],
     * from tomorrow on, found before the timing starts.
     *
     * @return list<array{0: Patient, 1: string}>
     */
    private function freeSlots(int $count): array
    {
        $slots = SlotService::forClinic();
        $starts = [];
        for ($day = today()->addDay(); count($starts) < $count && $day->lte(today()->addDays($slots->settings()->booking_window_days)); $day->addDay()) {
            foreach ($slots->generate($day) as [$start]) {
                $starts[] = $start->format('Y-m-d\TH:i:sP');
            }
        }

        $patients = Patient::query()
            ->whereDoesntHave('appointments', fn ($q) => $q->whereIn('status', [AppointmentStatus::Booked, AppointmentStatus::CheckedIn])->where('start_at', '>', now()))
            ->with('user')
            ->inRandomOrder()
            ->limit($count)
            ->get();

        if (count($starts) < $count || $patients->count() < $count) {
            throw new \RuntimeException("Not enough free slots or patients for {$count} bookings.");
        }

        return $patients->values()->map(fn (Patient $p, int $i) => [$p, $starts[$i]])->all();
    }
}
