<?php
/**
 * Менеджер администрирования правил расчёта сборов АВИА.
 *
 * Обеспечивает CRUD-операции для таблиц: clients, service_fee_rules,
 * transgran_rules, transgran_exceptions, acquiring_rules, client_special_rules.
 * Справочники service_types и payment_methods — только чтение для выпадающих списков.
 * Все методы с валидацией; при ошибках выбрасывается Exception с текстом на русском.
 */

namespace App;

class AdminManager
{
    /** @var Database */
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    // --- Клиенты ---

    /**
     * Возвращает всех клиентов (включая неактивных), отсортированных по sort_order.
     *
     * @return array
     */
    public function getClients()
    {
        return $this->db->fetchAll('SELECT * FROM clients ORDER BY sort_order ASC');
    }

    /**
     * Возвращает одного клиента по id или null.
     *
     * @param int $id
     * @return array|null
     */
    public function getClient($id)
    {
        $row = $this->db->fetchOne('SELECT * FROM clients WHERE id = ?', [(int) $id]);
        return $row ?: null;
    }

    /**
     * Создаёт нового клиента. Валидация: code уникален, country RU/KZ, result_currency RUB/EUR.
     *
     * @param array $data code, name, country, result_currency, use_max_rule, agent_hint, sort_order, is_active
     * @return int id созданной записи
     * @throws \Exception
     */
    public function createClient(array $data)
    {
        $this->validateRequired($data, ['code', 'name', 'country']);
        $code = trim((string) $data['code']);
        if ($code === '') {
            throw new \Exception('Код клиента не может быть пустым.');
        }
        if ($this->db->fetchOne('SELECT id FROM clients WHERE code = ?', [$code])) {
            throw new \Exception('Клиент с таким кодом уже существует.');
        }
        $this->validateEnum($data['country'], ['RU', 'KZ'], 'Страна');
        $resultCurrency = isset($data['result_currency']) ? $data['result_currency'] : 'RUB';
        $this->validateEnum($resultCurrency, ['RUB', 'EUR'], 'Валюта результата');
        $name = trim((string) $data['name']);
        if ($name === '') {
            throw new \Exception('Название клиента не может быть пустым.');
        }
        $useMaxRule = isset($data['use_max_rule']) ? (int) $data['use_max_rule'] : 0;
        $agentHint = isset($data['agent_hint']) ? trim((string) $data['agent_hint']) : null;
        if ($agentHint === '') {
            $agentHint = null;
        }
        $sortOrder = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;
        $isActive = isset($data['is_active']) ? (int) $data['is_active'] : 1;
        $this->db->execute(
            'INSERT INTO clients (code, name, country, result_currency, use_max_rule, agent_hint, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$code, $name, $data['country'], $resultCurrency, $useMaxRule, $agentHint, $sortOrder, $isActive]
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * Обновляет клиента по id. Валидация: code уникален среди других клиентов.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function updateClient($id, array $data)
    {
        $this->validateRequired($data, ['code', 'name', 'country']);
        $code = trim((string) $data['code']);
        if ($code === '') {
            throw new \Exception('Код клиента не может быть пустым.');
        }
        $existing = $this->db->fetchOne('SELECT id FROM clients WHERE code = ? AND id != ?', [$code, (int) $id]);
        if ($existing) {
            throw new \Exception('Клиент с таким кодом уже существует.');
        }
        $this->validateEnum($data['country'], ['RU', 'KZ'], 'Страна');
        $resultCurrency = isset($data['result_currency']) ? $data['result_currency'] : 'RUB';
        $this->validateEnum($resultCurrency, ['RUB', 'EUR'], 'Валюта результата');
        $name = trim((string) $data['name']);
        if ($name === '') {
            throw new \Exception('Название клиента не может быть пустым.');
        }
        $useMaxRule = isset($data['use_max_rule']) ? (int) $data['use_max_rule'] : 0;
        $agentHint = isset($data['agent_hint']) ? trim((string) $data['agent_hint']) : null;
        if ($agentHint === '') {
            $agentHint = null;
        }
        $sortOrder = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;
        $isActive = isset($data['is_active']) ? (int) $data['is_active'] : 1;
        $this->db->execute(
            'UPDATE clients SET code = ?, name = ?, country = ?, result_currency = ?, use_max_rule = ?, agent_hint = ?, sort_order = ?, is_active = ? WHERE id = ?',
            [$code, $name, $data['country'], $resultCurrency, $useMaxRule, $agentHint, $sortOrder, $isActive, (int) $id]
        );
        return true;
    }

    /**
     * Удаляет клиента по id. Связанные правила удаляются каскадно.
     *
     * @param int $id
     * @return bool
     */
    public function deleteClient($id)
    {
        $this->db->execute('DELETE FROM clients WHERE id = ?', [(int) $id]);
        return true;
    }

    /**
     * Переключает is_active клиента (1→0 или 0→1). Возвращает новое значение.
     *
     * @param int $id
     * @return int 0 или 1
     */
    public function toggleClient($id)
    {
        $this->db->execute('UPDATE clients SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?', [(int) $id]);
        $row = $this->db->fetchOne('SELECT is_active FROM clients WHERE id = ?', [(int) $id]);
        return $row ? (int) $row['is_active'] : 0;
    }

    // --- Сервисный сбор ---

    /**
     * Все правила сервисного сбора для клиента с JOIN по service_types.
     *
     * @param int $clientId
     * @return array
     */
    public function getServiceFeeRules($clientId)
    {
        return $this->db->fetchAll(
            'SELECT sfr.*, st.code AS service_type_code, st.name AS service_type_name FROM service_fee_rules sfr JOIN service_types st ON sfr.service_type_id = st.id WHERE sfr.client_id = ? ORDER BY st.sort_order',
            [(int) $clientId]
        );
    }

    /**
     * Одно правило сервисного сбора по id с JOIN.
     *
     * @param int $id
     * @return array|null
     */
    public function getServiceFeeRule($id)
    {
        $row = $this->db->fetchOne(
            'SELECT sfr.*, st.code AS service_type_code, st.name AS service_type_name FROM service_fee_rules sfr JOIN service_types st ON sfr.service_type_id = st.id WHERE sfr.id = ?',
            [(int) $id]
        );
        return $row ?: null;
    }

    /**
     * Создаёт правило сервисного сбора. Валидация: fee_type, дубликат (client_id + service_type_id).
     *
     * @param array $data
     * @return int
     * @throws \Exception
     */
    public function createServiceFeeRule(array $data)
    {
        $this->validateRequired($data, ['client_id', 'service_type_id', 'fee_type']);
        $this->validateEnum($data['fee_type'], ['percent', 'fixed'], 'Тип сбора');
        $currency = isset($data['currency']) ? $data['currency'] : 'RUB';
        $this->validateEnum($currency, ['RUB', 'EUR'], 'Валюта');
        $clientId = (int) $data['client_id'];
        $serviceTypeId = (int) $data['service_type_id'];
        if ($this->db->fetchOne('SELECT id FROM service_fee_rules WHERE client_id = ? AND service_type_id = ?', [$clientId, $serviceTypeId])) {
            throw new \Exception('Правило для этой пары клиент+тип услуги уже существует.');
        }
        if ($data['fee_type'] === 'percent') {
            $percentValue = isset($data['percent_value']) ? (float) $data['percent_value'] : 0.0;
            if ($percentValue < 0) {
                throw new \Exception('Процент не может быть отрицательным.');
            }
            $fixedValue = null;
        } else {
            $fixedValue = isset($data['fixed_value']) ? (float) $data['fixed_value'] : 0.0;
            if ($fixedValue < 0) {
                throw new \Exception('Фиксированная сумма не может быть отрицательной.');
            }
            $percentValue = null;
        }
        $minValue = isset($data['min_value']) && $data['min_value'] !== '' && $data['min_value'] !== null ? (float) $data['min_value'] : null;
        $maxValue = isset($data['max_value']) && $data['max_value'] !== '' && $data['max_value'] !== null ? (float) $data['max_value'] : null;
        $this->db->execute(
            'INSERT INTO service_fee_rules (client_id, service_type_id, fee_type, percent_value, fixed_value, min_value, max_value, currency) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$clientId, $serviceTypeId, $data['fee_type'], $percentValue, $fixedValue, $minValue, $maxValue, $currency]
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * Обновляет правило сервисного сбора.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function updateServiceFeeRule($id, array $data)
    {
        $this->validateRequired($data, ['client_id', 'service_type_id', 'fee_type']);
        $this->validateEnum($data['fee_type'], ['percent', 'fixed'], 'Тип сбора');
        $currency = isset($data['currency']) ? $data['currency'] : 'RUB';
        $this->validateEnum($currency, ['RUB', 'EUR'], 'Валюта');
        $clientId = (int) $data['client_id'];
        $serviceTypeId = (int) $data['service_type_id'];
        $existing = $this->db->fetchOne('SELECT id FROM service_fee_rules WHERE client_id = ? AND service_type_id = ? AND id != ?', [$clientId, $serviceTypeId, (int) $id]);
        if ($existing) {
            throw new \Exception('Правило для этой пары клиент+тип услуги уже существует.');
        }
        if ($data['fee_type'] === 'percent') {
            $percentValue = isset($data['percent_value']) ? (float) $data['percent_value'] : null;
            if ($percentValue !== null && $percentValue < 0) {
                throw new \Exception('Процент не может быть отрицательным.');
            }
            $fixedValue = null;
        } else {
            $fixedValue = isset($data['fixed_value']) ? (float) $data['fixed_value'] : null;
            if ($fixedValue !== null && $fixedValue < 0) {
                throw new \Exception('Фиксированная сумма не может быть отрицательной.');
            }
            $percentValue = null;
        }
        $minValue = isset($data['min_value']) && $data['min_value'] !== '' && $data['min_value'] !== null ? (float) $data['min_value'] : null;
        $maxValue = isset($data['max_value']) && $data['max_value'] !== '' && $data['max_value'] !== null ? (float) $data['max_value'] : null;
        $this->db->execute(
            'UPDATE service_fee_rules SET client_id = ?, service_type_id = ?, fee_type = ?, percent_value = ?, fixed_value = ?, min_value = ?, max_value = ?, currency = ? WHERE id = ?',
            [$clientId, $serviceTypeId, $data['fee_type'], $percentValue, $fixedValue, $minValue, $maxValue, $currency, (int) $id]
        );
        return true;
    }

    /**
     * Удаляет правило сервисного сбора.
     *
     * @param int $id
     * @return bool
     */
    public function deleteServiceFeeRule($id)
    {
        $this->db->execute('DELETE FROM service_fee_rules WHERE id = ?', [(int) $id]);
        return true;
    }

    // --- Трансгран ---

    /**
     * Все правила трансграна для клиента с JOIN по service_types.
     *
     * @param int $clientId
     * @return array
     */
    public function getTransgranRules($clientId)
    {
        return $this->db->fetchAll(
            'SELECT tr.*, st.code AS service_type_code, st.name AS service_type_name FROM transgran_rules tr JOIN service_types st ON tr.service_type_id = st.id WHERE tr.client_id = ? ORDER BY tr.transgran_column, st.sort_order',
            [(int) $clientId]
        );
    }

    /**
     * Одно правило трансграна по id.
     *
     * @param int $id
     * @return array|null
     */
    public function getTransgranRule($id)
    {
        $row = $this->db->fetchOne(
            'SELECT tr.*, st.code AS service_type_code, st.name AS service_type_name FROM transgran_rules tr JOIN service_types st ON tr.service_type_id = st.id WHERE tr.id = ?',
            [(int) $id]
        );
        return $row ?: null;
    }

    /**
     * Создаёт правило трансграна.
     *
     * @param array $data
     * @return int
     * @throws \Exception
     */
    public function createTransgranRule(array $data)
    {
        $this->validateRequired($data, ['client_id', 'service_type_id', 'transgran_column']);
        $this->validateEnum($data['transgran_column'], ['standard', 'kz_rub', 'kz_usd_eur'], 'Колонка трансграна');
        $percentValue = isset($data['percent_value']) ? (float) $data['percent_value'] : 0.0;
        if ($percentValue < 0) {
            throw new \Exception('Процент трансграна не может быть отрицательным.');
        }
        $clientId = (int) $data['client_id'];
        $serviceTypeId = (int) $data['service_type_id'];
        $column = $data['transgran_column'];
        if ($this->db->fetchOne('SELECT id FROM transgran_rules WHERE client_id = ? AND service_type_id = ? AND transgran_column = ?', [$clientId, $serviceTypeId, $column])) {
            throw new \Exception('Правило для этой комбинации уже существует.');
        }
        $minLow = isset($data['min_value_low']) && $data['min_value_low'] !== '' && $data['min_value_low'] !== null ? (float) $data['min_value_low'] : null;
        $minHigh = isset($data['min_value_high']) && $data['min_value_high'] !== '' && $data['min_value_high'] !== null ? (float) $data['min_value_high'] : null;
        $threshold = isset($data['threshold']) && $data['threshold'] !== '' && $data['threshold'] !== null ? (float) $data['threshold'] : 1000.0;
        $minCurrency = isset($data['min_currency']) && $data['min_currency'] !== '' ? $data['min_currency'] : null;
        $isApplicable = isset($data['is_applicable']) ? (int) $data['is_applicable'] : 1;
        $includeServiceFee = isset($data['include_service_fee']) ? (int) $data['include_service_fee'] : 1;
        $this->db->execute(
            'INSERT INTO transgran_rules (client_id, service_type_id, transgran_column, percent_value, min_value_low, min_value_high, threshold, min_currency, is_applicable, include_service_fee) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$clientId, $serviceTypeId, $column, $percentValue, $minLow, $minHigh, $threshold, $minCurrency, $isApplicable, $includeServiceFee]
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * Обновляет правило трансграна.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function updateTransgranRule($id, array $data)
    {
        $this->validateRequired($data, ['client_id', 'service_type_id', 'transgran_column']);
        $this->validateEnum($data['transgran_column'], ['standard', 'kz_rub', 'kz_usd_eur'], 'Колонка трансграна');
        $percentValue = isset($data['percent_value']) ? (float) $data['percent_value'] : 0.0;
        if ($percentValue < 0) {
            throw new \Exception('Процент трансграна не может быть отрицательным.');
        }
        $clientId = (int) $data['client_id'];
        $serviceTypeId = (int) $data['service_type_id'];
        $column = $data['transgran_column'];
        $existing = $this->db->fetchOne('SELECT id FROM transgran_rules WHERE client_id = ? AND service_type_id = ? AND transgran_column = ? AND id != ?', [$clientId, $serviceTypeId, $column, (int) $id]);
        if ($existing) {
            throw new \Exception('Правило для этой комбинации уже существует.');
        }
        $minLow = isset($data['min_value_low']) && $data['min_value_low'] !== '' && $data['min_value_low'] !== null ? (float) $data['min_value_low'] : null;
        $minHigh = isset($data['min_value_high']) && $data['min_value_high'] !== '' && $data['min_value_high'] !== null ? (float) $data['min_value_high'] : null;
        $threshold = isset($data['threshold']) && $data['threshold'] !== '' && $data['threshold'] !== null ? (float) $data['threshold'] : 1000.0;
        $minCurrency = isset($data['min_currency']) && $data['min_currency'] !== '' ? $data['min_currency'] : null;
        $isApplicable = isset($data['is_applicable']) ? (int) $data['is_applicable'] : 1;
        $includeServiceFee = isset($data['include_service_fee']) ? (int) $data['include_service_fee'] : 1;
        $this->db->execute(
            'UPDATE transgran_rules SET client_id = ?, service_type_id = ?, transgran_column = ?, percent_value = ?, min_value_low = ?, min_value_high = ?, threshold = ?, min_currency = ?, is_applicable = ?, include_service_fee = ? WHERE id = ?',
            [$clientId, $serviceTypeId, $column, $percentValue, $minLow, $minHigh, $threshold, $minCurrency, $isApplicable, $includeServiceFee, (int) $id]
        );
        return true;
    }

    /**
     * Удаляет правило трансграна. Исключения удаляются каскадно.
     *
     * @param int $id
     * @return bool
     */
    public function deleteTransgranRule($id)
    {
        $this->db->execute('DELETE FROM transgran_rules WHERE id = ?', [(int) $id]);
        return true;
    }

    // --- Исключения трансграна ---

    /**
     * Все исключения для правила трансграна.
     *
     * @param int $transgranRuleId
     * @return array
     */
    public function getTransgranExceptions($transgranRuleId)
    {
        return $this->db->fetchAll('SELECT * FROM transgran_exceptions WHERE transgran_rule_id = ?', [(int) $transgranRuleId]);
    }

    /**
     * Создаёт исключение трансграна.
     *
     * @param array $data transgran_rule_id, exception_type, exception_value
     * @return int
     * @throws \Exception
     */
    public function createTransgranException(array $data)
    {
        $this->validateRequired($data, ['transgran_rule_id', 'exception_type', 'exception_value']);
        $ruleId = (int) $data['transgran_rule_id'];
        if (!$this->db->fetchOne('SELECT id FROM transgran_rules WHERE id = ?', [$ruleId])) {
            throw new \Exception('Правило трансграна не найдено.');
        }
        $this->db->execute(
            'INSERT INTO transgran_exceptions (transgran_rule_id, exception_type, exception_value) VALUES (?, ?, ?)',
            [$ruleId, trim((string) $data['exception_type']), trim((string) $data['exception_value'])]
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * Удаляет исключение трансграна.
     *
     * @param int $id
     * @return bool
     */
    public function deleteTransgranException($id)
    {
        $this->db->execute('DELETE FROM transgran_exceptions WHERE id = ?', [(int) $id]);
        return true;
    }

    // --- Эквайринг ---

    /**
     * Все правила эквайринга для клиента с JOIN по payment_methods.
     *
     * @param int $clientId
     * @return array
     */
    public function getAcquiringRules($clientId)
    {
        return $this->db->fetchAll(
            'SELECT ar.*, pm.code AS payment_method_code, pm.name AS payment_method_name FROM acquiring_rules ar JOIN payment_methods pm ON ar.payment_method_id = pm.id WHERE ar.client_id = ? ORDER BY pm.sort_order',
            [(int) $clientId]
        );
    }

    /**
     * Одно правило эквайринга по id.
     *
     * @param int $id
     * @return array|null
     */
    public function getAcquiringRule($id)
    {
        $row = $this->db->fetchOne(
            'SELECT ar.*, pm.code AS payment_method_code, pm.name AS payment_method_name FROM acquiring_rules ar JOIN payment_methods pm ON ar.payment_method_id = pm.id WHERE ar.id = ?',
            [(int) $id]
        );
        return $row ?: null;
    }

    /**
     * Создаёт правило эквайринга.
     *
     * @param array $data
     * @return int
     * @throws \Exception
     */
    public function createAcquiringRule(array $data)
    {
        $this->validateRequired($data, ['client_id', 'payment_method_id']);
        $percentValue = isset($data['percent_value']) ? (float) $data['percent_value'] : 0.0;
        if ($percentValue < 0) {
            throw new \Exception('Процент эквайринга не может быть отрицательным.');
        }
        $clientId = (int) $data['client_id'];
        $paymentMethodId = (int) $data['payment_method_id'];
        if ($this->db->fetchOne('SELECT id FROM acquiring_rules WHERE client_id = ? AND payment_method_id = ?', [$clientId, $paymentMethodId])) {
            throw new \Exception('Правило для этой пары клиент+способ оплаты уже существует.');
        }
        $excludeType = isset($data['exclude_service_type']) && $data['exclude_service_type'] !== '' ? trim((string) $data['exclude_service_type']) : null;
        if ($excludeType !== null && !in_array($excludeType, ['issue', 'emd', 'refund', 'exchange'], true)) {
            throw new \Exception('Недопустимый код типа услуги для исключения.');
        }
        $this->db->execute(
            'INSERT INTO acquiring_rules (client_id, payment_method_id, percent_value, exclude_service_type) VALUES (?, ?, ?, ?)',
            [$clientId, $paymentMethodId, $percentValue, $excludeType]
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * Обновляет правило эквайринга.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function updateAcquiringRule($id, array $data)
    {
        $this->validateRequired($data, ['client_id', 'payment_method_id']);
        $percentValue = isset($data['percent_value']) ? (float) $data['percent_value'] : 0.0;
        if ($percentValue < 0) {
            throw new \Exception('Процент эквайринга не может быть отрицательным.');
        }
        $clientId = (int) $data['client_id'];
        $paymentMethodId = (int) $data['payment_method_id'];
        $existing = $this->db->fetchOne('SELECT id FROM acquiring_rules WHERE client_id = ? AND payment_method_id = ? AND id != ?', [$clientId, $paymentMethodId, (int) $id]);
        if ($existing) {
            throw new \Exception('Правило для этой пары клиент+способ оплаты уже существует.');
        }
        $excludeType = isset($data['exclude_service_type']) && $data['exclude_service_type'] !== '' ? trim((string) $data['exclude_service_type']) : null;
        if ($excludeType !== null && !in_array($excludeType, ['issue', 'emd', 'refund', 'exchange'], true)) {
            throw new \Exception('Недопустимый код типа услуги для исключения.');
        }
        $this->db->execute(
            'UPDATE acquiring_rules SET client_id = ?, payment_method_id = ?, percent_value = ?, exclude_service_type = ? WHERE id = ?',
            [$clientId, $paymentMethodId, $percentValue, $excludeType, (int) $id]
        );
        return true;
    }

    /**
     * Удаляет правило эквайринга.
     *
     * @param int $id
     * @return bool
     */
    public function deleteAcquiringRule($id)
    {
        $this->db->execute('DELETE FROM acquiring_rules WHERE id = ?', [(int) $id]);
        return true;
    }

    // --- Спецправила ---

    /**
     * Все спецправила клиента.
     *
     * @param int $clientId
     * @return array
     */
    public function getSpecialRules($clientId)
    {
        return $this->db->fetchAll('SELECT * FROM client_special_rules WHERE client_id = ?', [(int) $clientId]);
    }

    /**
     * Одно спецправило по id.
     *
     * @param int $id
     * @return array|null
     */
    public function getSpecialRule($id)
    {
        $row = $this->db->fetchOne('SELECT * FROM client_special_rules WHERE id = ?', [(int) $id]);
        return $row ?: null;
    }

    /**
     * Создаёт спецправило. applicable_service_types и params сохраняются как JSON.
     *
     * @param array $data
     * @return int
     * @throws \Exception
     */
    public function createSpecialRule(array $data)
    {
        $this->validateRequired($data, ['client_id', 'rule_type']);
        $clientId = (int) $data['client_id'];
        $ruleType = trim((string) $data['rule_type']);
        $applicable = isset($data['applicable_service_types']) ? $data['applicable_service_types'] : [];
        $params = isset($data['params']) ? $data['params'] : [];
        $applicableJson = is_array($applicable) ? json_encode($applicable, JSON_UNESCAPED_UNICODE) : '[]';
        $paramsJson = is_array($params) || is_object($params) ? json_encode($params, JSON_UNESCAPED_UNICODE) : '{}';
        $this->db->execute(
            'INSERT INTO client_special_rules (client_id, rule_type, applicable_service_types, params) VALUES (?, ?, ?, ?)',
            [$clientId, $ruleType, $applicableJson, $paramsJson]
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * Обновляет спецправило.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public function updateSpecialRule($id, array $data)
    {
        $this->validateRequired($data, ['client_id', 'rule_type']);
        $clientId = (int) $data['client_id'];
        $ruleType = trim((string) $data['rule_type']);
        $applicable = isset($data['applicable_service_types']) ? $data['applicable_service_types'] : [];
        $params = isset($data['params']) ? $data['params'] : [];
        $applicableJson = is_array($applicable) ? json_encode($applicable, JSON_UNESCAPED_UNICODE) : '[]';
        $paramsJson = is_array($params) || is_object($params) ? json_encode($params, JSON_UNESCAPED_UNICODE) : '{}';
        $this->db->execute(
            'UPDATE client_special_rules SET client_id = ?, rule_type = ?, applicable_service_types = ?, params = ? WHERE id = ?',
            [$clientId, $ruleType, $applicableJson, $paramsJson, (int) $id]
        );
        return true;
    }

    /**
     * Удаляет спецправило.
     *
     * @param int $id
     * @return bool
     */
    public function deleteSpecialRule($id)
    {
        $this->db->execute('DELETE FROM client_special_rules WHERE id = ?', [(int) $id]);
        return true;
    }

    // --- Справочники ---

    /**
     * Все типы услуг (для выпадающих списков в админке).
     *
     * @return array
     */
    public function getServiceTypes()
    {
        return $this->db->fetchAll('SELECT * FROM service_types ORDER BY sort_order');
    }

    /**
     * Все способы оплаты.
     *
     * @return array
     */
    public function getPaymentMethods()
    {
        return $this->db->fetchAll('SELECT * FROM payment_methods ORDER BY sort_order');
    }

    /**
     * Проверяет наличие обязательных полей в массиве. При отсутствии — Exception.
     *
     * @param array  $data
     * @param array  $fields
     * @return void
     * @throws \Exception
     */
    private function validateRequired(array $data, array $fields)
    {
        foreach ($fields as $f) {
            if (!array_key_exists($f, $data)) {
                throw new \Exception('Не указано обязательное поле: ' . $f . '.');
            }
        }
    }

    /**
     * Проверяет, что значение входит в список допустимых.
     *
     * @param mixed  $value
     * @param array  $allowed
     * @param string $fieldName Название поля для сообщения об ошибке.
     * @return void
     * @throws \Exception
     */
    private function validateEnum($value, array $allowed, $fieldName)
    {
        if (!in_array((string) $value, $allowed, true)) {
            throw new \Exception($fieldName . ' допускает только значения: ' . implode(', ', $allowed) . '.');
        }
    }
}
