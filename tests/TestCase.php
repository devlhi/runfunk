<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Berpindah pengguna di tengah tes dimulai dari sesi bersih.
     *
     * Middleware AuthenticateSession mengikat sesi ke hash kata sandi pemiliknya
     * — begitu identitasnya berganti, hash di sesi tidak lagi cocok dan Laravel
     * mengeluarkan penggunanya. Itu justru perilaku yang kita inginkan di dunia
     * nyata (sesi curian mati begitu kata sandi diganti), tapi tes sering
     * berpindah peran dalam satu alur: peserta mendaftar, lalu panitia menyetujui.
     *
     * Di peramban, keduanya memang dua sesi berbeda. Jadi di sini sesinya
     * dikosongkan lebih dulu supaya tes meniru keadaan itu, bukan melemahkan
     * middleware-nya.
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        $this->flushSession();

        return parent::actingAs($user, $guard);
    }
}
