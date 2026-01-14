<?php
require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = \App\Models\User::where('email', 'professor@example.com')->first();
if ($user) {
    echo "✓ User found: " . $user->name . "\n";
    echo "✓ Roles: " . $user->roles->pluck('name')->implode(', ') . "\n";
    echo "✓ Has teacher role: " . ($user->hasRole('teacher') ? 'Yes' : 'No') . "\n";

    if ($user->teacher) {
        echo "✓ Teacher: " . $user->teacher->name . "\n";
        echo "✓ Classrooms: " . $user->teacher->classrooms()->count() . "\n";
    }
} else {
    echo "✗ User not found\n";
}
