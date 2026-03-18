/**
 * Калькулятор сборов АВИА — логика интерфейса
 * Чистый JavaScript, без фреймворков. Все комментарии на русском.
 */

var App = {
    /** Загруженные справочники (clients, service_types, payment_methods, currencies, countries) */
    data: null,

    /** Счётчик услуг для генерации уникальных id блоков */
    serviceCount: 0,

    /** Последний результат расчёта (для копирования) */
    currentResult: null
};

/**
 * Инициализация при загрузке DOM.
 * Загружает справочники, заполняет выпадающие списки, навешивает обработчики, добавляет первую услугу.
 */
App.init = function () {
    var self = this;
    fetch('api.php?action=get_initial_data')
        .then(function (r) { return r.json(); })
        .then(function (json) {
            if (json.error) {
                alert('Ошибка загрузки данных: ' + json.error);
                return;
            }
            self.data = json;
            self.fillSelects();
            self.bindEvents();
            self.addService();
            self.updateFieldValidity(false);
        })
        .catch(function (err) {
            alert('Ошибка сети: ' + err.message);
        });
};

/**
 * Заполняет выпадающие списки из App.data.
 */
App.fillSelects = function () {
    var d = this.data;
    if (!d) return;

    var clientSelect = document.getElementById('client');
    var savedClient = clientSelect ? clientSelect.value : '';
    if (clientSelect) {
        clientSelect.innerHTML = '<option value="">— Выберите клиента —</option>';
        (d.clients || []).forEach(function (c) {
            var opt = document.createElement('option');
            opt.value = c.code;
            opt.textContent = c.name;
            clientSelect.appendChild(opt);
        });
        if (savedClient) clientSelect.value = savedClient;
    }

    var pmSelect = document.getElementById('payment_method');
    var savedPm = pmSelect ? pmSelect.value : '';
    if (pmSelect) {
        pmSelect.innerHTML = '<option value="">— Выберите способ оплаты —</option>';
        (d.payment_methods || []).forEach(function (p) {
            var opt = document.createElement('option');
            opt.value = p.code;
            opt.textContent = p.name;
            pmSelect.appendChild(opt);
        });
        if (savedPm) pmSelect.value = savedPm;
    }

    var stSelects = document.querySelectorAll('.service-type-select');
    stSelects.forEach(function (sel) {
        App.fillOneServiceTypeSelect(sel);
    });
};

/**
 * Заполняет один выпадающий список типов услуг.
 * @param {HTMLSelectElement} select
 */
App.fillOneServiceTypeSelect = function (select) {
    if (!this.data || !this.data.service_types) return;
    var current = select.value;
    select.innerHTML = '';
    this.data.service_types.forEach(function (st) {
        var opt = document.createElement('option');
        opt.value = st.code;
        opt.textContent = st.name;
        select.appendChild(opt);
    });
    if (current) select.value = current;
};

/**
 * Навешивает обработчики событий: вкладки, клиент, добавить/удалить услугу, рассчитать, скопировать, история.
 */
App.bindEvents = function () {
    var self = this;

    // Вкладки
    document.querySelectorAll('.tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tab = this.getAttribute('data-tab');
            document.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
            document.querySelectorAll('.tab-panel').forEach(function (p) { p.classList.remove('active'); });
            this.classList.add('active');
            var panel = document.getElementById('tab-' + tab);
            if (panel) panel.classList.add('active');
            if (tab === 'history') {
                self.loadHistory();
            }
        });
    });

    // Выбор клиента — показать/скрыть подсказку agent_hint
    document.getElementById('client').addEventListener('change', function () {
        var code = this.value;
        var hintEl = document.getElementById('agent-hint');
        hintEl.textContent = '';
        hintEl.classList.remove('visible');
        if (!code || !self.data || !self.data.clients) return;
        var client = self.data.clients.find(function (c) { return c.code === code; });
        if (client && client.agent_hint) {
            hintEl.textContent = client.agent_hint;
            hintEl.classList.add('visible');
        }
    });

    document.getElementById('add-service').addEventListener('click', function () {
        self.addService();
    });

    document.getElementById('btn-copy').addEventListener('click', function () {
        self.copyResult();
    });

    document.getElementById('btn-clear-history').addEventListener('click', function () {
        self.clearHistory();
    });

    document.getElementById('btn-history-back').addEventListener('click', function () {
        document.querySelector('.tab-btn[data-tab="calculator"]').click();
    });

    // Динамический пересчёт при изменении данных
    var calcDebounce = null;
    function scheduleCalculate() {
        if (calcDebounce) clearTimeout(calcDebounce);
        calcDebounce = setTimeout(function () {
            calcDebounce = null;
            if (self.collectInput(true)) {
                self.updateFieldValidity(true);
                self.calculate();
            } else {
                self.updateFieldValidity(false);
            }
        }, 400);
    }
    ['client', 'payment_method', 'supplier_country', 'settlement_currency', 'rate_rs_tls', 'rate_kz'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', scheduleCalculate);
            el.addEventListener('input', scheduleCalculate);
        }
    });
    document.getElementById('services-container').addEventListener('change', scheduleCalculate);
    document.getElementById('services-container').addEventListener('input', scheduleCalculate);
};

