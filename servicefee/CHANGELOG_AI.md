# Журнал изменений (AI)

## 2025-03-18

### Доработки интерфейса (стиль Битрикс24, динамический расчёт, копирование)

- **Изменено:**
  - `avia-calc/assets/style.css` — стиль в духе Битрикс24: фон #eef2f4, нейтральные серые (#525c69, #333c47), акцент #2fc6f6, спокойные границы и кнопки; подсказки param-hint скрыты (display: none); ссылка .header-admin скрыта (visibility: hidden); кнопка «Рассчитать» удалена; добавлены .btn-history-back, .btn-history-copy (вместо .btn-history-detail).
  - `avia-calc/index.php` — убраны подсказки под курсами РС ТЛС и КЗ, кнопка «Рассчитать», заголовок «Результат расчёта»; плейсхолдер результата: «Заполните данные для расчёта.»; в истории добавлена кнопка «Назад», ссылка «Админка» с aria-hidden.
  - `avia-calc/assets/app.js` — расчёт по таймеру при изменении полей (collectInput(true), debounce 400 ms); кнопка «Рассчитать» удалена; добавлен App.buildCopyText(result): первая строка — тип первой услуги, формулы с трансграном без названий типов, таблица без «Показатель», суммы в формате «X ₽ / Y €»; App.copyResult(result, btn) использует buildCopyText, поддерживает вызов из истории с передачей result_data и кнопки; в истории кнопка «Копировать» вместо «Подробнее», по клику — copyResult(item.result_data, button); кнопка «Назад» переключает на вкладку Калькулятор; в таблице результата заголовок без слова «Показатель» (пустой первый th).
- **Документация:**
  - Обновлён CURRENT_STAGE.md (раздел доработок интерфейса).

### Этап 8 (финальный): Админка правил

- **Добавлено:**
  - `src/AdminManager.php` — класс `App\AdminManager`: CRUD для clients (6 методов), service_fee_rules (5), transgran_rules (5), transgran_exceptions (3), acquiring_rules (4), client_special_rules (4); getServiceTypes(), getPaymentMethods(); validateRequired(), validateEnum(). Валидация при create/update, сообщения на русском.
  - В `api.php`: require AdminManager, создание `$adminManager`, функции sendAdminSuccess/sendAdminError/getAdminPostData; 22 действия admin_* (клиенты, серв.сбор, трансгран, исключения, эквайринг, спецправила, справочники). Ответы в формате {success, data} / {success: false, error}, try-catch по каждому действию.
  - `admin.php` — HTML: шапка с ссылкой на калькулятор, двухколоночный макет (sidebar со списком клиентов и кнопкой «+ Добавить клиента», контент с заглушкой и 5 секций-аккордеонов), модальное окно создания клиента. Подключены style.css и admin.js.
  - В `assets/style.css` добавлены стили админки: admin-page, admin-header, admin-container, admin-sidebar, client-list-item, accordion, rules-table, transgran-tabs, кнопки, exception-badge, modal, form-grid, notification.
  - `assets/admin.js` — объект Admin: init (справочники, loadClients, привязка событий), список клиентов и selectClient, renderClientDetails/saveClientDetails/deleteClient, loadServiceFeeRules/renderServiceFeeTable/editServiceFeeRule/saveServiceFeeRule/deleteServiceFeeRule/addServiceFeeRule, loadTransgranRules/renderTransgranTable/switchTransgranTab/deleteTransgranRule/addTransgranRule, loadAcquiringRules/renderAcquiringTable/deleteAcquiringRule/addAcquiringRule, loadSpecialRules/renderSpecialRulesTable/deleteSpecialRule/addSpecialRule, showCreateClientModal/hideModal/createClient, toggleAccordion, showNotification, fetchApi, getServiceTypeName, formatRuleTypeLabel, formatParams.
- **Документация:**
  - Обновлены `CURRENT_STAGE.md` (этап 8 завершён), `structure.md` (admin.php, admin.js, AdminManager.php).

### Этап 7: HistoryManager и обновление api.php

- **Добавлено:**
  - `src/HistoryManager.php` — класс `App\HistoryManager`: конструктор `(Database $db)`, свойства `$db`, `$limit = 500`. Методы: `save($inputData, $resultData, $formulas)` (INSERT + cleanup), `getAll()` (декодирование JSON), `getById($id)`, `delete($id)`, `clearAll()`, `cleanup()` (удаление старых при count > limit), `getCount()`. Комментарии на русском.
