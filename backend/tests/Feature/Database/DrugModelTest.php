<?php

use App\Enums\DrugCategory;
use App\Models\Drug;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

describe('drugs table (T9-01)', function () {
    it('has no price column', function () {
        $columns = collect(Schema::getColumnListing('drugs'));

        expect($columns->filter(fn ($c) => str_contains($c, 'price')))->toBeEmpty();
    });

    it('indexes (is_active, trade_name) and keeps seed_key unique', function () {
        expect(Schema::hasIndex('drugs', ['is_active', 'trade_name']))->toBeTrue()
            ->and(Schema::hasIndex('drugs', ['seed_key'], 'unique'))->toBeTrue();

        Drug::factory()->create(['seed_key' => 'flagyl-500-tabs']);

        expect(fn () => Drug::factory()->create(['seed_key' => 'flagyl-500-tabs']))
            ->toThrow(QueryException::class);
    });

    it('allows several drugs without a seed_key (added by the doctor)', function () {
        Drug::factory()->count(2)->create(['seed_key' => null]);

        expect(Drug::whereNull('seed_key')->count())->toBe(2);
    });
});

describe('Drug model (T9-01)', function () {
    it('saves and reads back its ingredients as a list of { name, note }', function () {
        $drug = Drug::factory()->create([
            'active_ingredients' => [
                ['name' => 'amoxicillin', 'note' => 'مضاد حيوي'],
                ['name' => 'clavulanic acid', 'note' => null],
            ],
        ]);

        expect($drug->fresh()->active_ingredients)->toBe([
            ['name' => 'amoxicillin', 'note' => 'مضاد حيوي'],
            ['name' => 'clavulanic acid', 'note' => null],
        ]);
    });

    it('casts category to the enum and is_active to bool, active by default', function () {
        $drug = Drug::create([
            'trade_name' => 'Flagyl 500 mg',
            'form' => 'tablets',
            'category' => DrugCategory::Antibiotic,
            'active_ingredients' => [['name' => 'metronidazole', 'note' => null]],
            'uses' => 'التهابات اللثة',
        ])->fresh();

        expect($drug->category)->toBe(DrugCategory::Antibiotic)
            ->and($drug->is_active)->toBeTrue();
    });

    it('has 11 categories, one per PDF section', function () {
        expect(DrugCategory::cases())->toHaveCount(11);
    });

    it('scopes to active drugs and has hidden() and category() factory states', function () {
        Drug::factory()->create();
        Drug::factory()->hidden()->create();
        $calcium = Drug::factory()->category(DrugCategory::Calcium)->create();

        expect(Drug::active()->count())->toBe(2)
            ->and(Drug::where('is_active', false)->count())->toBe(1)
            ->and($calcium->category)->toBe(DrugCategory::Calcium);
    });
});
