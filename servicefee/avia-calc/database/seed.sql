-- ============================================================
-- Начальные данные для «Калькулятор сборов АВИА»
-- ============================================================
-- Используются подзапросы по code для независимости от числовых id.
-- ============================================================

-- --- Клиенты ---
INSERT INTO clients (code, name, country, result_currency, use_max_rule, agent_hint, sort_order) VALUES
('RT',        'РТ',                        'RU', 'RUB', 1, NULL, 1),
('RME',       'РМЕ Клиент Казахстана',     'KZ', 'RUB', 0, 'Компания-плательщик ТОЛЬКО для сотрудников: Бахтигаряев, Алферов, Линьков, Процкий', 2),
('RDL',       'РДЛ',                        'RU', 'RUB', 1, NULL, 3),
('EV',        'ЭВ',                          'RU', 'RUB', 0, NULL, 4),
('TOP_BRS',   'ТОП БРС + РУСТ',             'RU', 'RUB', 0, NULL, 5),
('EMPLOYEES', 'Сотрудники БРС',              'RU', 'RUB', 0, NULL, 6),
('OTHER',     'Все остальные клиенты',       'RU', 'RUB', 0, NULL, 7),
('SDG',       'SDG Клиент Казахстана',       'KZ', 'EUR', 0, NULL, 8);

-- --- Типы услуг ---
INSERT INTO service_types (code, name, sort_order) VALUES
('issue',    'Выписка',                    1),
('emd',      'Доп.услуги на борту (EMD)',  2),
('refund',   'Возврат',                    3),
('exchange', 'Обмен',                      4);

-- --- Способы оплаты ---
INSERT INTO payment_methods (code, name, is_kz_card, kz_card_currency, sort_order) VALUES
('invoice',         'По счёту',                    0, NULL,      1),
('card_brs',        'По карте БРС',                0, NULL,      2),
('card_other',      'По карте стороннего банка',    0, NULL,      3),
('card_kz_rub',     'По карте КЗ (рубли)',         1, 'RUB',     4),
('card_kz_usd_eur', 'По карте КЗ (USD/EUR)',       1, 'USD_EUR', 5);

-- --- Сервисный сбор ---
INSERT INTO service_fee_rules (client_id, service_type_id, fee_type, percent_value, fixed_value, min_value, max_value, currency) VALUES
((SELECT id FROM clients WHERE code='RT'),        (SELECT id FROM service_types WHERE code='issue'),   'percent', 1.0, NULL, 1700, NULL, 'RUB'),
((SELECT id FROM clients WHERE code='RT'),        (SELECT id FROM service_types WHERE code='emd'),     'percent', 1.0, NULL, 500,  NULL, 'RUB'),
((SELECT id FROM clients WHERE code='RT'),        (SELECT id FROM service_types WHERE code='refund'),  'fixed',   NULL, 0,    NULL, NULL, 'RUB'),
((SELECT id FROM clients WHERE code='RT'),        (SELECT id FROM service_types WHERE code='exchange'),'fixed',   NULL, 1000, NULL, NULL, 'RUB'),

((SELECT id FROM clients WHERE code='RME'),       (SELECT id FROM service_types WHERE code='issue'),   'percent', 1.0, NULL, 1700, NULL, 'RUB'),
((SELECT id FROM clients WHERE code='RME'),       (SELECT id FROM service_types WHERE code='emd'),     'percent', 1.0, NULL, 500,  NULL, 'RUB'),
((SELECT id FROM clients WHERE code='RME'),       (SELECT id FROM service_types WHERE code='refund'),  'fixed',   NULL, 0,    NULL, NULL, 'RUB'),
((SELECT id FROM clients WHERE code='RME'),       (SELECT id FROM service_types WHERE code='exchange'),'fixed',   NULL, 1000, NULL, NULL, 'RUB'),