- **Изменено:**
  - `api.php`: подключён `HistoryManager`, создаётся `$historyManager`. В действии `calculate` — сохранение в историю через `$historyManager->save($input, $result, implode("\n", $result['formulas']))`. В `get_history` — ответ из `$historyManager->getAll()`. В `clear_history` — `$historyManager->clearAll()`. В `delete_history_item` — `$historyManager->delete($id)` с отправкой ошибки при отсутствии записи. Действия с историей обёрнуты в try-catch.
- **Документация:**
  - Обновлены `CURRENT_STAGE.md` (этап 7), `structure.md` (добавлен HistoryManager.php).

### Этап 6: Веб-интерфейс (index.php, assets/)

- **Добавлено:**
  - `index.php` — HTML: шапка с заголовком, вкладки «Калькулятор» и «История», ссылка «Админка» на admin.php; левая колонка — параметры заказа (клиент, способ оплаты, страна поставщика, валюта расчёта, курс РС ТЛС, курс КЗ со ссылкой на finance.kapital.kz), блок услуг (контейнер + кнопка «+ Добавить услугу»), кнопка «Рассчитать»; правая колонка — заглушка и блок результата (формулы, таблица, кнопка «Скопировать»); вкладка «История» — таблица с колонками №, Дата, Клиент, Сумма услуг, Итого сбор, Итого к оплате, Подробнее, ✕; кнопка «Очистить историю». Подключены assets/style.css и assets/app.js.
  - `assets/style.css` — стили: страница и контейнер, шапка и вкладки, двухколоночный flex, params-grid с фиксированной структурой, подсказка agent_hint (жёлтый фон), блок услуг и кнопки, кнопка «Рассчитать», формулы и таблица результата, мультивалютная колонка, кнопка «Скопировать», таблица истории и развёрнутые подробности, адаптивность, скрытие полей через visibility.
  - `assets/app.js` — App.data, App.serviceCount, App.currentResult; App.init (fetch get_initial_data, fillSelects, bindEvents, addService); обработчики вкладок, выбора клиента (agent_hint), addService/removeService, calculate (collectInput, POST, renderResult/ошибка), copyResult (clipboard, «Скопировано ✓»); renderResult (формулы, таблица с thead при мультивалютности), formatAmount, getCurrencySymbol; loadHistory, renderHistory, toggleHistoryDetail, deleteHistoryItem, clearHistory; updateFieldVisibility (TODO).
- **Документация:**
  - Обновлены `CURRENT_STAGE.md` (этап 6 завершён), `structure.md` (добавлены index.php, assets/app.js, assets/style.css).

### Этап 5: API для AJAX (api.php)

- **Добавлено:**
  - `avia-calc/api.php` — единая точка входа для AJAX: заголовки JSON и запрет кэширования; require классов из src/; инициализация Database и RuleEngine. Обработка action: get_initial_data (GET — справочники), calculate (POST — валидация, Calculator, сохранение в calculation_history), get_history (GET — последние 500), clear_history (POST), delete_history_item (POST, body id). Валидация входных данных для calculate по ТЗ; при ошибках и исключениях — ответ с полем error. Общий try-catch для вывода внутренних ошибок в JSON.
- **Документация:**
  - Обновлены `CURRENT_STAGE.md` (этап 5 завершён), `structure.md` (добавлен api.php).

### Этап 4: Калькулятор сборов Calculator

- **Добавлено:**
  - `src/Calculator.php` — класс `App\Calculator`: конструктор `(RuleEngine, CurrencyConverter)`; метод `calculate($input)` с полным алгоритмом (настройка конвертера, расчёт по каждой услуге: конвертация, сервисный сбор, трансгран с исключениями и спецправилом no_transgran_standard/override_by_kz_card, правило «что больше»; суммирование; эквайринг с учётом exclude_service_type; мультивалютный результат; формулы). Вспомогательные методы: `calculateServiceFee()`, `calculateTransgran()`, `applyMaxRule()`, `calculateAcquiring()`, `buildFormulas()`, `formatAmount()`; поиск способа оплаты и типа услуги по коду. Валидация: исключение при отсутствии клиента, способа оплаты или услуг.
  - В `CurrencyConverter` добавлен метод `setWorkingCurrency($currency)` для настройки рабочей валюты из калькулятора.
