<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CertificateVerifyController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\Panitia\AnnouncementController;
use App\Http\Controllers\Panitia\BibPrintController;
use App\Http\Controllers\Panitia\CategoryController;
use App\Http\Controllers\Panitia\CertificateSheetController;
use App\Http\Controllers\Panitia\CheckinController;
use App\Http\Controllers\Panitia\CommitteeCardController;
use App\Http\Controllers\Panitia\DashboardController as PanitiaDashboard;
use App\Http\Controllers\Panitia\EmailPreviewController;
use App\Http\Controllers\Panitia\NewsManageController;
use App\Http\Controllers\Panitia\NotificationController;
use App\Http\Controllers\Panitia\PaymentVerificationController;
use App\Http\Controllers\Panitia\RegistrationController as PanitiaRegistrations;
use App\Http\Controllers\Panitia\ReportController;
use App\Http\Controllers\Panitia\ResultController;
use App\Http\Controllers\Panitia\SettingController;
use App\Http\Controllers\Panitia\SponsorController;
use App\Http\Controllers\Panitia\UserController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentProofController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\ResultPublicController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SlotTransferController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');

/* ------------------------------------------------------ Mesin pencari */
Route::get('robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');

/* --------------------------------------------------------- Hasil lomba */
Route::get('hasil', [ResultPublicController::class, 'index'])->name('results.index');

