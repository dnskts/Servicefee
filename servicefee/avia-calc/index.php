<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Калькулятор сборов АВИА</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-wrap">
        <!-- Шапка: заголовок, вкладки, ссылка на админку -->
        <header class="header">
            <h1 class="header-title">Калькулятор сборов АВИА</h1>
            <nav class="header-tabs">
                <button type="button" class="tab-btn active" data-tab="calculator">Калькулятор</button>
                <button type="button" class="tab-btn" data-tab="history">История</button>
            </nav>
            <a href="admin.php" class="header-admin" aria-hidden="true">Админка</a>
        </header>

        <main class="main">
            <!-- Вкладка «Калькулятор»: двухколоночный макет -->
            <section id="tab-calculator" class="tab-panel active">
                <div class="two-columns">
                    <!-- Левая колонка: ввод данных -->
                    <div class="column-left">
                        <h2 class="block-title">Ввод данных</h2>

                        <div class="block block-params">
                            <h3 class="block-subtitle">Параметры заказа</h3>
                            <div class="params-grid">
                                <div class="param-row" data-field="client">
                                    <label class="param-label" for="client">Клиент</label>
                                    <div class="param-field-wrap">
                                        <select id="client" name="client_code" class="param-input" required>
                                            <option value="">— Выберите клиента —</option>
                                        </select>
                                        <div id="agent-hint" class="agent-hint" aria-live="polite"></div>
                                    </div>
                                </div>
                                <div class="param-row" data-field="payment_method">
                                    <label class="param-label" for="payment_method">Способ оплаты</label>
                                    <select id="payment_method" name="payment_method_code" class="param-input" required>
                                        <option value="">— Выберите способ оплаты —</option>
                                    </select>
                                </div>
                                <div class="param-row" data-field="supplier_country">
                                    <label class="param-label" for="supplier_country">Страна поставщика</label>
                                    <select id="supplier_country" name="supplier_country" class="param-input" required>
                                        <option value="RU">Россия</option>
                                        <option value="KZ">Казахстан</option>
                                    </select>
                                </div>
                                <div class="param-row" data-field="settlement_currency">
                                    <label class="param-label" for="settlement_currency">Валюта расчёта с поставщиком</label>
                                    <select id="settlement_currency" name="settlement_currency" class="param-input" required>
                                        <option value="RUB">RUB</option>
                                        <option value="EUR">EUR</option>
                                        <option value="USD">USD</option>
                                        <option value="KZT">KZT</option>
                                    </select>
                                </div>
                                <div class="param-row" data-field="rate_rs_tls">
                                    <label class="param-label" for="rate_rs_tls">Курс РС ТЛС</label>
                                    <input type="number" id="rate_rs_tls" name="rate_rs_tls" class="param-input" step="0.01" min="0.01" value="1" placeholder="1">
                                </div>
                                <div class="param-row" data-field="rate_kz">
                                    <label class="param-label param-label-link" for="rate_kz">
                                        <a href="https://finance.kapital.kz/" target="_blank" rel="noopener" class="label-link" title="Курс Казахстана">Курс Казахстана</a>
                                    </label>
                                    <div class="param-field-wrap">
                                        <input type="number" id="rate_kz" name="rate_kz" class="param-input" step="0.01" min="0" value="0" placeholder="0">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="block block-services">
                            <h3 class="block-subtitle">Услуги</h3>
                            <div id="services-container" class="services-container"></div>
                            <button type="button" id="add-service" class="btn-add-service">+ Добавить услугу</button>
                        </div>
                    </div>

                    <!-- Правая колонка: результат расчёта -->
                    <div class="column-right">
                        <div id="result-area" class="result-area">
                            <p id="result-placeholder" class="result-placeholder">Заполните данные для расчёта.</p>
                            <div id="result-content" class="result-content" style="display: none;">
                                <div class="formulas-block">
                                    <h4 class="formulas-title">Применённые формулы</h4>
                                    <pre id="formulas-text" class="formulas-text"></pre>
                                </div>
                                <div class="result-table-wrap">
                                    <table id="result-table" class="result-table"></table>
                                </div>
                                <button type="button" id="btn-copy" class="btn-copy">Скопировать</button>
                            </div>
                            <div id="result-error" class="result-error" style="display: none;"></div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Вкладка «История» -->
            <section id="tab-history" class="tab-panel">
                <div class="history-toolbar">
                    <button type="button" id="btn-history-back" class="btn-history-back">Назад</button>
                    <button type="button" id="btn-clear-history" class="btn-clear-history">Очистить историю</button>
                </div>
                <div class="history-wrap">
                    <table id="history-table" class="history-table">
                        <thead>
                            <tr>
                                <th>№</th>
                                <th>Дата и время</th>
                                <th>Клиент</th>
                                <th>Сумма услуг</th>
                                <th>Итого сбор</th>
                                <th>Итого к оплате</th>
                                <th></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="history-tbody">
                        </tbody>
                    </table>
                    <p id="history-empty" class="history-empty" style="display: none;">История расчётов пуста.</p>
                </div>
            </section>
        </main>
    </div>

    <script src="assets/app.js"></script>
</body>
</html>