((SELECT id FROM clients WHERE code='RDL'),       (SELECT id FROM service_types WHERE code='issue'),   'percent', 1.0, NULL, 1700, NULL, 'RUB'),
((SELECT id FROM clients WHERE code='RDL'),       (SELECT id FROM service_types WHERE code='emd'),     'percent', 1.0, NULL, 500,  NULL, 'RUB'),
((SELECT id FROM clients WHERE code='RDL'),       (SELECT id FROM service_types WHERE code='refund'),  'fixed',   NULL, 0,    NULL, NULL, 'RUB'),
((SELECT id FROM clients WHERE code='RDL'),       (SELECT id FROM service_types WHERE code='exchange'),'fixed',   NULL, 1000, NULL, NULL, 'RUB'),

((SELECT id FROM clients WHERE code='EV'),        (SELECT id FROM service_types WHERE code='issue'),   'percent', 1.0, NULL, 1700, NULL, 'RUB'),
((SELECT id FROM clients WHERE code='EV'),        (SELECT id FROM service_types WHERE code='emd'),     'percent', 1.0, NULL, 500,  NULL, 'RUB'),
((SELECT id FROM clients WHERE code='EV'),        (SELECT id FROM service_types WHERE code='refund'),  'fixed',   NULL, 0,    NULL, NULL, 'RUB'),
((SELECT id FROM clients WHERE code='EV'),        (SELECT id FROM service_types WHERE code='exchange'),'fixed',   NULL, 1000, NULL, NULL, 'RUB'),

((SELECT id FROM clients WHERE code='TOP_BRS'),   (SELECT id FROM service_types WHERE code='issue'),   'percent', 1.0, NULL, 1700, NULL, 'RUB'),
((SELECT id FROM clients WHERE code='TOP_BRS'),   (SELECT id FROM service_types WHERE code='emd'),     'percent', 1.0, NULL, 500,  NULL, 'RUB'),
((SELECT id FROM clients WHERE code='TOP_BRS'),   (SELECT id FROM service_types WHERE code='refund'),  'fixed',   NULL, 0,    NULL, NULL, 'RUB'),
((SELECT id FROM clients WHERE code='TOP_BRS'),   (SELECT id FROM service_types WHERE code='exchange'),'fixed',   NULL, 1000, NULL, NULL, 'RUB'),

((SELECT id FROM clients WHERE code='EMPLOYEES'), (SELECT id FROM service_types WHERE code='issue'),   'percent', 1.0, NULL, 1700, NULL, 'RUB'),
((SELECT id FROM clients WHERE code='EMPLOYEES'), (SELECT id FROM service_types WHERE code='emd'),     'percent', 1.0, NULL, 500,  NULL, 'RUB'),
((SELECT id FROM clients WHERE code='EMPLOYEES'), (SELECT id FROM service_types WHERE code='refund'),  'fixed',   NULL, 1000, NULL, NULL, 'RUB'),
((SELECT id FROM clients WHERE code='EMPLOYEES'), (SELECT id FROM service_types WHERE code='exchange'),'fixed',   NULL, 1000, NULL, NULL, 'RUB'),

((SELECT id FROM clients WHERE code='OTHER'),     (SELECT id FROM service_types WHERE code='issue'),   'percent', 5.0, NULL, 1700, NULL, 'RUB'),
((SELECT id FROM clients WHERE code='OTHER'),     (SELECT id FROM service_types WHERE code='emd'),     'percent', 2.0, NULL, 500,  NULL, 'RUB'),
((SELECT id FROM clients WHERE code='OTHER'),     (SELECT id FROM service_types WHERE code='refund'),  'fixed',   NULL, 1000, NULL, NULL, 'RUB'),
((SELECT id FROM clients WHERE code='OTHER'),     (SELECT id FROM service_types WHERE code='exchange'),'fixed',   NULL, 1000, NULL, NULL, 'RUB'),

((SELECT id FROM clients WHERE code='SDG'),       (SELECT id FROM service_types WHERE code='issue'),   'percent', 2.0, NULL, 10,   NULL, 'EUR'),
((SELECT id FROM clients WHERE code='SDG'),       (SELECT id FROM service_types WHERE code='emd'),     'percent', 2.0, NULL, 5,    NULL, 'EUR'),
((SELECT id FROM clients WHERE code='SDG'),       (SELECT id FROM service_types WHERE code='refund'),  'fixed',   NULL, 0,    NULL, NULL, 'EUR'),
((SELECT id FROM clients WHERE code='SDG'),       (SELECT id FROM service_types WHERE code='exchange'),'fixed',   NULL, 0,    NULL, NULL, 'EUR');

