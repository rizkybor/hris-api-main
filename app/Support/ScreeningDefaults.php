<?php

namespace App\Support;

/**
 * Seed data for Client Screening: the service catalog (Rate Card values as of
 * Nov 2026 plus the hourly / by-quote extras the Rate Card doesn't list) and
 * the default bilingual questionnaire.
 *
 * Option effects understood by ScreeningRecommender:
 *   add      [{item, hours?, qty?, label?}]  recommend a catalog item
 *   replace  [{from, to}]                    swap one recommended item for another
 *   points   int                             complexity points
 *   tags     [string]                        system type (see SYSTEM_TYPES)
 *   flags    [{id, en}]                      risks / notes for the team
 *   min/max  int                             budget range (budget question only)
 */
class ScreeningDefaults
{
    public const SYSTEM_TYPES = [
        'landing' => 'Landing Page',
        'company_profile' => 'Website Company Profile',
        'catalog' => 'Website Katalog Produk',
        'ecommerce' => 'Toko Online (E-commerce)',
        'custom_system' => 'Sistem Custom (ERP / CRM / Portal)',
        'system_extension' => 'Pengembangan Sistem yang Sudah Ada',
        'mobile' => 'Aplikasi Mobile',
        'chat' => 'Chat Commerce / Chatbot',
        'design' => 'Desain UI/UX',
        'redesign' => 'Redesign Website / Sistem',
        'seo' => 'SEO',
        'maintenance' => 'Maintenance & Support',
    ];

