<?php

namespace Sofa\History\Commands;

use Illuminate\Console\Command;

class HistoryCommand extends Command
{
    public $signature = 'history';

    public $description = 'My command';

    public function handle()
    {
        $this->comment('All done');
    }
}