-- --- Трансгран: колонка standard ---
INSERT INTO transgran_rules (client_id, service_type_id, transgran_column, percent_value, min_value_low, min_value_high, threshold, min_currency, is_applicable, include_service_fee) VALUES
((SELECT id FROM clients WHERE code='RT'),  (SELECT id FROM service_types WHERE code='issue'),   'standard', 0,   NULL, NULL, 1000, NULL,      0, 1),
((SELECT id FROM clients WHERE code='RT'),  (SELECT id FROM service_types WHERE code='emd'),     'standard', 1.8, NULL, NULL, 1000, NULL,      1, 1),
((SELECT id FROM clients WHERE code='RT'),  (SELECT id FROM service_types WHERE code='refund'),  'standard', 0,   NULL, NULL, 1000, NULL,      0, 1),
((SELECT id FROM clients WHERE code='RT'),  (SELECT id FROM service_types WHERE code='exchange'),'standard', 0,   NULL, NULL, 1000, NULL,      0, 1),

((SELECT id FROM clients WHERE code='RME'), (SELECT id FROM service_types WHERE code='issue'),   'standard', 1.8, 70, 120, 1000, 'USD_EUR', 1, 1),
((SELECT id FROM clients WHERE code='RME'), (SELECT id FROM service_types WHERE code='emd'),     'standard', 1.8, NULL, NULL, 1000, NULL,      1, 1),
((SELECT id FROM clients WHERE code='RME'), (SELECT id FROM service_types WHERE code='refund'),  'standard', 0,   NULL, NULL, 1000, NULL,      0, 1),
((SELECT id FROM clients WHERE code='RME'), (SELECT id FROM service_types WHERE code='exchange'),'standard', 1.8, 70, 120, 1000, 'USD_EUR', 1, 1),

((SELECT id FROM clients WHERE code='RDL'), (SELECT id FROM service_types WHERE code='issue'),   'standard', 1.8, 70, 120, 1000, 'USD_EUR', 1, 1),
((SELECT id FROM clients WHERE code='RDL'), (SELECT id FROM service_types WHERE code='emd'),     'standard', 1.8, NULL, NULL, 1000, NULL,      1, 1),
((SELECT id FROM clients WHERE code='RDL'), (SELECT id FROM service_types WHERE code='refund'),  'standard', 0,   NULL, NULL, 1000, NULL,      0, 1),
((SELECT id FROM clients WHERE code='RDL'), (SELECT id FROM service_types WHERE code='exchange'),'standard', 1.8, 70, 120, 1000, 'USD_EUR', 1, 1),

((SELECT id FROM clients WHERE code='EV'),  (SELECT id FROM service_types WHERE code='issue'),   'standard', 1.8, 70, 120, 1000, 'USD_EUR', 1, 1),
((SELECT id FROM clients WHERE code='EV'),  (SELECT id FROM service_types WHERE code='emd'),     'standard', 1.8, NULL, NULL, 1000, NULL,      1, 1),
((SELECT id FROM clients WHERE code='EV'),  (SELECT id FROM service_types WHERE code='refund'),  'standard', 0,   NULL, NULL, 1000, NULL,      0, 1),
((SELECT id FROM clients WHERE code='EV'),  (SELECT id FROM service_types WHERE code='exchange'),'standard', 1.8, 70, 120, 1000, 'USD_EUR', 1, 1),

((SELECT id FROM clients WHERE code='TOP_BRS'), (SELECT id FROM service_types WHERE code='issue'),   'standard', 1.8, 70, 120, 1000, 'USD_EUR', 1, 1),
((SELECT id FROM clients WHERE code='TOP_BRS'), (SELECT id FROM service_types WHERE code='emd'),     'standard', 1.8, NULL, NULL, 1000, NULL,      1, 1),
((SELECT id FROM clients WHERE code='TOP_BRS'), (SELECT id FROM service_types WHERE code='refund'),  'standard', 0,   NULL, NULL, 1000, NULL,      0, 1),
((SELECT id FROM clients WHERE code='TOP_BRS'), (SELECT id FROM service_types WHERE code='exchange'),'standard', 1.8, 70, 120, 1000, 'USD_EUR', 1, 1),

