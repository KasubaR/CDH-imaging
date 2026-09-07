<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackupScheduleTest extends TestCase
{
    public function test_backup_commands_are_registered_on_the_schedule(): void
    {
        Artisan::call('schedule:list');

        $output = Artisan::output();

        $this->assertStringContainsString('backup:database', $output);
        $this->assertStringContainsString('backup:images', $output);
        $this->assertStringContainsString('backup:prune', $output);
    }
}