// Pemeriksaan keaslian sertifikat lewat QR yang tercetak di lembarnya. Terbuka
// tanpa perlu masuk — yang memeriksa justru orang luar yang menerima sertifikat
// itu. Isinya tidak melebihi papan hasil yang memang sudah publik.
Route::get('verifikasi-sertifikat/{kode}', [CertificateVerifyController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('certificate.verify');

/* -------------------------------------------------------------- Berita */
Route::get('berita', [NewsController::class, 'index'])->name('news.index');
Route::get('berita/{news}', [NewsController::class, 'show'])->name('news.show');

Route::middleware(['jangan-indeks', 'auth'])->group(function () {
    // Dibatasi agar tidak bisa dibanjiri komentar.
    Route::post('berita/{news}/komentar', [NewsController::class, 'comment'])
        ->middleware('throttle:10,1')
        ->name('news.comment');
    Route::delete('komentar/{comment}', [NewsController::class, 'destroyComment'])->name('news.comment.destroy');
});

/* ---------------------------------------------------------------- Tamu */
// Halaman masuk & daftar tidak perlu masuk hasil pencarian — orang datang ke
// sini lewat tombol di halaman depan, bukan lewat Google.
Route::middleware(['jangan-indeks', 'guest'])->group(function () {
    Route::get('masuk', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('masuk', [AuthenticatedSessionController::class, 'store']);

    Route::get('daftar-akun', [RegisteredUserController::class, 'create'])->name('register');
    // Batasi pembuatan akun massal dari satu sumber.
    Route::post('daftar-akun', [RegisteredUserController::class, 'store'])->middleware('throttle:10,1');

    Route::get('lupa-kata-sandi', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('lupa-kata-sandi', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::get('atur-ulang-kata-sandi/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('atur-ulang-kata-sandi', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:6,1')
        ->name('password.update');
});

/* -------------------------------------------------------- Sudah masuk */
// Seluruh isinya data orang: e-tiket, nomor BIB, bukti bayar, sertifikat.
// Tidak satu pun boleh sampai ke mesin pencari.
Route::middleware(['jangan-indeks', 'auth'])->group(function () {
    Route::post('keluar', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dashboard', [RegistrationController::class, 'index'])->name('dashboard');

    /* ------------------------------------------------- Verifikasi email */
    Route::get('verifikasi-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    // Kode 6 angka hanya punya sejuta kemungkinan. Selain penghitung percobaan
    // per kode, lajunya juga direm di sini supaya tidak bisa digempur cepat.
    Route::post('verifikasi-email', [EmailVerificationController::class, 'confirm'])
        ->middleware('throttle:8,1')
        ->name('verification.confirm');
    Route::post('verifikasi-email/kirim-ulang', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:3,10')
        ->name('verification.resend');
    Route::get('verifikasi-email/{user}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:8,1'])
        ->name('verification.verify');

    // Slot lomba baru bisa diambil setelah emailnya terbukti benar.
    Route::middleware('email.terverifikasi')->group(function () {
        Route::get('pendaftaran/baru', [RegistrationController::class, 'create'])->name('registrations.create');
        Route::post('pendaftaran', [RegistrationController::class, 'store'])->name('registrations.store');
    });
    Route::get('pendaftaran/{registration}', [RegistrationController::class, 'show'])->name('registrations.show');
    Route::post('pendaftaran/{registration}/batal', [RegistrationController::class, 'cancel'])->name('registrations.cancel');
    Route::post('pendaftaran/{registration}/alihkan', [SlotTransferController::class, 'store'])
        ->middleware('throttle:5,10')
        ->name('registrations.transfer');
    Route::post('pendaftaran/{registration}/pembayaran', [PaymentController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('payments.store');
    Route::get('bukti-bayar/{payment}', [PaymentProofController::class, 'show'])->name('payments.proof');
    Route::get('sertifikat/{registration}', [CertificateController::class, 'show'])->name('certificate.show');

    Route::get('profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profil/kata-sandi', [ProfileController::class, 'updatePassword'])->name('profile.password');
});

/* ------------------------------------------------------------- Panitia */
Route::middleware(['jangan-indeks', 'auth', 'panitia'])->prefix('panitia')->name('panitia.')->group(function () {
    Route::get('/', PanitiaDashboard::class)->name('dashboard');

    Route::get('pendaftaran', [PanitiaRegistrations::class, 'index'])->name('registrations.index');
    Route::get('pendaftaran/ekspor', [PanitiaRegistrations::class, 'export'])->name('registrations.export');
    Route::get('pendaftaran/{registration}', [PanitiaRegistrations::class, 'show'])->name('registrations.show');
    Route::patch('pendaftaran/{registration}/catatan', [PanitiaRegistrations::class, 'updateNote'])->name('registrations.note');

    Route::post('pembayaran/{payment}/setujui', [PaymentVerificationController::class, 'approve'])->name('payments.approve');
    Route::post('pembayaran/{payment}/tolak', [PaymentVerificationController::class, 'reject'])->name('payments.reject');
    // Konfirmasi untuk pembayaran yang tidak punya bukti unggahan.
    Route::post('pendaftaran/{registration}/konfirmasi-manual', [PaymentVerificationController::class, 'manual'])
        ->name('payments.manual');

    Route::get('kategori', [CategoryController::class, 'index'])->name('categories.index');
    Route::patch('kategori/{category}', [CategoryController::class, 'update'])->name('categories.update');

    Route::get('sponsor', [SponsorController::class, 'index'])->name('sponsors.index');
    Route::post('sponsor', [SponsorController::class, 'store'])->name('sponsors.store');
    Route::patch('sponsor/{sponsor}', [SponsorController::class, 'update'])->name('sponsors.update');
    Route::delete('sponsor/{sponsor}', [SponsorController::class, 'destroy'])->name('sponsors.destroy');

    /* --- Kartu panitia: dicetak developer, diperiksa siapa pun panitia --- */
    // Pemeriksaan sengaja terbuka untuk semua panitia: yang berjaga di pintu
    // masuk belum tentu developer, tapi justru merekalah yang perlu memeriksa.
    Route::get('kartu-panitia/validasi', [CommitteeCardController::class, 'validasi'])->name('cards.validate');
    // Pas foto disimpan di disk privat; hanya pengelola yang boleh membukanya.
    Route::get('kartu-panitia/{user}/foto', [CommitteeCardController::class, 'foto'])->name('cards.photo');
    Route::post('kartu-panitia/validasi', [CommitteeCardController::class, 'periksa'])
        ->middleware('throttle:120,1')
        ->name('cards.check');

    Route::get('cetak-bib', [BibPrintController::class, 'index'])->name('bib.index');
    Route::get('cetak-bib/lembar', [BibPrintController::class, 'sheet'])->name('bib.sheet');
    Route::get('pendaftaran/{registration}/qr', [BibPrintController::class, 'qr'])->name('bib.qr');

    Route::get('berita', [NewsManageController::class, 'index'])->name('news.index');
    Route::post('berita', [NewsManageController::class, 'store'])->name('news.store');
    // Diikat lewat {news:id}, bukan slug seperti halaman publik. Judul berita
    // bisa diubah panitia — kalau terikat slug, tautan kelolanya berubah
    // setiap kali judulnya disunting.
    // POST (bukan PATCH) karena unggahan berkas tidak terkirim lewat PATCH
    // di banyak browser; formulirnya memakai _method spoofing Laravel.
    Route::post('berita/{news:id}', [NewsManageController::class, 'update'])->name('news.update');
    Route::delete('berita/{news:id}', [NewsManageController::class, 'destroy'])->name('news.destroy');

    Route::get('hasil', [ResultController::class, 'index'])->name('results.index');
    Route::post('hasil/{registration}', [ResultController::class, 'store'])->name('results.store');

    // Cetak sertifikat massal — satu halaman A4 lanskap per finisher.
    Route::get('cetak-sertifikat', [CertificateSheetController::class, 'index'])->name('certificates.index');
    Route::get('cetak-sertifikat/lembar', [CertificateSheetController::class, 'sheet'])->name('certificates.sheet');

    Route::get('laporan', [ReportController::class, 'index'])->name('reports.index');

    Route::get('kehadiran', [CheckinController::class, 'index'])->name('checkin.index');
    Route::post('kehadiran/{registration}', [CheckinController::class, 'toggle'])->name('checkin.toggle');
    // Batasnya dilonggarkan karena dipakai beruntun saat antrean race pack, dan
    // panitia sering berbagi satu akun sehingga jatahnya terbagi lintas ponsel.
    // Yang menjaga keaslian kode adalah tanda tangannya, bukan batas laju ini.
    Route::post('kehadiran/pindai/qr', [CheckinController::class, 'pindai'])
        ->middleware('throttle:600,1')
        ->name('checkin.scan');

    Route::get('pengumuman', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('pengumuman', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::patch('pengumuman/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
    Route::delete('pengumuman/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    Route::post('pengumuman/{announcement}/uji', [AnnouncementController::class, 'test'])
        ->middleware('throttle:10,1')
        ->name('announcements.test');
    // Dibatasi ketat: sekali tersebar, pesannya tidak bisa ditarik kembali.
    Route::post('pengumuman/{announcement}/kirim', [AnnouncementController::class, 'broadcast'])
        ->middleware('throttle:3,10')
        ->name('announcements.broadcast');

    Route::post('notifikasi/tandai-dibaca', [NotificationController::class, 'markSeen'])->name('notifications.seen');

    /* --- Khusus developer: kelola akun pengelola & pengaturan acara --- */
    Route::middleware('developer')->group(function () {
        Route::get('pengguna', [UserController::class, 'index'])->name('users.index');
        Route::post('pengguna', [UserController::class, 'store'])->name('users.store');
        Route::patch('pengguna/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('pengguna/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        // Sunting & pratinjau desain email, tanpa mengirim apa pun.
        Route::get('pratinjau-email', [EmailPreviewController::class, 'index'])->name('email-preview.index');
        Route::get('pratinjau-email/{template}', [EmailPreviewController::class, 'show'])->name('email-preview.show');
        Route::post('pratinjau-email/{template}/draf', [EmailPreviewController::class, 'draft'])->name('email-preview.draft');
        Route::patch('pratinjau-email/{template}', [EmailPreviewController::class, 'update'])->name('email-preview.update');
        Route::delete('pratinjau-email/{template}', [EmailPreviewController::class, 'reset'])->name('email-preview.reset');

        // Menerbitkan kartu hanya boleh developer — kartu yang dicetak sendiri
        // oleh panitia mana pun akan membuat pemeriksaan di lapangan tak berarti.
        Route::get('kartu-panitia', [CommitteeCardController::class, 'index'])->name('cards.index');
        Route::get('kartu-panitia/lembar', [CommitteeCardController::class, 'sheet'])->name('cards.sheet');
        Route::patch('kartu-panitia/{user}/jabatan', [CommitteeCardController::class, 'simpanJabatan'])->name('cards.title');
        Route::post('kartu-panitia/{user}/foto', [CommitteeCardController::class, 'unggahFoto'])->name('cards.photo.store');
        Route::delete('kartu-panitia/{user}/foto', [CommitteeCardController::class, 'hapusFoto'])->name('cards.photo.destroy');
        Route::post('kartu-panitia/{user}/terbitkan-ulang', [CommitteeCardController::class, 'terbitkanUlang'])->name('cards.reissue');

        Route::get('pengaturan', [SettingController::class, 'edit'])->name('settings.edit');
        Route::patch('pengaturan', [SettingController::class, 'update'])->name('settings.update');
        // Mengirim email sungguhan, jadi dibatasi supaya situs tidak bisa
        // dipakai jadi relai spam kalau akun developer sampai bocor.
        Route::post('pengaturan/uji-email', [SettingController::class, 'testMail'])
            ->middleware('throttle:5,10')
            ->name('settings.test-mail');
        Route::post('pengaturan/uji-wa', [SettingController::class, 'testWa'])
            ->middleware('throttle:5,10')
            ->name('settings.test-wa');
    });
});
