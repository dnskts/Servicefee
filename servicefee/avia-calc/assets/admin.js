/**
 * Администрирование правил — Калькулятор сборов АВИА
 * Логика страницы admin.php. Все комментарии на русском.
 */

var Admin = {
    data: { serviceTypes: [], paymentMethods: [] },
    clients: [],
    selectedClientId: null,
    selectedClient: null,
    transgranTab: 'standard',
    transgranRules: [],
    serviceFeeRules: [],
    acquiringRules: [],
    specialRules: []
};

/**
 * Инициализация при загрузке страницы: справочники, список клиентов, обработчики.
 */
Admin.init = function () {
    var self = this;
    Promise.all([
        fetch('api.php?action=admin_get_service_types').then(function (r) { return r.json(); }),
        fetch('api.php?action=admin_get_payment_methods').then(function (r) { return r.json(); })
    ]).then(function (results) {
        if (results[0].data) self.data.serviceTypes = results[0].data;
        if (results[1].data) self.data.paymentMethods = results[1].data;
        self.loadClients();
    }).catch(function () {
        self.showNotification('Ошибка загрузки справочников', 'error');
    });

    document.getElementById('btn-add-client').addEventListener('click', function () { self.showCreateClientModal(); });
    document.getElementById('btn-save-client').addEventListener('click', function () { self.saveClientDetails(); });
    document.getElementById('btn-delete-client').addEventListener('click', function () { self.deleteClient(); });
    document.getElementById('btn-create-client').addEventListener('click', function () { self.createClient(); });
    document.getElementById('btn-modal-cancel').addEventListener('click', function () { self.hideModal(); });
    document.getElementById('btn-add-service-fee').addEventListener('click', function () { self.addServiceFeeRule(); });
    document.getElementById('btn-add-transgran').addEventListener('click', function () { self.addTransgranRule(); });
    document.getElementById('btn-add-acquiring').addEventListener('click', function () { self.addAcquiringRule(); });
    document.getElementById('btn-add-special').addEventListener('click', function () { self.addSpecialRule(); });

    document.querySelectorAll('.accordion-header').forEach(function (h) {
        h.addEventListener('click', function () {
            var sectionId = this.getAttribute('data-section');
            if (sectionId) self.toggleAccordion(sectionId);
        });
    });

    document.querySelectorAll('.transgran-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            self.switchTransgranTab(this.getAttribute('data-column'));
        });
    });
};

/**
 * Универсальный запрос к API админки. GET — через query, POST — body JSON.
 */
Admin.fetchApi = function (action, method, data) {
    var url = 'api.php?action=' + encodeURIComponent(action);
    var opts = { method: method || 'GET' };
    if (method === 'POST' && data !== undefined) {
        opts.headers = { 'Content-Type': 'application/json' };
        opts.body = JSON.stringify(data);
    } else if (method === 'GET' && data && typeof data === 'object') {
        var params = new URLSearchParams(data);
        url += '&' + params.toString();
    }
    return fetch(url, opts).then(function (r) { return r.json(); });
};

/**
 * Показывает уведомление (успех или ошибка) на 3 секунды.
 */
Admin.showNotification = function (message, type) {
    type = type || 'success';
    var el = document.createElement('div');
    el.className = 'notification notification-' + type;
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(function () {
        el.classList.add('fade-out');
        setTimeout(function () { el.remove(); }, 300);
    }, 3000);
};

/**
 * Загружает список клиентов и отрисовывает его в левой панели.
 */
Admin.loadClients = function () {
    var self = this;
    this.fetchApi('admin_get_clients', 'GET').then(function (res) {
        if (res.success && res.data) {
            self.clients = res.data;
            self.renderClientList();
        } else {
            self.showNotification(res.error || 'Ошибка загрузки клиентов', 'error');
        }
    }).catch(function () {
        self.showNotification('Ошибка сети', 'error');
    });
};

/**
 * Отрисовывает список клиентов в #client-list.
 */
