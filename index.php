<?php
declare(strict_types=1);

/**
 * Wish Wall — single-file PHP 8.3 + SQLite app
 * - One page app: drifting wishes + modal form
 * - Endpoints:
 *    GET  /              -> HTML (wall + modal)
 *    POST /api/wish      -> Add wish (name, text, surprise=0|1)
 *    GET  /api/wishes    -> JSON list (visible only), supports ?since={lastId}
 *    GET  /export.csv    -> CSV export (all wishes)
 *    GET  /seed          -> Insert demo wishes (optional)
 *
 * Run locally: php -S 0.0.0.0:8080 index.php
 */

const DB_FILE = __DIR__ . '/data/wishes.sqlite';

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    if (!is_dir(__DIR__ . '/data')) {
        mkdir(__DIR__ . '/data', 0777, true);
    }
    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    // Busy timeout for concurrent writes
    $pdo->exec("PRAGMA journal_mode=WAL;");
    $pdo->exec("PRAGMA busy_timeout=3000;");
    init_db($pdo);
    return $pdo;
}

function init_db(PDO $pdo): void {
    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS wishes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        text TEXT NOT NULL,
        created_at TEXT NOT NULL,
        -- visible_at controls when it can appear on the wall (e.g., 'tomorrow')
        visible_at TEXT NOT NULL,
        -- free-form JSON for future flags like {"paywall":true,"tag":"cringe"}
        meta TEXT
    );
    SQL);
    // Helpful index for polling
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_wishes_visible_id ON wishes(visible_at, id)");
}

function nowIso(): string { return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'); }

function tomorrowIso(): string {
    return (new DateTimeImmutable('tomorrow 10:00'))->format('Y-m-d H:i:s');
}

/** Basic sanitization; encode on output as well */
function clean(string $s, int $max = 500): string {
    $s = trim($s);
    $s = preg_replace('/\s+/', ' ', $s);
    if (mb_strlen($s) > $max) $s = mb_substr($s, 0, $max);
    return $s;
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($uri === '/api/wish' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Accept application/x-www-form-urlencoded or JSON
    $input = file_get_contents('php://input');
    $data = [];
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_starts_with($ct, 'application/json')) {
        $data = json_decode($input, true) ?: [];
    } else {
        $data = $_POST ?: [];
        if (!$data && $input) parse_str($input, $data);
    }

    $name = clean(strval($data['name'] ?? ''));
    $text = clean(strval($data['text'] ?? ''), 1000);
    $surprise = intval($data['surprise'] ?? 0) === 1;

    if ($name === '' || $text === '') {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Name and text are required']);
        exit;
    }

    // Visible now or tomorrow (set to tomorrow 10:00 for ceremony vibe)
    $visibleAt = $surprise ? tomorrowIso() : nowIso();
    $meta = $surprise ? json_encode(['surprise' => true]) : null;

    $pdo = db();
    $stmt = $pdo->prepare("INSERT INTO wishes(name, text, created_at, visible_at, meta) VALUES(?,?,?,?,?)");
    $stmt->execute([$name, $text, nowIso(), $visibleAt, $meta]);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

if ($uri === '/api/wishes' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $since = isset($_GET['since']) ? max(0, intval($_GET['since'])) : 0;
    $now = nowIso();
    $pdo = db();
    if ($since > 0) {
        $stmt = $pdo->prepare("SELECT id, name, text, created_at FROM wishes WHERE visible_at <= ? AND id > ? ORDER BY id ASC");
        $stmt->execute([$now, $since]);
    } else {
        $stmt = $pdo->prepare("SELECT id, name, text, created_at FROM wishes WHERE visible_at <= ? ORDER BY id ASC");
        $stmt->execute([$now]);
    }
    $rows = $stmt->fetchAll();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'items' => $rows]);
    exit;
}

