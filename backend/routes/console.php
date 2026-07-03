<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('about:securewallet', function () {
    $this->info('SecureWallet API - INF781 Examen Final');
});
