<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /**
     * @use HasFactory<UserFactory>
     *
     * Trait MustVerifyEmail dipakai untuk hasVerifiedEmail(), tapi kontraknya
     * sengaja TIDAK diimplementasikan: kalau diimplementasikan, Laravel ikut
     * mengirim email verifikasi bawaannya sendiri di samping milik kita, jadi
     * pendaftar menerima dua email berbeda untuk satu pendaftaran.
     */
    use HasFactory, MustVerifyEmail, Notifiable;

    public const ROLE_PESERTA = 'peserta';

    public const ROLE_PANITIA = 'panitia';

    /** Developer: semua wewenang panitia, ditambah kelola akun & pengaturan acara. */
    public const ROLE_DEVELOPER = 'developer';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'gender',
        'birth_date',
        'city',
        'address',
        // Diisi saat developer membuat akun pengelola. Tanpa ada di sini, Laravel
        // membuangnya diam-diam dan akun panitia baru terjebak di halaman verifikasi.
        'email_verified_at',
        'card_version',
        'card_title',
        'photo_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Nilai bawaan kolom ada di basis data, tapi tidak ikut terbaca ke objek yang
     * baru dibuat — sampai dimuat ulang, card_version-nya null. Tanda tangan QR
     * kartu panitia dihitung dari angka ini, jadi tanpa bawaan di sini kartu yang
     * dicetak tepat setelah akunnya dibuat menghasilkan kode yang tidak sah.
     */
    protected $attributes = [
        'card_version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birth_date' => 'date',
            'notifications_seen_at' => 'datetime',
            'card_version' => 'integer',
        ];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /** Peran yang boleh dipilih saat membuat akun pengelola. */
    public static function rolesPengelola(): array
    {
        return [
            self::ROLE_PANITIA => 'Panitia',
            self::ROLE_DEVELOPER => 'Developer',
        ];
    }

    public function isPanitia(): bool
    {
        return $this->role === self::ROLE_PANITIA;
    }

    public function isDeveloper(): bool
    {
        return $this->role === self::ROLE_DEVELOPER;
    }

    /**
     * Boleh membuka panel pengelola. Developer sengaja ikut lolos di sini supaya
     * tidak perlu punya akun panitia terpisah untuk mengerjakan tugas harian.
     */
    public function isStaff(): bool
    {
        return in_array($this->role, [self::ROLE_PANITIA, self::ROLE_DEVELOPER], true);
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_DEVELOPER => 'Developer',
            self::ROLE_PANITIA => 'Panitia',
            default => 'Peserta',
        };
    }
}