Admin.renderClientList = function () {
    var container = document.getElementById('client-list');
    container.innerHTML = '';
    var self = this;
    this.clients.forEach(function (c) {
        var div = document.createElement('div');
        div.className = 'client-list-item' + (c.is_active == 0 ? ' inactive' : '');
        if (Number(c.id) === Number(self.selectedClientId)) div.classList.add('active');
        div.setAttribute('data-id', c.id);
        div.innerHTML = '<div class="client-name">' + (c.name || '') + '</div><div class="client-code">' + (c.code || '') + '</div>';
        div.addEventListener('click', function () { self.selectClient(c.id); });
        container.appendChild(div);
    });
};

/**
 * Выбирает клиента: подсветка, загрузка данных в правую панель.
 */
Admin.selectClient = function (id) {
    this.selectedClientId = id;
    this.selectedClient = this.clients.find(function (c) { return Number(c.id) === Number(id); }) || null;
    this.renderClientList();
    document.getElementById('no-client-selected').style.display = this.selectedClient ? 'none' : 'block';
    document.getElementById('client-content').style.display = this.selectedClient ? 'block' : 'none';
    if (this.selectedClient) {
        this.renderClientDetails();
        this.loadServiceFeeRules();
        this.loadTransgranRules();
        this.loadAcquiringRules();
        this.loadSpecialRules();
    }
};

/**
 * Заполняет форму «Основные данные» выбранного клиента.
 */
Admin.renderClientDetails = function () {
    var c = this.selectedClient;
    if (!c) return;
    document.getElementById('client-code').value = c.code || '';
    document.getElementById('client-name').value = c.name || '';
    document.getElementById('client-country').value = c.country || 'RU';
    document.getElementById('client-result-currency').value = c.result_currency || 'RUB';
    document.getElementById('client-use-max-rule').checked = c.use_max_rule == 1;
    document.getElementById('client-agent-hint').value = c.agent_hint || '';
    document.getElementById('client-sort-order').value = c.sort_order != null ? c.sort_order : 0;
    document.getElementById('client-is-active').checked = c.is_active != 0;
};

/**
 * Сохраняет изменения основных данных клиента (admin_update_client).
 */
Admin.saveClientDetails = function () {
    var self = this;
    if (!this.selectedClientId) return;
    var payload = {
        id: this.selectedClientId,
        code: document.getElementById('client-code').value.trim(),
        name: document.getElementById('client-name').value.trim(),
        country: document.getElementById('client-country').value,
        result_currency: document.getElementById('client-result-currency').value,
        use_max_rule: document.getElementById('client-use-max-rule').checked ? 1 : 0,
        agent_hint: document.getElementById('client-agent-hint').value.trim() || null,
        sort_order: parseInt(document.getElementById('client-sort-order').value, 10) || 0,
        is_active: document.getElementById('client-is-active').checked ? 1 : 0
    };
    this.fetchApi('admin_update_client', 'POST', payload).then(function (res) {
        if (res.success) {
            self.showNotification('Сохранено');
            self.loadClients();
        } else {
            self.showNotification(res.error || 'Ошибка', 'error');
        }
    }).catch(function () { self.showNotification('Ошибка сети', 'error'); });
};

/**
 * Удаляет клиента с подтверждением.
 */
Admin.deleteClient = function () {
    var self = this;
    if (!this.selectedClient) return;
    if (!confirm('Удалить клиента «' + this.selectedClient.name + '»? Все связанные правила будут удалены.')) return;
    this.fetchApi('admin_delete_client', 'POST', { id: this.selectedClientId }).then(function (res) {
        if (res.success) {
            self.selectedClientId = null;
            self.selectedClient = null;
            document.getElementById('no-client-selected').style.display = 'block';
            document.getElementById('client-content').style.display = 'none';
            self.loadClients();
            self.showNotification('Клиент удалён');
        } else {
            self.showNotification(res.error || 'Ошибка', 'error');
        }
    }).catch(function () { self.showNotification('Ошибка сети', 'error'); });
};