/**
 * Добавляет новую строку услуги (тип, сумма, валюта, кнопка удаления).
 */
App.addService = function () {
    this.serviceCount += 1;
    var id = 'service-' + this.serviceCount;
    var container = document.getElementById('services-container');
    var row = document.createElement('div');
    row.className = 'service-row';
    row.setAttribute('data-service-id', id);

    var currencies = this.data && this.data.currencies ? this.data.currencies : ['RUB', 'EUR', 'USD', 'KZT'];
    var typeOptions = (this.data && this.data.service_types ? this.data.service_types : []).map(function (st) {
        return '<option value="' + st.code + '">' + st.name + '</option>';
    }).join('');

    var currencyOptions = currencies.map(function (c) {
        return '<option value="' + c + '">' + c + '</option>';
    }).join('');

    row.innerHTML =
        '<select class="param-input service-type-select" name="service_type_code" required>' +
        '<option value="">— Тип —</option>' + typeOptions + '</select>' +
        '<input type="number" class="param-input service-amount" name="amount" step="0.01" min="0.01" placeholder="0" required>' +
        '<select class="param-input service-currency" name="invoice_currency">' + currencyOptions + '</select>' +
        '<button type="button" class="btn-remove-service" title="Удалить услугу">✕</button>';

    container.appendChild(row);

    var removeBtn = row.querySelector('.btn-remove-service');
    var self = this;
    removeBtn.addEventListener('click', function () {
        self.removeService(row);
    });

    var serviceRows = container.querySelectorAll('.service-row');
    if (serviceRows.length > 1) {
        serviceRows.forEach(function (r) {
            r.querySelector('.btn-remove-service').classList.remove('hidden');
        });
    }
};

/**
 * Удаляет блок услуги. Если остаётся одна — скрывает кнопки удаления.
 * @param {HTMLElement} rowElement
 */
App.removeService = function (rowElement) {
    var container = document.getElementById('services-container');
    rowElement.remove();
    var rows = container.querySelectorAll('.service-row');
    if (rows.length <= 1) {
        rows.forEach(function (r) {
            var btn = r.querySelector('.btn-remove-service');
            if (btn) btn.classList.add('hidden');
        });
    }
};

/**
 * Собирает данные формы в объект input и отправляет POST на api.php?action=calculate.
 * При успехе вызывает renderResult, при ошибке показывает сообщение в блоке результатов.
 */
App.calculate = function () {
    var input = this.collectInput();
    if (!input) return;

    var placeholder = document.getElementById('result-placeholder');
    var content = document.getElementById('result-content');
    var errorEl = document.getElementById('result-error');
    placeholder.style.display = 'none';
    content.style.display = 'none';
    errorEl.style.display = 'none';
    errorEl.textContent = '';

    var self = this;
    fetch('api.php?action=calculate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(input)
    })
        .then(function (r) { return r.json(); })
        .then(function (json) {
            if (json.error) {
                errorEl.textContent = json.error;
                errorEl.style.display = 'block';
                self.updateFieldValidity(false);
                return;
            }
            self.updateFieldValidity(true);
            self.currentResult = json;
            self.renderResult(json);
            content.style.display = 'block';
        })
        .catch(function (err) {
            errorEl.textContent = 'Ошибка сети: ' + err.message;
            errorEl.style.display = 'block';
        });
};

/**
 * Собирает объект входных данных из формы. Валидация на клиенте.
 * @param {boolean} [silent=false] при true не показывать alert при ошибке (для авто-пересчёта)
 * @returns {Object|null} input или null при ошибке валидации
 */
