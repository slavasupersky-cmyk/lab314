(function() {
  // Определяем активную страницу
  var path = window.location.pathname.split('/').pop() || 'index.html';

  var NAV_HTML = `
<style>
#site-nav {
  position: fixed; top: 0; left: 0; right: 0; z-index: 500;
  display: flex; align-items: center; justify-content: space-between;
  padding: 24px 48px;
  background: linear-gradient(to bottom, rgba(8,8,8,0.95), transparent);
  transition: background 0.4s, border-color 0.4s;
}
#site-nav.scrolled {
  background: rgba(8,8,8,0.97);
  border-bottom: 1px solid #1e1e1e;
}
.nav-logo {
  font-family: 'Unbounded', sans-serif; font-weight: 200;
  font-size: 15px; letter-spacing: 4px; color: #c9a96e; text-decoration: none;
  line-height: 1;
}
.nav-logo span {
  color: #8a8072; font-size: 12px; display: block; letter-spacing: 4px; margin-top: 2px;
  font-family: 'Jost', sans-serif; font-weight: 300; text-transform: uppercase;
}
.nav-links {
  display: flex; gap: 36px; align-items: center;
}
.nav-links a {
  font-family: 'Jost', sans-serif;
  font-size: 12px; letter-spacing: 2px; text-transform: uppercase;
  color: #c4b8a4; text-decoration: none; transition: color 0.3s;
  position: relative; white-space: nowrap;
}
.nav-links a::after {
  content: ''; position: absolute; bottom: -4px; left: 0;
  width: 0; height: 1px; background: #c9a96e; transition: width 0.3s;
}
.nav-links a:hover { color: #c9a96e; }
.nav-links a:hover::after { width: 100%; }
.nav-links a.nav-active { color: #c9a96e; }
.nav-links a.nav-active::after { width: 100%; }
.nav-dropdown { position: relative; display: flex; align-items: center; }
.nav-dropdown > a::after { display: none; }
.nav-dropdown-menu {
  position: absolute; top: calc(100% + 12px); left: 50%;
  transform: translateX(-50%) translateY(-6px);
  background: #151515; border: 1px solid #2a2a2a;
  min-width: 280px; opacity: 0; pointer-events: none;
  transition: opacity 0.2s, transform 0.2s; z-index: 200;
}
.nav-dropdown-menu.open {
  opacity: 1; pointer-events: all; transform: translateX(-50%) translateY(0);
}
.nav-dropdown-menu a {
  display: block; padding: 14px 20px; font-size: 11px; letter-spacing: 1px;
  border-bottom: 1px solid #1e1e1e; color: #c4b8a4;
  text-decoration: none; transition: color 0.2s, background 0.2s;
  white-space: nowrap;
}
.nav-dropdown-menu a::after { display: none !important; }
.nav-dropdown-menu a:last-child { border-bottom: none; }
.nav-dropdown-menu a:hover { background: #1e1e1e; color: #c9a96e; }
.nav-cta {
  font-size: 11px !important; letter-spacing: 2px; text-transform: uppercase;
  color: #080808 !important; background: #c9a96e;
  padding: 10px 20px; text-decoration: none;
  transition: background 0.3s !important; font-weight: 500;
}
.nav-cta::after { display: none !important; }
.nav-cta:hover { background: #e8c880 !important; color: #080808 !important; }
@media (max-width: 900px) {
  #site-nav { padding: 20px 24px; }
  .nav-links { gap: 16px; }
  .nav-links a:not(.nav-cta) { display: none; }
}
</style>
<nav id="site-nav">
  <a href="index.html" class="nav-logo">
    ARCH 3.14
    <span>Архитектурное бюро</span>
  </a>
  <div class="nav-links">
    <div class="nav-dropdown">
      <a href="#" id="nav-directions">Направления</a>
      <div class="nav-dropdown-menu" id="nav-dropdown-menu">
        <a href="index.html">ARCH 3.14 — Частная архитектура</a>
        <a href="lab.html">LAB 3.14 — Бизнес архитектура</a>
        <a href="construct.html">CONSTRUCT 3.14 — Бионические конструкции</a>
      </div>
    </div>
    <a href="portfolio.html" data-page="portfolio.html">Портфолио</a>
    <a href="prices.html" data-page="prices.html">Стоимость</a>
    <a href="#" data-page="">О бюро</a>
    <a href="start.html" class="nav-cta">Начать проект →</a>
  </div>
</nav>`;

  // Вставляем nav
  document.body.insertAdjacentHTML('afterbegin', NAV_HTML);

  // Активная ссылка
  document.querySelectorAll('#site-nav .nav-links a[data-page]').forEach(function(a) {
    if (a.dataset.page === path) a.classList.add('nav-active');
  });

  // Scroll
  var nav = document.getElementById('site-nav');
  window.addEventListener('scroll', function() {
    nav.classList.toggle('scrolled', window.scrollY > 60);
  });

  // Dropdown click
  var btn = document.getElementById('nav-directions');
  var menu = document.getElementById('nav-dropdown-menu');
  btn.addEventListener('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    menu.classList.toggle('open');
  });
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.nav-dropdown')) menu.classList.remove('open');
  });
})();
