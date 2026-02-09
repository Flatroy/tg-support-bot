# Настройка WhatsApp Cloud API

Руководство по подключению WhatsApp как провайдера сообщений (аналогично Telegram и VK).

---

## 1. Предварительные требования

- Аккаунт [Meta Business Suite](https://business.facebook.com)
- Приложение в [Meta for Developers](https://developers.facebook.com/apps)
- Бизнес-номер телефона WhatsApp (создаётся при прохождении Getting Started)
- Публичный HTTPS-домен для вебхука

---

## 2. Создание приложения Meta

1. Перейдите на [developers.facebook.com/apps](https://developers.facebook.com/apps)
2. Нажмите **Create App** → выберите **Business** → **Next**
3. Заполните название приложения и выберите бизнес-портфолио
4. На странице приложения найдите **WhatsApp** и нажмите **Set Up**
5. Пройдите шаги **Getting Started** — Meta создаст тестовый бизнес-аккаунт и номер

---

## 3. Получение значений для `.env`

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

## 4. Настройка `.env`

```env
WHATSAPP_TOKEN="EAAxxxxxxx..."
WHATSAPP_PHONE_NUMBER_ID="110518911664260"
WHATSAPP_VERIFY_TOKEN="my_secret_verify_token_2024"
WHATSAPP_APP_SECRET="abc123def456..."
WHATSAPP_API_VERSION="v22.0"
```

---

## 5. Настройка вебхука

### 5.1. Регистрация вебхука в Meta

1. **App Dashboard** → **WhatsApp** → **Configuration**
2. В разделе **Webhook** нажмите **Edit**
3. Заполните:
   - **Callback URL:** `https://your-domain.com/api/whatsapp/bot`
   - **Verify token:** значение из `WHATSAPP_VERIFY_TOKEN`
4. Нажмите **Verify and save**
5. Подпишитесь на события: `messages` (обязательно)

### 5.2. Проверка вебхука

При настройке Meta отправит GET-запрос на ваш URL:

```
GET https://your-domain.com/api/whatsapp/bot?hub.mode=subscribe&hub.verify_token=my_secret_verify_token_2024&hub.challenge=XXXX
```

Бот автоматически вернёт `hub.challenge`, подтверждая вебхук.

### 5.3. Подписка на события WABA

Если вебхук не получает сообщения, подпишите приложение на WABA:

```bash
curl -X POST \
  "https://graph.facebook.com/v22.0/{WABA_ID}/subscribed_apps" \
  -H "Authorization: Bearer {WHATSAPP_TOKEN}"
```

---

## 6. Тестирование

### 6.1. Отправка тестового шаблонного сообщения

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

### 6.2. Проверка входящих сообщений

1. Отправьте сообщение на ваш бизнес-номер WhatsApp
2. Проверьте, что сообщение появилось в Telegram-группе поддержки
3. Ответьте на сообщение в группе — ответ должен дойти в WhatsApp

### 6.3. Проверка логов

```bash
docker exec -it pet tail -f storage/logs/laravel.log
```

---

## 7. Особенности WhatsApp API

### 24-часовое окно

WhatsApp разрешает отправку **произвольных сообщений** только в течение 24 часов после последнего сообщения от пользователя. После этого можно отправлять только **шаблонные сообщения** (template messages), предварительно одобренные Meta.

### Типы поддерживаемых сообщений

| Тип | WA → TG | TG → WA |
|-----|---------|---------|
| Текст | ✅ | ✅       |
| Фото | ✅ | ✅       |
| Документ | ✅ | ✅       |
| Аудио/Голосовое | ✅ | ✅       |
| Видео | ✅ | —       |
| Стикер | ✅ | —       |
| Локация | ✅ | ✅       |
| Контакт | ✅ | —       |
| Реакции | — | —       |
| Прочитано (read) | — | —       |

### Медиа-файлы

URL медиа-файлов WhatsApp истекают через **5 минут**. Бот автоматически скачивает медиа и пересылает в Telegram (не передаёт временные ссылки).

### Редактирование сообщений

WhatsApp не поддерживает редактирование через API. Если сообщение отредактировано в Telegram, бот отправит новое сообщение с пометкой «✏️ Исправлено».

---

## 8. Диагностика проблем

### Вебхук не получает сообщения

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

## 9. Маршруты API

| Метод | URL | Описание |
|-------|-----|----------|
| GET | `/api/whatsapp/bot` | Верификация вебхука (Meta) |
| POST | `/api/whatsapp/bot` | Приём входящих сообщений |

---

## 10. Финальный чек-лист

- [ ] Переменные окружения заполнены в `.env`
- [ ] Вебхук верифицирован в Meta App Dashboard
- [ ] Подписка на события `messages` включена
- [ ] Приложение подписано на WABA
- [ ] Тестовое шаблонное сообщение отправляется успешно
- [ ] Входящие сообщения пересылаются в Telegram-группу
- [ ] Ответы из Telegram доставляются в WhatsApp
- [ ] Для продакшена: системный токен (не временный)
- [ ] Логи без ошибок