((SELECT id FROM clients WHERE code='EMPLOYEES'), (SELECT id FROM service_types WHERE code='issue'),   'standard', 1.5, 70, 120, 1000, 'USD_EUR', 1, 1),
((SELECT id FROM clients WHERE code='EMPLOYEES'), (SELECT id FROM service_types WHERE code='emd'),     'standard', 1.8, NULL, NULL, 1000, NULL,      1, 1),
((SELECT id FROM clients WHERE code='EMPLOYEES'), (SELECT id FROM service_types WHERE code='refund'),  'standard', 0,   NULL, NULL, 1000, NULL,      0, 1),
((SELECT id FROM clients WHERE code='EMPLOYEES'), (SELECT id FROM service_types WHERE code='exchange'),'standard', 1.5, 70, 120, 1000, 'USD_EUR', 1, 1),

((SELECT id FROM clients WHERE code='OTHER'), (SELECT id FROM service_types WHERE code='issue'),   'standard', 1.5, 70, 120, 1000, 'USD_EUR', 1, 1),
((SELECT id FROM clients WHERE code='OTHER'), (SELECT id FROM service_types WHERE code='emd'),     'standard', 1.8, NULL, NULL, 1000, NULL,      1, 1),
((SELECT id FROM clients WHERE code='OTHER'), (SELECT id FROM service_types WHERE code='refund'),  'standard', 0,   NULL, NULL, 1000, NULL,      0, 1),
((SELECT id FROM clients WHERE code='OTHER'), (SELECT id FROM service_types WHERE code='exchange'),'standard', 1.5, 70, 120, 1000, 'USD_EUR', 1, 1),

((SELECT id FROM clients WHERE code='SDG'), (SELECT id FROM service_types WHERE code='issue'),   'standard', 1.5, 70, 120, 1000, 'USD_EUR', 1, 1),
((SELECT id FROM clients WHERE code='SDG'), (SELECT id FROM service_types WHERE code='emd'),     'standard', 1.8, NULL, NULL, 1000, NULL,      1, 1),
((SELECT id FROM clients WHERE code='SDG'), (SELECT id FROM service_types WHERE code='refund'),  'standard', 0,   NULL, NULL, 1000, NULL,      0, 1),
((SELECT id FROM clients WHERE code='SDG'), (SELECT id FROM service_types WHERE code='exchange'),'standard', 1.5, 70, 120, 1000, 'USD_EUR', 1, 1);

