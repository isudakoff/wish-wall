<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Wish Wall — живое облако пожеланий</title>
  <link rel="stylesheet" href="/assets/style.css"/>
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

  <script src="/assets/app.js"></script>
</body>
</html>
