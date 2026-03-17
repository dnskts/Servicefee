<?php
/**
 * Движок правил — загрузка и кэширование правил из БД.
 *
 * Загружает правила из базы данных (клиенты, типы услуг, способы оплаты,
 * сервисный сбор, трансгран, эквайринг, спецправила) и предоставляет
 * удобные методы для их получения. При первом запросе данные читаются
 * из БД и сохраняются в памяти; при повторных — отдаются из кэша.
 * Метод clearCache() сбрасывает кэш (нужно после изменения данных в БД).
 */

namespace App;

class RuleEngine
{
    /** @var Database Экземпляр класса для работы с БД */
    private $db;

    /** @var array|null Кэш: список всех активных клиентов (после первого getClients) */
    private $cacheClients = null;

    /** @var array Кэш: клиент по коду [ code => строка из БД ] */
    private $cacheClientByCode = [];

    /** @var array|null Кэш: список всех активных типов услуг */
    private $cacheServiceTypes = null;

    /** @var array|null Кэш: список всех активных способов оплаты */
    private $cachePaymentMethods = null;

    /** @var array Кэш правил сервисного сбора [ 'clientId_serviceTypeId' => строка ] */
    private $cacheServiceFeeRules = [];

    /** @var array Кэш правил трансграна [ 'clientId_serviceTypeId_column' => строка ] */
    private $cacheTransgranRules = [];

    /** @var array Кэш исключений трансграна [ transgranRuleId => [ исключения ] ] */
    private $cacheTransgranExceptions = [];

    /** @var array Кэш спецправил клиента [ clientId => [ правила ] ] */
    private $cacheClientSpecialRules = [];

    /** @var array Кэш правил эквайринга [ 'clientId_paymentMethodId' => строка ] */
    private $cacheAcquiringRules = [];

    /**
     * Создаёт движок правил.
     *
     * @param Database $db Экземпляр класса Database (из этапа 1) для доступа к БД.
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Возвращает всех активных клиентов, отсортированных по полю sort_order.
     * Используется для выбора клиента в форме калькулятора.
     *
     * @return array Массив строк из таблицы clients (id, code, name, country, result_currency, use_max_rule, agent_hint, sort_order, is_active). Только is_active=1.
     */
    public function getClients()
    {
        if ($this->cacheClients === null) {
            $this->cacheClients = $this->db->fetchAll(
                'SELECT id, code, name, country, result_currency, use_max_rule, agent_hint, sort_order, is_active FROM clients WHERE is_active = 1 ORDER BY sort_order ASC'
            );
            $this->cacheClientByCode = [];
            foreach ($this->cacheClients as $row) {
                $this->cacheClientByCode[$row['code']] = $row;
            }
        }
        return $this->cacheClients;
    }

    /**
     * Возвращает клиента по его коду (например 'RT', 'RME').
     * Результат кэшируется: повторный запрос с тем же кодом не обращается к БД.
     *
     * @param string $code Код клиента (латиницей).
     * @return array|null Строка из таблицы clients или null, если клиент не найден или не активен.
     */
    public function getClientByCode($code)
    {
        if (isset($this->cacheClientByCode[$code])) {
            return $this->cacheClientByCode[$code];
        }
        $this->getClients();
        return isset($this->cacheClientByCode[$code]) ? $this->cacheClientByCode[$code] : null;
    }

    /**
     * Возвращает все активные типы услуг (выписка, EMD, возврат, обмен).
     *
     * @return array Массив строк из таблицы service_types (id, code, name, sort_order, is_active). Только is_active=1, по sort_order.
     */
    public function getServiceTypes()
    {
        if ($this->cacheServiceTypes === null) {
            $this->cacheServiceTypes = $this->db->fetchAll(
                'SELECT id, code, name, sort_order, is_active FROM service_types WHERE is_active = 1 ORDER BY sort_order ASC'
            );
        }
        return $this->cacheServiceTypes;
    }

    /**
     * Возвращает все активные способы оплаты (по счёту, карта БРС, карта КЗ и т.д.).
     *
     * @return array Массив строк из таблицы payment_methods (id, code, name, is_kz_card, kz_card_currency, sort_order, is_active). Только is_active=1, по sort_order.
     */
    public function getPaymentMethods()
    {
        if ($this->cachePaymentMethods === null) {
            $this->cachePaymentMethods = $this->db->fetchAll(
                'SELECT id, code, name, is_kz_card, kz_card_currency, sort_order, is_active FROM payment_methods WHERE is_active = 1 ORDER BY sort_order ASC'
            );
        }
        return $this->cachePaymentMethods;
    }

