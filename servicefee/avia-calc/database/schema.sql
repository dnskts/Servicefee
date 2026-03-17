-- ============================================================
-- Схема базы данных «Калькулятор сборов АВИА»
-- ============================================================
-- Все таблицы создаются с условием IF NOT EXISTS,
-- чтобы повторный запуск не вызывал ошибок.
-- ============================================================

-- Категории клиентов (РТ, РМЕ, SDG и т.д.)
CREATE TABLE IF NOT EXISTS clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,                    -- Уникальный код ('RT', 'RME', 'SDG' и т.д.)
    name TEXT NOT NULL,                           -- Отображаемое название
    country TEXT NOT NULL DEFAULT 'RU',           -- Страна клиента: 'RU' или 'KZ'
    result_currency TEXT NOT NULL DEFAULT 'RUB',  -- Валюта итогового результата: 'RUB' или 'EUR'
    use_max_rule INTEGER NOT NULL DEFAULT 0,      -- Правило «что больше» (серв.сбор vs трансгран): 0 или 1
    agent_hint TEXT DEFAULT NULL,                 -- Подсказка для агента (информационная)
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1
);

-- Типы услуг (выписка, EMD, возврат, обмен)
CREATE TABLE IF NOT EXISTS service_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,                    -- 'issue', 'emd', 'refund', 'exchange'
    name TEXT NOT NULL,                           -- Человекочитаемое название
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1
);

-- Способы оплаты (по счёту, картой БРС, картой стороннего банка и т.д.)
CREATE TABLE IF NOT EXISTS payment_methods (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,                    -- 'invoice', 'card_brs', 'card_other' и т.д.
    name TEXT NOT NULL,
    is_kz_card INTEGER NOT NULL DEFAULT 0,        -- Является ли картой Казахстана
    kz_card_currency TEXT DEFAULT NULL,            -- Валюта карты КЗ: NULL, 'RUB', 'USD_EUR'
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1
);

-- Правила сервисного сбора (для каждой пары клиент + тип услуги)
CREATE TABLE IF NOT EXISTS service_fee_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    service_type_id INTEGER NOT NULL,
    fee_type TEXT NOT NULL DEFAULT 'percent',      -- 'percent' — процент от суммы, 'fixed' — фиксированная сумма
    percent_value REAL DEFAULT NULL,               -- Процент (например 1.0 означает 1%)
    fixed_value REAL DEFAULT NULL,                 -- Фиксированная сумма сбора
    min_value REAL DEFAULT NULL,                   -- Минимальный сбор (если процент дал меньше)
    max_value REAL DEFAULT NULL,                   -- Максимальный сбор (NULL = без ограничения)
    currency TEXT NOT NULL DEFAULT 'RUB',          -- Валюта сбора
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (service_type_id) REFERENCES service_types(id) ON DELETE CASCADE
);

-- Правила трансграничного сбора
-- Для каждой комбинации (клиент, тип услуги, колонка трансграна)
-- transgran_column определяет условие применения:
--   'standard'   — обычный трансгран
--   'kz_rub'     — карта КЗ в рублях
--   'kz_usd_eur' — карта КЗ в USD/EUR
CREATE TABLE IF NOT EXISTS transgran_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    service_type_id INTEGER NOT NULL,
    transgran_column TEXT NOT NULL,                -- 'standard', 'kz_rub', 'kz_usd_eur'
    percent_value REAL NOT NULL,                   -- Процент трансграна
    min_value_low REAL DEFAULT NULL,               -- Мин. сбор при сумме услуги ≤ threshold
    min_value_high REAL DEFAULT NULL,              -- Мин. сбор при сумме услуги > threshold
    threshold REAL DEFAULT 1000,                   -- Порог суммы услуги (по умолчанию 1000)
    min_currency TEXT DEFAULT NULL,                -- Валюта минимумов и порога
    is_applicable INTEGER NOT NULL DEFAULT 1,      -- Применяется ли правило (0 = не берётся)
    include_service_fee INTEGER NOT NULL DEFAULT 1,-- Включать серв.сбор в базу расчёта трансграна
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (service_type_id) REFERENCES service_types(id) ON DELETE CASCADE
);

-- Исключения для трансграничного сбора
-- Например: если поставщик из Казахстана — трансгран не берётся
CREATE TABLE IF NOT EXISTS transgran_exceptions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    transgran_rule_id INTEGER NOT NULL,
    exception_type TEXT NOT NULL,                  -- Тип исключения, например 'supplier_country'
    exception_value TEXT NOT NULL,                 -- Значение, например 'KZ'
    FOREIGN KEY (transgran_rule_id) REFERENCES transgran_rules(id) ON DELETE CASCADE
);

-- Правила эквайринга (для каждой пары клиент + способ оплаты)
CREATE TABLE IF NOT EXISTS acquiring_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    payment_method_id INTEGER NOT NULL,
    percent_value REAL NOT NULL DEFAULT 0,         -- Процент эквайринга
    exclude_service_type TEXT DEFAULT NULL,         -- Тип услуги, при котором эквайринг = 0 (код типа)
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE CASCADE
);

-- Специальные правила клиента (нестандартная логика)
CREATE TABLE IF NOT EXISTS client_special_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    rule_type TEXT NOT NULL,                       -- Тип спецправила, например 'no_transgran_standard'
    applicable_service_types TEXT DEFAULT NULL,    -- JSON-массив кодов типов услуг
    params TEXT DEFAULT NULL,                      -- JSON с дополнительными параметрами
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- История расчётов (для аудита и отладки)
CREATE TABLE IF NOT EXISTS calculation_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    input_data TEXT NOT NULL,                      -- JSON со входными параметрами расчёта
    result_data TEXT NOT NULL,                     -- JSON с результатами расчёта
    formulas TEXT NOT NULL                         -- Текстовое описание применённых формул
);
