# Текущий этап проекта «Калькулятор сборов АВИА»

## Доработки интерфейса (после этапа 8)

**Состояние:** выполнено.

**Сделано:**
- Стиль в духе Битрикс24: спокойные цвета (#eef2f4, #f5f7f8, #525c69, #2fc6f6), нейтральные границы и тени.
- Мелкие правки CSS по результатам браузерного предпросмотра: `html { height: fit-content; }`; убран лишний верхний/нижний отступ у строки-разделителя итогов в таблице результата; обновлены цвета активной вкладки, фоны кнопок «+ Добавить услугу» и «Скопировать», а также отступы/скругления у «Скопировать».
- Убраны: заголовок «Результат расчёта», подсказки «Курс иностранной валюты к рублю» и «Курс валюты поставщика к KZT», кнопка «Рассчитать», ссылка «Админка» (скрыта).
- Результат применяется динамически при вводе данных (debounce 400 ms).
- Вкладка «История»: кнопка «Подробнее» заменена на «Копировать» (копирование в буфер в том же формате, что и на главной); добавлена кнопка «Назад» для возврата на калькулятор.
- Формат копирования: первая строка — тип первой услуги (например «Выписка»); в строке трансграна убраны названия типов услуг; без колонки «Показатель»; суммы в валюте и в рублях через слеш (например «1 000 ₽ / 50 €»).
- `README.md` — обновлён: запуск, возможности, структура и заметки по формулам/копированию.
- `admin.php` / `assets/style.css` — в админке уменьшены отступы (паддинги/маржины) до минимальных.
- `index.php` — слева ввод данных, справа результат; в правой колонке сначала расчёт (таблица), ниже — блок «Применённые формулы»; убран заголовок «Ввод данных».
- `assets/app.js` — обязательные поля подсвечиваются сразу при открытии страницы (после добавления первой услуги).

---

## Этап 8 (финальный) — Админка правил (завершён)

**Состояние:** выполнен.

**Сделано:**
- `src/AdminManager.php` — CRUD для clients (getClients, getClient, createClient, updateClient, deleteClient, toggleClient), service_fee_rules, transgran_rules, transgran_exceptions, acquiring_rules, client_special_rules; справочники getServiceTypes, getPaymentMethods; валидация и приватные validateRequired/validateEnum.
- `api.php` — подключён AdminManager, 22 действия admin_* (GET/POST), ответы {success, data} / {success: false, error}, getAdminPostData(), try-catch по действиям.
- `admin.php` — шапка, левая панель (список клиентов, кнопка «+ Добавить клиента»), правая панель (заглушка и 5 секций-аккордеонов: основные данные, сервисный сбор, трансгран с вкладками, эквайринг, спецправила), модальное окно создания клиента.
- `assets/style.css` — стили админки (admin-page, sidebar, client-list, accordion, rules-table, transgran-tabs, кнопки, exception-badge, modal, form-grid, notification).
- `assets/admin.js` — Admin.init (справочники, loadClients, обработчики), список клиентов и выбор, основные данные (сохранение, удаление), таблицы правил с inline-редактированием и добавлением (сервисный сбор, трансгран, эквайринг, спецправила), модальное окно создания клиента, аккордеон, уведомления, fetchApi.

**Следующий шаг:** проект завершён; при необходимости — доработки интерфейса или экспорт правил.

---

## Этап 7 — HistoryManager и обновление api.php (завершён)

**Состояние:** выполнен.

**Сделано:**
- Класс `App\HistoryManager`: конструктор `(Database $db)`, свойства `$db`, `$limit = 500`. Методы: `save($inputData, $resultData, $formulas)` — INSERT и вызов `cleanup()`; `getAll()` — записи от новых к старым с декодированием JSON; `getById($id)`; `delete($id)`; `clearAll()`; `cleanup()` — удаление старых записей при превышении лимита; `getCount()`.
- В `api.php`: подключён `HistoryManager`, создаётся `$historyManager` после инициализации БД. В `calculate` — сохранение через `$historyManager->save($input, $result, implode("\n", $result['formulas']))`. В `get_history` — `$historyManager->getAll()`. В `clear_history` — `$historyManager->clearAll()`. В `delete_history_item` — `$historyManager->delete($id)` с проверкой и ошибкой «Запись не найдена» при false. Действия с историей обёрнуты в try-catch.

**Следующий шаг:** этап 8 — админка (admin.php) или доработки.

---

## Этап 6 — Веб-интерфейс (index.php, assets/) (завершён)

**Состояние:** выполнен.

**Сделано:**
- `index.php` — шапка (заголовок, вкладки «Калькулятор» / «История», ссылка на admin.php), вкладка «Калькулятор» (двухколоночный макет: левая — параметры заказа и блок услуг с кнопками «+ Добавить услугу» и «Рассчитать», правая — результат с формулами, таблицей и кнопкой «Скопировать»), вкладка «История» (таблица, кнопка «Очистить историю», «Подробнее» и «✕» по записям).
- `assets/style.css` — общие стили, шапка и вкладки, двухколоночный flex, сетка полей (params-grid), подсказка agent_hint (жёлтый фон), блок услуг и кнопки, кнопка «Рассчитать», блок формул и таблица результата, мультивалютная колонка, кнопка «Скопировать», таблица истории и развёрнутые подробности, адаптивность (колонки в столбик на узких экранах), скрытие полей через visibility.
- `assets/app.js` — объект App (data, serviceCount, currentResult), init (загрузка get_initial_data, заполнение select, обработчики, первая услуга), переключение вкладок, выбор клиента и отображение agent_hint, addService/removeService, calculate (сбор данных, валидация, POST calculate, renderResult/ошибка), copyResult и показ «Скопировано ✓», renderResult (формулы, таблица, мультивалютность), formatAmount/getCurrencySymbol, loadHistory/renderHistory/toggleHistoryDetail/deleteHistoryItem/clearHistory, updateFieldVisibility (TODO).

**Следующий шаг:** этап 7 — админка (admin.php) или доработки интерфейса.

---

## Этап 5 — API для AJAX (api.php) (завершён)

**Состояние:** выполнен.

**Сделано:**
- Файл `avia-calc/api.php` — единая точка входа для AJAX. Подключение классов через require (Database, RuleEngine, CurrencyConverter, Calculator). Инициализация Database и RuleEngine. Заголовки: Content-Type: application/json; charset=utf-8, запрет кэширования.
- Действия: `get_initial_data` (GET) — справочники clients, service_types, payment_methods, currencies, countries; `calculate` (POST) — валидация входных данных, вызов Calculator, сохранение в calculation_history, возврат результата; `get_history` (GET) — последние 500 записей; `clear_history` (POST) — очистка истории; `delete_history_item` (POST) — удаление по id.
- Валидация для calculate: client_code, payment_method_code, supplier_country (RU/KZ), settlement_currency (RUB/EUR/USD/KZT), services (минимум 1, у каждой service_type_code, amount > 0, invoice_currency), rate_rs_tls > 0, rate_kz > 0 для SDG. Ошибки и исключения возвращаются в JSON с полем error.

**Следующий шаг:** этап 6 — веб-интерфейс (форма калькулятора, вызов api.php, отображение результата).

---

## Этап 4 — Калькулятор сборов Calculator (завершён)

**Состояние:** выполнен.

**Сделано:**
- Класс `App\Calculator`: конструктор `(RuleEngine, CurrencyConverter)`; основной метод `calculate($input)` — полный расчёт по заказу.
- Вход: client_code, payment_method_code, supplier_country, settlement_currency, rate_rs_tls, rate_kz, services[] (service_type_code, amount, invoice_currency). Выход: services (детализация по услугам), totals, working_currency, multi_currency, second_currency, totals_second, formulas, applied_rules.
- Алгоритм: настройка конвертера по клиенту и курсам; расчёт по каждой услуге (конвертация в рабочую валюту, сервисный сбор, трансгран с учётом исключений и спецправила no_transgran_standard/override_by_kz_card, правило «что больше»); суммирование; эквайринг (в т.ч. exclude_service_type по услугам); мультивалютный результат; формулы.
- Вспомогательные методы: `calculateServiceFee()`, `calculateTransgran()`, `applyMaxRule()`, `calculateAcquiring()`, `buildFormulas()`, `formatAmount()`; поиск способа оплаты и типа услуги по коду.
- В `CurrencyConverter` добавлен `setWorkingCurrency()` для настройки из калькулятора.

**Следующий шаг:** этап 5 — веб-интерфейс калькулятора и/или сохранение расчётов в calculation_history.

---

## Этап 3 — Конвертер валют CurrencyConverter (завершён)

---

## Этап 2 — Движок правил RuleEngine (завершён)

---

## Этап 1 — База данных и класс работы с БД (завершён)

**Состояние:** выполнен.

**Сделано:**
- Структура каталогов: `avia-calc/config`, `avia-calc/database`, `avia-calc/src`.
- Конфигурация БД: `avia-calc/config/database.php` (путь к SQLite, schema, seed).
- Схема БД: `avia-calc/database/schema.sql` — таблицы clients, service_types, payment_methods, service_fee_rules, transgran_rules, transgran_exceptions, acquiring_rules, client_special_rules, calculation_history.
- Начальные данные: `avia-calc/database/seed.sql` — все клиенты, типы услуг, способы оплаты, правила сервисного сбора, трансграна (standard, kz_rub, kz_usd_eur), исключения RME, спецправило RT, правила эквайринга. Используются подзапросы по `code`.
- Класс `App\Database`: синглтон, getInstance(), getConnection(), initializeDatabase(), query(), fetchAll(), fetchOne(), execute(), lastInsertId(). PDO с ERRMODE_EXCEPTION, WAL, foreign_keys.
