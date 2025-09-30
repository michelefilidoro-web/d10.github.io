/* ============================
   Animazioni & UX – script.js
   ============================ */

/* Reveal on Scroll (IntersectionObserver) */
(function () {
  const els = document.querySelectorAll('[data-animate], .reveal, .reveal-zoom');
  if (!els.length) return;

  if (!('IntersectionObserver' in window)) {
    // Fallback per browser vecchi: mostra tutto
    els.forEach(el => el.classList.add('is-visible'));
    return;
  }

  const io = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const el = entry.target;
        const delay = el.getAttribute('data-delay');
        if (delay) el.style.transitionDelay = delay;
        el.classList.add('is-visible');
        io.unobserve(el);
      }
    });
  }, { threshold: 0.15, rootMargin: '0px 0px -5% 0px' });

  els.forEach(el => io.observe(el));
})();

/* Navbar shadow quando si scrolla */
(function () {
  const nav = document.querySelector('.navbar');
  if (!nav) return;

  const onScroll = () => {
    if (window.scrollY > 6) nav.classList.add('is-scrolled');
    else nav.classList.remove('is-scrolled');
  };

  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });
})();

/* Parallax leggero sull’hero (opzionale, solo mouse/pointer fine) */
(function () {
  if (window.matchMedia && !window.matchMedia('(pointer: fine)').matches) return;

  const tiltEls = document.querySelectorAll('.parallax-tilt');
  if (!tiltEls.length) return;

  const max = 4; // spostamento massimo in px

  const onMove = (e) => {
    const { innerWidth: w, innerHeight: h } = window;
    const dx = (e.clientX / w - 0.5) * 2; // -1..1
    const dy = (e.clientY / h - 0.5) * 2;
    tiltEls.forEach(el => {
      el.style.transform = `translate(${dx * max}px, ${dy * max}px)`;
    });
  };

  const reset = () => {
    tiltEls.forEach(el => { el.style.transform = ''; });
  };

  document.addEventListener('mousemove', onMove);
  window.addEventListener('mouseout', reset);
})();

(function(){
  function ready(fn){
    if (document.readyState === 'complete' || document.readyState === 'interactive') setTimeout(fn,0);
    else document.addEventListener('DOMContentLoaded', fn, { once:true });
  }
  ready(() => {
    document.querySelectorAll('.hm-tech-toggle').forEach(btn => {
      const panelId = btn.getAttribute('aria-controls');
      const panel = document.getElementById(panelId);
      if (!panel) return;

      // >>> usa il <p> interno come label senza distruggere il markup
      const labelEl =
        btn.querySelector('.hm-tech-label') ||
        btn.querySelector('p') ||
        null;

      function setLabel(text){
        if (labelEl) labelEl.textContent = text;
        else btn.textContent = text; // fallback se manca il <p>
      }

      function open(){
        panel.classList.add('is-open');
        panel.style.maxHeight = panel.scrollHeight + 'px';
        btn.setAttribute('aria-expanded', 'true');
        setLabel('Nascondi scheda tecnica');
      }
      function close(){
        panel.style.maxHeight = panel.scrollHeight + 'px';
        requestAnimationFrame(() => {
          panel.classList.remove('is-open');
          panel.style.maxHeight = '0px';
          btn.setAttribute('aria-expanded', 'false');
          setLabel('Vedi scheda tecnica');
        });
      }
      function toggle(){ (btn.getAttribute('aria-expanded')==='true') ? close() : open(); }

      btn.addEventListener('click', toggle);
      window.addEventListener('resize', () => {
        if (btn.getAttribute('aria-expanded')==='true') {
          panel.style.maxHeight = panel.scrollHeight + 'px';
        }
      }, { passive:true });
    });
  });
})();