    /**
     * Возвращает правило сервисного сбора для пары «клиент + тип услуги».
     * Нужно для расчёта сервисного сбора (процент или фикс, мин/макс, валюта).
     *
     * @param int $clientId     ID клиента (из таблицы clients).
     * @param int $serviceTypeId ID типа услуги (из таблицы service_types).
     * @return array|null Ассоциативный массив: fee_type, percent_value, fixed_value, min_value, max_value, currency. Если правила нет — null.
     */
    public function getServiceFeeRule($clientId, $serviceTypeId)
    {
        $key = (int) $clientId . '_' . (int) $serviceTypeId;
        if (array_key_exists($key, $this->cacheServiceFeeRules)) {
            return $this->cacheServiceFeeRules[$key];
        }
        $row = $this->db->fetchOne(
            'SELECT fee_type, percent_value, fixed_value, min_value, max_value, currency FROM service_fee_rules WHERE client_id = ? AND service_type_id = ?',
            [$clientId, $serviceTypeId]
        );
        $this->cacheServiceFeeRules[$key] = $row;
        return $row;
    }

    /**
     * Возвращает правило трансграничного сбора для комбинации клиент + тип услуги + колонка трансграна.
     * Колонка определяет, какое правило применять: обычный трансгран, карта КЗ в рублях или в USD/EUR.
     *
     * @param int    $clientId        ID клиента.
     * @param int    $serviceTypeId   ID типа услуги.
     * @param string $transgranColumn 'standard', 'kz_rub' или 'kz_usd_eur'.
     * @return array|null Массив: id, percent_value, min_value_low, min_value_high, threshold, min_currency, is_applicable, include_service_fee. id — для вызова getTransgranExceptions. Если правила нет — null.
     */
    public function getTransgranRule($clientId, $serviceTypeId, $transgranColumn)
    {
        $key = (int) $clientId . '_' . (int) $serviceTypeId . '_' . $transgranColumn;
        if (array_key_exists($key, $this->cacheTransgranRules)) {
            return $this->cacheTransgranRules[$key];
        }
        $row = $this->db->fetchOne(
            'SELECT id, percent_value, min_value_low, min_value_high, threshold, min_currency, is_applicable, include_service_fee FROM transgran_rules WHERE client_id = ? AND service_type_id = ? AND transgran_column = ?',
            [$clientId, $serviceTypeId, $transgranColumn]
        );
        $this->cacheTransgranRules[$key] = $row;
        return $row;
    }

    /**
     * Возвращает список исключений для одного правила трансграна.
     * Например: «если поставщик из Казахстана — трансгран не берётся» (exception_type=supplier_country, exception_value=KZ).
     *
     * @param int $transgranRuleId ID записи из таблицы transgran_rules.
     * @return array Массив элементов [ exception_type => ..., exception_value => ... ]. Пустой массив, если исключений нет.
     */
    public function getTransgranExceptions($transgranRuleId)
    {
        if (array_key_exists($transgranRuleId, $this->cacheTransgranExceptions)) {
            return $this->cacheTransgranExceptions[$transgranRuleId];
        }
        $rows = $this->db->fetchAll(
            'SELECT exception_type, exception_value FROM transgran_exceptions WHERE transgran_rule_id = ?',
            [$transgranRuleId]
        );
        $this->cacheTransgranExceptions[$transgranRuleId] = $rows;
        return $rows;
    }

    /**
     * Проверяет, попадает ли ситуация под исключение трансграна.
     * Например: для правила с id=5 есть исключение «supplier_country = KZ» — тогда при поставщике из Казахстана трансгран не применяется.
     *
     * @param int    $transgranRuleId ID правила трансграна.
     * @param string $exceptionType   Тип исключения (например 'supplier_country').
     * @param string $value           Значение для проверки (например 'KZ').
     * @return bool true, если такое исключение есть (т.е. трансгран по этому правилу не берётся); false — исключения нет.
     */
    public function checkTransgranException($transgranRuleId, $exceptionType, $value)
    {
        $exceptions = $this->getTransgranExceptions($transgranRuleId);
        foreach ($exceptions as $ex) {
            if (isset($ex['exception_type'], $ex['exception_value'])
                && $ex['exception_type'] === $exceptionType
                && (string) $ex['exception_value'] === (string) $value) {
                return true;
            }
        }
        return false;
    }