-- --- Трансгран: колонка kz_rub (для всех клиентов одинаково) ---
INSERT INTO transgran_rules (client_id, service_type_id, transgran_column, percent_value, min_value_low, min_value_high, threshold, min_currency, is_applicable, include_service_fee) VALUES
((SELECT id FROM clients WHERE code='RT'),  (SELECT id FROM service_types WHERE code='issue'),   'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='RT'),  (SELECT id FROM service_types WHERE code='emd'),     'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='RT'),  (SELECT id FROM service_types WHERE code='refund'),  'kz_rub', 0,  NULL, NULL, 1000, NULL, 0, 1),
((SELECT id FROM clients WHERE code='RT'),  (SELECT id FROM service_types WHERE code='exchange'),'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1),

((SELECT id FROM clients WHERE code='RME'), (SELECT id FROM service_types WHERE code='issue'),   'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='RME'), (SELECT id FROM service_types WHERE code='emd'),     'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='RME'), (SELECT id FROM service_types WHERE code='refund'),  'kz_rub', 0,  NULL, NULL, 1000, NULL, 0, 1),
((SELECT id FROM clients WHERE code='RME'), (SELECT id FROM service_types WHERE code='exchange'),'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1),

((SELECT id FROM clients WHERE code='RDL'), (SELECT id FROM service_types WHERE code='issue'),   'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='RDL'), (SELECT id FROM service_types WHERE code='emd'),     'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='RDL'), (SELECT id FROM service_types WHERE code='refund'),  'kz_rub', 0,  NULL, NULL, 1000, NULL, 0, 1),
((SELECT id FROM clients WHERE code='RDL'), (SELECT id FROM service_types WHERE code='exchange'),'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1),

((SELECT id FROM clients WHERE code='EV'),  (SELECT id FROM service_types WHERE code='issue'),   'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='EV'),  (SELECT id FROM service_types WHERE code='emd'),     'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='EV'),  (SELECT id FROM service_types WHERE code='refund'),  'kz_rub', 0,  NULL, NULL, 1000, NULL, 0, 1),
((SELECT id FROM clients WHERE code='EV'),  (SELECT id FROM service_types WHERE code='exchange'),'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1),

((SELECT id FROM clients WHERE code='TOP_BRS'), (SELECT id FROM service_types WHERE code='issue'),   'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='TOP_BRS'), (SELECT id FROM service_types WHERE code='emd'),     'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='TOP_BRS'), (SELECT id FROM service_types WHERE code='refund'),  'kz_rub', 0,  NULL, NULL, 1000, NULL, 0, 1),
((SELECT id FROM clients WHERE code='TOP_BRS'), (SELECT id FROM service_types WHERE code='exchange'),'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1),

((SELECT id FROM clients WHERE code='EMPLOYEES'), (SELECT id FROM service_types WHERE code='issue'),   'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='EMPLOYEES'), (SELECT id FROM service_types WHERE code='emd'),     'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='EMPLOYEES'), (SELECT id FROM service_types WHERE code='refund'),  'kz_rub', 0,  NULL, NULL, 1000, NULL, 0, 1),
((SELECT id FROM clients WHERE code='EMPLOYEES'), (SELECT id FROM service_types WHERE code='exchange'),'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1),

((SELECT id FROM clients WHERE code='OTHER'), (SELECT id FROM service_types WHERE code='issue'),   'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='OTHER'), (SELECT id FROM service_types WHERE code='emd'),     'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='OTHER'), (SELECT id FROM service_types WHERE code='refund'),  'kz_rub', 0,  NULL, NULL, 1000, NULL, 0, 1),
((SELECT id FROM clients WHERE code='OTHER'), (SELECT id FROM service_types WHERE code='exchange'),'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1),

((SELECT id FROM clients WHERE code='SDG'), (SELECT id FROM service_types WHERE code='issue'),   'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='SDG'), (SELECT id FROM service_types WHERE code='emd'),     'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='SDG'), (SELECT id FROM service_types WHERE code='refund'),  'kz_rub', 0,  NULL, NULL, 1000, NULL, 0, 1),
((SELECT id FROM clients WHERE code='SDG'), (SELECT id FROM service_types WHERE code='exchange'),'kz_rub', 19, NULL, NULL, 1000, NULL, 1, 1);

-- --- Трансгран: колонка kz_usd_eur ---
INSERT INTO transgran_rules (client_id, service_type_id, transgran_column, percent_value, min_value_low, min_value_high, threshold, min_currency, is_applicable, include_service_fee) VALUES
((SELECT id FROM clients WHERE code='RT'),  (SELECT id FROM service_types WHERE code='issue'),   'kz_usd_eur', 1.8, 70, 120, 1000, 'USD_EUR', 1, 1),
((SELECT id FROM clients WHERE code='RT'),  (SELECT id FROM service_types WHERE code='emd'),     'kz_usd_eur', 1.8, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='RT'),  (SELECT id FROM service_types WHERE code='refund'),  'kz_usd_eur', 0,   NULL, NULL, 1000, NULL, 0, 1),
((SELECT id FROM clients WHERE code='RT'),  (SELECT id FROM service_types WHERE code='exchange'),'kz_usd_eur', 1.8, 70, 120, 1000, 'USD_EUR', 1, 1),

