# Tracking App

Веб-приложение для ручного ведения журнала пройденных и проеханных маршрутов. Маршрут создается точками на карте постфактум, сохраняется с датой, типом активности, комментарием и может быть открыт по публичной ссылке.

## Возможности

- регистрация и авторизация пользователей;
- создание маршрута вручную точками на Leaflet-карте;
- активности: ходьба, бег, скейтбординг, авто;
- сохранение названия, даты и комментария;
- расчет протяженности маршрута;
- расчет набора и сброса высоты через Open-Meteo Elevation API;
- список маршрутов со статистикой и фильтрами по дате/активности;
- личные маршруты и публичный доступ по ссылке;
- экспорт маршрута в GPX;
- экспорт красивой PNG-карточки маршрута 1024x1024.

## Стек

- PHP 8.2
- Laravel 12
- MySQL 8
- Inertia.js
- Vue 3 + TypeScript
- Tailwind CSS
- Leaflet
- Vite

## Локальный запуск

Установить PHP/Composer, Node.js и MySQL. Для OSPanel в текущем окружении MySQL слушает `127.127.126.6:3306`.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

Для разработки:

```bash
php artisan serve
npm run dev
```

Приложение будет доступно по адресу:

```text
http://127.0.0.1:8000
```

## Настройка окружения

Основные переменные в `.env`:

```env
APP_NAME=Tracking
APP_LOCALE=ru
DB_CONNECTION=mysql
DB_HOST=127.127.126.6
DB_PORT=3306
DB_DATABASE=tracking_local
DB_USERNAME=root
DB_PASSWORD=
```

Перед миграциями нужно создать базу:

```sql
CREATE DATABASE tracking_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## Проверки

```bash
php artisan test
npm run build
```

## Внешние сервисы

Высоты рассчитываются через Open-Meteo Elevation API. Если сервис временно недоступен, маршрут все равно сохраняется, но высоты могут остаться пустыми, а набор/сброс высоты будут равны 0.

## Примечания

- PNG-экспорт выполняется в браузере через `html-to-image`.
- Для карты используются внешние тайлы Leaflet-слоев, поэтому фактический PNG-экспорт зависит от доступности тайлов и CORS.
- `.env`, `vendor`, `node_modules` и собранный `public/build` не хранятся в репозитории.