/**
 * Загружает правила сервисного сбора и отрисовывает таблицу.
 */
Admin.loadServiceFeeRules = function () {
    var self = this;
    if (!this.selectedClientId) return;
    this.fetchApi('admin_get_service_fee_rules', 'GET', { client_id: this.selectedClientId }).then(function (res) {
        if (res.success && res.data) {
            self.serviceFeeRules = res.data;
            self.renderServiceFeeTable();
        }
    });
};

function dash(val) { return val !== null && val !== undefined && val !== '' ? val : '—'; }

/**
 * Отрисовывает таблицу правил сервисного сбора.
 */
Admin.renderServiceFeeTable = function () {
    var tbody = document.getElementById('service-fee-tbody');
    tbody.innerHTML = '';
    var self = this;
    (this.serviceFeeRules || []).forEach(function (r) {
        var tr = document.createElement('tr');
        tr.setAttribute('data-rule-id', r.id);
        tr.innerHTML =
            '<td>' + (r.service_type_name || r.service_type_code || '—') + '</td>' +
            '<td>' + (r.fee_type || '—') + '</td>' +
            '<td>' + dash(r.percent_value) + '</td>' +
            '<td>' + dash(r.fixed_value) + '</td>' +
            '<td>' + dash(r.min_value) + '</td>' +
            '<td>' + dash(r.max_value) + '</td>' +
            '<td>' + (r.currency || '—') + '</td>' +
            '<td><button type="button" class="btn-edit btn-edit-sf" data-id="' + r.id + '">Редактировать</button> <button type="button" class="btn-delete btn-delete-sf" data-id="' + r.id + '">Удалить</button></td>';
        tbody.appendChild(tr);
        tr.querySelector('.btn-edit-sf').addEventListener('click', function () { self.editServiceFeeRule(r.id); });
        tr.querySelector('.btn-delete-sf').addEventListener('click', function () { self.deleteServiceFeeRule(r.id); });
    });
};

/**
 * Включает режим редактирования строки правила сервисного сбора (inline).
 */
Admin.editServiceFeeRule = function (ruleId) {
    var self = this;
    var tr = document.querySelector('#service-fee-tbody tr[data-rule-id="' + ruleId + '"]');
    if (!tr) return;
    var rule = this.serviceFeeRules.find(function (r) { return Number(r.id) === Number(ruleId); });
    var typeOpts = (this.data.serviceTypes || []).map(function (st) {
        return '<option value="' + st.id + '"' + (rule && Number(rule.service_type_id) === Number(st.id) ? ' selected' : '') + '>' + st.name + '</option>';
    }).join('');
    tr.classList.add('editing');
    tr.innerHTML =
        '<td><select class="sf-service-type">' + typeOpts + '</select></td>' +
        '<td><select class="sf-fee-type"><option value="percent"' + (rule && rule.fee_type === 'percent' ? ' selected' : '') + '>percent</option><option value="fixed"' + (rule && rule.fee_type === 'fixed' ? ' selected' : '') + '>fixed</option></select></td>' +
        '<td><input type="number" step="0.01" class="sf-percent" value="' + (rule && rule.percent_value != null ? rule.percent_value : '') + '"></td>' +
        '<td><input type="number" step="0.01" class="sf-fixed" value="' + (rule && rule.fixed_value != null ? rule.fixed_value : '') + '"></td>' +
        '<td><input type="number" step="0.01" class="sf-min" value="' + (rule && rule.min_value != null ? rule.min_value : '') + '"></td>' +
        '<td><input type="number" step="0.01" class="sf-max" value="' + (rule && rule.max_value != null ? rule.max_value : '') + '"></td>' +
        '<td><select class="sf-currency"><option value="RUB"' + (rule && rule.currency === 'RUB' ? ' selected' : '') + '>RUB</option><option value="EUR"' + (rule && rule.currency === 'EUR' ? ' selected' : '') + '>EUR</option></select></td>' +
        '<td><button type="button" class="btn-save btn-save-sf">Сохранить</button> <button type="button" class="btn-cancel btn-cancel-sf">Отмена</button></td>';
    tr.setAttribute('data-rule-id', ruleId);
    tr.querySelector('.btn-save-sf').addEventListener('click', function () { self.saveServiceFeeRule(ruleId); });
    tr.querySelector('.btn-cancel-sf').addEventListener('click', function () { self.loadServiceFeeRules(); });
};

