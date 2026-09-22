<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     *
     * Kaki halaman muka membaca kontak bantuan WhatsApp dari tabel
     * `pengaturan` (App\Support\KontakBantuan, dipakai ulang — bukan
     * mekanisme baru), sehingga rute ini kini memang butuh migrasi berjalan
     * seperti hampir seluruh pengujian lain di proyek ini.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
