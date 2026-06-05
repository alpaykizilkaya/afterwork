<?php

declare(strict_types=1);

/**
 * Canonical option vocabularies for job listings.
 *
 * Single source of truth shared by BOTH the employer posting form
 * (dashboard-page.php) and the Akış feed filters (feed-page.php), so a value an
 * employer can pick is always a value the feed can filter on — no drift, no
 * filter that matches nothing.
 *
 * Date ranges and experience bands are derived from existing columns
 * (created_at / experience_level) and so live here only as label maps.
 */
function aw_taxonomy(): array
{
    static $t = null;
    if ($t !== null) {
        return $t;
    }

    $t = [
        // employers.sector
        'sectors' => [
            'Bilişim', 'Bilgi Teknolojileri', 'Yazılım', 'Telekomünikasyon',
            'Finans - Ekonomi', 'Sigortacılık', 'Danışmanlık', 'Eğitim',
            'Sağlık', 'Dental', 'İlaç', 'Üretim / Endüstriyel Ürünler',
            'Otomotiv', 'Elektrik & Elektronik', 'Enerji', 'Gıda', 'Kimya',
            'Maden ve Metal Sanayi', 'Mobilya & Aksesuar', 'Ev Eşyaları',
            'Tekstil', 'Yapı / İnşaat', 'Lojistik', 'Taşımacılık', 'Denizcilik',
            'Havacılık', 'Turizm', 'Perakende', 'Ticaret', 'Hızlı Tüketim Malları',
            'Dayanıklı Tüketim Ürünleri', 'Medya', 'Basım - Yayın',
            'Reklam ve Tanıtım', 'Eğlence - Kültür - Sanat', 'Tarım / Ziraat',
            'Hayvancılık', 'Orman Ürünleri', 'Güvenlik', 'Hizmet', 'Organizasyon',
            'Çevre', 'Atık Yönetimi ve Geri Dönüşüm', 'Bina ve Site Yönetimi',
            'Ofis / Büro Malzemeleri', 'Diğer',
        ],

        // job_listings.department
        'departments' => [
            'Akademik', 'AR-GE', 'Arşiv / Dokümantasyon', 'Bakım / Onarım',
            'Bilgi İşlem', 'Bilgi Teknolojileri / IT', 'Depo / Antrepo', 'Eğitim',
            'Finans', 'Mali İşler', 'Muhasebe', 'Güvenlik', 'Halkla İlişkiler',
            'Hizmet', 'Hukuk', 'İdari İşler', 'İnsan Kaynakları', 'İş Geliştirme',
            'İthalat / İhracat', 'Kalite', 'Lojistik', 'Mimarlık', 'Mühendislik',
            'Müşteri Hizmetleri / Çağrı Merkezi', 'Müşteri İlişkileri', 'Nakliye',
            'Operasyon', 'Organizasyon', 'Pazar Araştırma', 'Pazarlama',
            'Dijital Pazarlama', 'Reklam', 'Sağlık', 'Satınalma', 'Satış',
            'Satış Geliştirme', 'E-Ticaret', 'Sekreterya', 'Tasarım / Grafik',
            'Teknik', 'Teknikerlik', 'Teknisyenlik', 'Teknoloji', 'Turizm',
            'Üretim / İmalat', 'Yönetim', 'İş Sağlığı ve Güvenliği', 'Laboratuvar',
            'Planlama', 'Tedarik Yönetimi', 'Risk Yönetimi', 'Diğer',
        ],

        // job_listings.position_level
        'position_levels' => [
            'Üst düzey yönetici', 'Orta düzey yönetici', 'Yönetici adayı',
            'Uzman', 'Uzman Yardımcısı', 'Yeni Başlayan', 'Eleman',
            'İşçi ve Mavi Yaka', 'Hizmet Personeli', 'Serbest / Freelancer',
            'Stajyer',
        ],

        // job_listings.education_level
        'education_levels' => [
            'İlköğretim', 'Lise', 'Meslek Yüksekokulu (Ön Lisans)',
            'Üniversite (Lisans)', 'Yüksek Lisans', 'Doktora',
        ],

        // employers.company_size
        'company_sizes' => [
            '1–10 kişi', '11–50 kişi', '51–200 kişi', '201–500 kişi', '500+ kişi',
        ],

        // job_listings.work_model  (a.k.a. "Çalışma Tercihi")
        'work_models' => ['Ofiste', 'Uzaktan', 'Hibrit'],

        // job_listings.employment_type  (a.k.a. "Çalışma Şekli")
        'employment_types' => [
            'Tam Zamanlı', 'Yarı Zamanlı', 'Proje Bazlı', 'Dönemsel',
            'Sözleşmeli', 'Staj', 'Freelance',
        ],

        // job_listings.experience_level
        'experience_levels' => [
            'Deneyim Aranmıyor', 'Junior (0–2 yıl)', 'Mid-level (2–5 yıl)',
            'Senior (5+ yıl)', 'Lead / Yönetici',
        ],

        // job_listings.listing_language
        'languages' => ['Türkçe', 'İngilizce'],

        // Derived from created_at — value => label. Empty value = all dates.
        'date_ranges' => [
            'bugun' => 'Bugünün ilanları',
            '3saat' => 'Son 3 saat',
            '8saat' => 'Son 8 saat',
            '3gun'  => 'Son 3 gün',
            '7gun'  => 'Son 7 gün',
            '15gun' => 'Son 15 gün',
        ],

        // Derived from experience_level — value => label.
        'experience_bands' => [
            'deneyimli'  => 'Deneyimli',
            'deneyimsiz' => 'Deneyimsiz',
        ],
    ];

    return $t;
}