- **Документация:**
  - Обновлены `CURRENT_STAGE.md` (этап 4 завершён), `structure.md` (добавлен Calculator.php).

### Этап 3: Конвертер валют CurrencyConverter

- **Добавлено:**
  - `src/CurrencyConverter.php` — класс `App\CurrencyConverter`: свойства `rateRsTls`, `rateKz`, `workingCurrency`; конструктор с значениями по умолчанию. Методы: `convertToWorking()`, `convertFromWorking()`, `convertBetween()`, `convertToSettlement()`, `needsMultiCurrency()`, `getSecondCurrency()`, `round()`; геттеры `getWorkingCurrency()`, `getRateRsTls()`, `getRateKz()`; сеттеры `setRateRsTls()`, `setRateKz()`; заглушка `fetchRateFromBitrix()` с TODO для интеграции с Битрикс 24. Логика: для рабочей валюты RUB — курс РС ТЛС; для EUR (SDG) — курс Казахстана как коэффициент к EUR. Комментарии на русском.
- **Документация:**
  - Обновлены `CURRENT_STAGE.md` (этап 3 завершён), `structure.md` (добавлен CurrencyConverter.php).

### Этап 2: Движок правил RuleEngine

- **Добавлено:**
  - `src/RuleEngine.php` — класс `App\RuleEngine`: конструктор принимает `Database`; кэширование списков (клиенты, типы услуг, способы оплаты) и правил (сервисный сбор, трансгран, исключения, спецправила, эквайринг) при первом запросе; метод `clearCache()`. Методы: `getClients()`, `getClientByCode($code)`, `getServiceTypes()`, `getPaymentMethods()`, `getServiceFeeRule($clientId, $serviceTypeId)`, `getTransgranRule($clientId, $serviceTypeId, $transgranColumn)`, `getTransgranExceptions($transgranRuleId)`, `checkTransgranException($transgranRuleId, $exceptionType, $value)`, `getClientSpecialRules($clientId)`, `checkSpecialRule($clientId, $ruleType, $serviceTypeCode)`, `getAcquiringRule($clientId, $paymentMethodId)`, `determineTransgranColumn($paymentMethodCode)`, `isTransgranNeeded($clientCountry, $supplierCountry)`. JSON в спецправилах декодируется (applicable_service_types, params). Комментарии на русском.
- **Документация:**
  - Обновлены `CURRENT_STAGE.md` (этап 2 завершён), `structure.md` (добавлен RuleEngine.php).

### Этап 1: База данных и класс Database

- **Добавлено:**
  - Каталог `avia-calc/` с подкаталогами `config/`, `database/`, `src/`.
  - `config/database.php` — конфигурация: путь к `avia_calc.sqlite`, пути к `schema.sql` и `seed.sql`.
  - `database/schema.sql` — создание таблиц: `clients`, `service_types`, `payment_methods`, `service_fee_rules`, `transgran_rules`, `transgran_exceptions`, `acquiring_rules`, `client_special_rules`, `calculation_history` с комментариями на русском.
  - `database/seed.sql` — начальные данные: 8 клиентов, 4 типа услуг, 5 способов оплаты; правила сервисного сбора для всех пар (клиент × тип услуги); правила трансграна для колонок standard, kz_rub, kz_usd_eur; исключения трансграна для RME; спецправило для RT (no_transgran_standard); правила эквайринга для всех пар (клиент × способ оплаты). Использованы подзапросы по `code` для независимости от числовых id.
  - `src/Database.php` — класс `App\Database`: синглтон, `getInstance()`, `getConnection()` с настройкой PDO (ERRMODE_EXCEPTION, WAL, foreign_keys), `initializeDatabase()` (создание БД и выполнение schema + seed при отсутствии файла), вспомогательные методы `query()`, `fetchAll()`, `fetchOne()`, `execute()`, `lastInsertId()`. Комментарии на русском.
- **Документация:**
  - Созданы `CURRENT_STAGE.md`, `structure.md`, `CHANGELOG_AI.md` в корне проекта.
