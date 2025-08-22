(() => {
  const wall = document.getElementById('wall');
  const modalBackdrop = document.getElementById('modalBackdrop');
  const openModalBtn = document.getElementById('openModalBtn');
  const cancelBtn = document.getElementById('cancelBtn');
  const wishForm = document.getElementById('wishForm');

  let lastId = 0;
  const wishesMap = new Map(); // id -> element
  let paused = false;
  let focusedEl = null;

  function pauseAll() {
    paused = true;
    for (const el of wishesMap.values()) {
      const st = el._state;
      if (st && st.raf) {
        cancelAnimationFrame(st.raf);
        st.raf = null;
      }
    }
  }

  function resumeAll() {
    paused = false;
    for (const el of wishesMap.values()) {
      const st = el._state;
      if (!st) continue;
      st.lastTs = performance.now();
      if (!st.raf) st.raf = requestAnimationFrame(st.tick);
    }
  }

  function focusWish(el) {
    if (focusedEl) return;
    pauseAll();
    focusedEl = el;
    el.classList.add('focused');
    for (const other of wishesMap.values()) {
      if (other !== el) other.classList.add('dimmed');
    }
    const st = el._state;
    const wallRect = wall.getBoundingClientRect();
    const rect = el.getBoundingClientRect();
    st.x = (wallRect.width - rect.width) / 2;
    st.y = (wallRect.height - rect.height) / 2;
    el.style.transition = 'transform 0.6s';
    el.style.transform = `translate(${st.x}px, ${st.y}px)`;
    el.style.zIndex = '50';
    const btn = document.createElement('button');
    btn.className = 'close-btn';
    btn.innerHTML = '&times;';
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      unfocusWish();
    });
    el.appendChild(btn);
  }

  function unfocusWish() {
    if (!focusedEl) return;
    const el = focusedEl;
    el.classList.remove('focused');
    el.style.transition = '';
    el.style.zIndex = '';
    const btn = el.querySelector('.close-btn');
    if (btn) btn.remove();
    for (const other of wishesMap.values()) {
      other.classList.remove('dimmed');
    }
    focusedEl = null;
    resumeAll();
  }

  function openModal() {
    modalBackdrop.classList.add('show');
    modalBackdrop.setAttribute('aria-hidden', 'false');
    setTimeout(() => document.getElementById('name').focus(), 50);
  }
  function closeModal() {
    modalBackdrop.classList.remove('show');
    modalBackdrop.setAttribute('aria-hidden', 'true');
    wishForm.reset();
  }
  openModalBtn.addEventListener('click', openModal);
  cancelBtn.addEventListener('click', closeModal);
  modalBackdrop.addEventListener('click', (e) => { if (e.target === modalBackdrop) closeModal(); });

  wishForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(wishForm);
    try {
      const res = await fetch('/api/wish', { method:'POST', body: formData });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Ошибка отправки');
      closeModal();
      // Optionally: temporary badge telling user it's sent
      // New wishes (visible now) will arrive via polling
    } catch (err) {
      alert(err.message || 'Не удалось отправить');
    }
  });

  /** Create a floating card and start animation */
  function spawnWishCard(w) {
    if (wishesMap.has(w.id)) return;
    const el = document.createElement('div');
    el.className = 'wish';
    el.dataset.id = w.id;
    el.innerHTML = `
      <div class="text"></div>
      <div class="name">— <span></span></div>
    `;
    // Encode text and name to avoid XSS
    el.querySelector('.text').textContent = w.text;
    el.querySelector('.name span').textContent = w.name;

    wall.appendChild(el);
    if (focusedEl) el.classList.add('dimmed');

    // Random initial position
    const wallRect = wall.getBoundingClientRect();
    const elRect = el.getBoundingClientRect();
    const state = {
      x: Math.random() * Math.max(10, wallRect.width - elRect.width - 10),
      y: Math.random() * Math.max(10, wallRect.height - elRect.height - 10),
      speed: 30 + Math.random() * 45,
    };
    const angle = Math.random() * Math.PI * 2;
    state.vx = Math.cos(angle) * state.speed;
    state.vy = Math.sin(angle) * state.speed;
    state.lastTs = performance.now();
    function tick(ts) {
      const dt = (ts - state.lastTs) / 1000; // sec
      state.lastTs = ts;

      state.x += state.vx * dt;
      state.y += state.vy * dt;

      const rect = el.getBoundingClientRect();
      const maxX = wall.clientWidth - rect.width;
      const maxY = wall.clientHeight - rect.height;

      if (state.x < 0) { state.x = 0; state.vx = Math.abs(state.vx); }
      else if (state.x > maxX) { state.x = maxX; state.vx = -Math.abs(state.vx); }

      if (state.y < 0) { state.y = 0; state.vy = Math.abs(state.vy); }
      else if (state.y > maxY) { state.y = maxY; state.vy = -Math.abs(state.vy); }

      el.style.transform = `translate(${state.x}px, ${state.y}px)`;
      if (!paused) state.raf = requestAnimationFrame(tick);
    }
    state.tick = tick;
    el._state = state;
    if (!paused) state.raf = requestAnimationFrame(tick);

    const onResize = () => {
      const rect = el.getBoundingClientRect();
      const maxX = wall.clientWidth - rect.width;
      const maxY = wall.clientHeight - rect.height;
      if (state.x > maxX) state.x = Math.max(0, maxX);
      if (state.y > maxY) state.y = Math.max(0, maxY);
      el.style.transform = `translate(${state.x}px, ${state.y}px)`;
    };
    window.addEventListener('resize', onResize);

    el.addEventListener('click', () => {
      if (focusedEl === el) return;
      focusWish(el);
    });

    wishesMap.set(w.id, el);
    if (w.id > lastId) lastId = w.id;
  }

  async function fetchWishes(initial=false) {
    try {
      const url = lastId > 0 ? `/api/wishes?since=${lastId}` : '/api/wishes';
      const res = await fetch(url);
      const data = await res.json();
      if (!data.ok) return;
      for (const w of data.items) {
        spawnWishCard(w);
      }
      wall.setAttribute('aria-busy', 'false');
      // Slight sparkle for newcomers
      if (!initial && data.items && data.items.length) {
        for (const w of data.items) {
          const el = wishesMap.get(w.id);
          if (!el) continue;
          const badge = document.createElement('div');
          badge.className = 'badge-new';
          badge.textContent = 'NEW';
          el.appendChild(badge);
          setTimeout(() => badge.remove(), 4000);
        }
      }
    } catch (e) {
      // Ignore network hiccups
    }
  }

  // Initial load
  fetchWishes(true);
  // Poll every 2.5s for new items
  setInterval(fetchWishes, 2500);

})();
