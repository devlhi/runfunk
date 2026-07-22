<?php

use Illuminate\Support\Facades\Schedule;

// Lepas slot yang pembayarannya tidak diselesaikan tepat waktu.
Schedule::command('funrun:release-expired')->hourly();