    public static function catalog(): array
    {
        $rc = fn ($code, $name, $cat, $price, $unit, $dur, $desc, $avail = 'available') => compact('code', 'name', 'price', 'unit', 'dur', 'desc', 'avail') + ['category' => $cat, 'mode' => 'rate_card'];

        $rows = [
            $rc('jcd-lanspage', 'JCD Lanspage (Campaign Landing Page)', 'Pengembangan Web', 4000000, 'once', '2-3 minggu', 'Single page untuk campaign dengan tracking analitik konversi.'),
            $rc('jcd-compro-lite', 'JCD Compro Lite (Static Web / Non-CMS)', 'Pengembangan Web', 7500000, 'once', '2-3 minggu', 'Website korporat statis (Max 8 halaman). Keamanan maksimal, super cepat. Pembaruan via JCD Setup.'),
            $rc('jcd-compro', 'JCD Compro (Corporate Website with CMS Dashboard)', 'Pengembangan Web', 15000000, 'once', '6-8 minggu', 'CMS dinamis, multi-bahasa, dan audit keamanan standar.'),
            $rc('jcd-e-catalogue', 'JCD E-Catalogue', 'Pengembangan Web', 25000000, 'once', '4-6 minggu', null),
            $rc('jcd-commerce', 'JCD Commerce (Shopify E-commerce)', 'Commerce', null, 'once', null, null, 'preparation'),
            $rc('jcd-recreative', 'JCD Recreative (Enterprise Re-design)', 'Pengembangan Web', 6000000, 'once', '1-6 minggu (tergantung paket & kompleksitas)', 'Perombakan visual antarmuka (frontend) tanpa mengubah struktur database.'),
            $rc('jcd-core', 'JCD Core (Custom ERP, CRM, Portal)', 'Sistem Enterprise', 35000000, 'once', '4-6 bulan', 'Sistem operasional internal (MVP) dengan arsitektur custom.'),
            $rc('jcd-request', 'JCD Request (Custom Feature/Module)', 'Sistem Enterprise', 3500000, 'once', '1-2 minggu', 'Pengembangan modul tambahan di luar ruang lingkup awal (sprint-based).'),
            $rc('jcd-flow', 'JCD Flow (Rule-Based Chat Commerce / Non-AI)', 'AI & Automation', 4500000, 'once', '1-2 bulan', 'Chatbot transaksi statis (menu button/list) via WA API + Payment Link.'),
            $rc('jcd-smart', 'JCD Smart (AI Assistance/Chatbot)', 'AI & Automation', 8000000, 'once', '1-2 bulan', 'Integrasi LLM (AI) untuk customer support cerdas berdasarkan data perusahaan.'),
            $rc('jcd-engine', 'JCD Engine (Corporate SEO)', 'SEO', 3000000, 'month', 'Retainer Min. 6 bln', 'Optimasi organik on-page/off-page dan pembuatan strategi konten.'),
            $rc('jcd-creative', 'JCD Creative (UI/UX Design)', 'Desain', 5000000, 'once', '3-5 minggu', 'Riset pengguna, wireframing, dan high-fidelity prototype.'),
            $rc('jcd-setup', 'JCD Setup (SLA & Maintenance)', 'Pengembangan Web', 1500000, 'month', 'Kontrak min. 6 bulan', 'Kuota jam maintenance bulanan, monitoring uptime, dan SLA. Sisa kuota masuk Saldo Pengembangan untuk fitur baru.'),
            $rc('jcd-conversational', 'JCD Conversational (Omnichannel Chat Commerce)', 'AI & Automation', null, 'once', '2-3 bulan', null, 'preparation'),
        ];

        // Not on the Rate Card: priced from hours x the Project Calculator
        // sell rate, or by quote.
        $extra = [
            ['code' => 'custom-module', 'name' => 'Modul / Fitur Tambahan', 'category' => 'Sistem Enterprise', 'mode' => 'hourly', 'hours' => 40, 'desc' => 'Modul sistem di luar paket dasar, dihitung dari estimasi jam x rate Project Calculator.'],
            ['code' => 'integration', 'name' => 'Integrasi Pihak Ketiga', 'category' => 'Integrasi', 'mode' => 'hourly', 'hours' => 24, 'desc' => 'Integrasi API (payment gateway, WhatsApp, akuntansi, ekspedisi, dll.).'],
            ['code' => 'mobile-app', 'name' => 'Aplikasi Mobile (Android/iOS)', 'category' => 'Mobile', 'mode' => 'hourly', 'hours' => 160, 'desc' => 'Belum ada di Rate Card -- dihitung seperti JCD Core/Request dari estimasi jam.'],
            ['code' => 'data-migration', 'name' => 'Migrasi Data', 'category' => 'Sistem Enterprise', 'mode' => 'hourly', 'hours' => 16, 'desc' => 'Pemindahan data dari spreadsheet atau sistem lama.'],
            ['code' => 'copywriting', 'name' => 'Penulisan Konten (Copywriting)', 'category' => 'Konten', 'mode' => 'by_quote', 'desc' => 'Penyusunan teks halaman website.'],
            ['code' => 'hosting', 'name' => 'Hosting / Server', 'category' => 'Infrastruktur', 'mode' => 'by_quote', 'unit' => 'year', 'desc' => 'Belum ada di Rate Card -- harga sesuai penawaran.'],
            ['code' => 'domain', 'name' => 'Domain', 'category' => 'Infrastruktur', 'mode' => 'by_quote', 'unit' => 'year', 'desc' => 'Belum ada di Rate Card -- harga sesuai penawaran.'],
            ['code' => 'business-email', 'name' => 'Email Bisnis', 'category' => 'Infrastruktur', 'mode' => 'by_quote', 'unit' => 'year', 'desc' => 'Belum ada di Rate Card -- harga sesuai penawaran.'],
        ];

        $out = [];
        foreach ($rows as $i => $r) {
            $out[] = [
                'code' => $r['code'], 'name' => $r['name'], 'category' => $r['category'], 'description' => $r['desc'],
                'pricing_mode' => 'rate_card', 'price_from' => $r['price'], 'price_unit' => $r['unit'], 'default_hours' => null,
                'duration_text' => $r['dur'], 'availability' => $r['avail'], 'sort_order' => ($i + 1) * 10,
            ];
        }
        foreach ($extra as $i => $r) {
            $out[] = [
                'code' => $r['code'], 'name' => $r['name'], 'category' => $r['category'], 'description' => $r['desc'],
                'pricing_mode' => $r['mode'], 'price_from' => null, 'price_unit' => $r['unit'] ?? 'once', 'default_hours' => $r['hours'] ?? null,
                'duration_text' => null, 'availability' => 'available', 'sort_order' => 500 + $i * 10,
            ];
        }

        return $out;
    }