App.collectInput = function (silent) {
    var clientCode = document.getElementById('client').value;
    var paymentMethodCode = document.getElementById('payment_method').value;
    var supplierCountry = document.getElementById('supplier_country').value;
    var settlementCurrency = document.getElementById('settlement_currency').value;
    var rateRsTls = parseFloat(document.getElementById('rate_rs_tls').value, 10) || 0;
    var rateKz = parseFloat(document.getElementById('rate_kz').value, 10) || 0;

    if (!clientCode) {
        if (!silent) alert('Выберите клиента.');
        return null;
    }
    if (!paymentMethodCode) {
        if (!silent) alert('Выберите способ оплаты.');
        return null;
    }
    if (rateRsTls <= 0) {
        if (!silent) alert('Курс РС ТЛС должен быть больше 0.');
        return null;
    }

    var services = [];
    var rows = document.querySelectorAll('#services-container .service-row');
    rows.forEach(function (row) {
        var typeSelect = row.querySelector('.service-type-select');
        var amountInput = row.querySelector('.service-amount');
        var currencySelect = row.querySelector('.service-currency');
        var typeCode = typeSelect ? typeSelect.value : '';
        var amount = parseFloat(amountInput ? amountInput.value : 0, 10) || 0;
        var invoiceCurrency = currencySelect ? currencySelect.value : 'RUB';
        if (typeCode && amount > 0) {
            services.push({
                service_type_code: typeCode,
                amount: amount,
                invoice_currency: invoiceCurrency
            });
        }
    });

    if (services.length === 0) {
        if (!silent) alert('Добавьте хотя бы одну услугу с суммой больше 0.');
        return null;
    }

    return {
        client_code: clientCode,
        payment_method_code: paymentMethodCode,
        supplier_country: supplierCountry,
        settlement_currency: settlementCurrency,
        rate_rs_tls: rateRsTls,
        rate_kz: rateKz,
        services: services
    };
};

/**
 * Отмечает поля красной обводкой (field-invalid), если без них нельзя рассчитать сборы.
 * @param {boolean} [clearOnly=false] если true — только снять обводку со всех полей
 */
App.updateFieldValidity = function (clearOnly) {
    var clientEl = document.getElementById('client');
    var paymentEl = document.getElementById('payment_method');
    var rateEl = document.getElementById('rate_rs_tls');
    var allInputs = document.querySelectorAll('.param-input.field-invalid');
    allInputs.forEach(function (el) { el.classList.remove('field-invalid'); });
    if (clearOnly) return;

    var needInvalid = false;
    if (!clientEl || !clientEl.value) {
        if (clientEl) clientEl.classList.add('field-invalid');
        needInvalid = true;
    }
    if (!paymentEl || !paymentEl.value) {
        if (paymentEl) paymentEl.classList.add('field-invalid');
        needInvalid = true;
    }
    var rateVal = parseFloat(rateEl ? rateEl.value : 0, 10) || 0;
    if (!rateEl || rateVal <= 0) {
        if (rateEl) rateEl.classList.add('field-invalid');
        needInvalid = true;
    }
    var hasValidService = false;
    document.querySelectorAll('#services-container .service-row').forEach(function (row) {
        var typeSelect = row.querySelector('.service-type-select');
        var amountInput = row.querySelector('.service-amount');
        var typeCode = typeSelect ? typeSelect.value : '';
        var amount = parseFloat(amountInput ? amountInput.value : 0, 10) || 0;
        if (typeCode && amount > 0) hasValidService = true;
        if (typeSelect && !typeCode) typeSelect.classList.add('field-invalid');
        else if (typeSelect) typeSelect.classList.remove('field-invalid');
        if (amountInput && amount <= 0) amountInput.classList.add('field-invalid');
        else if (amountInput) amountInput.classList.remove('field-invalid');
    });
    if (!hasValidService) needInvalid = true;
};

/**
 * Отображает результат расчёта в правой колонке: формулы (сначала итоги, потом по услугам), таблица (сначала итоги, потом расшифровка), кнопка «Скопировать».
 * @param {Object} result Ответ API calculate
 */