if ($uri === '/export.csv') {
    $pdo = db();
    $stmt = $pdo->query("SELECT id, name, text, created_at, visible_at, COALESCE(meta,'') as meta FROM wishes ORDER BY id ASC");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=\"wishes_export.csv\"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id','name','text','created_at','visible_at','meta']);
    while ($row = $stmt->fetch()) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

if ($uri === '/seed') {
    $pdo = db();
    $samples = [
        ['Иван', 'Любите и танцуйте до утра!'],
        ['Мария', 'Пусть каждый день будет как сегодня — с огоньком!'],
        ['Дима', 'Путешествий, смеха и терпения друг к другу!'],
        ['Аня', 'Дом — там, где вы вместе. Счастья!'],
        ['Лена', 'Миллион поцелуев и ноль ссор!']
    ];
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO wishes(name, text, created_at, visible_at, meta) VALUES(?,?,?,?,NULL)");
    $now = nowIso();
    foreach ($samples as [$n,$t]) {
        $stmt->execute([$n, $t, $now, $now]);
    }
    $pdo->commit();
    header('Location: /');
    exit;
}

// Fallback: serve the app HTML
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Wish Wall — живое облако пожеланий</title>
  <style>
    :root {
      --bg1: #0f1020;
      --bg2: #1a1c3a;
      --card: #ffffff;
      --accent: #66e3ff;
      --accent2: #ff9fd6;
      --text: #0e1222;
    }
    * { box-sizing: border-box; }
    html, body { height: 100%; margin: 0; padding: 0; }
    body {
      background: radial-gradient(1000px 600px at 20% 10%, rgba(102,227,255,0.07), transparent 60%),
                  radial-gradient(800px 500px at 80% 80%, rgba(255,159,214,0.08), transparent 60%),
                  linear-gradient(180deg, var(--bg1), var(--bg2));
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, 'Noto Sans', 'Helvetica Neue', Arial, sans-serif;
      color: #f5f7fb;
      overflow: hidden; /* wall takes full screen */
    }
    .toolbar {
      position: fixed; top: 14px; left: 50%; transform: translateX(-50%);
      display: flex; gap: 10px; z-index: 10;
      background: rgba(19,22,45,0.65); border: 1px solid rgba(255,255,255,0.07);
      padding: 8px 12px; border-radius: 999px; backdrop-filter: blur(8px);
      box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    }
    .btn {
      appearance: none; border: 0; cursor: pointer; padding: 10px 16px; border-radius: 999px;
      background: linear-gradient(90deg, var(--accent), var(--accent2));
      color: #06101a; font-weight: 700; letter-spacing: 0.2px; box-shadow: 0 6px 18px rgba(0,0,0,0.25);
    }
    .btn.secondary { background: rgba(255,255,255,0.1); color: #eaf6ff; font-weight: 600; }
    .wall {
      position: absolute; inset: 0; overflow: hidden;
    }
    .wish {
      position: absolute; min-width: 200px; max-width: 380px;
      background: var(--card); color: var(--text);
      border-radius: 14px; padding: 12px 14px; box-shadow: 0 10px 30px rgba(0,0,0,0.25);
      border: 1px solid rgba(0,0,0,0.08);
      will-change: transform;
      user-select: none;
    }
    .wish .text { font-size: 16px; line-height: 1.35; }
    .wish .name { margin-top: 8px; font-size: 13px; font-weight: 700; color: #4e5b6f; text-align: right; }
    .badge-new {
      position: absolute; top: -8px; left: -8px; font-size: 11px; font-weight: 800;
      background: #ffe766; color: #3a2d00; padding: 4px 8px; border-radius: 999px;
      border: 1px solid rgba(0,0,0,0.08);
    }
    /* Modal */
    .modal-backdrop {
      position: fixed; inset: 0; background: rgba(10, 12, 24, 0.55);
      display: none; align-items: center; justify-content: center; z-index: 20;
      backdrop-filter: blur(4px);
    }
    .modal-backdrop.show { display: flex; }
    .modal {
      width: min(560px, 92vw);
      background: #0e1324; color: #e8f3ff; border-radius: 14px; padding: 18px;
      border: 1px solid rgba(255,255,255,0.08);
      box-shadow: 0 18px 60px rgba(0,0,0,0.5);
    }
    .modal h2 { margin: 0 0 12px; }
    .row { display: flex; flex-direction: column; gap: 8px; margin: 10px 0; }
    label { font-size: 13px; color: #b8c7db; }
    input[type=text], textarea {
      width: 100%; padding: 12px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.15);
      background: #0b0f1e; color: #eaf6ff; resize: vertical;
    }
    .hint { font-size: 12px; color: #92a6bf; }
    .form-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 8px; }
    .switch { display: inline-flex; align-items: center; gap: 8px; }
    .credits { position: fixed; bottom: 8px; right: 12px; font-size: 11px; color: #91a2b8; opacity: 0.65; }
    @media (max-width: 640px) {
      .wish { min-width: 160px; max-width: 300px; }
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <button class="btn" id="openModalBtn">Оставить пожелание</button>
    <a class="btn secondary" href="/export.csv" title="Экспорт для печати">Экспорт CSV</a>
    <a class="btn secondary" href="/seed" title="Добавить демо-пожелания">Демо</a>
  </div>

  <div class="wall" id="wall" aria-live="polite" aria-busy="true"></div>

  <div class="modal-backdrop" id="modalBackdrop" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true">
      <h2>Пожелание для молодожёнов</h2>
      <form id="wishForm">
        <div class="row">
          <label for="name">Ваше имя</label>
          <input type="text" id="name" name="name" maxlength="60" placeholder="Например: Илья" required>
        </div>
        <div class="row">
          <label for="text">Текст пожелания</label>
          <textarea id="text" name="text" rows="5" maxlength="600" placeholder="Напишите что-нибудь тёплое и яркое…" required></textarea>
          <div class="hint">До 600 символов. Пожелание появится на экране.</div>
        </div>
        <div class="row">
          <label class="switch">
            <input type="checkbox" id="surprise" name="surprise" value="1">
            <span>Сюрприз: показать завтра ✨</span>
          </label>
          <div class="hint">Если включить — пожелание не будет видно сегодня, но откроется завтра (и попадёт в экспорт).</div>
        </div>
        <div class="form-actions">
          <button type="button" class="btn secondary" id="cancelBtn">Отмена</button>
          <button type="submit" class="btn">Отправить</button>
        </div>
      </form>
    </div>
  </div>

  <div class="credits">Wish Wall · PHP + SQLite · автообновление</div>

<script>
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
</script>
</body>
</html>
