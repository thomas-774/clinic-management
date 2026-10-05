<?php

use App\Enums\DrugCategory;
use App\Models\Drug;
use App\Models\User;
use Database\Seeders\DrugSeeder;

// T9-03: drug search and catalogue API (FR-J.2, J.3, J.6, RX-3).

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    $this->doctor = User::factory()->doctor()->create();
});

function validDrug(array $overrides = []): array
{
    return [
        'trade_name' => 'Dentofix 500 mg',
        'form' => 'tablets',
        'pack' => '20 tabs',
        'category' => 'analgesic_sedative',
        'active_ingredients' => [
            ['name' => 'paracetamol', 'note' => 'مسكن وخافض للحرارة'],
            ['name' => 'caffeine', 'note' => ''],
        ],
        'uses' => "مسكن لآلام الأسنان\nخافض للحرارة",
        'warnings' => 'لا يؤخذ مع أدوية أخرى تحتوي على باراسيتامول',
        'suggested_dose' => 'قرص كل 8 ساعات',
        ...$overrides,
    ];
}

describe('GET /doctor/drugs/search (seeded catalogue)', function () {
    beforeEach(fn () => $this->seed(DrugSeeder::class));

    it('lists Augmentin entries first for "aug"', function () {
        $names = $this->actingAs($this->doctor)->getJson('/api/v1/doctor/drugs/search?q=aug')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'trade_name', 'form', 'pack', 'category', 'short_use']], 'message'])
            ->json('data.*.trade_name');

        $augmentin = Drug::where('trade_name', 'like', 'Augmentin%')->count();

        expect($augmentin)->toBeGreaterThan(0)
            ->and(array_slice($names, 0, $augmentin))->each->toStartWith('Augmentin');
    });

    it('is case-insensitive', function () {
        $lower = $this->actingAs($this->doctor)->getJson('/api/v1/doctor/drugs/search?q=augm')->json('data.*.id');
        $upper = $this->actingAs($this->doctor)->getJson('/api/v1/doctor/drugs/search?q=AUGM')->json('data.*.id');

        expect($lower)->not->toBeEmpty()->and($upper)->toBe($lower);
    });

    it('finds Augmentin, Flumox and Hibiotic by the ingredient "amoxicillin"', function () {
        $names = collect($this->actingAs($this->doctor)->getJson('/api/v1/doctor/drugs/search?q=amoxicillin')
            ->assertOk()
            ->json('data.*.trade_name'));

        foreach (['Augmentin', 'Flumox', 'Hibiotic'] as $brand) {
            expect($names->contains(fn ($name) => str_starts_with($name, $brand)))->toBeTrue("{$brand} not found");
        }
    });

    it('returns at most 15 results', function () {
        expect(Drug::active()->matching('amoxicillin')->count())->toBeGreaterThan(15);

        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/drugs/search?q=amoxicillin')
            ->assertOk()
            ->assertJsonCount(15, 'data');
    });

    it('returns an empty list for a query under 2 characters', function (string $q) {
        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/drugs/search?q='.urlencode($q))
            ->assertOk()
            ->assertExactJson(['data' => [], 'message' => null]);
    })->with(['', 'a', ' a ']);
});

