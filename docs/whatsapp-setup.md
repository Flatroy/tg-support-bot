# Настройка WhatsApp

Поддерживаются три провайдера для работы с WhatsApp:
1. **GOWA** (`gowa`) — самостоятельный WhatsApp-сервер на Go, без Docker Hub, лёгкий
2. **WAHA** (`waha`) — самостоятельный WhatsApp-сервер через Docker
3. **WhatsApp Cloud API** (`cloud_api`) — официальный API от Meta

Поддерживаемые функции: текст, фото, документы, аудио/голосовые, видео, стикеры, локация, контакты. Медиа-файлы автоматически скачиваются и пересылаются.

---

## 1. WhatsApp Cloud API (Meta)

См. секцию ниже для настройки через официальный API Meta.

---

## 2. WAHA (Самостоятельный сервер)

Быстрая альтернатива без регистрации в Meta Business. WAHA работает как отдельный Docker-контейнер с HTTP API.

### 2.1. Запуск WAHA

```bash
docker run -d --name waha \
  -p 3000:3000 \
  -v $(pwd)/.sessions:/app/.sessions \
  devlikeapro/waha:latest
```

### 2.2. Получение значений для `.env`

| Переменная | Описание | Пример |
|------------|----------|--------|
| `WHATSAPP_PROVIDER` | Выбор провайдера | `waha` |
| `WAHA_BASE_URL` | URL WAHA-сервера | `http://localhost:3000` |
| `WAHA_API_KEY` | API-ключ (если настроен в WAHA) | `06521A90E99047F489FAFA2384CD9735` |
| `WAHA_SESSION` | Имя сессии | `default` |
| `WAHA_BASIC_AUTH` | Basic Auth (формат `user:pass`) | `admin:password123` |

### 2.3. Настройка вебхука в WAHA

Отправьте запрос к WAHA для подписки на события:

```bash
curl -X POST \
  "http://localhost:3000/api/{SESSION}/webhooks" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://your-bot.com/api/waha/bot",
    "events": ["message.any"]
  }'
```

Рекомендуемое событие: `message.any` — все входящие сообщения.

### 2.4. Проверка подключения

```bash
php@8.3 artisan waha:validate http://localhost:3000 your_api_key default
```

Или с Basic Auth:
```bash
php@8.3 artisan waha:validate http://localhost:3000 your_api_key default --basic-auth="admin:password"
```

### 2.5. Важные отличия WAHA

- **Нет 24-часового окна** — можно писать в любое время
- **Нет шаблонов** — обычные сообщения работают всегда
- **Статусы сессии**: `AUTHENTICATED` или `WORKING` — готово к работе
- Для сканирования QR: откройте `http://localhost:3000/api/{session}/auth/qr`

---

## 3. Настройка WhatsApp Cloud API

### 3.1. Предварительные требования

