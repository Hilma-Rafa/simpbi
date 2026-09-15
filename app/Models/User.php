<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /**
     * Kewenangan membuka panel SIMPBI.
     *
     * Wajib ada, dan bukan sekadar formalitas. Filament menolak dengan 403
     * setiap pengguna yang modelnya tidak mengumumkan kewenangan ini, kecuali
     * ketika aplikasi berjalan pada lingkungan `local` — sehingga tanpa metode
     * ini sistem tampak baik-baik saja selama dikembangkan, lalu menolak
     * seluruh pengguna begitu dipasang di peladen dengan APP_ENV=production.
     *
     * Isinya memeriksa status aktif, yang sebelumnya tidak pernah ditegakkan
     * di mana pun: akun yang dinonaktifkan Administrator tetap dapat masuk dan
     * memakai sistem seperti biasa. Pemeriksaan di sini menutupnya untuk
     * seluruh halaman sekaligus, termasuk sesi yang sudah terlanjur berjalan
     * ketika akunnya dinonaktifkan.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->status_aktif;
    }

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /** Tim kerja tempat pengguna bernaung (null untuk peran non-tim). */
    public function tim(): BelongsTo
    {
        return $this->belongsTo(Tim::class);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
    'username',
    'name',
    'email',
    'password',
    'nip',
    'no_hp',
    'role',
    'tim_id',
    'status_aktif',
    'harus_ganti_sandi',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'password'           => 'hashed',
            'status_aktif'       => 'boolean',
            'harus_ganti_sandi'  => 'boolean',
            'tanda_tangan_at'    => 'datetime',
        ];
    }
}
