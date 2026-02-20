<?php

namespace App\Listeners;

use App\Events\AppStepCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleAppStepCompletion
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(AppStepCompleted $event): void
    {
        //
    }
}
