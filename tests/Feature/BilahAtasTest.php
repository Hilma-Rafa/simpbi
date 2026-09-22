<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Pengaturan;
use App\Filament\Pages\PusatBantuan;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Bilah atas: kolom pencarian dihapus, toggle tema terang/gelap, dropdown
 * profil, dan dialog konfirmasi keluar.
 *
 * Yang diuji di sini adalah kontrak markup yang dikirim peladen. Perilaku
 * peramban (animasi, fokus, localStorage, tooltip) tidak dapat dijalankan oleh
 * PHPUnit; bagian itu diperiksa di peramban sungguhan dan dilaporkan terpisah.
 * Yang paling dijaga adalah blok identitas: ia pernah dirender sebagai item
 * menu, lengkap dengan aksi Livewire yang tidak menuju ke mana pun.
 */
class BilahAtasTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private string $html = '';

    private DOMXPath $xpath;

    /** Membuka dasbor sebagai satu peran, lalu menyiapkan pohon DOM-nya. */
    private function bukaSebagai(string $peran = 'admin'): void
    {
        $tim = in_array($peran, ['ketua_tim', 'tim'], true) ? $this->buatTim() : null;

        $pengguna = $this->lengkapiAkun($this->buatPengguna($peran, $tim, [
            'name'  => 'Administrator Sistem',
            'email' => $peran . '@bps.go.id',
        ]));

        $this->html = $this->actingAs($pengguna)->get(Dashboard::getUrl())->assertOk()->getContent();

        $dokumen = new DOMDocument();
        libxml_use_internal_errors(true);
        $dokumen->loadHTML('<?xml encoding="utf-8" ?>' . $this->html);
        libxml_clear_errors();

        $this->xpath = new DOMXPath($dokumen);
    }

    private function satu(string $kueri): DOMElement
    {
        $simpul = $this->xpath->query($kueri);

        $this->assertSame(1, $simpul->length, "Diharapkan tepat satu simpul untuk: {$kueri}");

        return $simpul->item(0);
    }

    private function teks(string $kueri): string
    {
        return trim(preg_replace('/\s+/', ' ', $this->satu($kueri)->textContent));
    }

    private const PANEL = "//div[contains(@class,'simpbi-profil-panel')]";

    private const IDENTITAS = "//div[contains(@class,'simpbi-profil-identitas')]";

    // =====================================================================
    // HEADER: PENCARIAN DIHAPUS
    // =====================================================================

    public function test_header_tidak_lagi_memuat_kolom_pencarian(): void
    {
        $this->bukaSebagai();

        $this->assertStringNotContainsString('fi-global-search', $this->html);
        $this->assertStringNotContainsString('Pencarian global', $this->html);
        $this->assertStringNotContainsString('type="search"', $this->html);
        $this->assertStringNotContainsString('placeholder="Cari"', $this->html);
    }

    public function test_pencarian_global_dimatikan_di_panel(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertFalse(Filament::isGlobalSearchEnabled());
    }

    public function test_urutan_kelompok_kanan_toggle_lonceng_avatar(): void
    {
        $this->bukaSebagai();

        $toggle = strpos($this->html, 'role="switch"');
        $lonceng = strpos($this->html, 'wire:name="lonceng-notifikasi"');
        $avatar = strpos($this->html, 'fi-user-menu-trigger');

        $this->assertNotFalse($toggle);
        $this->assertNotFalse($lonceng);
        $this->assertNotFalse($avatar);
        $this->assertLessThan($lonceng, $toggle, 'Toggle tema harus tepat di sebelah kiri lonceng.');
        $this->assertLessThan($avatar, $lonceng, 'Lonceng harus di sebelah kiri avatar.');
    }

    // =====================================================================
    // TOGGLE TEMA
    // =====================================================================

    public function test_toggle_tema_adalah_switch_dengan_dua_keadaan(): void
    {
        $this->bukaSebagai();

        $toggle = $this->satu("//button[@role='switch']");

        $this->assertSame('button', $toggle->tagName);
        $this->assertSame('button', $toggle->getAttribute('type'));
        $this->assertSame('Mode gelap', $toggle->getAttribute('aria-label'));
        $this->assertSame('false', $toggle->getAttribute('aria-checked'));

        // Hanya terang dan gelap yang dikirim; tidak ada "sistem".
        $klik = $toggle->getAttribute('x-on:click');
        $this->assertStringContainsString("'theme-changed'", $klik);
        $this->assertStringContainsString("'light'", $klik);
        $this->assertStringContainsString("'dark'", $klik);
        $this->assertStringNotContainsString('system', $klik);

        // Tooltip mengikuti keadaan.
        $tooltip = $toggle->getAttribute('x-tooltip');
        $this->assertStringContainsString('Ganti ke mode gelap', $tooltip);
        $this->assertStringContainsString('Ganti ke mode terang', $tooltip);

        // Trek dan ikon bersifat dekoratif.
        $this->assertSame('true', $this->satu("//button[@role='switch']/span[contains(@class,'simpbi-tema-trek')]")->getAttribute('aria-hidden'));
    }

    public function test_pemilih_tema_tidak_lagi_ada_di_dalam_dropdown(): void
    {
        $this->bukaSebagai();

        $this->assertSame(0, $this->xpath->query(self::PANEL . "//*[contains(@class,'fi-theme-switcher')]")->length);
        $this->assertStringNotContainsString('Sesuai tema perangkat', $this->html);
        $this->assertStringNotContainsString('fi-theme-switcher-btn', $this->html);
    }

    // =====================================================================
    // DROPDOWN: BLOK IDENTITAS
    // =====================================================================

    public function test_blok_identitas_bukan_tombol_dan_tidak_interaktif(): void
    {
        $this->bukaSebagai();

        $identitas = $this->satu(self::IDENTITAS);

        $this->assertSame('div', $identitas->tagName);

        // Tidak ada elemen atau atribut yang membuatnya bisa diklik atau difokus.
        $this->assertSame(
            0,
            $this->xpath->query('.//a | .//button | .//input | .//*[@tabindex] | .//*[@role] | .//*[@href]', $identitas)->length,
        );

        $atribut = [];
        foreach ($this->xpath->query('descendant-or-self::*/@*', $identitas) as $a) {
            $atribut[] = $a->nodeName;
        }

        foreach ($atribut as $nama) {
            $this->assertFalse(
                str_starts_with($nama, 'wire:') || str_starts_with($nama, 'x-') || str_starts_with($nama, '@') || str_starts_with($nama, 'on'),
                "Blok identitas tidak boleh membawa penangan/aksi: {$nama}",
            );
        }

        // Bukan hasil komponen atau perulangan item menu.
        $this->assertStringNotContainsString('fi-dropdown-list-item', $identitas->getAttribute('class'));
        $this->assertStringNotContainsString('simpbi-profil-item', $identitas->getAttribute('class'));
        $this->assertSame(0, $this->xpath->query('.//*[contains(@class,"simpbi-profil-item")]', $identitas)->length);
    }

    public function test_blok_identitas_memuat_avatar_nama_dan_email_saja(): void
    {
        $this->bukaSebagai('kasubbag');

        $this->assertSame('AS', $this->teks(self::IDENTITAS . "//span[contains(@class,'simpbi-profil-avatar')]"));

        $nama = $this->satu(self::IDENTITAS . "//p[contains(@class,'simpbi-profil-nama')]");
        $email = $this->satu(self::IDENTITAS . "//p[contains(@class,'simpbi-profil-email')]");

        $this->assertSame('Administrator Sistem', trim($nama->textContent));
        $this->assertSame('Administrator Sistem', $nama->getAttribute('title'));
        $this->assertSame('kasubbag@bps.go.id', trim($email->textContent));
        $this->assertSame('kasubbag@bps.go.id', $email->getAttribute('title'));

        // KEPUTUSAN 4: tidak ada badge peran, nama tim, atau teks lain.
        $this->assertSame(2, $this->xpath->query(self::IDENTITAS . '//p')->length);
        $this->assertStringNotContainsString('Kasubbag', $this->teks(self::IDENTITAS));
    }

    public function test_blok_identitas_untuk_peran_bertim_tidak_menampilkan_nama_tim(): void
    {
        $this->bukaSebagai('ketua_tim');

        $this->assertStringNotContainsString('Statistik Sosial', $this->teks(self::IDENTITAS));
        $this->assertStringNotContainsString('·', $this->teks(self::PANEL));
    }

    // =====================================================================
    // DROPDOWN: GARIS, MENU, KELUAR
    // =====================================================================

    public function test_tepat_dua_garis_pemisah_dan_keduanya_dekoratif(): void
    {
        $this->bukaSebagai();

        $garis = $this->xpath->query(self::PANEL . '//hr');

        $this->assertSame(2, $garis->length);

        foreach ($garis as $hr) {
            $this->assertSame('true', $hr->getAttribute('aria-hidden'));
        }

        // Garis pertama memisahkan identitas dari menu; garis kedua menyisihkan Keluar.
        $urutan = [];
        foreach ($this->xpath->query(self::PANEL . '/*') as $anak) {
            $urutan[] = $anak->tagName === 'hr' ? 'hr' : (str_contains($anak->getAttribute('class'), 'identitas') ? 'identitas' : 'blok');
        }
        $this->assertSame(['identitas', 'hr', 'blok', 'hr', 'blok'], $urutan);
    }

    /**
     * @return array<string, array{0:string,1:string,2:string}>
     */
    public static function judulPengaturanPerPeran(): array
    {
        return [
            'admin'          => ['admin', 'Pengaturan', 'PROFIL, KEAMANAN & SISTEM'],
            'kasubbag'       => ['kasubbag', 'Pengaturan Profil', 'PROFIL & KEAMANAN'],
            'petugas_gudang' => ['petugas_gudang', 'Pengaturan Profil', 'PROFIL & KEAMANAN'],
            'ketua_tim'      => ['ketua_tim', 'Pengaturan Profil', 'PROFIL & KEAMANAN'],
            'tim'            => ['tim', 'Pengaturan Profil', 'PROFIL & KEAMANAN'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('judulPengaturanPerPeran')]
    public function test_menu_pengaturan_mengikuti_peran(string $peran, string $judul, string $subjudul): void
    {
        $this->bukaSebagai($peran);

        $tautan = $this->satu(self::PANEL . "//a[@href='" . Pengaturan::getUrl() . "']");

        // Judul dan subjudul adalah dua elemen terpisah, tanpa pemisah "·".
        $this->assertSame($judul, trim($this->satu(self::PANEL . "//a[@href='" . Pengaturan::getUrl() . "']//span[contains(@class,'simpbi-profil-judul')]")->textContent));
        $sub = $this->satu(self::PANEL . "//a[@href='" . Pengaturan::getUrl() . "']//span[contains(@class,'simpbi-profil-subjudul')]");
        $this->assertSame($subjudul, trim($sub->textContent));
        $this->assertSame($subjudul, $sub->getAttribute('title'));
        $this->assertStringNotContainsString('·', $tautan->textContent);
    }

    public function test_pusat_bantuan_tampil_untuk_semua_peran_dan_menuju_halamannya(): void
    {
        foreach (['admin', 'kasubbag', 'petugas_gudang', 'ketua_tim', 'tim'] as $peran) {
            $this->flushSession();
            $this->bukaSebagai($peran);

            $tautan = $this->satu(self::PANEL . "//a[@href='" . PusatBantuan::getUrl() . "']");

            $this->assertSame('Pusat Bantuan', trim($this->satu(self::PANEL . "//a[@href='" . PusatBantuan::getUrl() . "']//span[contains(@class,'simpbi-profil-judul')]")->textContent));
            $this->assertSame('PANDUAN & KONTAK', trim($this->satu(self::PANEL . "//a[@href='" . PusatBantuan::getUrl() . "']//span[contains(@class,'simpbi-profil-subjudul')]")->textContent));
            $this->assertSame('', $tautan->getAttribute('target'), 'Tujuan internal dibuka di tab yang sama.');
        }
    }

    public function test_dropdown_tidak_memuat_menu_selain_yang_ditetapkan(): void
    {
        $this->bukaSebagai();

        $this->assertSame(
            ['Pengaturan', 'Pusat Bantuan', 'Keluar'],
            array_map(
                fn ($simpul) => trim($simpul->textContent),
                iterator_to_array($this->xpath->query(self::PANEL . "//span[contains(@class,'simpbi-profil-judul')]")),
            ),
        );
    }

    public function test_keluar_membuka_dialog_dan_tidak_langsung_mengirim_logout(): void
    {
        $this->bukaSebagai();

        $keluar = $this->satu(self::PANEL . "//button[contains(@class,'simpbi-profil-item-keluar')]");

        $this->assertSame('button', $keluar->getAttribute('type'));
        $this->assertSame('dialog', $keluar->getAttribute('aria-haspopup'));
        $this->assertStringContainsString('open-modal', $keluar->getAttribute('x-on:click'));
        $this->assertStringContainsString('dialog-keluar', $keluar->getAttribute('x-on:click'));

        // Tidak berada di dalam formulir dan tidak menunjuk ke rute logout.
        $this->assertSame(0, $this->xpath->query('ancestor::form', $keluar)->length);
        $this->assertSame(0, $this->xpath->query(self::PANEL . '//form')->length);
        $this->assertStringNotContainsString('/logout', $this->satu(self::PANEL)->ownerDocument->saveHTML($this->satu(self::PANEL)));

        $this->assertSame('Keluar', trim($this->satu(self::PANEL . "//button[contains(@class,'simpbi-profil-item-keluar')]//span[contains(@class,'simpbi-profil-judul')]")->textContent));
        $this->assertSame('AKHIRI SESI ANDA', trim($this->satu(self::PANEL . "//button[contains(@class,'simpbi-profil-item-keluar')]//span[contains(@class,'simpbi-profil-subjudul')]")->textContent));
    }

    // =====================================================================
    // DIALOG KELUAR
    // =====================================================================

    public function test_dialog_keluar_adalah_alertdialog_dengan_isi_yang_ditetapkan(): void
    {
        $this->bukaSebagai();

        $dialog = $this->satu("//div[@id='dialog-keluar']");

        $this->assertSame('alertdialog', $dialog->getAttribute('role'));
        $this->assertSame('true', $dialog->getAttribute('aria-modal'));

        $judul = $this->satu("//*[@id='" . $dialog->getAttribute('aria-labelledby') . "']");
        $isi = $this->satu("//*[@id='" . $dialog->getAttribute('aria-describedby') . "']");

        $this->assertSame('Keluar dari akun?', trim($judul->textContent));
        $this->assertSame('Sesi Anda akan diakhiri dan Anda perlu masuk kembali untuk memakai SIMPBI.', trim($isi->textContent));

        $tombol = $this->xpath->query("//div[@id='dialog-keluar']//form//button");
        $this->assertSame(2, $tombol->length);
        $this->assertSame('Batal', trim($tombol->item(0)->textContent));
        $this->assertTrue($tombol->item(0)->hasAttribute('autofocus'), 'Fokus awal dialog ada pada Batal.');
        $this->assertSame('button', $tombol->item(0)->getAttribute('type'));
        $this->assertStringContainsString('Ya, Keluar', $tombol->item(1)->textContent);
        $this->assertSame('submit', $tombol->item(1)->getAttribute('type'));
    }

    public function test_dialog_memakai_mekanisme_keluar_yang_sama_persis(): void
    {
        $this->bukaSebagai();

        $form = $this->satu("//div[@id='dialog-keluar']//form");

        $this->assertSame('post', strtolower($form->getAttribute('method')));
        $this->assertSame(Filament::getLogoutUrl(), $form->getAttribute('action'));
        $this->assertSame(1, $this->xpath->query(".//input[@type='hidden'][@name='_token']", $form)->length);
    }

    public function test_konfirmasi_menjalankan_logout_dan_mengakhiri_sesi(): void
    {
        $pengguna = $this->lengkapiAkun($this->buatPengguna('kasubbag'));

        $this->actingAs($pengguna)->get(Dashboard::getUrl())->assertOk();
        $this->assertAuthenticated();

        $this->post(Filament::getLogoutUrl())->assertRedirect();

        $this->assertGuest();
        $this->get(Dashboard::getUrl())->assertRedirect();
    }

    public function test_tamu_tidak_menerima_dialog_maupun_kelompok_kanan(): void
    {
        $html = $this->get(Filament::getLoginUrl())->assertOk()->getContent();

        $this->assertStringNotContainsString('dialog-keluar', $html);
        $this->assertStringNotContainsString('role="switch"', $html);
    }
}
