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

(function initHyperMISTCarousel(rootSel = ".hypermist-carousel"){
  const roots = document.querySelectorAll(rootSel);
  roots.forEach(root => {
    const slides = Array.from(root.querySelectorAll(".hm-slide"));
    if (slides.length < 3) return;

    const prev = root.querySelector(".hm-prev");
    const next = root.querySelector(".hm-next");
    const track = root.querySelector(".hm-track");

    // TROVA la caption anche se è fuori dal <section>
    // 1) prova dentro root
    // 2) prova nel fratello successivo (es. la tua <div class="row text-center">)
    // 3) prova nel parent (fallback)
    let captionEl =
      root.querySelector(".hm-caption") ||
      root.nextElementSibling?.querySelector?.(".hm-caption") ||
      root.parentElement?.querySelector?.(".hm-caption");

    const captions = slides.map(el =>
      el.dataset.caption?.trim() ||
      el.querySelector("img")?.alt?.trim() ||
      ""
    );

    let i = 0, startX = 0, dx = 0, isPointerDown = false;
    const sideGap = 0.38;
    let firstRender = true; // evita che la caption resti opaca al primo giro

    function updateCaption(){
      if (!captionEl) return;
      const text = captions[i] || "";
      if (firstRender){
        captionEl.textContent = text;
        captionEl.style.opacity = 1;     // visibile subito
        firstRender = false;
        return;
      }
      captionEl.style.opacity = 0;
      requestAnimationFrame(() => {
        captionEl.textContent = text;
        captionEl.style.opacity = 0.92;  // fade-in
      });
    }

    function render(){
      const N = slides.length;
      slides.forEach((el, k) => {
        let d = k - i;
        if (d >  N/2) d -= N;
        if (d < -N/2) d += N;

        const baseX = d * sideGap;
        const w = el.getBoundingClientRect().width || 1;
        const dragOffset = (dx / w);

        const depth = Math.abs(d);
        const translateX = (baseX - dragOffset) * 100;
        const scale = d === 0 ? 1 : parseFloat(getComputedStyle(root).getPropertyValue("--hm-side-scale")) || 0.82;
        const opacitySide = parseFloat(getComputedStyle(root).getPropertyValue("--hm-side-fade")) || 0.55;
        const opacity = d === 0 ? 1 : (depth === 1 ? opacitySide : 0);

        el.style.transform = `translateX(${translateX}%) scale(${d===0?1:scale})`;
        el.style.opacity = opacity;
        el.style.pointerEvents = d === 0 ? "auto" : "none";
        el.style.filter = d === 0 ? "none" : "grayscale(.1) saturate(.9)";
        el.style.zIndex = String(100 - depth);
        el.ariaHidden = d === 0 ? "false" : "true";
      });

      updateCaption();
    }

    function go(delta){ i = (i + delta + slides.length) % slides.length; dx = 0; render(); }

    next?.addEventListener("click", () => go(+1));
    prev?.addEventListener("click", () => go(-1));

    root.tabIndex = 0;
    root.addEventListener("keydown", (e) => {
      if (e.key === "ArrowRight") go(+1);
      if (e.key === "ArrowLeft")  go(-1);
    });

    track.addEventListener("pointerdown", (e) => {
      isPointerDown = true; startX = e.clientX; dx = 0;
      track.setPointerCapture(e.pointerId);
      slides.forEach(s => s.style.transition = "none");
    });
    track.addEventListener("pointermove", (e) => {
      if (!isPointerDown) return;
      dx = e.clientX - startX; render();
    });
    track.addEventListener("pointerup", endDrag);
    track.addEventListener("pointercancel", endDrag);
    function endDrag(){
      if (!isPointerDown) return;
      isPointerDown = false; slides.forEach(s => s.style.transition = "");
      const threshold = (root.clientWidth * 0.08);
      if (dx < -threshold) go(+1);
      else if (dx > threshold) go(-1);
      else { dx = 0; render(); }
    }

    const ro = new ResizeObserver(render);
    ro.observe(root);

    render(); // inizializza TUTTO, caption inclusa
  });
})();