((SELECT id FROM clients WHERE code='RME'), (SELECT id FROM service_types WHERE code='issue'),   'kz_usd_eur', 1.8, 70, 120, 1000, 'USD_EUR', 1, 1),
((SELECT id FROM clients WHERE code='RME'), (SELECT id FROM service_types WHERE code='emd'),     'kz_usd_eur', 1.8, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='RME'), (SELECT id FROM service_types WHERE code='refund'),  'kz_usd_eur', 0,   NULL, NULL, 1000, NULL, 0, 1),
((SELECT id FROM clients WHERE code='RME'), (SELECT id FROM service_types WHERE code='exchange'),'kz_usd_eur', 1.8, 70, 120, 1000, 'USD_EUR', 1, 1),

((SELECT id FROM clients WHERE code='RDL'), (SELECT id FROM service_types WHERE code='issue'),   'kz_usd_eur', 1.8, 70, 120, 1000, 'USD_EUR', 1, 1),
((SELECT id FROM clients WHERE code='RDL'), (SELECT id FROM service_types WHERE code='emd'),     'kz_usd_eur', 1.8, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='RDL'), (SELECT id FROM service_types WHERE code='refund'),  'kz_usd_eur', 0,   NULL, NULL, 1000, NULL, 0, 1),
((SELECT id FROM clients WHERE code='RDL'), (SELECT id FROM service_types WHERE code='exchange'),'kz_usd_eur', 1.8, 70, 120, 1000, 'USD_EUR', 1, 1),

((SELECT id FROM clients WHERE code='EV'),  (SELECT id FROM service_types WHERE code='issue'),   'kz_usd_eur', 1.8, 70, 120, 1000, 'USD_EUR', 1, 1),
((SELECT id FROM clients WHERE code='EV'),  (SELECT id FROM service_types WHERE code='emd'),     'kz_usd_eur', 3.5, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='EV'),  (SELECT id FROM service_types WHERE code='refund'),  'kz_usd_eur', 0,   NULL, NULL, 1000, NULL, 0, 1),
((SELECT id FROM clients WHERE code='EV'),  (SELECT id FROM service_types WHERE code='exchange'),'kz_usd_eur', 1.8, 70, 120, 1000, 'USD_EUR', 1, 1),

((SELECT id FROM clients WHERE code='TOP_BRS'), (SELECT id FROM service_types WHERE code='issue'),   'kz_usd_eur', 1.5, 70, 120, 1000, 'USD_EUR', 1, 1),
((SELECT id FROM clients WHERE code='TOP_BRS'), (SELECT id FROM service_types WHERE code='emd'),     'kz_usd_eur', 3.5, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='TOP_BRS'), (SELECT id FROM service_types WHERE code='refund'),  'kz_usd_eur', 0,   NULL, NULL, 1000, NULL, 0, 1),
((SELECT id FROM clients WHERE code='TOP_BRS'), (SELECT id FROM service_types WHERE code='exchange'),'kz_usd_eur', 1.5, 70, 120, 1000, 'USD_EUR', 1, 1),

((SELECT id FROM clients WHERE code='EMPLOYEES'), (SELECT id FROM service_types WHERE code='issue'),   'kz_usd_eur', 1.5, 70, 120, 1000, 'USD_EUR', 1, 1),
((SELECT id FROM clients WHERE code='EMPLOYEES'), (SELECT id FROM service_types WHERE code='emd'),     'kz_usd_eur', 3.5, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='EMPLOYEES'), (SELECT id FROM service_types WHERE code='refund'),  'kz_usd_eur', 0,   NULL, NULL, 1000, NULL, 0, 1),
((SELECT id FROM clients WHERE code='EMPLOYEES'), (SELECT id FROM service_types WHERE code='exchange'),'kz_usd_eur', 1.5, 70, 120, 1000, 'USD_EUR', 1, 1),

((SELECT id FROM clients WHERE code='OTHER'), (SELECT id FROM service_types WHERE code='issue'),   'kz_usd_eur', 1.5, 70, 120, 1000, 'USD_EUR', 1, 1),
((SELECT id FROM clients WHERE code='OTHER'), (SELECT id FROM service_types WHERE code='emd'),     'kz_usd_eur', 3.5, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='OTHER'), (SELECT id FROM service_types WHERE code='refund'),  'kz_usd_eur', 0,   NULL, NULL, 1000, NULL, 0, 1),
((SELECT id FROM clients WHERE code='OTHER'), (SELECT id FROM service_types WHERE code='exchange'),'kz_usd_eur', 1.5, 70, 120, 1000, 'USD_EUR', 1, 1),

