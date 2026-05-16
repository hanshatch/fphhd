<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class SourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['name' => 'Agencia',      'kind' => Source::KIND_AGENCY],
            ['name' => 'Capacitación', 'kind' => Source::KIND_TRAINING],
        ];

        foreach ($sources as $data) {
            Source::create($data);
        }
    }
}