App.renderResult = function (result) {
    var formulasEl = document.getElementById('formulas-text');
    var tableEl = document.getElementById('result-table');
    var workingCurrency = result.working_currency || 'RUB';
    var formulas = result.formulas || {};
    var totals = result.totals || {};
    var services = result.services || [];
    var multiCurrency = result.multi_currency && result.totals_second;
    var secondCurrency = result.second_currency;

    // Блок формул: заголовок «Итоги по заказу», затем формулы из API, «Итого к оплате», при нескольких — блок «Услуги» (без заголовка «Заказ»)
    var lines = [];
    lines.push('——— Итоги по заказу ———');
    if (formulas.service_fee) {
        formulas.service_fee.split('\n').forEach(function (line) {
            lines.push(line);
        });
    }
    if (formulas.transgran) lines.push(formulas.transgran);
    if (formulas.acquiring) lines.push(formulas.acquiring);
    lines.push('Итого к оплате: ' + App.formatAmount(totals.total_order, workingCurrency));
    if (services.length > 1) {
        lines.push('');
        lines.push('——— Услуги ———');
        services.forEach(function (s) {
            lines.push(s.service_type_name + ': сумма ' + App.formatAmount(s.amount_work, workingCurrency) + ', серв.сбор ' + App.formatAmount(s.service_fee, workingCurrency));
        });
    }
    formulasEl.textContent = lines.join('\n');

    // Таблица: сначала итоги, потом расшифровка по услугам
    tableEl.innerHTML = '';
    if (multiCurrency && secondCurrency) {
        var thead = document.createElement('thead');
        thead.innerHTML = '<tr><th></th><th class="amount-cell">' + workingCurrency + '</th><th class="amount-cell">' + secondCurrency + '</th></tr>';
        tableEl.appendChild(thead);
    }

    function addRow(label, value, value2, rowClass) {
        var tr = document.createElement('tr');
        if (rowClass) tr.className = rowClass;
        var th = document.createElement('th');
        th.textContent = label;
        th.scope = 'row';
        tr.appendChild(th);
        var td = document.createElement('td');
        td.className = 'amount-cell';
        td.textContent = App.formatAmount(value, workingCurrency);
        tr.appendChild(td);
        if (multiCurrency && value2 !== undefined && value2 !== null) {
            var td2 = document.createElement('td');
            td2.className = 'amount-cell second-currency';
            td2.textContent = App.formatAmount(value2, secondCurrency);
            tr.appendChild(td2);
        } else if (multiCurrency) {
            var empty = document.createElement('td');
            empty.className = 'amount-cell second-currency';
            tr.appendChild(empty);
        }
        tableEl.appendChild(tr);
    }

    // При нескольких услугах — заголовок «Заказ»
    if (services.length > 1) {
        var orderSep = document.createElement('tr');
        orderSep.className = 'result-section-sep result-section-order';
        orderSep.innerHTML = '<td colspan="' + (multiCurrency ? 3 : 2) + '" class="result-section-label">Заказ</td>';
        tableEl.appendChild(orderSep);
    }

    // Итоги по заказу
    addRow('Сумма услуг:', totals.total_service_amount, multiCurrency ? result.totals_second.total_service_amount : null);
    addRow('Сервисный сбор:', totals.total_service_fee, multiCurrency ? result.totals_second.total_service_fee : null);
    addRow('Трансгран:', totals.total_transgran, multiCurrency ? result.totals_second.total_transgran : null);
    addRow('Эквайринг:', totals.total_acquiring, multiCurrency ? result.totals_second.total_acquiring : null);

    var trSep = document.createElement('tr');
    trSep.className = 'total-row total-row-sep';
    trSep.innerHTML = '<th></th><td></td>' + (multiCurrency ? '<td></td>' : '');
    tableEl.appendChild(trSep);

    addRow('Итого сбор:', totals.total_fees, multiCurrency ? result.totals_second.total_fees : null);
    addRow('Итого к оплате:', totals.total_order, multiCurrency ? result.totals_second.total_order : null);

    // Расшифровка по услугам (без трансграна по продуктам) — только при нескольких услугах
    if (services.length > 1) {
        var sepDetail = document.createElement('tr');
        sepDetail.className = 'result-section-sep';
        sepDetail.innerHTML = '<td colspan="' + (multiCurrency ? 3 : 2) + '" class="result-section-label">Услуги</td>';
        tableEl.appendChild(sepDetail);

        services.forEach(function (s) {
            addRow(s.service_type_name + ' (сумма):', s.amount_work, null, 'result-detail-row');
            addRow(s.service_type_name + ' (серв.сбор):', s.service_fee, null, 'result-detail-row');
        });
    }

    document.getElementById('result-content').style.display = 'block';
};