/**
 * Отправляет сохранение правила сервисного сбора (создание или обновление).
 */
Admin.saveServiceFeeRule = function (ruleId) {
    var self = this;
    var tr = document.querySelector('#service-fee-tbody tr.editing');
    if (!tr) return;
    var payload = {
        client_id: this.selectedClientId,
        service_type_id: tr.querySelector('.sf-service-type').value,
        fee_type: tr.querySelector('.sf-fee-type').value,
        percent_value: tr.querySelector('.sf-percent').value || null,
        fixed_value: tr.querySelector('.sf-fixed').value || null,
        min_value: tr.querySelector('.sf-min').value || null,
        max_value: tr.querySelector('.sf-max').value || null,
        currency: tr.querySelector('.sf-currency').value
    };
    if (ruleId) {
        payload.id = ruleId;
        this.fetchApi('admin_update_service_fee_rule', 'POST', payload).then(function (res) {
            if (res.success) { self.loadServiceFeeRules(); self.showNotification('Сохранено'); }
            else self.showNotification(res.error, 'error');
        });
    } else {
        this.fetchApi('admin_create_service_fee_rule', 'POST', payload).then(function (res) {
            if (res.success) { self.loadServiceFeeRules(); self.showNotification('Правило добавлено'); }
            else self.showNotification(res.error, 'error');
        });
    }
};

Admin.deleteServiceFeeRule = function (ruleId) {
    var self = this;
    if (!confirm('Удалить правило сервисного сбора?')) return;
    this.fetchApi('admin_delete_service_fee_rule', 'POST', { id: ruleId }).then(function (res) {
        if (res.success) { self.loadServiceFeeRules(); self.showNotification('Удалено'); }
        else self.showNotification(res.error, 'error');
    });
};

/**
 * Добавляет пустую строку для нового правила сервисного сбора.
 */
Admin.addServiceFeeRule = function () {
    var tbody = document.getElementById('service-fee-tbody');
    var tr = document.createElement('tr');
    tr.classList.add('editing');
    tr.setAttribute('data-rule-id', 'new');
    var self = this;
    var typeOpts = (this.data.serviceTypes || []).map(function (st) {
        return '<option value="' + st.id + '">' + st.name + '</option>';
    }).join('');
    tr.innerHTML =
        '<td><select class="sf-service-type"><option value="">—</option>' + typeOpts + '</select></td>' +
        '<td><select class="sf-fee-type"><option value="percent">percent</option><option value="fixed">fixed</option></select></td>' +
        '<td><input type="number" step="0.01" class="sf-percent" value=""></td>' +
        '<td><input type="number" step="0.01" class="sf-fixed" value=""></td>' +
        '<td><input type="number" step="0.01" class="sf-min" value=""></td>' +
        '<td><input type="number" step="0.01" class="sf-max" value=""></td>' +
        '<td><select class="sf-currency"><option value="RUB">RUB</option><option value="EUR">EUR</option></select></td>' +
        '<td><button type="button" class="btn-save btn-save-sf">Сохранить</button> <button type="button" class="btn-cancel btn-cancel-sf">Отмена</button></td>';
    tbody.appendChild(tr);
    tr.querySelector('.btn-save-sf').addEventListener('click', function () { self.saveServiceFeeRule(null); });
    tr.querySelector('.btn-cancel-sf').addEventListener('click', function () { self.loadServiceFeeRules(); });
};

/**
 * Загружает правила трансграна для выбранного клиента.
 */
