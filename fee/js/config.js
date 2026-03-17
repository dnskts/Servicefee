// js/config.js
// -----------------------------------------------------------
// Работа с конфигом (настройками) приложения.
// - Хранение в localStorage.
// - Сущности (Клиент, Вид услуги, Тип услуги и т.п.).
// - Пример правил (в т.ч. для РТ).
// -----------------------------------------------------------

const ConfigStorage = (function () {
    const STORAGE_KEY = "feeCalculatorConfigV4";

    // Дефолтный конфиг: сущности и несколько примерных правил
    function getDefaultConfig() {
        return {
            version: 4,
            // ---------- СУЩНОСТИ (параметры условий) ----------
            entities: [
                {
                    id: "client",
                    name: "Клиент",
                    type: "list",          // list | number | percent | boolean
                    multiSelect: false,
                    values: [
                        "РТ",
                        "РМЕ клиент Казахстана",
                        "РДЛ",
                        "ЭВ",
                        "ТОП БРС + РУСТ",
                        "Сотрудники БРС",
                        "Все остальные клиенты",
                        "SDG клиент Казахстана"
                    ],
                    showOnMain: true,
                    inputLabel: "Клиент",
                    inputHint: "Выберите тип клиента"
                },
                {
                    id: "serviceType",
                    name: "Вид услуги",
                    type: "list",
                    multiSelect: false,
                    values: ["Авиа", "Наземные услуги"],
                    showOnMain: true,
                    inputLabel: "Вид услуги",
                    inputHint: "Например, Авиа или Наземные услуги"
                },
                {
                    id: "serviceKind",
                    name: "Тип услуги",
                    type: "list",
                    multiSelect: false,
                    values: ["Выписка", "Доп.услуги на борту", "Возврат", "Обмен"],
                    showOnMain: true,
                    inputLabel: "Тип услуги",
                    inputHint: "Для Авиа: Выписка, Возврат, Обмен и т.п."
                },
                {
                    id: "paymentType",
                    name: "Тип оплаты",
                    type: "list",
                    multiSelect: false,
                    values: ["Карта БРС", "Ссылка"],
                    showOnMain: true,
                    inputLabel: "Тип оплаты",
                    inputHint: "Например, карта БРС или оплата по ссылке"
                },
                {
                    id: "hasCrossBorder",
                    name: "Трансграничный перевод",
                    type: "boolean",
                    multiSelect: false,
                    values: [],
                    showOnMain: true,
                    inputLabel: "Есть трансграничный перевод?",
                    inputHint: "ДА — если есть комиссия за трансграничный перевод"
                }
            ],

            // ---------- ПРАВИЛА (примерные) ----------
            rules: [
                // Пример правила: РТ / Авиа / Выписка / Карта БРС
                {
                    id: "rt_avia_emission_brs",
                    name: "РТ / Авиа / Выписка / Карта БРС",
                    conditionsLogic: "AND",
                    conditions: [
                        {
                            entityId: "client",
                            operator: "EQ",
                            not: false,
                            value: "РТ"
                        },
                        {
                            entityId: "serviceType",
                            operator: "EQ",
                            not: false,
                            value: "Авиа"
                        },
                        {
                            entityId: "serviceKind",
                            operator: "EQ",
                            not: false,
                            value: "Выписка"
                        },
                        {
                            entityId: "paymentType",
                            operator: "EQ",
                            not: false,
                            value: "Карта БРС"
                        }
                    ],
                    // ---------- ДЕЙСТВИЯ (как считать сбор) ----------
                    calculation: {
                        // Тип стандартного сбора:
                        //  FIXED       — фиксированная сумма
                        //  PERCENT     — % от суммы
                        //  PERCENT_MIN — % от суммы, но не меньше минимума
                        baseFeeType: "PERCENT_MIN",

                        percent: 1.0,        // стандартный сбор = 1% от суммы
                        minFeeRub: 1000,     // но не меньше 1000 руб.
                        fixedRub: 0,         // доп. фикс при необходимости
                        maxFeeRub: 0,        // 0 = без максимального ограничения

                        // --- ТРАНСГРАНИЧНЫЙ ПЕРЕВОД ---
                        // Тип:
                        //   NONE    — нет,
                        //   PERCENT — процент от суммы,
                        //   FIXED   — фиксированная сумма
                        crossBorderType: "NONE",

                        // Если crossBorderType = "PERCENT"
                        crossBorderPercent: 0,          // в %

                        // Если crossBorderType = "FIXED"
                        crossBorderFixedAmount: 0,       // число
                        // Валюта фиксированной суммы:
                        //   RUB     — в рублях,
                        //   SERVICE — в валюте услуги (будет пересчитано по курсу)
                        crossBorderFixedCurrency: "RUB",

                        // --- ЭКВАЙРИНГ ---
                        // % от (сумма в рублях + базовый сбор + трансграничный)
                        acquiringPercent: 0
                    }
                },

                // Пример правила: Наземные / Все остальные клиенты
                {
                    id: "ground_other",
                    name: "Наземные / Все остальные клиенты",
                    conditionsLogic: "AND",
                    conditions: [
                        {
                            entityId: "serviceType",
                            operator: "EQ",
                            not: false,
                            value: "Наземные услуги"
                        },
                        {
                            entityId: "client",
                            operator: "EQ",
                            not: false,
                            value: "Все остальные клиенты"
                        }
                    ],
                    calculation: {
                        baseFeeType: "FIXED",
                        percent: 0,
                        minFeeRub: 0,
                        fixedRub: 1500,
                        maxFeeRub: 0,

                        crossBorderType: "NONE",
                        crossBorderPercent: 0,
                        crossBorderFixedAmount: 0,
                        crossBorderFixedCurrency: "RUB",

                        acquiringPercent: 2.0
                    }
                }
            ]
        };
    }

    function loadConfig() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return getDefaultConfig();
            const parsed = JSON.parse(raw);
            return parsed || getDefaultConfig();
        } catch (e) {
            console.error("Ошибка при чтении настроек из localStorage:", e);
            return getDefaultConfig();
        }
    }

    function saveConfig(config) {
        try {
            const json = JSON.stringify(config, null, 2);
            localStorage.setItem(STORAGE_KEY, json);
        } catch (e) {
            console.error("Ошибка при сохранении настроек в localStorage:", e);
            alert("Не удалось сохранить настройки. Подробности в консоли браузера.");
        }
    }

    return {
        STORAGE_KEY,
        loadConfig,
        saveConfig,
        getDefaultConfig
    };
})();