((SELECT id FROM clients WHERE code='SDG'), (SELECT id FROM service_types WHERE code='issue'),   'kz_usd_eur', 1.5, 70, 120, 1000, 'USD_EUR', 1, 1),
((SELECT id FROM clients WHERE code='SDG'), (SELECT id FROM service_types WHERE code='emd'),     'kz_usd_eur', 3.5, NULL, NULL, 1000, NULL, 1, 1),
((SELECT id FROM clients WHERE code='SDG'), (SELECT id FROM service_types WHERE code='refund'),  'kz_usd_eur', 0,   NULL, NULL, 1000, NULL, 0, 1),
((SELECT id FROM clients WHERE code='SDG'), (SELECT id FROM service_types WHERE code='exchange'),'kz_usd_eur', 1.5, 70, 120, 1000, 'USD_EUR', 1, 1);

-- --- Исключения трансграна (RME: если поставщик из Казахстана — трансгран не берётся) ---
INSERT INTO transgran_exceptions (transgran_rule_id, exception_type, exception_value)
SELECT tr.id, 'supplier_country', 'KZ'
FROM transgran_rules tr
JOIN clients c ON c.id = tr.client_id
JOIN service_types st ON st.id = tr.service_type_id
WHERE c.code = 'RME' AND st.code != 'refund';

-- --- Спецправила: RT — не брать стандартный трансгран для issue и exchange (если не карта КЗ) ---
INSERT INTO client_special_rules (client_id, rule_type, applicable_service_types, params) VALUES
((SELECT id FROM clients WHERE code='RT'), 'no_transgran_standard', '["issue","exchange"]', '{"override_by_kz_card": true}');

-- --- Эквайринг ---
INSERT INTO acquiring_rules (client_id, payment_method_id, percent_value, exclude_service_type) VALUES
((SELECT id FROM clients WHERE code='RT'),  (SELECT id FROM payment_methods WHERE code='invoice'),         0,   NULL),
((SELECT id FROM clients WHERE code='RT'),  (SELECT id FROM payment_methods WHERE code='card_brs'),        0,   NULL),
((SELECT id FROM clients WHERE code='RT'),  (SELECT id FROM payment_methods WHERE code='card_other'),     2,   NULL),
((SELECT id FROM clients WHERE code='RT'),  (SELECT id FROM payment_methods WHERE code='card_kz_rub'),     2,   NULL),
((SELECT id FROM clients WHERE code='RT'),  (SELECT id FROM payment_methods WHERE code='card_kz_usd_eur'), 2,   NULL),

((SELECT id FROM clients WHERE code='RME'), (SELECT id FROM payment_methods WHERE code='invoice'),         0,   NULL),
((SELECT id FROM clients WHERE code='RME'), (SELECT id FROM payment_methods WHERE code='card_brs'),        0,   NULL),
((SELECT id FROM clients WHERE code='RME'), (SELECT id FROM payment_methods WHERE code='card_other'),     2,   NULL),
((SELECT id FROM clients WHERE code='RME'), (SELECT id FROM payment_methods WHERE code='card_kz_rub'),     2,   NULL),
((SELECT id FROM clients WHERE code='RME'), (SELECT id FROM payment_methods WHERE code='card_kz_usd_eur'), 2,   NULL),

((SELECT id FROM clients WHERE code='RDL'), (SELECT id FROM payment_methods WHERE code='invoice'),         0,   NULL),
((SELECT id FROM clients WHERE code='RDL'), (SELECT id FROM payment_methods WHERE code='card_brs'),        0,   NULL),
((SELECT id FROM clients WHERE code='RDL'), (SELECT id FROM payment_methods WHERE code='card_other'),     2,   NULL),
((SELECT id FROM clients WHERE code='RDL'), (SELECT id FROM payment_methods WHERE code='card_kz_rub'),     2,   NULL),
((SELECT id FROM clients WHERE code='RDL'), (SELECT id FROM payment_methods WHERE code='card_kz_usd_eur'), 2,   NULL),