/**
 * Форматирует сумму: пробел — разделитель тысяч, запятая — десятичный, 2 знака, символ валюты.
 * @param {number} amount
 * @param {string} currency RUB | EUR | USD | KZT
 * @returns {string}
 */
App.formatAmount = function (amount, currency) {
    var n = Number(amount);
    if (isNaN(n)) return '—';
    var s = n.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    var sym = this.getCurrencySymbol(currency);
    return s + ' ' + sym;
};

/**
 * Возвращает символ валюты для отображения.
 * @param {string} currency
 * @returns {string}
 */
App.getCurrencySymbol = function (currency) {
    var c = (currency || '').toUpperCase();
    if (c === 'RUB') return '₽';
    if (c === 'EUR') return '€';
    if (c === 'USD') return '$';
    if (c === 'KZT') return '₸';
    return c || '';
};

/**
 * Строит текст для копирования в формате блока «Применённые формулы»: итоги по заказу (включая «Итого к оплате»), при нескольких услугах — блок «Услуги».
 * @param {Object} result result_data с formulas, totals, totals_second, working_currency, services
 * @returns {string}
 */
App.buildCopyText = function (result) {
    if (!result || !result.totals) return '';
    var workingCurrency = result.working_currency || 'RUB';
    var formulas = result.formulas || {};
    var totals = result.totals || {};
    var services = result.services || [];
    var lines = [];

    if (services.length > 1) {
        lines.push('——— Заказ ———');
        lines.push('');
    }
    lines.push('——— Итоги по заказу ———');
    if (formulas.service_fee) {
        formulas.service_fee.split('\n').forEach(function (line) {
            lines.push(line);
        });
    }
    if (formulas.transgran) lines.push(formulas.transgran);
    if (formulas.acquiring) lines.push(formulas.acquiring);
    lines.push('Итого к оплате: ' + App.formatAmount(totals.total_order, workingCurrency));
    if (services.length > 1) {
        lines.push('');
        lines.push('——— Услуги ———');
        services.forEach(function (s) {
            lines.push(s.service_type_name + ': сумма ' + App.formatAmount(s.amount_work, workingCurrency) + ', серв.сбор ' + App.formatAmount(s.service_fee, workingCurrency));
        });
    }
    return lines.join('\n');
};

/**
 * Копирует результат в буфер обмена (главная страница или история).
 * @param {Object} result result_data для buildCopyText
 * @param {HTMLElement} [btn] кнопка для показа «Скопировано ✓»
 */
App.copyResult = function (result, btn) {
    var targetResult = result || this.currentResult;
    if (!targetResult) return;
    var text = this.buildCopyText(targetResult);
    if (!text) return;
    var button = btn || document.getElementById('btn-copy');
    var self = this;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () {
            self.showCopySuccess(button);
        }).catch(function () {
            self.fallbackCopyText(text, button);
        });
    } else {
        this.fallbackCopyText(text, button);
    }
};

App.showCopySuccess = function (btn) {
    var prev = btn.textContent;
    btn.textContent = 'Скопировано ✓';
    btn.classList.add('copied');
    var self = this;
    setTimeout(function () {
        btn.textContent = prev || 'Скопировать';
        btn.classList.remove('copied');
    }, 2000);
};

App.fallbackCopyText = function (text, btn) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    try {
        document.execCommand('copy');
        this.showCopySuccess(btn);
    } catch (e) {}
    document.body.removeChild(ta);
};

/**
 * Загружает историю расчётов с сервера и отображает таблицу.
 */
App.loadHistory = function () {
    var self = this;
    fetch('api.php?action=get_history')
        .then(function (r) { return r.json(); })
        .then(function (json) {
            if (json.error) {
                alert('Ошибка: ' + json.error);
                return;
            }
            self.renderHistory(json.history || []);
        })
        .catch(function (err) {
            alert('Ошибка сети: ' + err.message);
        });
};

/**
 * Отрисовывает таблицу истории.
 * @param {Array} history Массив записей { id, created_at, input_data, result_data, formulas }
 */
