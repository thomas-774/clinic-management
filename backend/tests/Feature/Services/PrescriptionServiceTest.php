<?php

use App\Models\Drug;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use App\Services\PrescriptionService;
use Illuminate\Validation\ValidationException;

// T9-05: PrescriptionService::save() checks RX-1 / RX-5 itself, not only through the Form Request.

beforeEach(function () {
    $this->service = app(PrescriptionService::class);
    $this->doctor = User::factory()->doctor()->create();
    $this->patient = Patient::factory()->create();
});

it('numbers the lines in the order they were written, even with shuffled keys', function () {
    $drug = Drug::factory()->create(['trade_name' => 'Brufen 400 mg']);

    // Laravel's validated() can hand the lines back as [1 => …, 0 => …].
    $prescription = $this->service->save($this->patient, $this->doctor, ['items' => [
        1 => ['drug_id' => $drug->id, 'instructions' => 'second'],
        0 => ['drug_name' => 'Panadol', 'instructions' => 'first'],
        2 => ['drug_name' => 'Rinse', 'instructions' => 'third'],
    ]]);

    expect($prescription->items->pluck('instructions')->all())->toBe(['first', 'second', 'third'])
        ->and($prescription->items->pluck('position')->all())->toBe([1, 2, 3])
        ->and($prescription->issued_on->isToday())->toBeTrue();
});

it('enforces RX-1 and RX-5', function (Closure $data, string $key) {
    try {
        $this->service->save($this->patient, $this->doctor, $data($this));
        $this->fail('Expected a ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($key);
    }

    expect(Prescription::count())->toBe(0);
})->with([
    'no lines' => [fn () => ['items' => []], 'items'],
    '16 lines' => [fn () => ['items' => array_fill(0, 16, ['drug_name' => 'x', 'instructions' => 'y'])], 'items'],
    'no drug' => [fn () => ['items' => [['instructions' => 'y']]], 'items.0.drug_name'],
    'no instructions' => [fn () => ['items' => [['drug_name' => 'x', 'instructions' => ' ']]], 'items.0.instructions'],
    "another patient's visit" => [fn () => ['visit_id' => Visit::factory()->create()->id, 'items' => [['drug_name' => 'x', 'instructions' => 'y']]], 'visit_id'],
]);
