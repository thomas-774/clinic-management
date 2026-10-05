<?php

namespace Database\Seeders;

use App\Models\Drug;
use Illuminate\Database\Seeder;

class DrugSeeder extends Seeder
{
    public const FILE = 'data/drugs.json';

    /**
     * The drug catalogue transcribed from Drugs-for-Dentistry.pdf (FR-J.1),
     * without prices. Rows are upserted by seed_key, so running it again
     * updates instead of duplicating. Drugs the doctor added (seed_key NULL)
     * are never touched, and is_active is left alone so a drug the doctor hid
     * stays hidden.
     */
    public function run(): void
    {
        foreach (self::entries() as $entry) {
            Drug::updateOrCreate(['seed_key' => $entry['seed_key']], $entry);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function entries(): array
    {
        return json_decode(
            file_get_contents(database_path(self::FILE)),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
