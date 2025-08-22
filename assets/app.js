(() => {
  const wall = document.getElementById('wall');
  const modalBackdrop = document.getElementById('modalBackdrop');
  const openModalBtn = document.getElementById('openModalBtn');
  const cancelBtn = document.getElementById('cancelBtn');
  const wishForm = document.getElementById('wishForm');

  let lastId = 0;
  const wishesMap = new Map(); // id -> element

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

    // Random initial position
    const wallRect = wall.getBoundingClientRect();
    const elRect = el.getBoundingClientRect();
    let x = Math.random() * Math.max(10, wallRect.width - elRect.width - 10);
    let y = Math.random() * Math.max(10, wallRect.height - elRect.height - 10);

    // Random velocity (px/sec)
    let speed = 30 + Math.random() * 45; // 30..75 px/s
    // Random direction
    let angle = Math.random() * Math.PI * 2;
    let vx = Math.cos(angle) * speed;
    let vy = Math.sin(angle) * speed;

    // Keep within bounds and bounce
    let lastTs = performance.now();
    function tick(ts) {
      const dt = (ts - lastTs) / 1000; // sec
      lastTs = ts;

      x += vx * dt;
      y += vy * dt;

      const rect = el.getBoundingClientRect();
      // Bounds (using current element size to avoid clipping)
      const maxX = wall.clientWidth - rect.width;
      const maxY = wall.clientHeight - rect.height;

      if (x < 0) { x = 0; vx = Math.abs(vx); }
      else if (x > maxX) { x = maxX; vx = -Math.abs(vx); }

      if (y < 0) { y = 0; vy = Math.abs(vy); }
      else if (y > maxY) { y = maxY; vy = -Math.abs(vy); }

      el.style.transform = `translate(${x}px, ${y}px)`;
      el._raf = requestAnimationFrame(tick);
    }
    el._raf = requestAnimationFrame(tick);

    // Handle resize
    const onResize = () => {
      const rect = el.getBoundingClientRect();
      const maxX = wall.clientWidth - rect.width;
      const maxY = wall.clientHeight - rect.height;
      if (x > maxX) x = Math.max(0, maxX);
      if (y > maxY) y = Math.max(0, maxY);
    };
    window.addEventListener('resize', onResize);

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