    /**
     * Возвращает все спецправила клиента (например «не брать стандартный трансгран для выписки и обмена»).
     * applicable_service_types и params возвращаются уже декодированными из JSON (массив и объект/массив).
     *
     * @param int $clientId ID клиента.
     * @return array Массив элементов: rule_type (строка), applicable_service_types (массив кодов услуг), params (массив/объект из JSON). Пустой массив, если спецправил нет.
     */
    public function getClientSpecialRules($clientId)
    {
        if (array_key_exists($clientId, $this->cacheClientSpecialRules)) {
            return $this->cacheClientSpecialRules[$clientId];
        }
        $rows = $this->db->fetchAll(
            'SELECT rule_type, applicable_service_types, params FROM client_special_rules WHERE client_id = ?',
            [$clientId]
        );
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'rule_type' => $row['rule_type'],
                'applicable_service_types' => $this->decodeJson($row['applicable_service_types'], []),
                'params' => $this->decodeJson($row['params'], []),
            ];
        }
        $this->cacheClientSpecialRules[$clientId] = $result;
        return $result;
    }

    /**
     * Проверяет, есть ли у клиента спецправило указанного типа, распространяющееся на данный тип услуги.
     * Используется, например, чтобы понять: для клиента RT по выписке (issue) не брать стандартный трансгран.
     *
     * @param int    $clientId        ID клиента.
     * @param string $ruleType        Тип спецправила (например 'no_transgran_standard').
     * @param string $serviceTypeCode Код типа услуги (например 'issue', 'exchange').
     * @return array|null Параметры спецправила (params), декодированные из JSON; если подходящего правила нет — null.
     */
    public function checkSpecialRule($clientId, $ruleType, $serviceTypeCode)
    {
        $rules = $this->getClientSpecialRules($clientId);
        foreach ($rules as $rule) {
            if ($rule['rule_type'] !== $ruleType) {
                continue;
            }
            $applicable = $rule['applicable_service_types'];
            if (is_array($applicable) && in_array($serviceTypeCode, $applicable, true)) {
                return $rule['params'];
            }
        }
        return null;
    }

    /**
     * Возвращает правило эквайринга для пары «клиент + способ оплаты».
     * Нужно для расчёта процента эквайринга и проверки exclude_service_type (при каком типе услуги эквайринг = 0).
     *
     * @param int $clientId        ID клиента.
     * @param int $paymentMethodId ID способа оплаты (из таблицы payment_methods).
     * @return array|null Массив: percent_value, exclude_service_type. Если правила нет — null.
     */
    public function getAcquiringRule($clientId, $paymentMethodId)
    {
        $key = (int) $clientId . '_' . (int) $paymentMethodId;
        if (array_key_exists($key, $this->cacheAcquiringRules)) {
            return $this->cacheAcquiringRules[$key];
        }
        $row = $this->db->fetchOne(
            'SELECT percent_value, exclude_service_type FROM acquiring_rules WHERE client_id = ? AND payment_method_id = ?',
            [$clientId, $paymentMethodId]
        );
        $this->cacheAcquiringRules[$key] = $row;
        return $row;
    }

    /**
     * Определяет колонку трансграна по коду способа оплаты.
     * От неё зависит, какое правило из transgran_rules использовать (standard / kz_rub / kz_usd_eur).
     *
     * @param string $paymentMethodCode Код способа оплаты (например 'card_kz_rub', 'card_brs').
     * @return string 'kz_rub', 'kz_usd_eur' или 'standard'.
     */
    public function determineTransgranColumn($paymentMethodCode)
    {
        if ($paymentMethodCode === 'card_kz_rub') {
            return 'kz_rub';
        }
        if ($paymentMethodCode === 'card_kz_usd_eur') {
            return 'kz_usd_eur';
        }
        return 'standard';
    }

    /**
     * Определяет, нужен ли вообще расчёт трансграничного сбора.
     * Трансгран берётся, когда страна клиента и страна поставщика различаются (международная операция).
     *
     * @param string $clientCountry   Код страны клиента (например 'RU', 'KZ').
     * @param string $supplierCountry Код страны поставщика (например 'RU', 'KZ').
     * @return bool true — страны разные, трансгран нужен; false — одна страна, трансгран не нужен.
     */
    public function isTransgranNeeded($clientCountry, $supplierCountry)
    {
        return (string) $clientCountry !== (string) $supplierCountry;
    }

    /**
     * Очищает все кэши движка.
     * Вызывать после изменения данных в БД (через админку или скрипты), чтобы при следующих запросах правила подтягивались заново.
     *
     * @return void
     */
    public function clearCache()
    {
        $this->cacheClients = null;
        $this->cacheClientByCode = [];
        $this->cacheServiceTypes = null;
        $this->cachePaymentMethods = null;
        $this->cacheServiceFeeRules = [];
        $this->cacheTransgranRules = [];
        $this->cacheTransgranExceptions = [];
        $this->cacheClientSpecialRules = [];
        $this->cacheAcquiringRules = [];
    }

    /**
     * Декодирует JSON-строку в массив или объект. При ошибке возвращает значение по умолчанию.
     *
     * @param string|null $json    Строка JSON (или null).
     * @param mixed       $default Значение, если декодирование не удалось.
     * @return mixed
     */
    private function decodeJson($json, $default = [])
    {
        if ($json === null || $json === '') {
            return $default;
        }
        $decoded = json_decode($json, true);
        return $decoded !== null ? $decoded : $default;
    }
}
