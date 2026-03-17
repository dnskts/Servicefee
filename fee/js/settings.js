// js/settings.js
// -----------------------------------------------------------
// Страница настроек:
//
// 1) JSON экспорт/импорт (сворачиваемый блок)
// 2) Сущности (клиенты, типы услуг, типы оплаты и т.п.)
// 3) Правила:
//    - Условия: набор условий по сущностям с И / ИЛИ / НЕ.
//    - Действия: что делаем с сервисным сбором, трансграничным,
//                эквайрингом (процент, фикс, мин/макс + валюты).
//
// Формат actions в JSON:
//
// rule.actions = {
//   serviceFee: {
//      enabled: true/false,
//      percent: number|null,
//      fixedAmount: number|null,
//      minAmount: number|null,
//      maxAmount: number|null,
//      currency: "RUB"|"SERVICE"|"USD"|"EUR"
//   },
//   crossBorder: {
//      enabled: true/false,
//      percent: number|null,
//      fixedAmount: number|null,
//      minAmount: number|null,
//      maxAmount: number|null,
//      currency: "RUB"|"SERVICE"|"USD"|"EUR"
//   },
//   acquiring: {
//      enabled: true/false,
//      percent: number|null
//   }
// }
//
// Старый формат (fixedRub, minRub, maxRub, mode и т.д.) автоматически
// приводится к новому в функции normalizeRuleActions.
// -----------------------------------------------------------