Admin.loadTransgranRules = function () {
    var self = this;
    if (!this.selectedClientId) return;
    this.fetchApi('admin_get_transgran_rules', 'GET', { client_id: this.selectedClientId }).then(function (res) {
        if (res.success && res.data) {
            self.transgranRules = res.data;
            self.renderTransgranTable();
        }
    });
};

Admin.renderTransgranTable = function () {
    var tbody = document.getElementById('transgran-tbody');
    tbody.innerHTML = '';
    var column = this.transgranTab;
    var rules = (this.transgranRules || []).filter(function (r) { return r.transgran_column === column; });
    var self = this;
    rules.forEach(function (r) {
        var tr = document.createElement('tr');
        tr.setAttribute('data-rule-id', r.id);
        tr.innerHTML =
            '<td>' + (r.service_type_name || r.service_type_code || '—') + '</td>' +
            '<td>' + dash(r.percent_value) + '</td>' +
            '<td>' + dash(r.min_value_low) + '</td>' +
            '<td>' + dash(r.min_value_high) + '</td>' +
            '<td>' + dash(r.threshold) + '</td>' +
            '<td>' + dash(r.min_currency) + '</td>' +
            '<td>' + (r.is_applicable == 1 ? 'Да' : 'Нет') + '</td>' +
            '<td>' + (r.include_service_fee == 1 ? 'Да' : 'Нет') + '</td>' +
            '<td>—</td>' +
            '<td><button type="button" class="btn-edit">Ред.</button> <button type="button" class="btn-delete btn-del-transgran">Удал.</button></td>';
        tbody.appendChild(tr);
        tr.querySelector('.btn-del-transgran').addEventListener('click', function () { self.deleteTransgranRule(r.id); });
    });
    document.querySelectorAll('.transgran-tab').forEach(function (t) {
        t.classList.toggle('active', t.getAttribute('data-column') === column);
    });
};

Admin.switchTransgranTab = function (column) {
    this.transgranTab = column;
    this.renderTransgranTable();
};

Admin.deleteTransgranRule = function (ruleId) {
    var self = this;
    if (!confirm('Удалить правило трансграна?')) return;
    this.fetchApi('admin_delete_transgran_rule', 'POST', { id: ruleId }).then(function (res) {
        if (res.success) { self.loadTransgranRules(); self.showNotification('Удалено'); }
        else self.showNotification(res.error, 'error');
    });
};

Admin.addTransgranRule = function () {
    var self = this;
    if (!this.selectedClientId) return;
    var typeOpts = (this.data.serviceTypes || []).map(function (st) {
        return '<option value="' + st.id + '">' + st.name + '</option>';
    }).join('');
    var tr = document.createElement('tr');
    tr.classList.add('editing');
    tr.setAttribute('data-rule-id', 'new');
    tr.innerHTML =
        '<td><select class="tg-service-type"><option value="">—</option>' + typeOpts + '</select></td>' +
        '<td><input type="number" step="0.01" class="tg-percent" value="0"></td>' +
        '<td><input type="number" step="0.01" class="tg-min-low" placeholder="—"></td>' +
        '<td><input type="number" step="0.01" class="tg-min-high" placeholder="—"></td>' +
        '<td><input type="number" step="0.01" class="tg-threshold" value="1000"></td>' +
        '<td><select class="tg-min-currency"><option value="">—</option><option value="USD_EUR">USD_EUR</option></select></td>' +
        '<td><input type="checkbox" class="tg-applicable" checked></td>' +
        '<td><input type="checkbox" class="tg-include-fee" checked></td>' +
        '<td>—</td>' +
        '<td><button type="button" class="btn-save btn-save-tg">Сохранить</button> <button type="button" class="btn-cancel btn-cancel-tg">Отмена</button></td>';
    document.getElementById('transgran-tbody').appendChild(tr);
    var payload = function () {
        return {
            client_id: self.selectedClientId,
            service_type_id: tr.querySelector('.tg-service-type').value,
            transgran_column: self.transgranTab,
            percent_value: parseFloat(tr.querySelector('.tg-percent').value, 10) || 0,
            min_value_low: tr.querySelector('.tg-min-low').value || null,
            min_value_high: tr.querySelector('.tg-min-high').value || null,
            threshold: parseFloat(tr.querySelector('.tg-threshold').value, 10) || 1000,
            min_currency: tr.querySelector('.tg-min-currency').value || null,
            is_applicable: tr.querySelector('.tg-applicable').checked ? 1 : 0,
            include_service_fee: tr.querySelector('.tg-include-fee').checked ? 1 : 0
        };
    };
    tr.querySelector('.btn-save-tg').addEventListener('click', function () {
        self.fetchApi('admin_create_transgran_rule', 'POST', payload()).then(function (res) {
            if (res.success) { self.loadTransgranRules(); self.showNotification('Правило добавлено'); }
            else self.showNotification(res.error, 'error');
        });
    });
    tr.querySelector('.btn-cancel-tg').addEventListener('click', function () { self.loadTransgranRules(); });
};

