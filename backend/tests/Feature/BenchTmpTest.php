<?php

use App\Models\MedicalHistoryEntry;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Models\Visit;

// Temporary before/after timing (not committed): same data, same requests, in-process.
it('times key endpoints', function () {
    $doctor = User::factory()->doctor()->create();
    $assistant = User::factory()->assistant()->create();
    $patients = Patient::factory()->count(200)->create(['current_illness' => 'ألم في الضرس السفلي منذ أسبوع']);
    foreach ($patients as $p) {
        MedicalHistoryEntry::factory()->count(3)->for($p)->create(['title' => 'حساسية', 'details' => 'Rash after amoxicillin']);
        $v = Visit::factory()->for($p)->create(['work_done' => 'حشو للضرس', 'total_amount' => 500, 'visit_date' => today()]);
        Payment::factory()->for($v)->create(['amount' => 200]);
        $rx = Prescription::factory()->for($p)->for($v)->create(['notes' => 'أكل طري']);
        PrescriptionItem::factory()->count(3)->for($rx)->create(['instructions' => 'قرص كل 8 ساعات']);
    }
    $p = $patients->first();
    $rx = Prescription::first();
    $cases = [
        'patients list' => fn () => $this->actingAs($doctor)->getJson('/api/v1/doctor/patients'),
        'patients search' => fn () => $this->actingAs($doctor)->getJson('/api/v1/doctor/patients?search=a'),
        'patient details' => fn () => $this->actingAs($doctor)->getJson("/api/v1/doctor/patients/{$p->id}"),
        'prescription show' => fn () => $this->actingAs($doctor)->getJson("/api/v1/doctor/prescriptions/{$rx->id}"),
        'prescriptions list' => fn () => $this->actingAs($doctor)->getJson("/api/v1/doctor/patients/{$p->id}/prescriptions"),
        'assistant patient' => fn () => $this->actingAs($assistant)->getJson("/api/v1/assistant/patients/{$p->id}"),
        'report month' => fn () => $this->actingAs($doctor)->getJson('/api/v1/doctor/reports/summary?period=month'),
        'schedule week' => fn () => $this->actingAs($doctor)->getJson('/api/v1/doctor/appointments?from='.today()->toDateString().'&to='.today()->addDays(6)->toDateString()),
        'history add' => fn () => $this->actingAs($doctor)->postJson("/api/v1/doctor/patients/{$p->id}/history", ['type' => 'note', 'title' => 'x', 'recorded_on' => today()->toDateString()]),
    ];
    $out = [];
    foreach ($cases as $name => $call) {
        $call()->assertSuccessful();
        $times = [];
        for ($i = 0; $i < 30; $i++) {
            app('auth')->forgetGuards();
            $t = hrtime(true);
            $call();
            $times[] = (hrtime(true) - $t) / 1e6;
        }
        sort($times);
        $out[] = sprintf('%-20s median %6.1f ms   p95 %6.1f ms', $name, $times[14], $times[28]);
    }
    fwrite(STDERR, "\n".implode("\n", $out)."\n");
    expect(true)->toBeTrue();
});
