<?php

use App\Enums\DrugCategory;
use App\Models\Drug;
use Database\Seeders\DrugSeeder;

describe('drugs.json (T9-02)', function () {
    it('gives every entry a trade name, form, valid category, ingredients and uses', function () {
        $categories = array_column(DrugCategory::cases(), 'value');

        foreach (DrugSeeder::entries() as $entry) {
            expect($entry['trade_name'] ?? null)->toBeString()->not->toBeEmpty()
                ->and($entry['form'] ?? null)->toBeString()->not->toBeEmpty()
                ->and($categories)->toContain($entry['category'])
                ->and($entry['active_ingredients'])->toBeArray()->not->toBeEmpty()
                ->and(trim($entry['uses'] ?? ''))->not->toBeEmpty()
                ->and($entry['seed_key'])->toMatch('/^[a-z0-9-]+$/');

            foreach ($entry['active_ingredients'] as $ingredient) {
                expect(array_keys($ingredient))->toBe(['name', 'note'])
                    ->and(trim($ingredient['name']))->not->toBeEmpty();
            }
        }
    });

    it('has unique seed_keys and covers all 11 PDF sections', function () {
        $entries = collect(DrugSeeder::entries());

        expect($entries->pluck('seed_key')->duplicates())->toBeEmpty()
            ->and($entries->pluck('category')->unique())->toHaveCount(11)
            ->and($entries->count())->toBeGreaterThanOrEqual(100);
    });

    it('never carries a price', function () {
        $keys = collect(DrugSeeder::entries())->flatMap(fn ($entry) => array_keys($entry))->unique();

        expect($keys->filter(fn ($key) => str_contains(strtolower($key), 'price')))->toBeEmpty()
            ->and(file_get_contents(database_path(DrugSeeder::FILE)))->not->toContain('جنيه');
    });
});

describe('DrugSeeder (T9-02)', function () {
    it('gives the same row count when run twice', function () {
        $this->seed(DrugSeeder::class);
        $count = Drug::count();
        $this->seed(DrugSeeder::class);

        expect($count)->toBe(count(DrugSeeder::entries()))
            ->and(Drug::count())->toBe($count);
    });

    it('updates seeded rows but never touches drugs the doctor added or hid', function () {
        $own = Drug::factory()->create(['trade_name' => 'Doctor drug', 'seed_key' => null]);
        $this->seed(DrugSeeder::class);

        $augmentin = Drug::where('seed_key', 'augmentin-1g-tabs')->sole();
        $augmentin->update(['uses' => 'edited', 'is_active' => false]);
        $this->seed(DrugSeeder::class);

        expect($augmentin->fresh()->uses)->not->toBe('edited')
            ->and($augmentin->fresh()->is_active)->toBeFalse()
            ->and($own->fresh()->trade_name)->toBe('Doctor drug')
            ->and(Drug::whereNull('seed_key')->count())->toBe(1);
    });

    it('stores ingredients that the search can find (amoxicillin → Augmentin, Flumox, Hibiotic)', function () {
        $this->seed(DrugSeeder::class);

        $names = Drug::all()
            ->filter(fn (Drug $drug) => collect($drug->active_ingredients)->contains(fn ($i) => str_contains($i['name'], 'amoxicillin')))
            ->pluck('trade_name');

        foreach (['Augmentin', 'Flumox', 'Hibiotic'] as $brand) {
            expect($names->contains(fn ($name) => str_starts_with($name, $brand)))->toBeTrue();
        }
    });
});
