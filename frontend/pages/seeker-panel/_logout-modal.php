<div id="logout-modal" class="logout-modal" role="dialog" aria-modal="true" aria-labelledby="logout-modal-title">
  <div class="logout-modal__backdrop" aria-hidden="true"></div>
  <div class="logout-modal__card" role="document">
    <p class="logout-modal__kicker">Çıkış Yap</p>
    <h2 id="logout-modal-title" class="logout-modal__title">Emin misin?</h2>
    <p class="logout-modal__lead">Hesabından çıkış yapmak üzeresin. Devam edersen tekrar giriş yapman gerekecek.</p>
    <div class="logout-modal__actions">
      <form class="logout-modal__form" action="/logout.php" method="post">
        <input type="hidden" name="confirm" value="yes">
        <button type="submit" class="logout-modal__btn logout-modal__btn--danger">Evet, çıkış yap</button>
      </form>
      <button type="button" class="logout-modal__btn logout-modal__btn--ghost" data-logout-close>Vazgeç</button>
    </div>
  </div>
</div>
