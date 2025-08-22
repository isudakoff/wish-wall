# Запуск Wish Wall через Docker

## Требования
- Docker
- Docker Compose

## Запуск
```bash
# Запустить приложение
docker-compose up -d

# Открыть в браузере
open http://localhost:8080
```

## Остановка
```bash
docker-compose down
```

## Просмотр логов
```bash
# Логи nginx
docker-compose logs nginx

# Логи PHP
docker-compose logs php

# Все логи
docker-compose logs
```

## Структура
- `index.php` - основное приложение
- `data/` - директория для SQLite базы данных (создается автоматически)
- `nginx.conf` - конфигурация nginx
- `docker-compose.yml` - конфигурация Docker

## Эндпоинты
- `GET /` - главная страница с облаком пожеланий
- `POST /api/wish` - создать пожелание
- `GET /api/wishes` - получить список пожеланий
- `GET /export.csv` - экспорт всех пожеланий
- `GET /seed` - добавить демо-данные
