<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

class TestEventClassrooms extends Command
{
    protected $signature = 'test:event-classrooms';
    protected $description = 'Test Event-Classroom many-to-many relationship';

    public function handle()
    {
        $this->info('Testing Event-Classroom relationship...');

        $event = Event::first();

        if (!$event) {
            $this->error('No events found in database');
            return;
        }

        $this->info("Event: {$event->name}");
        $this->info("Classrooms count: {$event->classrooms()->count()}");

        $classrooms = $event->classrooms()->get();
        if ($classrooms->isEmpty()) {
            $this->warn('No classrooms assigned to this event');
        } else {
            $this->info('Classrooms:');
            foreach ($classrooms as $classroom) {
                $this->info("  - {$classroom->name}");
            }
        }
    }
}
