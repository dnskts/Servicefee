<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Администрирование — Калькулятор сборов АВИА</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="admin-page">

    <!-- Шапка -->
    <header class="admin-header">
        <h1>Администрирование правил</h1>
        <a href="index.php" class="back-link">← Вернуться к калькулятору</a>
    </header>

    <!-- Основной контент -->
    <div class="admin-container">

        <!-- Левая панель — список клиентов -->
        <aside class="admin-sidebar">
            <h3>Клиенты</h3>
            <div id="client-list">
                <!-- Заполняется через JavaScript -->
            </div>
            <button class="btn-add" type="button" id="btn-add-client">+ Добавить клиента</button>
        </aside>

        <!-- Правая панель — данные клиента -->
        <main class="admin-content">

            <!-- Заглушка (показывается, пока клиент не выбран) -->
            <div id="no-client-selected" class="placeholder-text">
                Выберите клиента из списка слева
            </div>

            <!-- Контент клиента (скрыт, пока не выбран клиент) -->
            <div id="client-content" style="display: none;">

                <!-- Секция 1: Основные данные -->
                <div class="accordion-section">
                    <div class="accordion-header" data-section="section-details">
                        <span class="accordion-arrow">▼</span> Основные данные
                    </div>
                    <div class="accordion-body open" id="section-details">
                        <div class="form-grid">
                            <label>Код:</label>
                            <input type="text" id="client-code" readonly>

                            <label>Название:</label>
                            <input type="text" id="client-name">

                            <label>Страна:</label>
                            <select id="client-country">
                                <option value="RU">Россия</option>
                                <option value="KZ">Казахстан</option>
                            </select>

                            <label>Валюта результата:</label>
                            <select id="client-result-currency">
                                <option value="RUB">RUB</option>
                                <option value="EUR">EUR</option>
                            </select>

                            <label>Правило «что больше»:</label>
                            <div>
                                <input type="checkbox" id="client-use-max-rule">
                                <span class="hint">Берётся максимум из серв.сбора и трансграна</span>
                            </div>

                            <label>Подсказка для агента:</label>
                            <textarea id="client-agent-hint" rows="3"></textarea>

                            <label>Порядок сортировки:</label>
                            <input type="number" id="client-sort-order" value="0">

                            <label>Активен:</label>
                            <input type="checkbox" id="client-is-active">
                        </div>
                        <div class="form-actions">
                            <button class="btn-save" type="button" id="btn-save-client">Сохранить изменения</button>
                            <button class="btn-delete" type="button" id="btn-delete-client">Удалить клиента</button>
                        </div>
                    </div>
                </div>

                <!-- Секция 2: Сервисный сбор -->
                <div class="accordion-section">
                    <div class="accordion-header" data-section="section-service-fee">
                        <span class="accordion-arrow">▶</span> Сервисный сбор
                    </div>
                    <div class="accordion-body" id="section-service-fee">
                        <table class="rules-table">
                            <thead>
                                <tr>
                                    <th>Тип услуги</th>
                                    <th>Тип сбора</th>
                                    <th>Процент (%)</th>
                                    <th>Фикс. сумма</th>
                                    <th>Минимум</th>
                                    <th>Максимум</th>
                                    <th>Валюта</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody id="service-fee-tbody">
                            </tbody>
                        </table>
                        <button class="btn-add" type="button" id="btn-add-service-fee">+ Добавить правило</button>
                    </div>
                </div>

                <!-- Секция 3: Трансграничный сбор -->
                <div class="accordion-section">
                    <div class="accordion-header" data-section="section-transgran">
                        <span class="accordion-arrow">▶</span> Трансграничный сбор
                    </div>
                    <div class="accordion-body" id="section-transgran">
                        <div class="transgran-tabs">
                            <button class="transgran-tab active" type="button" data-column="standard">Standard</button>
                            <button class="transgran-tab" type="button" data-column="kz_rub">Карта КЗ (рубли)</button>
                            <button class="transgran-tab" type="button" data-column="kz_usd_eur">Карта КЗ (USD/EUR)</button>
                        </div>
                        <table class="rules-table">
                            <thead>
                                <tr>
                                    <th>Тип услуги</th>
                                    <th>% трансграна</th>
                                    <th>Мин. (до порога)</th>
                                    <th>Мин. (свыше порога)</th>
                                    <th>Порог</th>
                                    <th>Валюта мин.</th>
                                    <th>Применяется</th>
                                    <th>Вкл. серв.сбор</th>
                                    <th>Исключения</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody id="transgran-tbody">
                            </tbody>
                        </table>
                        <button class="btn-add" type="button" id="btn-add-transgran">+ Добавить правило</button>
                    </div>
                </div>

                <!-- Секция 4: Эквайринг -->
                <div class="accordion-section">
                    <div class="accordion-header" data-section="section-acquiring">
                        <span class="accordion-arrow">▶</span> Эквайринг
                    </div>
                    <div class="accordion-body" id="section-acquiring">
                        <table class="rules-table">
                            <thead>
                                <tr>
                                    <th>Способ оплаты</th>
                                    <th>Процент (%)</th>
                                    <th>Исключение (тип услуги)</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody id="acquiring-tbody">
                            </tbody>
                        </table>
                        <button class="btn-add" type="button" id="btn-add-acquiring">+ Добавить правило</button>
                    </div>
                </div>

                <!-- Секция 5: Специальные правила -->
                <div class="accordion-section">
                    <div class="accordion-header" data-section="section-special">
                        <span class="accordion-arrow">▶</span> Специальные правила
                    </div>
                    <div class="accordion-body" id="section-special">
                        <table class="rules-table">
                            <thead>
                                <tr>
                                    <th>Тип правила</th>
                                    <th>Типы услуг</th>
                                    <th>Параметры</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody id="special-rules-tbody">
                            </tbody>
                        </table>
                        <button class="btn-add" type="button" id="btn-add-special">+ Добавить правило</button>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Модальное окно создания клиента -->
    <div class="modal-overlay" id="create-client-modal" style="display: none;">
        <div class="modal-window">
            <h2 class="modal-title">Создать нового клиента</h2>

            <div class="form-grid">
                <label>Код (латиница, уникальный):</label>
                <input type="text" id="new-client-code" placeholder="Например: NEW_CLIENT">

                <label>Название:</label>
                <input type="text" id="new-client-name" placeholder="Например: Новый клиент">

                <label>Страна:</label>
                <select id="new-client-country">
                    <option value="RU">Россия</option>
                    <option value="KZ">Казахстан</option>
                </select>

                <label>Валюта результата:</label>
                <select id="new-client-result-currency">
                    <option value="RUB">RUB</option>
                    <option value="EUR">EUR</option>
                </select>

                <label>Правило «что больше»:</label>
                <div>
                    <input type="checkbox" id="new-client-use-max-rule">
                    <span class="hint">Берётся максимум из серв.сбора и трансграна</span>
                </div>

                <label>Подсказка для агента:</label>
                <textarea id="new-client-agent-hint" rows="2" placeholder="Необязательно"></textarea>

                <label>Порядок сортировки:</label>
                <input type="number" id="new-client-sort-order" value="0">
            </div>

            <div id="modal-error" class="error-message" style="display: none;"></div>

            <div class="modal-actions">
                <button class="btn-save" type="button" id="btn-create-client">Создать</button>
                <button class="btn-cancel" type="button" id="btn-modal-cancel">Отмена</button>
            </div>
        </div>
    </div>

    <script src="assets/admin.js"></script>
</body>
</html>
