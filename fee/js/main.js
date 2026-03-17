// js/main.js
// -----------------------------------------------------------
// Главная страница (index.html):
// - строим поля по сущностям,
// - собираем введённые значения,
// - ищем подходящее правило,
// - считаем:
//     * сумму услуги в рублях,
//     * сервисный сбор (включая трансграничный и эквайринг),
//     * тотал.
// -----------------------------------------------------------

document.addEventListener("DOMContentLoaded", function () {
    const config = ConfigStorage.loadConfig();

    const dynamicContainer = document.getElementById("dynamicConditionsContainer");
    const calculateBtn = document.getElementById("calculateBtn");
    const clearBtn = document.getElementById("clearBtn");
    const mainError = document.getElementById("mainError");

    const amountInput = document.getElementById("amountInput");
    const rateInput = document.getElementById("rateInput");

    const serviceFeeMainEl = document.getElementById("serviceFeeMain");
    const amountRubResultEl = document.getElementById("amountRubResult");
    const serviceFeeResultEl = document.getElementById("serviceFeeResult");
    const totalResultEl = document.getElementById("totalResult");
    const ruleInfoEl = document.getElementById("ruleInfo");

    // ---------- Рисуем поля по сущностям ----------

    function createFieldForEntity(entity) {
        const wrapper = document.createElement("div");
        wrapper.className = "field";

        const label = document.createElement("div");
        label.className = "field-label";
        label.textContent = entity.inputLabel || entity.name;

        const hint = document.createElement("div");
        hint.className = "field-hint";
        hint.textContent = entity.inputHint || "";

        wrapper.appendChild(label);
        wrapper.appendChild(hint);

        const inputId = "entity_" + entity.id;
        let inputElement = null;

        if (entity.type === "list") {
            const select = document.createElement("select");
            select.id = inputId;

            if (entity.multiSelect) {
                select.multiple = true;
                select.size = Math.min(5, (entity.values || []).length || 3);
            } else {
                const emptyOption = document.createElement("option");
                emptyOption.value = "";
                emptyOption.textContent = "— не выбрано —";
                select.appendChild(emptyOption);
            }

            (entity.values || []).forEach(function (val) {
                const opt = document.createElement("option");
                opt.value = val;
                opt.textContent = val;
                select.appendChild(opt);
            });

            inputElement = select;
        } else if (entity.type === "boolean") {
            const select = document.createElement("select");
            select.id = inputId;

            const emptyOption = document.createElement("option");
            emptyOption.value = "";
            emptyOption.textContent = "— не выбрано —";
            select.appendChild(emptyOption);

            const yesOption = document.createElement("option");
            yesOption.value = "true";
            yesOption.textContent = "Да";
            select.appendChild(yesOption);

            const noOption = document.createElement("option");
            noOption.value = "false";
            noOption.textContent = "Нет";
            select.appendChild(noOption);

            inputElement = select;
        } else {
            const input = document.createElement("input");
            input.type = "number";
            input.id = inputId;
            input.step = "0.01";
            input.min = "0";
            input.placeholder = entity.type === "percent"
                ? "Процент, например 1.2"
                : "Число";

            inputElement = input;
        }

        wrapper.appendChild(inputElement);
        return wrapper;
    }

    (config.entities || []).forEach(function (entity) {
        if (!entity.showOnMain) return;
        const fieldEl = createFieldForEntity(entity);
        dynamicContainer.appendChild(fieldEl);
    });

    // ---------- Вспомогательные функции ----------

    function setError(message) {
        if (!message) {
            mainError.style.display = "none";
            mainError.textContent = "";
            return;
        }
        mainError.style.display = "block";
        mainError.textContent = message;
    }

    function clearResults() {
        serviceFeeMainEl.textContent = "—";
        amountRubResultEl.textContent = "—";
        serviceFeeResultEl.textContent = "—";
        totalResultEl.textContent = "—";
        ruleInfoEl.textContent = "Правило ещё не применялось.";
        setError("");
    }

    // Собираем значения сущностей с формы
    function collectEntityValues() {
        const valuesById = {};

        (config.entities || []).forEach(function (entity) {
            if (!entity.showOnMain) return;

            const inputId = "entity_" + entity.id;
            const el = document.getElementById(inputId);
            if (!el) return;

            let value = null;

            if (entity.type === "list") {
                if (entity.multiSelect && el.multiple) {
                    const selected = [];
                    for (let i = 0; i < el.options.length; i++) {
                        const opt = el.options[i];
                        if (opt.selected) selected.push(opt.value);
                    }
                    value = selected.length ? selected : null;
                } else {
                    value = el.value || null;
                }
            } else if (entity.type === "boolean") {
                const v = el.value;
                if (v === "true") value = true;
                else if (v === "false") value = false;
                else value = null;
            } else {
                const raw = el.value;
                if (raw === "") {
                    value = null;
                } else {
                    const num = parseFloat(raw);
                    value = isNaN(num) ? null : num;
                }
            }

            valuesById[entity.id] = value;
        });

        return valuesById;
    }

    // Проверка одного условия
    function checkSingleCondition(condition, entityValues) {
        const entityId = condition.entityId;
        const entityValue = entityValues[entityId];

        if (entityValue === null || entityValue === undefined) return false;

        const op = condition.operator || "EQ";
        let result = false;
        const condVal = condition.value;

        const isArrayValue = Array.isArray(entityValue);

        if (op === "EQ") {
            if (isArrayValue) {
                if (Array.isArray(condVal)) {
                    result = condVal.some(v => entityValue.includes(String(v)));
                } else {
                    result = entityValue.includes(String(condVal));
                }
            } else {
                if (typeof entityValue === "boolean") {
                    result = (entityValue === condVal);
                } else {
                    result = String(entityValue) === String(condVal);
                }
            }
        } else if (op === "IN") {
            const condArr = Array.isArray(condVal) ? condVal : [condVal];
            if (isArrayValue) {
                result = condArr.some(v => entityValue.includes(String(v)));
            } else {
                result = condArr.some(v => String(v) === String(entityValue));
            }
        }

        if (condition.not) result = !result;
        return result;
    }

    // Проверка, подходит ли правило
    function checkRule(rule, entityValues) {
        const conditions = rule.conditions || [];
        if (!conditions.length) return false;

        const logic = rule.conditionsLogic === "OR" ? "OR" : "AND";

        if (logic === "AND") {
            return conditions.every(cond => checkSingleCondition(cond, entityValues));
        }
        return conditions.some(cond => checkSingleCondition(cond, entityValues));
    }

    // ---------- Сам расчёт ----------

    function calculate() {
        clearResults();

        const amountRaw = amountInput.value || "0";
        const rateRaw = rateInput.value || "0";

        const amount = parseFloat(amountRaw.replace(",", "."));
        const rate = parseFloat(rateRaw.replace(",", "."));

        if (isNaN(amount) || amount <= 0) {
            setError("Введите корректную сумму услуги (больше нуля).");
            return;
        }
        if (isNaN(rate) || rate <= 0) {
            setError("Введите корректный курс в рубли (больше нуля).");
            return;
        }

        const entityValues = collectEntityValues();

        let matchedRule = null;
        for (let i = 0; i < (config.rules || []).length; i++) {
            const rule = config.rules[i];
            if (checkRule(rule, entityValues)) {
                matchedRule = rule;
                break;
            }
        }

        if (!matchedRule) {
            setError("Не найдено подходящее правило в настройках. Проверьте параметры или добавьте правило в настройках.");
            return;
        }

        const calc = matchedRule.calculation || {};
        const baseFeeType = calc.baseFeeType || "FIXED";

        // Сумма услуги в рублях
        const amountRub = amount * rate;
        let baseServiceFeeRub = 0;

        // --- стандартный сбор ---
        if (baseFeeType === "FIXED") {
            baseServiceFeeRub = calc.fixedRub || 0;
        } else if (baseFeeType === "PERCENT") {
            const percent = calc.percent || 0;
            baseServiceFeeRub = amountRub * percent / 100;
        } else if (baseFeeType === "PERCENT_MIN") {
            const percent = calc.percent || 0;
            const minFee = calc.minFeeRub || 0;
            const byPercent = amountRub * percent / 100;
            baseServiceFeeRub = Math.max(byPercent, minFee);
            if (calc.fixedRub) {
                baseServiceFeeRub += calc.fixedRub;
            }
        }

        // --- максимум ---
        const maxFee = calc.maxFeeRub || 0;
        if (maxFee > 0 && baseServiceFeeRub > maxFee) {
            baseServiceFeeRub = maxFee;
        }

        // --- трансграничный ---
        let crossBorderRub = 0;
        const cbType = calc.crossBorderType || "NONE";

        if (cbType === "PERCENT") {
            // ВНИМАНИЕ: здесь считаем % именно от суммы в рублях.
            const cbPercent = calc.crossBorderPercent || 0;
            crossBorderRub = amountRub * cbPercent / 100;
        } else if (cbType === "FIXED") {
            const value = calc.crossBorderFixedAmount || 0;
            const currency = calc.crossBorderFixedCurrency || "RUB";
            if (currency === "RUB") {
                crossBorderRub = value;
            } else {
                // "SERVICE" = валюта услуги → переводим в рубли по текущему курсу
                crossBorderRub = value * rate;
            }
        }

        // --- эквайринг ---
        const acquiringPercent = calc.acquiringPercent || 0;
        const acquiringRub = (amountRub + baseServiceFeeRub + crossBorderRub) * acquiringPercent / 100;

        // --- итоговый сервисный сбор ---
        const finalServiceFeeRub = baseServiceFeeRub + crossBorderRub + acquiringRub;

        // --- тотал ---
        const totalRub = amountRub + finalServiceFeeRub;

        function formatMoney(value) {
            return value.toFixed(2);
        }

        serviceFeeMainEl.textContent = formatMoney(finalServiceFeeRub);
        amountRubResultEl.textContent = formatMoney(amountRub);
        serviceFeeResultEl.textContent = formatMoney(finalServiceFeeRub);
        totalResultEl.textContent = formatMoney(totalRub);

        const ruleName = matchedRule.name || matchedRule.id || "Без названия";
        ruleInfoEl.innerHTML = 'Применено правило: <span class="badge-small">' + ruleName + "</span>";
    }

    // ---------- Обработчики ----------

    calculateBtn.addEventListener("click", calculate);

    clearBtn.addEventListener("click", function () {
        amountInput.value = "";
        rateInput.value = "1";

        (config.entities || []).forEach(function (entity) {
            if (!entity.showOnMain) return;
            const inputId = "entity_" + entity.id;
            const el = document.getElementById(inputId);
            if (!el) return;

            if (el.tagName === "SELECT") {
                if (el.multiple) {
                    for (let i = 0; i < el.options.length; i++) {
                        el.options[i].selected = false;
                    }
                } else {
                    el.value = "";
                }
            } else {
                el.value = "";
            }
        });

        clearResults();
    });
});