describe('GET /doctor/drugs/search ranking', function () {
    it('ranks trade-name prefix, then trade-name contains, then ingredient only', function () {
        Drug::factory()->create(['trade_name' => 'Zeta Gelcaine', 'active_ingredients' => [['name' => 'x', 'note' => null]]]);
        Drug::factory()->create(['trade_name' => 'Other', 'active_ingredients' => [['name' => 'Gelatin', 'note' => null]]]);
        Drug::factory()->create(['trade_name' => 'Gel B', 'active_ingredients' => [['name' => 'y', 'note' => null]]]);
        Drug::factory()->create(['trade_name' => 'gel A', 'active_ingredients' => [['name' => 'z', 'note' => null]]]);
        Drug::factory()->create(['trade_name' => 'Unrelated', 'active_ingredients' => [['name' => 'w', 'note' => null]]]);

        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/drugs/search?q=gel')
            ->assertOk()
            ->assertJsonPath('data.*.trade_name', ['gel A', 'Gel B', 'Zeta Gelcaine', 'Other']);
    });

    it('treats % and _ as plain text', function () {
        Drug::factory()->create(['trade_name' => 'Plain']);

        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/drugs/search?q=%25%25')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('returns the first line of uses as short_use', function () {
        Drug::factory()->create(['trade_name' => 'Dentofix', 'uses' => "مسكن لآلام الأسنان\nخافض للحرارة"]);

        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/drugs/search?q=dento')
            ->assertJsonPath('data.0.short_use', 'مسكن لآلام الأسنان');
    });

    it('never suggests a hidden drug, even by ingredient (RX-3)', function () {
        Drug::factory()->hidden()->create(['trade_name' => 'Hiddenol', 'active_ingredients' => [['name' => 'hiddenine', 'note' => null]]]);

        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/drugs/search?q=hidden')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });
});

describe('GET /doctor/drugs/{drug}', function () {
    it('returns the full drug for the side note, hidden ones too', function () {
        $drug = Drug::factory()->hidden()->create(validDrug());

        $this->actingAs($this->doctor)->getJson("/api/v1/doctor/drugs/{$drug->id}")
            ->assertOk()
            ->assertJsonPath('data.trade_name', 'Dentofix 500 mg')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.active_ingredients.0', ['name' => 'paracetamol', 'note' => 'مسكن وخافض للحرارة'])
            ->assertJsonPath('data.warnings', 'لا يؤخذ مع أدوية أخرى تحتوي على باراسيتامول')
            ->assertJsonStructure(['data' => ['id', 'trade_name', 'form', 'pack', 'category', 'active_ingredients', 'uses', 'warnings', 'suggested_dose', 'source_page', 'is_active']]);
    });

    it('answers 404 for an unknown drug', function () {
        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/drugs/999999')->assertNotFound();
    });
});

describe('GET /doctor/drugs (catalogue)', function () {
    it('pages active drugs by name, filtered by search and category', function () {
        Drug::factory()->category(DrugCategory::Antibiotic)->create(['trade_name' => 'Bactrex']);
        Drug::factory()->category(DrugCategory::Antibiotic)->create(['trade_name' => 'Amoxil']);
        Drug::factory()->category(DrugCategory::Calcium)->create(['trade_name' => 'Calcimax']);
        Drug::factory()->category(DrugCategory::Antibiotic)->hidden()->create(['trade_name' => 'Abandoned']);

        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/drugs')
            ->assertOk()
            ->assertJsonPath('data.*.trade_name', ['Amoxil', 'Bactrex', 'Calcimax'])
            ->assertJsonPath('meta.total', 3);

        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/drugs?category=antibiotic&include_hidden=1')
            ->assertJsonPath('data.*.trade_name', ['Abandoned', 'Amoxil', 'Bactrex']);

        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/drugs?search=calc')
            ->assertJsonPath('data.*.trade_name', ['Calcimax']);
    });

    it('rejects an unknown category', function () {
        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/drugs?category=sweets')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);
    });
});

describe('POST /doctor/drugs', function () {
    it('adds a drug the doctor can search for', function () {
        $this->actingAs($this->doctor)->postJson('/api/v1/doctor/drugs', validDrug())
            ->assertCreated()
            ->assertJsonPath('message', 'Drug added.')
            ->assertJsonPath('data.category', 'analgesic_sedative')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.active_ingredients.1', ['name' => 'caffeine', 'note' => null]);

        $drug = Drug::sole();
        expect($drug->seed_key)->toBeNull()->and($drug->source_page)->toBeNull();

        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/drugs/search?q=caffeine')
            ->assertJsonPath('data.0.id', $drug->id);
    });

    it('validates the fields and ingredients', function (array $overrides, string $error) {
        $this->actingAs($this->doctor)->postJson('/api/v1/doctor/drugs', validDrug($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$error]);

        expect(Drug::count())->toBe(0);
    })->with([
        'no trade name' => [['trade_name' => ''], 'trade_name'],
        'no form' => [['form' => null], 'form'],
        'unknown category' => [['category' => 'sweets'], 'category'],
        'no uses' => [['uses' => ''], 'uses'],
        'no ingredients' => [['active_ingredients' => []], 'active_ingredients'],
        '11 ingredients' => [['active_ingredients' => array_fill(0, 11, ['name' => 'x'])], 'active_ingredients'],
        'ingredient without name' => [['active_ingredients' => [['name' => '', 'note' => 'x']]], 'active_ingredients.0.name'],
        'trade name too long' => [['trade_name' => str_repeat('a', 151)], 'trade_name'],
    ]);

    it('ignores a price and a seed_key in the request', function () {
        $this->actingAs($this->doctor)->postJson('/api/v1/doctor/drugs', validDrug(['price' => 25, 'seed_key' => 'hack']))
            ->assertCreated()
            ->assertJsonMissingPath('data.price');

        expect(Drug::sole()->seed_key)->toBeNull();
    });

    it('returns localized validation messages', function () {
        $this->actingAs($this->doctor)->postJson('/api/v1/doctor/drugs', validDrug(['trade_name' => '']), ['Accept-Language' => 'ar'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.trade_name.0', 'حقل الاسم التجاري مطلوب.');

        $this->actingAs($this->doctor)->postJson('/api/v1/doctor/drugs', validDrug(['active_ingredients' => [['name' => '']]]))
            ->assertJsonPath('errors', ['active_ingredients.0.name' => ['The ingredient name field is required.']]);
    });
});

describe('PUT /doctor/drugs/{drug}', function () {
    it('edits any field', function () {
        $drug = Drug::factory()->create(validDrug());

        $this->actingAs($this->doctor)->putJson("/api/v1/doctor/drugs/{$drug->id}", validDrug([
            'trade_name' => 'Dentofix Forte',
            'active_ingredients' => [['name' => 'ibuprofen']],
            'warnings' => null,
        ]))
            ->assertOk()
            ->assertJsonPath('message', 'Drug updated.')
            ->assertJsonPath('data.trade_name', 'Dentofix Forte')
            ->assertJsonPath('data.active_ingredients', [['name' => 'ibuprofen', 'note' => null]])
            ->assertJsonPath('data.warnings', null);
    });

    it('hides and shows a drug with is_active alone', function () {
        $drug = Drug::factory()->create(validDrug());

        $this->actingAs($this->doctor)->putJson("/api/v1/doctor/drugs/{$drug->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.trade_name', 'Dentofix 500 mg');

        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/drugs/search?q=dento')->assertJsonCount(0, 'data');

        $this->actingAs($this->doctor)->putJson("/api/v1/doctor/drugs/{$drug->id}", ['is_active' => true])
            ->assertJsonPath('data.is_active', true);

        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/drugs/search?q=dento')->assertJsonCount(1, 'data');
    });

    it('rejects an invalid edit with 422', function (array $payload, string $error) {
        $drug = Drug::factory()->create(validDrug());

        $this->actingAs($this->doctor)->putJson("/api/v1/doctor/drugs/{$drug->id}", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$error]);

        expect($drug->refresh()->trade_name)->toBe('Dentofix 500 mg');
    })->with([
        'empty trade name' => [['trade_name' => ''], 'trade_name'],
        'no ingredients' => [['active_ingredients' => []], 'active_ingredients'],
        'is_active not boolean' => [['is_active' => 'maybe'], 'is_active'],
    ]);

    it('has no delete endpoint', function () {
        $drug = Drug::factory()->create();

        $this->actingAs($this->doctor)->deleteJson("/api/v1/doctor/drugs/{$drug->id}")->assertMethodNotAllowed();
        expect(Drug::count())->toBe(1);
    });
});