((SELECT id FROM clients WHERE code='EV'),  (SELECT id FROM payment_methods WHERE code='invoice'),         0,   NULL),
((SELECT id FROM clients WHERE code='EV'),  (SELECT id FROM payment_methods WHERE code='card_brs'),        1.2, NULL),
((SELECT id FROM clients WHERE code='EV'),  (SELECT id FROM payment_methods WHERE code='card_other'),     2,   NULL),
((SELECT id FROM clients WHERE code='EV'),  (SELECT id FROM payment_methods WHERE code='card_kz_rub'),     2,   NULL),
((SELECT id FROM clients WHERE code='EV'),  (SELECT id FROM payment_methods WHERE code='card_kz_usd_eur'), 2,   NULL),

((SELECT id FROM clients WHERE code='TOP_BRS'), (SELECT id FROM payment_methods WHERE code='invoice'),         0,   NULL),
((SELECT id FROM clients WHERE code='TOP_BRS'), (SELECT id FROM payment_methods WHERE code='card_brs'),        1.2, NULL),
((SELECT id FROM clients WHERE code='TOP_BRS'), (SELECT id FROM payment_methods WHERE code='card_other'),     2,   NULL),
((SELECT id FROM clients WHERE code='TOP_BRS'), (SELECT id FROM payment_methods WHERE code='card_kz_rub'),     2,   NULL),
((SELECT id FROM clients WHERE code='TOP_BRS'), (SELECT id FROM payment_methods WHERE code='card_kz_usd_eur'), 2,   NULL),

((SELECT id FROM clients WHERE code='EMPLOYEES'), (SELECT id FROM payment_methods WHERE code='invoice'),         0,   NULL),
((SELECT id FROM clients WHERE code='EMPLOYEES'), (SELECT id FROM payment_methods WHERE code='card_brs'),        1.2, NULL),
((SELECT id FROM clients WHERE code='EMPLOYEES'), (SELECT id FROM payment_methods WHERE code='card_other'),     2,   NULL),
((SELECT id FROM clients WHERE code='EMPLOYEES'), (SELECT id FROM payment_methods WHERE code='card_kz_rub'),     2,   NULL),
((SELECT id FROM clients WHERE code='EMPLOYEES'), (SELECT id FROM payment_methods WHERE code='card_kz_usd_eur'), 2,   NULL),

((SELECT id FROM clients WHERE code='OTHER'), (SELECT id FROM payment_methods WHERE code='invoice'),         0,   NULL),
((SELECT id FROM clients WHERE code='OTHER'), (SELECT id FROM payment_methods WHERE code='card_brs'),        1.2, 'issue'),
((SELECT id FROM clients WHERE code='OTHER'), (SELECT id FROM payment_methods WHERE code='card_other'),     2,   NULL),
((SELECT id FROM clients WHERE code='OTHER'), (SELECT id FROM payment_methods WHERE code='card_kz_rub'),     2,   NULL),
((SELECT id FROM clients WHERE code='OTHER'), (SELECT id FROM payment_methods WHERE code='card_kz_usd_eur'), 2,   NULL),

((SELECT id FROM clients WHERE code='SDG'), (SELECT id FROM payment_methods WHERE code='invoice'),         0,   NULL),
((SELECT id FROM clients WHERE code='SDG'), (SELECT id FROM payment_methods WHERE code='card_brs'),        0,   NULL),
((SELECT id FROM clients WHERE code='SDG'), (SELECT id FROM payment_methods WHERE code='card_other'),     0,   NULL),
((SELECT id FROM clients WHERE code='SDG'), (SELECT id FROM payment_methods WHERE code='card_kz_rub'),     0,   NULL),
((SELECT id FROM clients WHERE code='SDG'), (SELECT id FROM payment_methods WHERE code='card_kz_usd_eur'), 0,   NULL);