App.renderHistory = function (history) {
    var tbody = document.getElementById('history-tbody');
    var emptyEl = document.getElementById('history-empty');
    tbody.innerHTML = '';
    if (!history || history.length === 0) {
        emptyEl.style.display = 'block';
        return;
    }
    emptyEl.style.display = 'none';

    var self = this;
    history.forEach(function (item, index) {
        var result = item.result_data || {};
        var totals = result.totals || {};
        var input = item.input_data || {};
        var clientName = (input.client_code && self.data && self.data.clients)
            ? (self.data.clients.find(function (c) { return c.code === input.client_code; }) || {}).name || input.client_code
            : (result.applied_rules && result.applied_rules.client) || '—';

        var tr = document.createElement('tr');
        tr.setAttribute('data-id', item.id);
        tr.innerHTML =
            '<td>' + (index + 1) + '</td>' +
            '<td>' + (item.created_at || '—') + '</td>' +
            '<td>' + clientName + '</td>' +
            '<td class="amount-cell">' + self.formatAmount(totals.total_service_amount, result.working_currency) + '</td>' +
            '<td class="amount-cell">' + self.formatAmount(totals.total_fees, result.working_currency) + '</td>' +
            '<td class="amount-cell">' + self.formatAmount(totals.total_order, result.working_currency) + '</td>' +
            '<td><button type="button" class="btn-history-copy" data-id="' + item.id + '">Копировать</button></td>' +
            '<td><button type="button" class="btn-history-delete" data-id="' + item.id + '">✕</button></td>';
        tbody.appendChild(tr);

        tr.querySelector('.btn-history-copy').addEventListener('click', function () {
            var b = this;
            self.copyResult(item.result_data, b);
        });
        tr.querySelector('.btn-history-delete').addEventListener('click', function () {
            self.deleteHistoryItem(item.id);
        });
    });
};

/**
 * Разворачивает или сворачивает подробности записи истории.
 * @param {number} id
 */
App.toggleHistoryDetail = function (id) {
    var existing = document.querySelector('.history-detail-row[data-detail-id="' + id + '"]');
    if (existing) {
        existing.remove();
        return;
    }
    var row = document.querySelector('#history-tbody tr[data-id="' + id + '"]');
    if (!row) return;

    var self = this;
    fetch('api.php?action=get_history')
        .then(function (r) { return r.json(); })
        .then(function (json) {
            var item = (json.history || []).find(function (h) { return String(h.id) === String(id); });
            if (!item) return;
            var detailRow = document.createElement('tr');
            detailRow.className = 'history-detail-row';
            detailRow.setAttribute('data-detail-id', id);
            var td = document.createElement('td');
            td.colSpan = 8;
            var content = 'Входные данные:\n' + JSON.stringify(item.input_data, null, 2) + '\n\nРезультат:\n' + JSON.stringify(item.result_data, null, 2);
            td.innerHTML = '<div class="history-detail-content">' + content.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</div>';
            detailRow.appendChild(td);
            row.parentNode.insertBefore(detailRow, row.nextSibling);
        });
};

/**
 * Удаляет одну запись из истории.
 * @param {number} id
 */
App.deleteHistoryItem = function (id) {
    var self = this;
    fetch('api.php?action=delete_history_item', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
        .then(function (r) { return r.json(); })
        .then(function (json) {
            if (json.error) {
                alert('Ошибка: ' + json.error);
                return;
            }
            self.loadHistory();
        })
        .catch(function (err) {
            alert('Ошибка сети: ' + err.message);
        });
};

/**
 * Очищает всю историю с подтверждением.
 */
App.clearHistory = function () {
    if (!confirm('Очистить всю историю расчётов?')) return;
    var self = this;
    fetch('api.php?action=clear_history', { method: 'POST' })
        .then(function (r) { return r.json(); })
        .then(function (json) {
            if (json.error) {
                alert('Ошибка: ' + json.error);
                return;
            }
            self.loadHistory();
        })
        .catch(function (err) {
            alert('Ошибка сети: ' + err.message);
        });
};

/**
 * TODO: логика скрытия полей будет добавлена позже.
 * Каждое поле-обёртка имеет data-field (rate_rs_tls, rate_kz и т.д.).
 * Скрытие через element.style.visibility = 'hidden'.
 */
App.updateFieldVisibility = function () {
    // TODO: логика скрытия полей будет добавлена позже
};

// Запуск при готовности DOM
document.addEventListener('DOMContentLoaded', function () {
    App.init();
});