/**
 * Загружает и отрисовывает правила эквайринга.
 */
Admin.loadAcquiringRules = function () {
    var self = this;
    if (!this.selectedClientId) return;
    this.fetchApi('admin_get_acquiring_rules', 'GET', { client_id: this.selectedClientId }).then(function (res) {
        if (res.success && res.data) {
            self.acquiringRules = res.data;
            self.renderAcquiringTable();
        }
    });
};

Admin.getServiceTypeName = function (code) {
    var st = (this.data.serviceTypes || []).find(function (s) { return s.code === code; });
    return st ? st.name : code;
};

Admin.renderAcquiringTable = function () {
    var tbody = document.getElementById('acquiring-tbody');
    tbody.innerHTML = '';
    var self = this;
    (this.acquiringRules || []).forEach(function (r) {
        var tr = document.createElement('tr');
        tr.setAttribute('data-rule-id', r.id);
        tr.innerHTML =
            '<td>' + (r.payment_method_name || r.payment_method_code || '—') + '</td>' +
            '<td>' + dash(r.percent_value) + '</td>' +
            '<td>' + (r.exclude_service_type ? self.getServiceTypeName(r.exclude_service_type) + ' (' + r.exclude_service_type + ')' : '—') + '</td>' +
            '<td><button type="button" class="btn-edit">Ред.</button> <button type="button" class="btn-delete btn-del-acq" data-id="' + r.id + '">Удал.</button></td>';
        tbody.appendChild(tr);
        tr.querySelector('.btn-del-acq').addEventListener('click', function () { self.deleteAcquiringRule(r.id); });
    });
};

Admin.deleteAcquiringRule = function (ruleId) {
    var self = this;
    if (!confirm('Удалить правило эквайринга?')) return;
    this.fetchApi('admin_delete_acquiring_rule', 'POST', { id: ruleId }).then(function (res) {
        if (res.success) { self.loadAcquiringRules(); self.showNotification('Удалено'); }
        else self.showNotification(res.error, 'error');
    });
};

Admin.addAcquiringRule = function () {
    var self = this;
    if (!this.selectedClientId) return;
    var pmOpts = (this.data.paymentMethods || []).map(function (p) {
        return '<option value="' + p.id + '">' + p.name + '</option>';
    }).join('');
    var tr = document.createElement('tr');
    tr.classList.add('editing');
    tr.innerHTML =
        '<td><select class="acq-pm"><option value="">—</option>' + pmOpts + '</select></td>' +
        '<td><input type="number" step="0.01" class="acq-percent" value="0"></td>' +
        '<td><select class="acq-exclude"><option value="">— нет —</option><option value="issue">Выписка</option><option value="emd">EMD</option><option value="refund">Возврат</option><option value="exchange">Обмен</option></select></td>' +
        '<td><button type="button" class="btn-save btn-save-acq">Сохранить</button> <button type="button" class="btn-cancel btn-cancel-acq">Отмена</button></td>';
    document.getElementById('acquiring-tbody').appendChild(tr);
    tr.querySelector('.btn-save-acq').addEventListener('click', function () {
        var payload = {
            client_id: self.selectedClientId,
            payment_method_id: tr.querySelector('.acq-pm').value,
            percent_value: parseFloat(tr.querySelector('.acq-percent').value, 10) || 0,
            exclude_service_type: tr.querySelector('.acq-exclude').value || null
        };
        self.fetchApi('admin_create_acquiring_rule', 'POST', payload).then(function (res) {
            if (res.success) { self.loadAcquiringRules(); self.showNotification('Правило добавлено'); }
            else self.showNotification(res.error, 'error');
        });
    });
    tr.querySelector('.btn-cancel-acq').addEventListener('click', function () { self.loadAcquiringRules(); });
};