document.addEventListener("DOMContentLoaded", function () {
    let config = ConfigStorage.loadConfig();

    // ---------- JSON-блок ----------
    const jsonTextarea = document.getElementById("configJsonTextarea");
    const saveJsonBtn = document.getElementById("saveJsonBtn");
    const resetDefaultBtn = document.getElementById("resetDefaultBtn");
    const jsonMessage = document.getElementById("jsonMessage");

    const jsonToggle = document.getElementById("jsonToggle");
    const jsonBody = document.getElementById("jsonBody");
    const jsonArrow = document.getElementById("jsonArrow");
    const jsonCard = jsonToggle ? jsonToggle.closest(".card") : null;

    // ---------- Сущности ----------
    const entitiesTableBody = document.querySelector("#entitiesTable tbody");
    const newEntityIdInput = document.getElementById("newEntityId");
    const newEntityNameInput = document.getElementById("newEntityName");
    const newEntityTypeSelect = document.getElementById("newEntityType");
    const newEntityValuesTextarea = document.getElementById("newEntityValues");
    const newEntityMultiCheckbox = document.getElementById("newEntityMulti");
    const newEntityShowOnMainCheckbox = document.getElementById("newEntityShowOnMain");
    const addEntityBtn = document.getElementById("addEntityBtn");
    const cancelEntityEditBtn = document.getElementById("cancelEntityEditBtn");
    const entitiesMessage = document.getElementById("entitiesMessage");

    const entitiesToggle = document.getElementById("entitiesToggle");
    const entitiesBody = document.getElementById("entitiesBody");
    const entitiesArrow = document.getElementById("entitiesArrow");
    const entitiesCard = entitiesToggle ? entitiesToggle.closest(".card") : null;
    const entityForm = document.getElementById("entityForm");
    const entityFormMode = document.getElementById("entityFormMode");

    let editingEntityIndex = null; // индекс сущности, которую редактируем

    // ---------- Правила: общие поля ----------
    const ruleNameInput = document.getElementById("ruleNameInput");
    const ruleLogicSelect = document.getElementById("ruleLogicSelect");

    // ---------- Правила: условия ----------
    const condEntitySelect = document.getElementById("condEntitySelect");
    const condLinkSelect = document.getElementById("condLinkSelect");
    const condNotCheckbox = document.getElementById("condNotCheckbox");
    const condValueContainer = document.getElementById("condValueContainer");
    const addConditionBtn = document.getElementById("addConditionBtn");

    const ruleConditionsList = document.getElementById("ruleConditionsList");
    const ruleConditionsUl = document.getElementById("ruleConditionsUl");

    // ---------- Правила: действия (сборы) ----------
    // Сервисный сбор
    const actServiceEnabled = document.getElementById("actServiceEnabled");
    const actServicePercent = document.getElementById("actServicePercent");
    const actServiceFixed = document.getElementById("actServiceFixed");
    const actServiceMin = document.getElementById("actServiceMin");
    const actServiceMax = document.getElementById("actServiceMax");
    const actServiceCurrency = document.getElementById("actServiceCurrency");

    // Трансграничный
    const actCrossEnabled = document.getElementById("actCrossEnabled");
    const actCrossPercent = document.getElementById("actCrossPercent");
    const actCrossFixed = document.getElementById("actCrossFixed");
    const actCrossMin = document.getElementById("actCrossMin");
    const actCrossMax = document.getElementById("actCrossMax");
    const actCrossCurrency = document.getElementById("actCrossCurrency");

    // Эквайринг
    const actAcquiringEnabled = document.getElementById("actAcquiringEnabled");
    const actAcquiringPercent = document.getElementById("actAcquiringPercent");

    // Кнопки для правил
    const saveRuleBtn = document.getElementById("saveRuleBtn");
    const clearRuleFormBtn = document.getElementById("clearRuleFormBtn");
    const ruleMessage = document.getElementById("ruleMessage");

    // ---------- Таблица правил ----------
    const rulesTableBody = document.querySelector("#rulesTable tbody");

    // Текущее редактируемое правило
    let currentRuleConditions = [];  // массив условий
    let editingRuleIndex = null;     // индекс правила, редактируемого в конструкторе

    // ---------- Модальное окно ПРОСМОТРА ----------
    const ruleModal = document.getElementById("ruleModal");
    const ruleModalCloseBtn = document.getElementById("ruleModalCloseBtn");
    const ruleModalText = document.getElementById("ruleModalText");

    let modalEditingRuleIndex = null; // индекс просматриваемого правила (для информации)

    // =========================================================
    // Вспомогательные функции
    // =========================================================

    // Преобразовать значение поля в число или null
    function parseNullableFloat(inputElement) {
        const raw = inputElement.value.trim();
        if (!raw) return null;
        const num = parseFloat(raw);
        return isNaN(num) ? null : num;
    }

    function getEntityById(id) {
        return (config.entities || []).find(e => e.id === id) || null;
    }

    function isEntityUsedInRules(entityId) {
        return (config.rules || []).some(function (rule) {
            return (rule.conditions || []).some(function (cond) {
                return cond.entityId === entityId;
            });
        });
    }

    function openEntitiesSection() {
        if (!entitiesBody || !entitiesArrow) return;
        entitiesBody.style.display = "block";
        entitiesArrow.classList.add("open");
        if (entitiesCard) entitiesCard.classList.remove("collapsed");
    }

    // Нормализуем структуру действий (поддержка старого формата)
    function normalizeRuleActions(rule) {
        if (!rule.actions) {
            // Если rule.calculation остался от старой версии — можно его
            // при желании сюда тоже подтянуть. Сейчас считаем, что новые
            // правила будут писать actions.
            rule.actions = {};
        }
        const a = rule.actions;

        // ---------- Сервисный сбор ----------
        if (!a.serviceFee) a.serviceFee = {};
        const s = a.serviceFee;

        // Старые поля вида minRub/maxRub/fixedRub → новые minAmount/maxAmount/fixedAmount
        if (s.minAmount == null && s.minRub != null) s.minAmount = s.minRub;
        if (s.maxAmount == null && s.maxRub != null) s.maxAmount = s.maxRub;
        if (s.fixedAmount == null && s.fixedRub != null) s.fixedAmount = s.fixedRub;

        if (s.currency == null) s.currency = "RUB";
        if (s.enabled == null) s.enabled = true;

        // ---------- Трансграничный ----------
        if (!a.crossBorder) a.crossBorder = {};
        const c = a.crossBorder;

        // Если раньше использовалось поле mode/ fixedRub/fixedService
        if (c.mode && !c.currency && c.fixedAmount == null) {
            if (c.mode === "FIXED_RUB" && c.fixedRub != null) {
                c.fixedAmount = c.fixedRub;
                c.currency = "RUB";
            } else if (c.mode === "FIXED_SERVICE" && c.fixedService != null) {
                c.fixedAmount = c.fixedService;
                c.currency = "SERVICE";
            }
        }

        if (c.minAmount == null && c.minRub != null) c.minAmount = c.minRub;
        if (c.maxAmount == null && c.maxRub != null) c.maxAmount = c.maxRub;
        if (c.currency == null) c.currency = "RUB";
        if (c.enabled == null) c.enabled = false;

        // ---------- Эквайринг ----------
        if (!a.acquiring) a.acquiring = {};
        const q = a.acquiring;
        if (q.enabled == null) q.enabled = false;

        return a;
    }

    // =========================================================
    // JSON
    // =========================================================

    function refreshJsonTextarea() {
        jsonTextarea.value = JSON.stringify(config, null, 2);
    }

    function saveJsonConfig() {
        jsonMessage.style.display = "none";
        jsonMessage.textContent = "";

        const raw = jsonTextarea.value.trim();
        if (!raw) {
            alert("Поле JSON пустое. Нечего сохранять.");
            return;
        }

        try {
            const parsed = JSON.parse(raw);
            config = parsed;
            ConfigStorage.saveConfig(config);

            renderEntitiesTable();
            renderRulesTable();
            initCondEntitySelect();

            jsonMessage.className = "alert-success";
            jsonMessage.textContent = "JSON успешно разобран и сохранён.";
            jsonMessage.style.display = "block";
        } catch (e) {
            console.error("Ошибка парсинга JSON:", e);
            jsonMessage.className = "alert-error";
            jsonMessage.textContent = "Ошибка: не удалось разобрать JSON. Подробности в консоли.";
            jsonMessage.style.display = "block";
        }
    }

    function resetToDefault() {
        const ok = confirm("Сбросить настройки к значениям по умолчанию? Текущие настройки будут потеряны.");
        if (!ok) return;

        config = ConfigStorage.getDefaultConfig();
        ConfigStorage.saveConfig(config);

        refreshJsonTextarea();
        renderEntitiesTable();
        renderRulesTable();
        initCondEntitySelect();

        jsonMessage.className = "alert-success";
        jsonMessage.textContent = "Настройки сброшены к значениям по умолчанию.";
        jsonMessage.style.display = "block";
    }

    function setupJsonCollapse() {
        if (!jsonToggle || !jsonBody || !jsonArrow) return;

        // по умолчанию свёрнуто
        jsonBody.style.display = "none";
        jsonArrow.classList.remove("open");
        if (jsonCard) jsonCard.classList.add("collapsed");

        jsonToggle.addEventListener("click", function () {
            const isHidden = jsonBody.style.display === "none";
            jsonBody.style.display = isHidden ? "block" : "none";

            if (isHidden) {
                jsonArrow.classList.add("open");
                if (jsonCard) jsonCard.classList.remove("collapsed");
            } else {
                jsonArrow.classList.remove("open");
                if (jsonCard) jsonCard.classList.add("collapsed");
            }
        });
    }

    function setupEntitiesCollapse() {
        if (!entitiesToggle || !entitiesBody || !entitiesArrow) return;

        entitiesBody.style.display = "none";
        entitiesArrow.classList.remove("open");
        if (entitiesCard) entitiesCard.classList.add("collapsed");

        entitiesToggle.addEventListener("click", function () {
            const isHidden = entitiesBody.style.display === "none";
            entitiesBody.style.display = isHidden ? "block" : "none";

            if (isHidden) {
                entitiesArrow.classList.add("open");
                if (entitiesCard) entitiesCard.classList.remove("collapsed");
            } else {
                entitiesArrow.classList.remove("open");
                if (entitiesCard) entitiesCard.classList.add("collapsed");
            }
        });
    }

    // =========================================================
    // СУЩНОСТИ
    // =========================================================

    function renderEntitiesTable() {
        entitiesTableBody.innerHTML = "";

        (config.entities || []).forEach(function (entity, index) {
            const tr = document.createElement("tr");

            function td(text) {
                const el = document.createElement("td");
                el.textContent = text;
                return el;
            }

            tr.appendChild(td(entity.id));
            tr.appendChild(td(entity.name));
            tr.appendChild(td(entity.type));
            tr.appendChild(td(entity.multiSelect ? "Да" : "Нет"));
            tr.appendChild(td(entity.showOnMain ? "Да" : "Нет"));

            if (entity.type === "list") {
                tr.appendChild(td((entity.values || []).join(", ")));
            } else {
                tr.appendChild(td("—"));
            }

            const tdActions = document.createElement("td");

            const editBtn = document.createElement("button");
            editBtn.className = "btn btn-icon btn-secondary";
            editBtn.title = "Редактировать сущность";
            editBtn.textContent = "✏";
            editBtn.style.marginRight = "4px";
            editBtn.onclick = function () {
                startEditEntity(index);
            };
            tdActions.appendChild(editBtn);

            const delBtn = document.createElement("button");
            delBtn.className = "btn btn-icon btn-danger";
            delBtn.title = "Удалить сущность";
            delBtn.textContent = "🗑";
            delBtn.onclick = function () {
                deleteEntity(index);
            };
            tdActions.appendChild(delBtn);

            tr.appendChild(tdActions);
            entitiesTableBody.appendChild(tr);
        });
    }

    function startEditEntity(index) {
        const entity = config.entities[index];
        if (!entity) return;

        editingEntityIndex = index;

        openEntitiesSection();

        if (entityForm) entityForm.classList.add("editing");
        if (entityFormMode) entityFormMode.textContent = "Режим: редактирование сущности";

        newEntityIdInput.value = entity.id;
        newEntityNameInput.value = entity.name;
        newEntityTypeSelect.value = entity.type;
        newEntityValuesTextarea.value = (entity.values || []).join("\n");
        newEntityMultiCheckbox.checked = !!entity.multiSelect;
        newEntityShowOnMainCheckbox.checked = !!entity.showOnMain;

        newEntityIdInput.disabled = true; // ID не меняем

        addEntityBtn.textContent = "Сохранить изменения сущности";
        cancelEntityEditBtn.style.display = "inline-flex";

        entitiesMessage.style.display = "none";
        entitiesMessage.textContent = "";
    }

    function cancelEntityEdit() {
        editingEntityIndex = null;

        if (entityForm) entityForm.classList.remove("editing");
        if (entityFormMode) entityFormMode.textContent = "Режим: создание сущности";

        newEntityIdInput.value = "";
        newEntityNameInput.value = "";
        newEntityTypeSelect.value = "list";
        newEntityValuesTextarea.value = "";
        newEntityMultiCheckbox.checked = false;
        newEntityShowOnMainCheckbox.checked = true;

        newEntityIdInput.disabled = false;
        addEntityBtn.textContent = "Добавить сущность";
        cancelEntityEditBtn.style.display = "none";

        entitiesMessage.style.display = "none";
        entitiesMessage.textContent = "";
    }

    function deleteEntity(index) {
        const entity = config.entities[index];
        if (!entity) return;

        if (isEntityUsedInRules(entity.id)) {
            alert("Нельзя удалить сущность \"" + entity.name + "\", так как она используется в правилах. Сначала измените или удалите соответствующие правила.");
            return;
        }

        const ok = confirm("Удалить сущность \"" + entity.name + "\"?");
        if (!ok) return;

        config.entities.splice(index, 1);
        ConfigStorage.saveConfig(config);

        refreshJsonTextarea();
        renderEntitiesTable();
        initCondEntitySelect();

        if (editingEntityIndex === index) {
            cancelEntityEdit();
        }
    }

    function saveEntity() {
        entitiesMessage.style.display = "none";
        entitiesMessage.textContent = "";

        const id = newEntityIdInput.value.trim();
        const name = newEntityNameInput.value.trim();
        const type = newEntityTypeSelect.value;
        const valuesRaw = newEntityValuesTextarea.value;
        const multi = newEntityMultiCheckbox.checked;
        const showOnMain = newEntityShowOnMainCheckbox.checked;

        if (!id || !name) {
            alert("Пожалуйста, заполните ID и Название сущности.");
            return;
        }

        let values = [];
        if (type === "list") {
            values = valuesRaw
                .split(/\r?\n/)
                .map(line => line.trim())
                .filter(line => line.length > 0);
        }

        if (editingEntityIndex === null) {
            const exists = (config.entities || []).some(e => e.id === id);
            if (exists) {
                alert("Сущность с таким ID уже существует.");
                return;
            }

            const newEntity = {
                id,
                name,
                type,
                multiSelect: multi,
                values,
                showOnMain,
                inputLabel: name,
                inputHint: ""
            };

            config.entities = config.entities || [];
            config.entities.push(newEntity);

            entitiesMessage.textContent = "Сущность добавлена и сохранена.";
        } else {
            const entity = config.entities[editingEntityIndex];
            entity.name = name;
            entity.type = type;
            entity.multiSelect = multi;
            entity.values = values;
            entity.showOnMain = showOnMain;
            entity.inputLabel = name;

            entitiesMessage.textContent = "Изменения сущности сохранены.";
        }

        ConfigStorage.saveConfig(config);
        refreshJsonTextarea();
        renderEntitiesTable();
        initCondEntitySelect();

        entitiesMessage.style.display = "block";
        cancelEntityEdit();
    }

    // =========================================================
    // ПРАВИЛА: таблица
    // =========================================================

    function renderRulesTable() {
        rulesTableBody.innerHTML = "";

        (config.rules || []).forEach(function (rule, index) {
            const actions = normalizeRuleActions(rule);
            const tr = document.createElement("tr");

            function td(text) {
                const el = document.createElement("td");
                el.textContent = text;
                return el;
            }

            tr.appendChild(td((index + 1).toString()));
            tr.appendChild(td(rule.name || rule.id || "Без имени"));
            tr.appendChild(td(rule.conditionsLogic || "AND"));
            tr.appendChild(td((rule.conditions || []).length.toString()));

            // Сервисный сбор
            tr.appendChild(td(actions.serviceFee.percent != null ? String(actions.serviceFee.percent) : "—"));
            tr.appendChild(td(actions.serviceFee.fixedAmount != null ? String(actions.serviceFee.fixedAmount) : "—"));
            tr.appendChild(td(actions.serviceFee.minAmount != null ? String(actions.serviceFee.minAmount) : "—"));
            tr.appendChild(td(actions.serviceFee.maxAmount != null ? String(actions.serviceFee.maxAmount) : "—"));
            tr.appendChild(td(actions.serviceFee.currency || "RUB"));

            // Трансграничный
            tr.appendChild(td(actions.crossBorder.percent != null ? String(actions.crossBorder.percent) : "—"));
            tr.appendChild(td(actions.crossBorder.fixedAmount != null ? String(actions.crossBorder.fixedAmount) : "—"));
            tr.appendChild(td(actions.crossBorder.minAmount != null ? String(actions.crossBorder.minAmount) : "—"));
            tr.appendChild(td(actions.crossBorder.maxAmount != null ? String(actions.crossBorder.maxAmount) : "—"));
            tr.appendChild(td(actions.crossBorder.currency || "RUB"));

            // Эквайринг
            tr.appendChild(td(actions.acquiring.percent != null ? String(actions.acquiring.percent) : "—"));

            const tdActions = document.createElement("td");

            const viewBtn = document.createElement("button");
            viewBtn.className = "btn btn-icon btn-secondary";
            viewBtn.title = "Просмотр";
            viewBtn.textContent = "👁";
            viewBtn.style.marginRight = "4px";
            viewBtn.onclick = function () {
                openRuleModal(index);
            };
            tdActions.appendChild(viewBtn);

            const editBtn = document.createElement("button");
            editBtn.className = "btn btn-icon btn-secondary";
            editBtn.title = "Редактировать";
            editBtn.textContent = "✏";
            editBtn.style.marginRight = "4px";
            editBtn.onclick = function () {
                startEditRule(index);
            };
            tdActions.appendChild(editBtn);

            const delBtn = document.createElement("button");
            delBtn.className = "btn btn-icon btn-danger";
            delBtn.title = "Удалить";
            delBtn.textContent = "🗑";
            delBtn.onclick = function () {
                const ok = confirm("Удалить это правило?");
                if (!ok) return;
                config.rules.splice(index, 1);
                ConfigStorage.saveConfig(config);
                refreshJsonTextarea();
                renderRulesTable();
                if (editingRuleIndex === index) {
                    clearRuleForm();
                }
            };
            tdActions.appendChild(delBtn);

            tr.appendChild(tdActions);
            rulesTableBody.appendChild(tr);
        });
    }

    // =========================================================
    // ПРАВИЛА: конструктор (условия)
    // =========================================================

    function initCondEntitySelect() {
        condEntitySelect.innerHTML = "";
        (config.entities || []).forEach(function (entity) {
            const opt = document.createElement("option");
            opt.value = entity.id;
            opt.textContent = entity.name + " (" + entity.id + ")";
            condEntitySelect.appendChild(opt);
        });

        updateCondValueField();
    }

    function updateCondValueField() {
        const entityId = condEntitySelect.value;
        const entity = getEntityById(entityId);
        condValueContainer.innerHTML = "";

        if (!entity) {
            const span = document.createElement("span");
            span.textContent = "Нет сущности";
            condValueContainer.appendChild(span);
            return;
        }

        // Для списков — multi-select
        if (entity.type === "list") {
            const select = document.createElement("select");
            select.id = "condValueSelect";
            select.multiple = true;
            select.size = Math.min(5, (entity.values || []).length || 3);

            (entity.values || []).forEach(function (val) {
                const opt = document.createElement("option");
                opt.value = val;
                opt.textContent = val;
                select.appendChild(opt);
            });

            condValueContainer.appendChild(select);
        }
        // Для boolean — Да/Нет
        else if (entity.type === "boolean") {
            const select = document.createElement("select");
            select.id = "condValueBoolean";
            const optYes = document.createElement("option");
            optYes.value = "true";
            optYes.textContent = "Да";
            const optNo = document.createElement("option");
            optNo.value = "false";
            optNo.textContent = "Нет";
            select.appendChild(optYes);
            select.appendChild(optNo);
            condValueContainer.appendChild(select);
        }
        // Для числа / процента — numeric
        else {
            const input = document.createElement("input");
            input.type = "number";
            input.id = "condValueNumber";
            input.step = "0.01";
            condValueContainer.appendChild(input);
        }
    }

    function addConditionToCurrentRule() {
        const entityId = condEntitySelect.value;
        const entity = getEntityById(entityId);
        if (!entity) {
            alert("Не выбрана сущность для условия.");
            return;
        }

        const not = condNotCheckbox.checked;
        let value = null;

        if (entity.type === "list") {
            const sel = document.getElementById("condValueSelect");
            const selected = [];
            for (let i = 0; i < sel.options.length; i++) {
                const opt = sel.options[i];
                if (opt.selected) selected.push(opt.value);
            }
            if (!selected.length) {
                alert("Выберите хотя бы одно значение для условия.");
                return;
            }
            value = selected;
        } else if (entity.type === "boolean") {
            const sel = document.getElementById("condValueBoolean");
            value = sel.value === "true";
        } else {
            const input = document.getElementById("condValueNumber");
            const raw = input.value;
            if (!raw) {
                alert("Введите числовое значение для условия.");
                return;
            }
            const num = parseFloat(raw);
            if (isNaN(num)) {
                alert("Некорректное число.");
                return;
            }
            value = num;
        }

        // Как соединяем с предыдущим условием: И / ИЛИ
        let link = "AND";
        if (currentRuleConditions.length > 0) {
            link = condLinkSelect.value || "AND";
        }

        const condition = {
            entityId,
            operator: "EQ",
            not,
            value,
            link // "AND" или "OR" — связь с предыдущей строкой
        };

        currentRuleConditions.push(condition);
        renderCurrentRuleConditions();
    }

    function renderCurrentRuleConditions() {
        ruleConditionsUl.innerHTML = "";

        if (!currentRuleConditions.length) {
            ruleConditionsList.style.display = "none";
            return;
        }

        currentRuleConditions.forEach(function (cond, index) {
            const entity = getEntityById(cond.entityId);
            const li = document.createElement("li");

            let parts = [];

            // Для первой строки связь не показываем, для остальных пишем И/ИЛИ
            if (index > 0) {
                parts.push(cond.link === "OR" ? "ИЛИ" : "И");
            }

            let name = entity ? entity.name : cond.entityId;
            parts.push(name + ":");

            if (cond.not) {
                parts.push("НЕ");
            }

            let valText;
            if (Array.isArray(cond.value)) {
                valText = "[" + cond.value.join(", ") + "]";
            } else if (typeof cond.value === "boolean") {
                valText = cond.value ? "Да" : "Нет";
            } else {
                valText = String(cond.value);
            }

            parts.push("равно");
            parts.push(valText);

            li.textContent = parts.join(" ");

            // Кнопка удаления условия
            const delBtn = document.createElement("button");
            delBtn.className = "btn btn-secondary";
            delBtn.style.marginLeft = "4px";
            delBtn.style.padding = "2px 6px";
            delBtn.style.fontSize = "11px";
            delBtn.textContent = "X";
            delBtn.onclick = function () {
                currentRuleConditions.splice(index, 1);
                renderCurrentRuleConditions();
            };
            li.appendChild(delBtn);

            ruleConditionsUl.appendChild(li);
        });

        ruleConditionsList.style.display = "block";
    }

    function clearRuleForm() {
        ruleNameInput.value = "";
        ruleLogicSelect.value = "AND";
        currentRuleConditions = [];
        renderCurrentRuleConditions();

        // Сброс действий (сборов)
        actServiceEnabled.checked = true;
        actServicePercent.value = "";
        actServiceFixed.value = "";
        actServiceMin.value = "";
        actServiceMax.value = "";
        actServiceCurrency.value = "RUB";

        actCrossEnabled.checked = false;
        actCrossPercent.value = "";
        actCrossFixed.value = "";
        actCrossMin.value = "";
        actCrossMax.value = "";
        actCrossCurrency.value = "RUB";

        actAcquiringEnabled.checked = false;
        actAcquiringPercent.value = "";

        condNotCheckbox.checked = false;
        condLinkSelect.value = "AND";

        editingRuleIndex = null;
        saveRuleBtn.textContent = "Добавить правило";

        ruleMessage.style.display = "none";
        ruleMessage.textContent = "";
    }

    function startEditRule(index) {
        const rule = config.rules[index];
        if (!rule) return;

        editingRuleIndex = index;

        const actions = normalizeRuleActions(rule);

        ruleNameInput.value = rule.name || "";
        ruleLogicSelect.value = rule.conditionsLogic || "AND";

        // Восстанавливаем условия
        currentRuleConditions = (rule.conditions || []).map(function (c) {
            return {
                entityId: c.entityId,
                operator: c.operator || "EQ",
                not: !!c.not,
                value: Array.isArray(c.value) ? c.value.slice() : c.value,
                link: c.link || rule.conditionsLogic || "AND"
            };
        });
        renderCurrentRuleConditions();

        // Восстанавливаем действия — сервисный сбор
        actServiceEnabled.checked = actions.serviceFee.enabled !== false; // по умолчанию true
        actServicePercent.value = actions.serviceFee.percent != null ? actions.serviceFee.percent : "";
        actServiceFixed.value = actions.serviceFee.fixedAmount != null ? actions.serviceFee.fixedAmount : "";
        actServiceMin.value = actions.serviceFee.minAmount != null ? actions.serviceFee.minAmount : "";
        actServiceMax.value = actions.serviceFee.maxAmount != null ? actions.serviceFee.maxAmount : "";
        actServiceCurrency.value = actions.serviceFee.currency || "RUB";

        // Трансграничный
        actCrossEnabled.checked = !!actions.crossBorder.enabled;
        actCrossPercent.value = actions.crossBorder.percent != null ? actions.crossBorder.percent : "";
        actCrossFixed.value = actions.crossBorder.fixedAmount != null ? actions.crossBorder.fixedAmount : "";
        actCrossMin.value = actions.crossBorder.minAmount != null ? actions.crossBorder.minAmount : "";
        actCrossMax.value = actions.crossBorder.maxAmount != null ? actions.crossBorder.maxAmount : "";
        actCrossCurrency.value = actions.crossBorder.currency || "RUB";

        // Эквайринг
        actAcquiringEnabled.checked = !!actions.acquiring.enabled;
        actAcquiringPercent.value = actions.acquiring.percent != null ? actions.acquiring.percent : "";

        saveRuleBtn.textContent = "Сохранить изменения правила";
        ruleMessage.style.display = "none";
        ruleMessage.textContent = "";

        window.scrollTo({ top: document.body.scrollHeight, behavior: "smooth" });
    }

    function saveRuleFromForm() {
        ruleMessage.style.display = "none";
        ruleMessage.textContent = "";

        const name = ruleNameInput.value.trim();
        const logic = ruleLogicSelect.value || "AND";

        if (!name) {
            alert("Введите название правила.");
            return;
        }
        if (!currentRuleConditions.length) {
            alert("Добавьте хотя бы одно условие.");
            return;
        }

        // Собираем условия, сохраняем link (И/ИЛИ)
        const conditions = currentRuleConditions.map(function (c) {
            return {
                entityId: c.entityId,
                operator: c.operator || "EQ",
                not: !!c.not,
                value: Array.isArray(c.value) ? c.value.slice() : c.value,
                link: c.link || "AND"
            };
        });

        // Собираем действия по сервисному сбору
        const serviceActions = {
            enabled: actServiceEnabled.checked,
            percent: parseNullableFloat(actServicePercent),
            fixedAmount: parseNullableFloat(actServiceFixed),
            minAmount: parseNullableFloat(actServiceMin),
            maxAmount: parseNullableFloat(actServiceMax),
            currency: actServiceCurrency.value || "RUB"
        };

        // Собираем действия по трансграничному
        const crossActions = {
            enabled: actCrossEnabled.checked,
            percent: parseNullableFloat(actCrossPercent),
            fixedAmount: parseNullableFloat(actCrossFixed),
            minAmount: parseNullableFloat(actCrossMin),
            maxAmount: parseNullableFloat(actCrossMax),
            currency: actCrossCurrency.value || "RUB"
        };

        // Эквайринг
        const acquiringActions = {
            enabled: actAcquiringEnabled.checked,
            percent: parseNullableFloat(actAcquiringPercent)
        };

        const rule = {
            id: editingRuleIndex === null
                ? ("rule_" + Date.now())
                : (config.rules[editingRuleIndex].id || "rule_" + Date.now()),
            name,
            conditionsLogic: logic, // общий режим (для справки)
            conditions,
            actions: {
                serviceFee: serviceActions,
                crossBorder: crossActions,
                acquiring: acquiringActions
            }
        };

        if (editingRuleIndex === null) {
            config.rules = config.rules || [];
            config.rules.push(rule);
            ruleMessage.textContent = "Правило добавлено и сохранено.";
        } else {
            config.rules[editingRuleIndex] = rule;
            ruleMessage.textContent = "Изменения правила сохранены.";
        }

        ConfigStorage.saveConfig(config);
        refreshJsonTextarea();
        renderRulesTable();

        ruleMessage.style.display = "block";
        clearRuleForm();
    }

    // =========================================================
    // Модальное окно ПРОСМОТРА правила
    // =========================================================

    function openRuleModal(index) {
        const rule = config.rules[index];
        if (!rule) return;

        const actions = normalizeRuleActions(rule);

        modalEditingRuleIndex = index;

        let textParts = [];

        textParts.push("Название: " + (rule.name || "Без имени"));
        textParts.push("Общий режим условий: " + (rule.conditionsLogic || "AND"));
        textParts.push("");

        textParts.push("УСЛОВИЯ:");
        if (!rule.conditions || !rule.conditions.length) {
            textParts.push("  (нет условий)");
        } else {
            rule.conditions.forEach(function (cond, idx) {
                const entity = getEntityById(cond.entityId);
                let line = "  ";
                if (idx > 0) {
                    line += (cond.link === "OR" ? "[ИЛИ] " : "[И] ");
                }
                line += (entity ? entity.name : cond.entityId) + " ";
                if (cond.not) line += "НЕ ";
                line += "равно ";
                if (Array.isArray(cond.value)) {
                    line += "[" + cond.value.join(", ") + "]";
                } else if (typeof cond.value === "boolean") {
                    line += cond.value ? "Да" : "Нет";
                } else {
                    line += String(cond.value);
                }
                textParts.push(line);
            });
        }

        textParts.push("");
        textParts.push("ДЕЙСТВИЯ:");

        // Сервисный сбор
        textParts.push("  Сервисный сбор:");
        textParts.push("    Включен: " + (actions.serviceFee.enabled !== false ? "Да" : "Нет"));
        textParts.push("    %: " + (actions.serviceFee.percent != null ? actions.serviceFee.percent : "—"));
        textParts.push("    Фикс: " + (actions.serviceFee.fixedAmount != null ? actions.serviceFee.fixedAmount : "—"));
        textParts.push("    Мин: " + (actions.serviceFee.minAmount != null ? actions.serviceFee.minAmount : "—"));
        textParts.push("    Макс: " + (actions.serviceFee.maxAmount != null ? actions.serviceFee.maxAmount : "—"));
        textParts.push("    Валюта: " + (actions.serviceFee.currency || "RUB"));

        // Трансграничный
        textParts.push("  Трансграничный сбор:");
        textParts.push("    Включен: " + (actions.crossBorder.enabled ? "Да" : "Нет"));
        textParts.push("    %: " + (actions.crossBorder.percent != null ? actions.crossBorder.percent : "—"));
        textParts.push("    Фикс: " + (actions.crossBorder.fixedAmount != null ? actions.crossBorder.fixedAmount : "—"));
        textParts.push("    Мин: " + (actions.crossBorder.minAmount != null ? actions.crossBorder.minAmount : "—"));
        textParts.push("    Макс: " + (actions.crossBorder.maxAmount != null ? actions.crossBorder.maxAmount : "—"));
        textParts.push("    Валюта: " + (actions.crossBorder.currency || "RUB"));

        // Эквайринг
        textParts.push("  Эквайринг:");
        textParts.push("    Включен: " + (actions.acquiring.enabled ? "Да" : "Нет"));
        textParts.push("    %: " + (actions.acquiring.percent != null ? actions.acquiring.percent : "—"));

        ruleModalText.textContent = textParts.join("\n");
        ruleModal.style.display = "flex";
    }

    function closeRuleModal() {
        modalEditingRuleIndex = null;
        ruleModal.style.display = "none";
    }

    // =========================================================
    // Обработчики
    // =========================================================

    addEntityBtn.addEventListener("click", saveEntity);
    cancelEntityEditBtn.addEventListener("click", cancelEntityEdit);

    saveJsonBtn.addEventListener("click", saveJsonConfig);
    resetDefaultBtn.addEventListener("click", resetToDefault);

    condEntitySelect.addEventListener("change", updateCondValueField);
    addConditionBtn.addEventListener("click", addConditionToCurrentRule);

    saveRuleBtn.addEventListener("click", saveRuleFromForm);
    clearRuleFormBtn.addEventListener("click", clearRuleForm);

    ruleModalCloseBtn.addEventListener("click", closeRuleModal);

    // =========================================================
    // Инициализация
    // =========================================================

    if (entityFormMode) {
        entityFormMode.textContent = "Режим: создание сущности";
    }

    // Если в конфиге ещё нет поля actions у правил — normalizeRuleActions
    // его создаст "на лету" при выводе.
    refreshJsonTextarea();
    renderEntitiesTable();
    renderRulesTable();
    initCondEntitySelect();
    setupJsonCollapse();
    setupEntitiesCollapse();
});
