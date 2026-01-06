<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = \App\Models\User::first();

// Restore Nucleus Mining logistics
\App\Models\Blueprint::create([
    'user_id' => $user->id,
    'name' => 'Nucleus Mining logistics',
    'expected_amount' => 74900.00,
    'item_type' => 'income',
    'bucket' => 'needs',
    'nature' => 'private',
    'due_day' => 25,
    'due_day_tolerance' => 3,
    'amount_tolerance' => 5.00,
    'match_pattern' => 'NUCLEUS MININ',
    'match_type' => 'contains',
    'frequency' => 'monthly',
    'is_active' => true,
]);
echo "Created: Nucleus Mining logistics - R74,900.00\n";

// Restore OMLAC
\App\Models\Blueprint::create([
    'user_id' => $user->id,
    'name' => 'OMLAC',
    'expected_amount' => 177.46,
    'item_type' => 'income',
    'bucket' => 'needs',
    'nature' => 'private',
    'due_day' => null,
    'due_day_tolerance' => 3,
    'amount_tolerance' => 5.00,
    'match_pattern' => 'OMLAC',
    'match_type' => 'contains',
    'frequency' => 'monthly',
    'is_active' => true,
]);
echo "Created: OMLAC - R177.46\n";

echo "\nDone! Both income items restored.\n";