/**
 * Загружает и отрисовывает спецправила.
 */
Admin.loadSpecialRules = function () {
    var self = this;
    if (!this.selectedClientId) return;
    this.fetchApi('admin_get_special_rules', 'GET', { client_id: this.selectedClientId }).then(function (res) {
        if (res.success && res.data) {
            self.specialRules = res.data;
            self.renderSpecialRulesTable();
        }
    });
};

Admin.formatRuleTypeLabel = function (code) {
    if (code === 'no_transgran_standard') return 'Не берём трансгран (standard) при указанных типах';
    return code || '—';
};

Admin.formatParams = function (ruleType, paramsStr) {
    if (!paramsStr) return '—';
    try {
        var p = typeof paramsStr === 'string' ? JSON.parse(paramsStr) : paramsStr;
        if (ruleType === 'no_transgran_standard' && p) {
            return 'Отменяется при карте КЗ: ' + (p.override_by_kz_card ? 'Да' : 'Нет');
        }
        return JSON.stringify(p);
    } catch (e) {
        return paramsStr;
    }
};

Admin.renderSpecialRulesTable = function () {
    var tbody = document.getElementById('special-rules-tbody');
    tbody.innerHTML = '';
    var self = this;
    (this.specialRules || []).forEach(function (r) {
        var applicable = [];
        try {
            var arr = typeof r.applicable_service_types === 'string' ? JSON.parse(r.applicable_service_types) : r.applicable_service_types;
            if (Array.isArray(arr)) applicable = arr.map(function (code) { return self.getServiceTypeName(code) || code; });
        } catch (e) {}
        var tr = document.createElement('tr');
        tr.setAttribute('data-rule-id', r.id);
        tr.innerHTML =
            '<td>' + self.formatRuleTypeLabel(r.rule_type) + '</td>' +
            '<td>' + (applicable.length ? applicable.join(', ') : '—') + '</td>' +
            '<td>' + self.formatParams(r.rule_type, r.params) + '</td>' +
            '<td><button type="button" class="btn-edit">Ред.</button> <button type="button" class="btn-delete btn-del-spec" data-id="' + r.id + '">Удал.</button></td>';
        tbody.appendChild(tr);
        tr.querySelector('.btn-del-spec').addEventListener('click', function () { self.deleteSpecialRule(r.id); });
    });
};

Admin.deleteSpecialRule = function (ruleId) {
    var self = this;
    if (!confirm('Удалить спецправило?')) return;
    this.fetchApi('admin_delete_special_rule', 'POST', { id: ruleId }).then(function (res) {
        if (res.success) { self.loadSpecialRules(); self.showNotification('Удалено'); }
        else self.showNotification(res.error, 'error');
    });
};