    private static function t(string $id, string $en): array
    {
        return ['id' => $id, 'en' => $en];
    }

    private static function opt(string $value, string $id, string $en, array $effects = []): array
    {
        return ['value' => $value, 'label' => self::t($id, $en)] + $effects;
    }

    private static function yesNo(array $yes = [], array $no = []): array
    {
        return [self::opt('yes', 'Ya', 'Yes', $yes), self::opt('no', 'Tidak', 'No', $no)];
    }

    private static function module(string $id, string $en, int $hours): array
    {
        return ['item' => 'custom-module', 'hours' => $hours, 'label' => self::t($id, $en)];
    }

    private static function integration(string $id, string $en, int $hours): array
    {
        return ['item' => 'integration', 'hours' => $hours, 'label' => self::t($id, $en)];
    }

    private static function flag(string $id, string $en): array
    {
        return self::t($id, $en);
    }

    public static function template(): array
    {
        $t = fn ($id, $en) => self::t($id, $en);
        $o = fn (...$a) => self::opt(...$a);
        $needs = fn (...$values) => ['question' => 'needs', 'includes' => $values];

        return ['sections' => [
            [
                'key' => 'needs',
                'title' => $t('Kebutuhan Anda', 'Your Needs'),
                'description' => $t('Pilih semua yang Anda butuhkan. Pertanyaan berikutnya menyesuaikan pilihan ini.', 'Pick everything you need. The next questions follow your choices.'),
                'questions' => [[
                    'key' => 'needs', 'type' => 'multi', 'required' => true,
                    'label' => $t('Apa yang Anda butuhkan?', 'What do you need?'),
                    'options' => [
                        $o('website', 'Website', 'Website'),
                        $o('system', 'Sistem / aplikasi web (ERP, CRM, portal, dll.)', 'Web system / application (ERP, CRM, portal, etc.)'),
                        $o('mobile', 'Aplikasi mobile (Android/iOS)', 'Mobile app (Android/iOS)', ['tags' => ['mobile']]),
                        $o('redesign', 'Redesign website / sistem yang sudah ada', 'Redesign of an existing website / system', ['tags' => ['redesign']]),
                        $o('chat', 'Chat commerce / chatbot WhatsApp', 'Chat commerce / WhatsApp chatbot', ['tags' => ['chat']]),
                        $o('design', 'Desain UI/UX', 'UI/UX design', ['add' => [['item' => 'jcd-creative']], 'tags' => ['design']]),
                        $o('seo', 'SEO & peningkatan trafik', 'SEO & traffic growth', ['add' => [['item' => 'jcd-engine']], 'tags' => ['seo']]),
                        $o('hosting', 'Hosting, domain & email bisnis', 'Hosting, domain & business email'),
                        $o('maintenance', 'Maintenance & support', 'Maintenance & support', ['add' => [['item' => 'jcd-setup']], 'tags' => ['maintenance']]),
                    ],
                ]],
            ],
            [
                'key' => 'business',
                'title' => $t('Tentang Bisnis Anda', 'About Your Business'),
                'questions' => [
                    ['key' => 'industry', 'type' => 'text', 'required' => true, 'label' => $t('Bidang usaha', 'Industry')],
                    ['key' => 'company_size', 'type' => 'single', 'required' => true, 'label' => $t('Jumlah karyawan', 'Number of employees'), 'options' => [
                        $o('1-10', '1-10 orang', '1-10 people'),
                        $o('11-50', '11-50 orang', '11-50 people', ['points' => 2]),
                        $o('51-200', '51-200 orang', '51-200 people', ['points' => 4]),
                        $o('200+', 'Lebih dari 200 orang', 'More than 200 people', ['points' => 6]),
                    ]],
                    ['key' => 'goals', 'type' => 'textarea', 'required' => true, 'label' => $t('Masalah apa yang ingin diselesaikan, atau apa tujuannya?', 'What problem do you want to solve, or what is the goal?')],
                    ['key' => 'references', 'type' => 'textarea', 'required' => false, 'label' => $t('Referensi website / aplikasi yang Anda sukai (link)', 'Websites / apps you like as a reference (links)')],
                ],
            ],
            [
                'key' => 'website',
                'show_if' => $needs('website'),
                'title' => $t('Website', 'Website'),
                'questions' => [
                    ['key' => 'website_type', 'type' => 'single', 'required' => true, 'label' => $t('Jenis website', 'Type of website'), 'options' => [
                        $o('landing', 'Landing page untuk campaign / iklan', 'Landing page for a campaign / ads', ['add' => [['item' => 'jcd-lanspage']], 'tags' => ['landing']]),
                        $o('company_profile', 'Company profile', 'Company profile', ['add' => [['item' => 'jcd-compro-lite']], 'tags' => ['company_profile'], 'points' => 2]),
                        $o('catalog', 'Katalog produk (tanpa checkout)', 'Product catalogue (no checkout)', ['add' => [['item' => 'jcd-e-catalogue']], 'tags' => ['catalog'], 'points' => 6]),
                        $o('shop', 'Toko online dengan pembayaran', 'Online shop with payments', ['add' => [['item' => 'jcd-commerce']], 'tags' => ['ecommerce'], 'points' => 10,
                            'flags' => [self::flag('JCD Commerce masih tahap persiapan -- tawarkan JCD E-Catalogue + JCD Flow (order via WhatsApp) sebagai alternatif.', 'JCD Commerce is still in preparation -- offer JCD E-Catalogue + JCD Flow as an alternative.')]]),
                        $o('portal', 'Portal / blog / berita', 'Portal / blog / news', ['add' => [['item' => 'jcd-compro']], 'tags' => ['company_profile'], 'points' => 5]),
                    ]],
                    ['key' => 'pages', 'type' => 'single', 'required' => false, 'label' => $t('Perkiraan jumlah halaman', 'Approximate number of pages'), 'options' => [
                        $o('1', '1 halaman', '1 page'),
                        $o('2-8', '2-8 halaman', '2-8 pages', ['points' => 1]),
                        $o('9-20', '9-20 halaman', '9-20 pages', ['points' => 3, 'replace' => [['from' => 'jcd-compro-lite', 'to' => 'jcd-compro']]]),
                        $o('20+', 'Lebih dari 20 halaman', 'More than 20 pages', ['points' => 5, 'replace' => [['from' => 'jcd-compro-lite', 'to' => 'jcd-compro']]]),
                    ]],
                    ['key' => 'cms', 'type' => 'boolean', 'required' => false, 'label' => $t('Ingin mengubah isi website sendiri (CMS)?', 'Do you want to edit the content yourself (CMS)?'),
                        'options' => self::yesNo(['points' => 2, 'replace' => [['from' => 'jcd-compro-lite', 'to' => 'jcd-compro']]])],
                    ['key' => 'multilang', 'type' => 'boolean', 'required' => false, 'label' => $t('Perlu lebih dari satu bahasa?', 'Do you need more than one language?'),
                        'options' => self::yesNo(['points' => 2, 'replace' => [['from' => 'jcd-compro-lite', 'to' => 'jcd-compro']]])],
                    ['key' => 'content_ready', 'type' => 'single', 'required' => false, 'label' => $t('Apakah konten (teks, foto) sudah siap?', 'Is the content (text, photos) ready?'), 'options' => [
                        $o('ready', 'Sudah siap', 'Ready'),
                        $o('partial', 'Sebagian', 'Partly', ['flags' => [self::flag('Konten baru sebagian -- jadwal bisa mundur kalau konten terlambat.', 'Content only partly ready -- timeline may slip.')]]),
                        $o('none', 'Belum ada', 'Not yet', ['add' => [['item' => 'copywriting']], 'points' => 2, 'flags' => [self::flag('Konten belum ada -- tawarkan copywriting atau sepakati tenggat konten.', 'No content yet -- offer copywriting or agree on a content deadline.')]]),
                    ]],
                ],
            ],
            [
                'key' => 'system',
                'show_if' => $needs('system'),
                'title' => $t('Sistem / Aplikasi Web', 'Web System / Application'),
                'questions' => [
                    ['key' => 'system_kind', 'type' => 'single', 'required' => true, 'label' => $t('Yang Anda butuhkan', 'What you need'), 'options' => [
                        $o('new', 'Membangun sistem baru', 'Build a new system', ['add' => [['item' => 'jcd-core']], 'tags' => ['custom_system'], 'points' => 10]),
                        $o('extend', 'Menambah fitur di sistem yang sudah ada', 'Add features to an existing system', ['add' => [['item' => 'jcd-request']], 'tags' => ['system_extension'], 'points' => 4]),
                    ]],
                    ['key' => 'modules', 'type' => 'multi', 'required' => true, 'label' => $t('Modul yang dibutuhkan', 'Modules you need'), 'options' => [
                        $o('hr', 'SDM, absensi & penggajian', 'HR, attendance & payroll', ['add' => [self::module('Modul SDM, absensi & penggajian', 'HR, attendance & payroll module', 80)], 'points' => 6]),
                        $o('inventory', 'Gudang / inventory', 'Warehouse / inventory', ['add' => [self::module('Modul gudang / inventory', 'Warehouse / inventory module', 60)], 'points' => 5]),
                        $o('sales', 'Penjualan / kasir (POS)', 'Sales / point of sale', ['add' => [self::module('Modul penjualan / POS', 'Sales / POS module', 60)], 'points' => 5]),
                        $o('purchasing', 'Pembelian / pengadaan', 'Purchasing / procurement', ['add' => [self::module('Modul pembelian / pengadaan', 'Purchasing module', 50)], 'points' => 4]),
                        $o('finance', 'Keuangan & akuntansi', 'Finance & accounting', ['add' => [self::module('Modul keuangan & akuntansi', 'Finance & accounting module', 70)], 'points' => 6]),
                        $o('crm', 'CRM / pelanggan', 'CRM / customers', ['add' => [self::module('Modul CRM', 'CRM module', 50)], 'points' => 4]),
                        $o('project', 'Proyek & tugas', 'Projects & tasks', ['add' => [self::module('Modul proyek & tugas', 'Projects & tasks module', 50)], 'points' => 4]),
                        $o('booking', 'Reservasi / booking', 'Reservations / booking', ['add' => [self::module('Modul reservasi / booking', 'Booking module', 40)], 'points' => 3]),
                        $o('portal', 'Portal klien / member', 'Client / member portal', ['add' => [self::module('Portal klien / member', 'Client / member portal', 50)], 'points' => 4]),
                        $o('reports', 'Dashboard & laporan', 'Dashboards & reports', ['add' => [self::module('Dashboard & laporan', 'Dashboards & reports', 30)], 'points' => 2]),
                        $o('other', 'Lainnya (jelaskan di catatan)', 'Other (describe in the notes)', ['add' => [self::module('Modul custom lainnya', 'Other custom module', 40)], 'points' => 4]),
                    ]],
                    ['key' => 'users', 'type' => 'single', 'required' => true, 'label' => $t('Perkiraan jumlah pengguna', 'Expected number of users'), 'options' => [
                        $o('<10', 'Kurang dari 10', 'Fewer than 10'),
                        $o('10-50', '10-50', '10-50', ['points' => 2]),
                        $o('51-500', '51-500', '51-500', ['points' => 5]),
                        $o('500+', 'Lebih dari 500', 'More than 500', ['points' => 8, 'add' => [['item' => 'hosting']], 'flags' => [self::flag('Pengguna > 500 -- sarankan server dedicated dan uji beban.', 'Over 500 users -- suggest a dedicated server and load testing.')]]),
                    ]],
                    ['key' => 'approval', 'type' => 'boolean', 'required' => false, 'label' => $t('Perlu alur persetujuan (approval) berjenjang?', 'Do you need multi-level approval flows?'),
                        'options' => self::yesNo(['add' => [self::module('Alur approval berjenjang', 'Multi-level approval flow', 24)], 'points' => 3])],
                    ['key' => 'existing_data', 'type' => 'single', 'required' => false, 'label' => $t('Data yang sudah ada sekarang', 'Your current data'), 'options' => [
                        $o('none', 'Belum ada / mulai dari nol', 'None / starting fresh'),
                        $o('spreadsheet', 'Di spreadsheet (Excel / Google Sheets)', 'In spreadsheets (Excel / Google Sheets)', ['add' => [['item' => 'data-migration', 'hours' => 16]], 'points' => 2]),
                        $o('old_system', 'Di sistem / aplikasi lama', 'In an old system / application', ['add' => [['item' => 'data-migration', 'hours' => 40]], 'points' => 4,
                            'flags' => [self::flag('Ada sistem lama -- perlu analisis struktur data sebelum migrasi.', 'Existing system -- data structure needs analysis before migration.')]]),
                    ]],
                ],
            ],
            [
                'key' => 'mobile',
                'show_if' => $needs('mobile'),
                'title' => $t('Aplikasi Mobile', 'Mobile App'),
                'questions' => [
                    ['key' => 'platforms', 'type' => 'multi', 'required' => true, 'label' => $t('Platform', 'Platforms'), 'options' => [
                        $o('android', 'Android', 'Android', ['add' => [['item' => 'mobile-app', 'hours' => 160]], 'points' => 8]),
                        $o('ios', 'iOS', 'iOS', ['add' => [['item' => 'mobile-app', 'hours' => 80]], 'points' => 4]),
                    ]],
                    ['key' => 'offline', 'type' => 'boolean', 'required' => false, 'label' => $t('Harus bisa dipakai tanpa internet (offline)?', 'Must it work offline?'),
                        'options' => self::yesNo(['add' => [['item' => 'mobile-app', 'hours' => 40]], 'points' => 4])],
                    ['key' => 'push', 'type' => 'boolean', 'required' => false, 'label' => $t('Perlu notifikasi (push notification)?', 'Do you need push notifications?'),
                        'options' => self::yesNo(['add' => [['item' => 'mobile-app', 'hours' => 16]], 'points' => 1])],
                    ['key' => 'mobile_backend', 'type' => 'boolean', 'required' => false, 'label' => $t('Aplikasi terhubung ke sistem / data di server?', 'Does the app connect to a system / data on a server?'),
                        'options' => self::yesNo(['add' => [self::module('Backend & API untuk aplikasi mobile', 'Backend & API for the mobile app', 80)], 'points' => 4])],
                ],
            ],
            [
                'key' => 'integrations',
                'show_if' => $needs('website', 'system', 'mobile', 'chat'),
                'title' => $t('Integrasi', 'Integrations'),
                'questions' => [[
                    'key' => 'integrations', 'type' => 'multi', 'required' => false,
                    'label' => $t('Layanan yang perlu disambungkan', 'Services to connect'),
                    'options' => [
                        $o('payment', 'Payment gateway (Midtrans, Xendit, dll.)', 'Payment gateway (Midtrans, Xendit, etc.)', ['add' => [self::integration('Integrasi payment gateway', 'Payment gateway integration', 24)], 'points' => 3]),
                        $o('whatsapp', 'WhatsApp API', 'WhatsApp API', ['add' => [self::integration('Integrasi WhatsApp API', 'WhatsApp API integration', 16)], 'points' => 2]),
                        $o('accounting', 'Software akuntansi (Jurnal, Accurate, dll.)', 'Accounting software (Jurnal, Accurate, etc.)', ['add' => [self::integration('Integrasi software akuntansi', 'Accounting software integration', 32)], 'points' => 4]),
                        $o('shipping', 'Ekspedisi / ongkos kirim', 'Shipping / courier rates', ['add' => [self::integration('Integrasi ekspedisi / ongkir', 'Shipping integration', 16)], 'points' => 2]),
                        $o('maps', 'Peta / lokasi', 'Maps / location', ['add' => [self::integration('Integrasi peta / lokasi', 'Maps integration', 8)], 'points' => 1]),
                        $o('login', 'Login Google / SSO', 'Google login / SSO', ['add' => [self::integration('Login Google / SSO', 'Google login / SSO', 8)], 'points' => 1]),
                        $o('other_api', 'API lain (jelaskan di catatan)', 'Another API (describe in the notes)', ['add' => [self::integration('Integrasi API lainnya', 'Other API integration', 24)], 'points' => 3]),
                    ],
                ]],
            ],
            [
                'key' => 'chat',
                'show_if' => $needs('chat'),
                'title' => $t('Chat Commerce / Chatbot', 'Chat Commerce / Chatbot'),
                'questions' => [[
                    'key' => 'chat_type', 'type' => 'single', 'required' => true, 'label' => $t('Jenis chatbot', 'Type of chatbot'),
                    'options' => [
                        $o('menu', 'Menu & tombol untuk order / transaksi', 'Menus & buttons for orders / transactions', ['add' => [['item' => 'jcd-flow']], 'points' => 3]),
                        $o('ai', 'AI yang menjawab pertanyaan pelanggan', 'AI that answers customer questions', ['add' => [['item' => 'jcd-smart']], 'points' => 5]),
                        $o('omnichannel', 'Banyak kanal (WhatsApp, Instagram, web) dalam satu dashboard', 'Many channels (WhatsApp, Instagram, web) in one dashboard', ['add' => [['item' => 'jcd-conversational']], 'points' => 8,
                            'flags' => [self::flag('JCD Conversational masih tahap persiapan -- tawarkan JCD Flow / JCD Smart dulu.', 'JCD Conversational is still in preparation -- offer JCD Flow / JCD Smart first.')]]),
                    ],
                ]],
            ],
            [
                'key' => 'design',
                'show_if' => $needs('design', 'redesign', 'website'),
                'title' => $t('Desain & Brand', 'Design & Brand'),
                'questions' => [
                    ['key' => 'brand', 'type' => 'single', 'required' => false, 'label' => $t('Identitas brand yang sudah ada', 'Existing brand identity'), 'options' => [
                        $o('guideline', 'Sudah ada brand guideline', 'Brand guidelines exist'),
                        $o('logo', 'Hanya logo', 'Logo only', ['points' => 1]),
                        $o('none', 'Belum ada', 'None yet', ['points' => 2, 'flags' => [self::flag('Belum ada identitas brand -- perlu waktu ekstra untuk eksplorasi visual.', 'No brand identity yet -- allow extra time for visual exploration.')]]),
                    ]],
                    ['key' => 'redesign_scope', 'type' => 'single', 'required' => true, 'show_if' => $needs('redesign'), 'label' => $t('Cakupan redesign', 'Redesign scope'), 'options' => [
                        $o('visual', 'Tampilan saja (tanpa mengubah fitur)', 'Look only (features unchanged)', ['add' => [['item' => 'jcd-recreative']], 'points' => 2]),
                        $o('flow', 'Tampilan dan alur penggunaan', 'Look and user flow', ['add' => [['item' => 'jcd-recreative'], ['item' => 'jcd-creative']], 'points' => 4]),
                    ]],
                ],
            ],
            [
                'key' => 'infrastructure',
                'show_if' => $needs('hosting'),
                'title' => $t('Hosting, Domain & Email', 'Hosting, Domain & Email'),
                'questions' => [
                    ['key' => 'has_domain', 'type' => 'boolean', 'required' => false, 'label' => $t('Sudah punya domain?', 'Do you already have a domain?'), 'options' => self::yesNo([], ['add' => [['item' => 'domain']]])],
                    ['key' => 'has_hosting', 'type' => 'boolean', 'required' => false, 'label' => $t('Sudah punya hosting / server?', 'Do you already have hosting / a server?'), 'options' => self::yesNo([], ['add' => [['item' => 'hosting']]])],
                    ['key' => 'business_email', 'type' => 'boolean', 'required' => false, 'label' => $t('Butuh email bisnis (nama@perusahaan.com)?', 'Do you need business email (name@company.com)?'), 'options' => self::yesNo(['add' => [['item' => 'business-email']]])],
                ],
            ],
            [
                'key' => 'maintenance',
                'show_if' => $needs('maintenance'),
                'title' => $t('Maintenance & Support', 'Maintenance & Support'),
                'questions' => [[
                    'key' => 'support_hours', 'type' => 'single', 'required' => false, 'label' => $t('Perkiraan kebutuhan support per bulan', 'Expected support per month'),
                    'options' => [
                        $o('light', 'Ringan (sesekali perubahan kecil)', 'Light (occasional small changes)'),
                        $o('regular', 'Rutin (update tiap minggu)', 'Regular (weekly updates)', ['points' => 1]),
                        $o('intensive', 'Intensif (pengembangan berkelanjutan)', 'Intensive (continuous development)', ['points' => 2,
                            'flags' => [self::flag('Butuh support intensif -- tawarkan paket JCD Setup dengan kuota jam lebih besar.', 'Intensive support -- offer a JCD Setup plan with more hours.')]]),
                    ],
                ]],
            ],
            [
                'key' => 'timeline',
                'title' => $t('Waktu & Anggaran', 'Timeline & Budget'),
                'questions' => [
                    ['key' => 'deadline', 'type' => 'single', 'required' => true, 'label' => $t('Target selesai', 'Target completion'), 'options' => [
                        $o('<1m', 'Kurang dari 1 bulan', 'Less than 1 month', ['points' => 3, 'flags' => [self::flag('Target < 1 bulan -- cek kapasitas tim atau sarankan rilis bertahap.', 'Target under 1 month -- check team capacity or suggest a phased release.')]]),
                        $o('1-3m', '1-3 bulan', '1-3 months'),
                        $o('3-6m', '3-6 bulan', '3-6 months'),
                        $o('flexible', 'Fleksibel', 'Flexible'),
                    ]],
                    ['key' => 'budget', 'type' => 'single', 'required' => true, 'label' => $t('Kisaran anggaran', 'Budget range'), 'options' => [
                        $o('<10', 'Di bawah Rp. 10 juta', 'Under IDR 10 million', ['min' => 0, 'max' => 10000000]),
                        $o('10-25', 'Rp. 10-25 juta', 'IDR 10-25 million', ['min' => 10000000, 'max' => 25000000]),
                        $o('25-50', 'Rp. 25-50 juta', 'IDR 25-50 million', ['min' => 25000000, 'max' => 50000000]),
                        $o('50-100', 'Rp. 50-100 juta', 'IDR 50-100 million', ['min' => 50000000, 'max' => 100000000]),
                        $o('100+', 'Di atas Rp. 100 juta', 'Over IDR 100 million', ['min' => 100000000]),
                        $o('unknown', 'Belum tahu / ingin dikonsultasikan', 'Not sure / want advice'),
                    ]],
                    ['key' => 'notes', 'type' => 'textarea', 'required' => false, 'label' => $t('Hal lain yang perlu kami ketahui', 'Anything else we should know')],
                ],
            ],
        ]];
    }
}
