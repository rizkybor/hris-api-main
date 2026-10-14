<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The standard JC Digital "Proposal Penawaran" outline every new proposal
 * starts from, taken from the proposals previously written in Notion. The
 * company-wide sections (about us, security, warranty, terms...) come
 * pre-written; project-specific ones hold [bracketed] guidance to replace.
 * Everything stays editable per proposal -- this is only the starting point.
 */
class ProposalTemplate
{
    /**
     * @param  array{client?: string, recipient?: string}  $vars
     */
    public static function sections(array $vars = []): array
    {
        $client = e($vars['client'] ?? '[Nama Klien]');
        $recipient = e(filled($vars['recipient'] ?? null) ? $vars['recipient'] : '[Bapak/Ibu Nama Penerima]');

        $sections = [
            ['Pendahuluan', <<<HTML
<p>Kepada Yth.<br>{$recipient}<br>{$client}</p>
<p>Dengan hormat,</p>
<p>Terima kasih atas kepercayaan Bapak/Ibu untuk berdiskusi dengan kami mengenai rencana [jelaskan kebutuhan singkat, misalnya: pembuatan aplikasi pengelolaan order] di {$client}.</p>
<p>Berdasarkan [kebutuhan/alur kerja] yang sudah Bapak/Ibu bagikan, kami menyusun penawaran ini. Isinya mencakup gambaran sistem, fitur, jadwal, biaya, dan garansi.</p>
<p>Jika ada bagian yang kurang jelas atau ingin disesuaikan, kami dengan senang hati membahasnya bersama.</p>
HTML],
            ['Tentang JC Digital', <<<'HTML'
<p>PT Jendela Cakra Digital (JC Digital) adalah software house dan digital agency yang berkantor di Tangerang Selatan. Kami membuat aplikasi web dan mobile, merancang tampilan (UI/UX), serta menangani SEO dan e-commerce.</p>
<p>Kami terbiasa membangun sistem internal perusahaan, yaitu aplikasi yang dipakai tim setiap hari untuk mencatat, memantau, dan merapikan pekerjaan administrasi.</p>
HTML],
            ['Pemahaman Kebutuhan', <<<HTML
<p>[Jelaskan proses kerja {$client} saat ini, sesuai yang disampaikan klien.]</p>
<p>Kalau semua ini dicatat secara manual, beberapa masalah biasanya muncul:</p>
<ul><li>[Masalah 1]</li><li>[Masalah 2]</li><li>[Masalah 3]</li></ul>
<p>Aplikasi ini dibuat untuk menjawab hal-hal tersebut.</p>
HTML],
            ['Solusi yang Kami Tawarkan', <<<'HTML'
<p>[Jelaskan solusi secara ringkas: aplikasi apa yang dibuat dan bagaimana aplikasi tersebut menyelesaikan masalah di atas.]</p>
<p>[Sisipkan gambar alur kerja atau diagram jika ada.]</p>
HTML],
            ['Fitur yang Akan Dibuat', <<<'HTML'
<h3>5.1 [Nama Modul]</h3>
<ul><li>[Fitur]</li><li>[Fitur]</li></ul>
<h3>5.2 [Nama Modul]</h3>
<ul><li>[Fitur]</li><li>[Fitur]</li></ul>
<h3>5.3 Pendukung</h3>
<ul><li>Panduan penggunaan dalam bentuk PDF.</li><li>Pelatihan untuk tim sebanyak 2 sesi, masing-masing maksimal 2 jam, di kantor Bapak/Ibu atau secara online.</li></ul>
HTML],
            ['Hak Akses Pengguna', <<<'HTML'
<p>[Jelaskan jenis pengguna, misalnya Superadmin untuk pimpinan dan Admin untuk staf.] Jumlah akun pengguna tidak dibatasi.</p>
<table><tbody><tr><th>Yang bisa dilakukan</th><th>Superadmin</th><th>Admin</th></tr><tr><td>Mengelola akun pengguna</td><td>Ya</td><td>Tidak</td></tr><tr><td>[Hak akses]</td><td>Ya</td><td>Ya</td></tr></tbody></table>
<p>Pembagian ini bisa kami sesuaikan saat rapat awal proyek, mengikuti kebijakan perusahaan.</p>
HTML],
            ['Keamanan Data', <<<'HTML'
<p>Data perusahaan adalah data penting, jadi keamanannya kami siapkan sejak awal.</p>
<ul>
<li><strong>Login aman.</strong> Password disimpan dalam bentuk terenkripsi. Akun dikunci sementara jika salah password berkali-kali, dan otomatis keluar jika lama tidak dipakai.</li>
<li><strong>Hak akses dicek di server.</strong> Pengguna tidak bisa membuka fitur di luar haknya, walaupun mencoba lewat alamat halaman secara langsung.</li>
<li><strong>Catatan aktivitas.</strong> Setiap perubahan data tercatat: siapa, kapan, dan apa yang diubah.</li>
<li><strong>Koneksi terenkripsi HTTPS.</strong></li>
<li><strong>File aman.</strong> Lampiran dan PDF disimpan di folder tertutup, hanya bisa dibuka oleh pengguna yang login.</li>
<li><strong>Backup harian otomatis</strong> ke lokasi terpisah, dan kami uji pemulihannya secara berkala.</li>
<li><strong>Perlindungan data pribadi.</strong> Pengelolaan data mengikuti UU Pelindungan Data Pribadi No. 27 Tahun 2022.</li>
</ul>
HTML],
            ['Teknologi yang Digunakan', <<<'HTML'
<p>Kami memakai teknologi yang umum di Indonesia, sudah terbukti stabil, dan mudah dirawat oleh developer lain di kemudian hari.</p>
<table><tbody><tr><th>Bagian</th><th>Teknologi</th></tr><tr><td>Aplikasi</td><td>Laravel 12 (PHP 8.3)</td></tr><tr><td>Database</td><td>MySQL 8</td></tr><tr><td>Server</td><td>Ubuntu Server 24.04 LTS dengan Nginx</td></tr><tr><td>Keamanan server</td><td>SSL (Let's Encrypt) dan firewall server</td></tr></tbody></table>
<p>Aplikasi dibuka lewat browser (Chrome, Edge, Firefox). Tidak perlu memasang aplikasi apa pun di setiap komputer.</p>
HTML],
            ['Tahapan dan Jadwal Pengerjaan', <<<'HTML'
<p>Pengerjaan membutuhkan waktu <strong>[jumlah] minggu</strong> sejak kontrak ditandatangani, sampai aplikasi siap dipakai dan diserahterimakan.</p>
<table><tbody><tr><th>Tahap</th><th>Minggu</th></tr><tr><td>Rapat awal dan analisis</td><td>[M1]</td></tr><tr><td>Desain tampilan</td><td>[M1–M2]</td></tr><tr><td>Pembuatan fitur</td><td>[M2–M7]</td></tr><tr><td>Pengujian bersama (UAT)</td><td>[M8–M9]</td></tr><tr><td>Pelatihan dan serah terima (BAST)</td><td>[M10]</td></tr></tbody></table>
<p>Desain tampilan mendapat maksimal 2 putaran revisi.</p>
HTML],
            ['Investasi Biaya', <<<'HTML'
<p>Biaya pembuatan aplikasi adalah <strong>Rp [nominal]</strong>, belum termasuk PPN.</p>
<table><tbody><tr><th>Komponen</th><th>Biaya</th></tr><tr><td>Pembuatan aplikasi (seluruh fitur di bagian Fitur yang Akan Dibuat)</td><td>Rp [nominal]</td></tr><tr><td>Pemasangan server dan backup</td><td>Rp [nominal]</td></tr><tr><td><strong>Subtotal</strong></td><td><strong>Rp [nominal]</strong></td></tr></tbody></table>
<p>Biaya di atas sudah termasuk analisis, desain tampilan, pembuatan aplikasi, pengujian, pemasangan, panduan penggunaan, dan pelatihan.</p>
<p>Biaya di luar penawaran ini, dibayarkan langsung oleh perusahaan: sewa server (VPS) sekitar Rp 150.000–400.000 per bulan dan domain sekitar Rp 200.000 per tahun.</p>
HTML],
            ['Termin Pembayaran', <<<'HTML'
<p>Pembayaran dibagi menjadi empat tahap, termasuk 5% dana retensi sebagai jaminan garansi.</p>
<table><tbody><tr><th>Tahap</th><th>Waktu pembayaran</th><th>Porsi</th><th>Nominal</th></tr><tr><td>1</td><td>Saat penandatanganan kontrak</td><td>30%</td><td>Rp [nominal]</td></tr><tr><td>2</td><td>Setelah seluruh fitur selesai dan siap diuji bersama</td><td>50%</td><td>Rp [nominal]</td></tr><tr><td>3</td><td>Setelah Berita Acara Serah Terima (BAST) ditandatangani</td><td>15%</td><td>Rp [nominal]</td></tr><tr><td>4</td><td>Retensi, 8 minggu setelah BAST</td><td>5%</td><td>Rp [nominal]</td></tr></tbody></table>
<p>Nominal di atas belum termasuk PPN 11%. Setiap tahap disertai invoice dan Faktur Pajak dari kami, dan ditransfer ke rekening PT Jendela Cakra Digital.</p>
<p>Dana retensi 5% ditahan oleh perusahaan selama 8 minggu setelah BAST. Selama masa ini, kami memastikan aplikasi berjalan lancar dan semua bug yang ditemukan sudah diperbaiki. Setelah masa retensi selesai, dana tersebut dibayarkan kepada kami.</p>
HTML],
            ['Garansi dan Dukungan', <<<'HTML'
<p>Kami memberikan garansi <strong>8 minggu</strong> sejak BAST ditandatangani, sama dengan masa retensi. Selama masa garansi, kesalahan (bug) pada fitur yang tercantum di penawaran ini kami perbaiki tanpa biaya tambahan.</p>
<table><tbody><tr><th>Jenis masalah</th><th>Contoh</th><th>Waktu tanggapan</th></tr><tr><td>Mendesak</td><td>Aplikasi tidak bisa dibuka, fitur utama tidak bisa dipakai</td><td>Maksimal 1 hari kerja</td></tr><tr><td>Biasa</td><td>Tampilan kurang rapi, filter laporan tidak sesuai</td><td>Maksimal 3 hari kerja</td></tr></tbody></table>
<p>Garansi tidak berlaku untuk:</p>
<ul><li>Penambahan fitur baru atau perubahan alur kerja.</li><li>Kesalahan karena salah input data oleh pengguna.</li><li>Gangguan internet, listrik, atau perangkat milik perusahaan.</li><li>Aplikasi atau server yang sudah diubah oleh pihak lain selain JC Digital.</li></ul>
<p>Setelah masa garansi selesai, kami menyediakan paket perawatan bulanan mulai Rp 1.000.000 per bulan. Isinya pemantauan server, pengecekan backup, pembaruan keamanan, dan perbaikan bug. Paket ini sifatnya pilihan, tidak wajib.</p>
HTML],
            ['Yang Tidak Termasuk dalam Penawaran', <<<'HTML'
<p>Supaya tidak ada salah paham di kemudian hari, berikut hal-hal yang belum termasuk:</p>
<ul><li>[Hal yang tidak termasuk]</li><li>Aplikasi khusus HP Android/iOS. Aplikasi tetap bisa dibuka lewat browser di HP.</li><li>Biaya server, domain, dan perangkat, seperti dijelaskan di bagian Investasi Biaya.</li></ul>
<p>Jika nanti diperlukan, hal-hal di atas bisa dikerjakan sebagai tahap lanjutan dengan penawaran terpisah.</p>
HTML],
            ['Syarat dan Ketentuan', <<<HTML
<ol>
<li>Penawaran ini berlaku selama 30 hari sejak tanggal dokumen.</li>
<li>Pekerjaan dimulai setelah kontrak ditandatangani dan pembayaran tahap pertama kami terima.</li>
<li>Jadwal pengerjaan berlaku jika data dan masukan dari tim Bapak/Ibu kami terima tepat waktu. Keterlambatan masukan dapat menggeser jadwal.</li>
<li>Permintaan perubahan atau tambahan fitur di luar daftar fitur akan kami hitung dan sampaikan terlebih dahulu sebelum dikerjakan.</li>
<li>Seluruh kode program dan data menjadi milik {$client} setelah pelunasan.</li>
<li>Kedua pihak menjaga kerahasiaan data dan informasi yang diterima selama proyek berjalan.</li>
</ol>
HTML],
            ['Penutup', <<<HTML
<p>Demikian penawaran ini kami sampaikan. Kami berharap aplikasi ini bisa membantu tim {$client} bekerja lebih rapi dan efisien.</p>
<p>Jika ada pertanyaan atau bagian yang ingin disesuaikan, Bapak/Ibu bisa menghubungi kami kapan saja. Kami juga siap melakukan presentasi singkat untuk menjelaskan penawaran ini secara langsung.</p>
HTML],
        ];

        return array_map(fn ($section) => self::section($section[0], trim($section[1])), $sections);
    }

    public static function section(string $title = '', string $content = '', bool $pageBreakBefore = false): array
    {
        return [
            'id' => (string) Str::uuid(),
            'title' => $title,
            'content' => $content,
            'page_break_before' => $pageBreakBefore,
        ];
    }
}