Admin.addSpecialRule = function () {
    var self = this;
    if (!this.selectedClientId) return;
    var types = this.data.serviceTypes || [];
    var checks = types.map(function (st) {
        return '<label><input type="checkbox" class="spec-type" value="' + st.code + '"> ' + st.name + '</label>';
    }).join(' ');
    var tr = document.createElement('tr');
    tr.classList.add('editing');
    tr.innerHTML =
        '<td><select class="spec-rule-type"><option value="no_transgran_standard">Не берём трансгран (standard)</option></select></td>' +
        '<td><div class="spec-types">' + checks + '</div></td>' +
        '<td><label><input type="checkbox" class="spec-override-kz" checked> Отменяется при оплате картой КЗ</label></td>' +
        '<td><button type="button" class="btn-save btn-save-spec">Сохранить</button> <button type="button" class="btn-cancel btn-cancel-spec">Отмена</button></td>';
    document.getElementById('special-rules-tbody').appendChild(tr);
    tr.querySelector('.btn-save-spec').addEventListener('click', function () {
        var applicable = [];
        tr.querySelectorAll('.spec-type:checked').forEach(function (cb) { applicable.push(cb.value); });
        var payload = {
            client_id: self.selectedClientId,
            rule_type: tr.querySelector('.spec-rule-type').value,
            applicable_service_types: applicable,
            params: { override_by_kz_card: tr.querySelector('.spec-override-kz').checked }
        };
        self.fetchApi('admin_create_special_rule', 'POST', payload).then(function (res) {
            if (res.success) { self.loadSpecialRules(); self.showNotification('Правило добавлено'); }
            else self.showNotification(res.error, 'error');
        });
    });
    tr.querySelector('.btn-cancel-spec').addEventListener('click', function () { self.loadSpecialRules(); });
};

/**
 * Показывает модальное окно создания клиента.
 */
Admin.showCreateClientModal = function () {
    document.getElementById('create-client-modal').style.display = 'flex';
    document.getElementById('new-client-code').value = '';
    document.getElementById('new-client-name').value = '';
    document.getElementById('new-client-country').value = 'RU';
    document.getElementById('new-client-result-currency').value = 'RUB';
    document.getElementById('new-client-use-max-rule').checked = false;
    document.getElementById('new-client-agent-hint').value = '';
    document.getElementById('new-client-sort-order').value = '0';
    document.getElementById('modal-error').style.display = 'none';
};

Admin.hideModal = function () {
    document.getElementById('create-client-modal').style.display = 'none';
};

/**
 * Создаёт нового клиента (admin_create_client).
 */
Admin.createClient = function () {
    var self = this;
    var payload = {
        code: document.getElementById('new-client-code').value.trim(),
        name: document.getElementById('new-client-name').value.trim(),
        country: document.getElementById('new-client-country').value,
        result_currency: document.getElementById('new-client-result-currency').value,
        use_max_rule: document.getElementById('new-client-use-max-rule').checked ? 1 : 0,
        agent_hint: document.getElementById('new-client-agent-hint').value.trim() || null,
        sort_order: parseInt(document.getElementById('new-client-sort-order').value, 10) || 0
    };
    document.getElementById('modal-error').style.display = 'none';
    this.fetchApi('admin_create_client', 'POST', payload).then(function (res) {
        if (res.success && res.data && res.data.id) {
            self.hideModal();
            self.loadClients();
            setTimeout(function () { self.selectClient(res.data.id); }, 300);
            self.showNotification('Клиент создан');
        } else {
            document.getElementById('modal-error').textContent = res.error || 'Ошибка';
            document.getElementById('modal-error').style.display = 'block';
        }
    }).catch(function () {
        document.getElementById('modal-error').textContent = 'Ошибка сети';
        document.getElementById('modal-error').style.display = 'block';
    });
};

/**
 * Переключает секцию аккордеона (открыть/закрыть).
 */
Admin.toggleAccordion = function (sectionId) {
    var body = document.getElementById(sectionId);
    var header = document.querySelector('.accordion-header[data-section="' + sectionId + '"]');
    if (!body || !header) return;
    var isOpen = body.classList.contains('open');
    body.classList.toggle('open', !isOpen);
    header.classList.toggle('open', !isOpen);
    var arrow = header.querySelector('.accordion-arrow');
    if (arrow) arrow.textContent = !isOpen ? '▼' : '▶';
};

document.addEventListener('DOMContentLoaded', function () {
    Admin.init();
});
