<?php
/*
 * Çok sayfalı ürün turu yapılandırması — role göre adım listesi + tour.js.
 * Her panel sayfası </body>'den önce bu partial'ı include eder; tour.css ise
 * sayfa <head>'inde yüklenir. Adımlar ekranlar arası gezer (Dükkan/Vitrin →
 * Akış → Mesajlar) ve "İleri" başka ekrana ait bir adıma gelince oraya geçer.
 */
$awTourRole = (string) ($_SESSION['account']['role'] ?? '');

if ($awTourRole === 'seeker') {
    $awTour = [
        'id'   => 'seeker',
        'auto' => true,
        'steps' => [
            ['path' => '/seeker-panel.php', 'el' => null, 'kicker' => 'Hoş geldin', 'title' => 'Panelini tanıyalım', 'text' => 'Afterwork’te işin nasıl yürüdüğünü ekran ekran gezerek görelim. Dilediğin an “Turu atla” diyebilirsin.'],
            ['path' => '/seeker-panel.php', 'el' => '[data-tour="sk-nav"]', 'placement' => 'right', 'title' => 'Bölümlerin burada', 'text' => 'Vitrinin, göz attıkların, cebindekiler ve başvuruların — hepsine soldaki menüden geçersin.'],
            ['path' => '/seeker-panel.php', 'el' => '[data-tour="otw"]', 'placement' => 'bottom', 'title' => 'Görünür ol', 'text' => 'Bu anahtar açıkken işverenler seni aday aramalarında kolayca bulur.'],
            ['path' => '/seeker-panel.php', 'el' => '[data-tour="nav"]', 'placement' => 'bottom', 'title' => 'Şimdi Akış’a geçelim', 'text' => 'İlanlar Akış’ta. “İleri”ye bas, birlikte oraya geçip devam edelim.'],
            ['path' => '/akis.php', 'el' => '.ep-feed-filters', 'placement' => 'bottom', 'title' => 'Akış · ilanları filtrele', 'text' => 'Pozisyon, şehir, maaş ve daha fazlasıyla sana en uygun ilanları daralt.'],
            ['path' => '/akis.php', 'el' => '.ep-feed-foot-actions', 'placement' => 'top', 'title' => 'Başvur · cebe at · mesaj', 'text' => 'Beğendiğin ilana tek tıkla başvur; sonra bakmak için cebine at ya da işverene mesaj gönder.'],
            ['path' => '/akis.php', 'el' => '[data-tour="nav"]', 'placement' => 'bottom', 'title' => 'Mesajlara geçelim', 'text' => 'İşverenlerle yazışmaların Mesajlar’da toplanır. “İleri” ile oraya gidelim.'],
            ['path' => '/mesajlar.php', 'el' => null, 'title' => 'Mesajlar', 'text' => 'İşverenlerle tüm yazışmaların burada. Görüşme taleplerini ve sohbetleri buradan sürdürürsün.'],
            ['path' => '/mesajlar.php', 'el' => null, 'title' => 'Hazırsın 🎉', 'text' => 'Vitrinini doldur, ilanlara başvur. Bu turu sağ üstteki “?” ile istediğin an tekrar açabilirsin.'],
        ],
    ];
} elseif ($awTourRole === 'employer') {
    $awTour = [
        'id'   => 'employer',
        'auto' => true,
        'steps' => [
            ['path' => '/isveren-panel.php', 'el' => null, 'kicker' => 'Hoş geldin', 'title' => 'Dükkanını tanıyalım', 'text' => 'İşin nasıl yürüdüğünü ekran ekran gezelim. Dilediğin an “Turu atla” diyebilirsin.'],
            ['path' => '/isveren-panel.php', 'el' => '[data-tour="new"]', 'placement' => 'bottom', 'title' => 'İlanını yayınla', 'text' => 'Buradan yeni ilan oluştur: pozisyon, maaş bandı ve aranan özellikler. Doğru adaylar seni bulmaya başlar.'],
            ['path' => '/isveren-panel.php', 'el' => '[data-tour="nav"]', 'placement' => 'bottom', 'title' => 'Dükkan & Mercek', 'text' => 'İlanlarının görüntülenme ve başvuru sayıları Dükkan’da; bir ilana tıklayınca Mercek’te detaylı analitik açılır. Şimdi Akış’a geçelim.'],
            ['path' => '/akis.php', 'el' => '.ep-feed-filters', 'placement' => 'bottom', 'title' => 'Akış · aday ve ilanları gör', 'text' => 'Diğer ilanları ve adayları filtreleyerek incele, rakiplerini ve pazarı takip et.'],
            ['path' => '/akis.php', 'el' => '.ep-feed-foot-actions', 'placement' => 'top', 'title' => 'İletişime geç', 'text' => 'İlgilendiğin aday veya şirketle buradan mesajlaşmayı başlatabilirsin.'],
            ['path' => '/akis.php', 'el' => '[data-tour="nav"]', 'placement' => 'bottom', 'title' => 'Mesajlara geçelim', 'text' => 'Adaylarla yazışmaların Mesajlar’da. “İleri” ile oraya gidelim.'],
            ['path' => '/mesajlar.php', 'el' => null, 'title' => 'Mesajlar', 'text' => 'Adaylarla tüm yazışmaların burada toplanır; görüşmeye buradan geçersin.'],
            ['path' => '/mesajlar.php', 'el' => null, 'title' => 'Hazırsın 🎉', 'text' => 'İlk ilanını yayınla, gerisi akışına gelsin. Bu turu sağ üstteki “?” ile tekrar açabilirsin.'],
        ],
    ];
} else {
    $awTour = null;
}

if ($awTour !== null):
?>
<script>window.AW_TOUR = <?= json_encode($awTour, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="/frontend/assets/js/shared/tour.js?v=<?= filemtime(__DIR__ . '/../assets/js/shared/tour.js') ?>" defer></script>
<?php endif; ?>