- Аккаунт [Meta Business Suite](https://business.facebook.com)
- Приложение в [Meta for Developers](https://developers.facebook.com/apps)
- Бизнес-номер телефона WhatsApp (создаётся при прохождении Getting Started)
- Публичный HTTPS-домен для вебхука

---

### 3.2. Создание приложения Meta

1. Перейдите на [developers.facebook.com/apps](https://developers.facebook.com/apps)
2. Нажмите **Create App** → выберите **Business** → **Next**
3. Заполните название приложения и выберите бизнес-портфолио
4. На странице приложения найдите **WhatsApp** и нажмите **Set Up**
5. Пройдите шаги **Getting Started** — Meta создаст тестовый бизнес-аккаунт и номер

---

### 3.3. Получение значений для `.env`

### `WHATSAPP_PHONE_NUMBER_ID`

**App Dashboard** → **WhatsApp** → **Getting Started** → поле **Phone number ID** (или через API).

Пример: `110518911664260`

### `WHATSAPP_TOKEN`

Для тестирования можно использовать **временный токен** (24 часа) со страницы Getting Started.

Для продакшена создайте **системный токен**:

1. [business.facebook.com/settings](https://business.facebook.com/settings) → **Users** → **System users**
2. Создайте системного пользователя с ролью **Admin**
3. Нажмите **Generate new token** → выберите ваше приложение
4. Выберите разрешения: `whatsapp_business_messaging`, `whatsapp_business_management`
5. Срок действия: **Never expire**
6. Скопируйте токен

### `WHATSAPP_VERIFY_TOKEN`

Произвольная строка, которую вы придумываете сами. Используется для верификации вебхука.

Пример: `my_secret_verify_token_2024`

### `WHATSAPP_APP_SECRET`

**App Dashboard** → **App settings** → **Basic** → поле **App secret** (нажмите **Show**).

Используется для проверки подписи входящих вебхуков (HMAC SHA-256).

### `WHATSAPP_API_VERSION`

Текущая версия Graph API. Рекомендуется: `v22.0`

---

### 3.4. Настройка `.env` для Cloud API

#### `WHATSAPP_PROVIDER`

Выбор провайдера: `cloud_api` (Meta) или `waha` (свой сервер).

Для Cloud API:
```env
WHATSAPP_PROVIDER=cloud_api
WHATSAPP_TOKEN="EAAxxxxxxx..."
WHATSAPP_PHONE_NUMBER_ID="110518911664260"
WHATSAPP_VERIFY_TOKEN="my_secret_verify_token_2024"
WHATSAPP_APP_SECRET="abc123def456..."
WHATSAPP_API_VERSION="v22.0"
```

Для WAHA:
```env
WHATSAPP_PROVIDER=waha
WAHA_BASE_URL="http://localhost:3000"
WAHA_API_KEY="06521A90E99047F489FAFA2384CD9735"
WAHA_SESSION="default"
WAHA_BASIC_AUTH="admin:password123"
```

---

### 3.5. Настройка вебхука (Cloud API)

#### 5.1. Регистрация вебхука в Meta

1. **App Dashboard** → **WhatsApp** → **Configuration**
2. В разделе **Webhook** нажмите **Edit**
3. Заполните:
   - **Callback URL:** `https://your-domain.com/api/whatsapp/bot`
   - **Verify token:** значение из `WHATSAPP_VERIFY_TOKEN`
4. Нажмите **Verify and save**
5. Подпишитесь на события: `messages` (обязательно)

#### 5.2. Проверка вебхука

При настройке Meta отправит GET-запрос на ваш URL:

```
GET https://your-domain.com/api/whatsapp/bot?hub.mode=subscribe&hub.verify_token=my_secret_verify_token_2024&hub.challenge=XXXX
```

Бот автоматически вернёт `hub.challenge`, подтверждая вебхук.

#### 5.3. Подписка на события WABA

Если вебхук не получает сообщения, подпишите приложение на WABA:

```bash
curl -X POST \
  "https://graph.facebook.com/v22.0/{WABA_ID}/subscribed_apps" \
  -H "Authorization: Bearer {WHATSAPP_TOKEN}"
```

---

## 4. Тестирование

### 4.1. Отправка тестового сообщения (Cloud API)

```bash
curl -i -X POST \
  https://graph.facebook.com/v22.0/{PHONE_NUMBER_ID}/messages \
  -H 'Authorization: Bearer {WHATSAPP_TOKEN}' \
  -H 'Content-Type: application/json' \
  -d '{
    "messaging_product": "whatsapp",
    "to": "{НОМЕР_ПОЛУЧАТЕЛЯ}",
    "type": "template",
    "template": {
      "name": "hello_world",
      "language": { "code": "en_US" }
    }
  }'
```

**Ожидаемый ответ:**
```json
{
  "messaging_product": "whatsapp",
  "contacts": [{ "input": "972XXXXXXXXX", "wa_id": "972XXXXXXXXX" }],
  "messages": [{ "id": "wamid.XXXXX" }]
}
```

### 4.2. Проверка входящих сообщений

1. Отправьте сообщение в WhatsApp
2. Проверьте, что сообщение появилось в Telegram-группе
3. Ответьте в Telegram — ответ должен дойти в WhatsApp

### 4.3. Проверка логов

```bash
docker exec -it pet tail -f storage/logs/laravel.log
```

---

## 5. Особенности WhatsApp API

### Сравнение провайдеров

| Функция | Cloud API (Meta) | WAHA |
|---------|------------------|----|
| Текст | ✅ | ✅ |
| Фото | ✅ | нужен WAHA Plus? |
| Документы | ✅ | нужен WAHA Plus? |
| Аудио/Голос | ✅ | нужен WAHA Plus? |
| Видео | ✅ | нужен WAHA Plus? |
| Стикеры | ✅ | ✅ |
| Локация | ✅ | ✅ |
| Контакты | ✅ | ✅ |
| 24-часовое окно | ❌ Требуется | ✅ Не требуется |
| Шаблоны | ✅ Обязательны | ❌ Не нужны |
| Регистрация Meta | ✅ Обязательна | ❌ Не нужна |

### Поддерживаемые типы сообщений (подробно)

| Тип | WA → TG | TG → WA | Примечание |
|-----|---------|---------|------------|
| Текст | ✅ | ✅ | |
| Фото/Изображение | ✅ | ✅ | Скачивается и пересылается |
| Документ | ✅ | ✅ | PDF, DOC, любые файлы |
| Аудио/Голосовое | ✅ | ✅ | OGG, MP3 |
| Видео | ✅ | ✅ | Скачивается в Telegram |
| Стикер | ✅ | ❌ | Только в одну сторону |
| Локация | ✅ | ✅ | Широта/долгота |
| Контакт | ✅ | ❌ | Имя, телефон, vCard |
| Реакции (emoji) | ❌ | ❌ | Не поддерживаются |
| Прочитано | ❌ | ❌ | Не отправляется обратно |

**Важно**: Медиа-файлы автоматически скачиваются во временные файлы и пересылаются — никаких временных ссылок, которые могут истечь.

### 24-часовое окно (только Cloud API)

WhatsApp разрешает отправку произвольных сообщений только в течение 24 часов после последнего сообщения от пользователя. После этого можно отправлять только **шаблонные сообщения** (template messages), предварительно одобренные Meta.

WAHA не имеет этого ограничения — можно писать в любое время.

### Медиа-файлы

URL медиа-файлов Cloud API истекают через **5 минут**. Бот автоматически скачивает медиа и пересылает в Telegram (не передаёт временные ссылки).

WAHA возвращает прямые ссылки или бинарные данные — также обрабатываются автоматически.

### Редактирование сообщений

WhatsApp не поддерживает редактирование через API. Если сообщение отредактировано в Telegram, бот отправит новое сообщение с пометкой «✏️ Исправлено».

---

## 6. Маршруты API

| Метод | URL | Описание | Провайдер |
|-------|-----|----------|-----------|
| GET | `/api/whatsapp/bot` | Верификация вебхука | Cloud API |
| POST | `/api/whatsapp/bot` | Приём входящих сообщений | Cloud API |
| POST | `/api/waha/bot` | Приём входящих сообщений | WAHA |
| POST | `/api/waha/validate` | API проверки подключения | WAHA |

---

## 7. Диагностика проблем

### Общие проблемы

- [ ] Проверьте `WHATSAPP_PROVIDER` — должно быть `cloud_api` или `waha`
- [ ] Убедитесь, что все переменные окружения заполнены для выбранного провайдера
- [ ] Проверьте логи: `storage/logs/laravel.log`

### Проблемы WAHA

- [ ] Проверьте, что WAHA-контейнер запущен: `docker ps | grep waha`
- [ ] Проверьте статус сессии: должен быть `AUTHENTICATED` или `WORKING`
- [ ] Если `SCAN_QR` — отсканируйте QR-код
- [ ] Проверьте вебхук: должен быть подписан на событие `message.any`
- [ ] Убедитесь, что URL бота доступен из интернета

### Вебхук не получает сообщения (Cloud API)

- [ ] Проверьте подписку на события `messages` в App Dashboard → Configuration
- [ ] Убедитесь, что приложение подписано на WABA (пункт 5.3)
- [ ] Проверьте `WHATSAPP_APP_SECRET` — неверный секрет = отклонение запросов (403)

### Сообщения не доставляются в WhatsApp

- [ ] Проверьте, что 24-часовое окно ещё открыто
- [ ] Проверьте логи на ошибки API (error code, error message)
- [ ] Убедитесь, что `WHATSAPP_TOKEN` не истёк (для временных токенов — 24 часа)

### Ошибка 403 на вебхуке

Неверная подпись запроса. Проверьте, что `WHATSAPP_APP_SECRET` в `.env` совпадает с **App secret** в App Dashboard → App settings → Basic.

### Тестовый номер не отправляет сообщения

При использовании тестового номера Meta, получатели должны быть добавлены в список тестовых номеров: **App Dashboard** → **WhatsApp** → **Getting Started** → **Add phone number**.

---

## 8. Финальный чек-лист

### Для Cloud API:
- [ ] `WHATSAPP_PROVIDER=cloud_api`
- [ ] Переменные окружения Cloud API заполнены
- [ ] Вебхук верифицирован в Meta
- [ ] Подписка на события `messages` включена
- [ ] Приложение подписано на WABA

### Для WAHA:
- [ ] `WHATSAPP_PROVIDER=waha`
- [ ] WAHA-контейнер запущен
- [ ] Сессия авторизована (статус `AUTHENTICATED` или `WORKING`)
- [ ] Вебхук настроен в WAHA на событие `message.any`
- [ ] Команда `waha:validate` проходит успешно

### Общее:
- [ ] Тестовое сообщение отправляется
- [ ] Входящие сообщения пересылаются в Telegram
- [ ] Ответы из Telegram доставляются в WhatsApp
- [ ] Логи без ошибок
