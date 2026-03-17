<?php
/**
 * Калькулятор сборов АВИА — основной класс расчёта.
 *
 * Выполняет полный расчёт итогового сбора по заказу: сервисный сбор,
 * трансграничный сбор, эквайринг. Использует RuleEngine для правил и
 * CurrencyConverter для конвертации валют. Результат включает детализацию
 * по услугам, итоги, мультивалютный вывод и текстовые формулы.
 */

namespace App;

class Calculator
{
    /** @var RuleEngine Движок правил (клиенты, типы услуг, правила сборов) */
    private $ruleEngine;

    /** @var CurrencyConverter Конвертер валют (настраивается в calculate() по входным данным) */
    private $converter;

    /**
     * Создаёт калькулятор с заданными движком правил и конвертером валют.
     *
     * @param RuleEngine       $ruleEngine Движок правил (загружает данные из БД).
     * @param CurrencyConverter $converter  Конвертер валют (в calculate() настраивается по клиенту и курсам из $input).
     */
    public function __construct(RuleEngine $ruleEngine, CurrencyConverter $converter)
    {
        $this->ruleEngine = $ruleEngine;
        $this->converter = $converter;
    }

    /**
     * Выполняет полный расчёт сборов по заказу.
     *
     * Принимает массив входных данных (клиент, способ оплаты, страна поставщика,
     * валюты, курсы, список услуг) и возвращает массив с детализацией по услугам,
     * итогами, формулами и признаком мультивалютного результата.
     *
     * @param array $input Входные данные (client_code, payment_method_code, supplier_country, settlement_currency, rate_rs_tls, rate_kz, services[]).
     * @return array Массив результата (services, totals, working_currency, formulas, applied_rules и т.д.).
     * @throws \InvalidArgumentException Если клиент не найден или нет услуг.
     */
    public function calculate(array $input)
    {
        // --- ШАГ 0: Загрузка данных и настройка конвертера ---
        $client = $this->ruleEngine->getClientByCode(isset($input['client_code']) ? $input['client_code'] : '');
        if (!$client) {
            throw new \InvalidArgumentException('Клиент с указанным кодом не найден.');
        }
        $paymentMethod = $this->getPaymentMethodByCode(isset($input['payment_method_code']) ? $input['payment_method_code'] : '');
        if (!$paymentMethod) {
            throw new \InvalidArgumentException('Способ оплаты с указанным кодом не найден.');
        }
        $servicesInput = isset($input['services']) && is_array($input['services']) ? $input['services'] : [];
        if (empty($servicesInput)) {
            throw new \InvalidArgumentException('Список услуг не может быть пустым.');
        }

        $workingCurrency = isset($client['result_currency']) ? $client['result_currency'] : 'RUB';
        $this->converter->setWorkingCurrency($workingCurrency);
        $this->converter->setRateRsTls(isset($input['rate_rs_tls']) ? (float) $input['rate_rs_tls'] : 1.0);
        $this->converter->setRateKz(isset($input['rate_kz']) ? (float) $input['rate_kz'] : 0.0);

        $supplierCountry = isset($input['supplier_country']) ? (string) $input['supplier_country'] : 'RU';
        $settlementCurrency = isset($input['settlement_currency']) ? (string) $input['settlement_currency'] : $workingCurrency;

        $transgranNeeded = (string) $client['country'] !== (string) $supplierCountry;
        $transgranColumn = $this->ruleEngine->determineTransgranColumn($input['payment_method_code']);
        $useMaxRule = !empty($client['use_max_rule']);
        $isKzCard = !empty($paymentMethod['is_kz_card']);

        $appliedRules = [
            'client' => $client['name'],
            'transgran_column' => $transgranColumn,
            'transgran_needed' => $transgranNeeded,
            'special_rules' => [],
        ];

        $servicesResult = [];
        $invoiceCurrencies = [];

        // --- ШАГ 3: Расчёт по каждой услуге ---
        foreach ($servicesInput as $svc) {
            $serviceTypeCode = isset($svc['service_type_code']) ? $svc['service_type_code'] : '';
            $serviceType = $this->getServiceTypeByCode($serviceTypeCode);
            if (!$serviceType) {
                continue;
            }
            $amount = isset($svc['amount']) ? (float) $svc['amount'] : 0.0;
            $invoiceCurrency = isset($svc['invoice_currency']) ? $svc['invoice_currency'] : 'RUB';
            $invoiceCurrencies[] = $invoiceCurrency;

            $amountWork = $this->converter->convertToWorking($amount, $invoiceCurrency);
            $amountWork = $this->converter->round($amountWork);

            $feeRule = $this->ruleEngine->getServiceFeeRule($client['id'], $serviceType['id']);
            $serviceFeeBeforeMax = 0.0;
            if ($feeRule) {
                $serviceFeeBeforeMax = $this->calculateServiceFee($amountWork, $feeRule);
                $serviceFeeBeforeMax = $this->converter->round($serviceFeeBeforeMax);
            }

            $transgranBeforeMax = 0.0;
            if ($transgranNeeded && $serviceTypeCode !== 'refund') {
                $tRule = $this->ruleEngine->getTransgranRule($client['id'], $serviceType['id'], $transgranColumn);
                $transgranBeforeMax = $this->calculateTransgran(
                    $amountWork,
                    $serviceFeeBeforeMax,
                    $tRule,
                    $amount,
                    $invoiceCurrency,
                    $settlementCurrency,
                    $supplierCountry,
                    $client['id'],
                    $serviceTypeCode,
                    $transgranColumn,
                    $isKzCard
                );
                $transgranBeforeMax = $this->converter->round($transgranBeforeMax);
            }

            list($serviceFeeFinal, $transgranFinal, $maxRuleWinner) = $this->applyMaxRule(
                $serviceFeeBeforeMax,
                $transgranBeforeMax,
                $useMaxRule
            );

            if ($maxRuleWinner !== null) {
                $appliedRules['special_rules'][] = 'use_max_rule: ' . $maxRuleWinner . ' для ' . $serviceTypeCode;
            }

            $feeFormulaType = $feeRule && isset($feeRule['fee_type']) ? $feeRule['fee_type'] : null;
            $feeFormulaPercent = $feeRule && isset($feeRule['percent_value']) ? $feeRule['percent_value'] : null;
            $feeFormulaFixed = $feeRule && isset($feeRule['fixed_value']) ? $feeRule['fixed_value'] : null;
            $feeFormulaBase = $amountWork;

            $servicesResult[] = [
                'service_type_code' => $serviceTypeCode,
                'service_type_name' => $serviceType['name'],
                'original_amount' => $amount,
                'invoice_currency' => $invoiceCurrency,
                'amount_work' => $amountWork,
                'service_fee' => $serviceFeeFinal,
                'service_fee_before_max_rule' => $serviceFeeBeforeMax,
                'transgran' => $transgranFinal,
                'transgran_before_max_rule' => $transgranBeforeMax,
                'max_rule_applied' => $maxRuleWinner !== null,
                'max_rule_winner' => $maxRuleWinner,
                'fee_formula_type' => $feeFormulaType,
                'fee_formula_percent' => $feeFormulaPercent,
                'fee_formula_fixed' => $feeFormulaFixed,
                'fee_formula_base' => $feeFormulaBase,
            ];
        }

        // --- ШАГ 4: Суммирование по заказу ---
        $totalServiceAmount = 0.0;
        $totalServiceFee = 0.0;
        $totalTransgran = 0.0;
        foreach ($servicesResult as $s) {
            $totalServiceAmount += $s['amount_work'];
            $totalServiceFee += $s['service_fee'];
            $totalTransgran += $s['transgran'];
        }
        $totalServiceAmount = $this->converter->round($totalServiceAmount);
        $totalServiceFee = $this->converter->round($totalServiceFee);
        $totalTransgran = $this->converter->round($totalTransgran);

        // --- ШАГ 5: Эквайринг ---
        $acquiringRule = $this->ruleEngine->getAcquiringRule($client['id'], $paymentMethod['id']);
        $totalAcquiring = $this->calculateAcquiring(
            $totalServiceAmount,
            $totalServiceFee,
            $totalTransgran,
            $paymentMethod['code'],
            $acquiringRule,
            $servicesResult
        );
        $totalAcquiring = $this->converter->round($totalAcquiring);

        // --- ШАГ 6: Итоги ---
        $totalFees = $this->converter->round($totalServiceFee + $totalTransgran + $totalAcquiring);
        $totalOrder = $this->converter->round($totalServiceAmount + $totalFees);

        $totals = [
            'total_service_amount' => $totalServiceAmount,
            'total_service_fee' => $totalServiceFee,
            'total_transgran' => $totalTransgran,
            'total_acquiring' => $totalAcquiring,
            'total_fees' => $totalFees,
            'total_order' => $totalOrder,
        ];

        // --- ШАГ 7: Мультивалютный результат ---
        $multiCurrency = $this->converter->needsMultiCurrency($settlementCurrency, $invoiceCurrencies);
        $secondCurrency = $this->converter->getSecondCurrency($settlementCurrency, $invoiceCurrencies);
        $totalsSecond = null;
        if ($multiCurrency && $secondCurrency !== null) {
            $totalsSecond = [
                'total_service_amount' => $this->converter->round($this->converter->convertFromWorking($totals['total_service_amount'], $secondCurrency)),
                'total_service_fee' => $this->converter->round($this->converter->convertFromWorking($totals['total_service_fee'], $secondCurrency)),
                'total_transgran' => $this->converter->round($this->converter->convertFromWorking($totals['total_transgran'], $secondCurrency)),
                'total_acquiring' => $this->converter->round($this->converter->convertFromWorking($totals['total_acquiring'], $secondCurrency)),
                'total_fees' => $this->converter->round($this->converter->convertFromWorking($totals['total_fees'], $secondCurrency)),
                'total_order' => $this->converter->round($this->converter->convertFromWorking($totals['total_order'], $secondCurrency)),
            ];
        }

        // --- ШАГ 9: Формулы ---
        $formulas = $this->buildFormulas($servicesResult, $totals, $workingCurrency, $totalAcquiring, $acquiringRule);

        return [
            'services' => $servicesResult,
            'totals' => $totals,
            'working_currency' => $workingCurrency,
            'multi_currency' => $multiCurrency,
            'second_currency' => $secondCurrency,
            'totals_second' => $totalsSecond,
            'formulas' => $formulas,
            'applied_rules' => $appliedRules,
        ];
    }

    /**
     * Возвращает способ оплаты по коду (поиск среди активных способов).
     *
     * @param string $code Код способа оплаты (например 'card_brs').
     * @return array|null Строка из БД или null.
     */
    private function getPaymentMethodByCode($code)
    {
        foreach ($this->ruleEngine->getPaymentMethods() as $pm) {
            if (isset($pm['code']) && $pm['code'] === $code) {
                return $pm;
            }
        }
        return null;
    }

    /**
     * Возвращает тип услуги по коду.
     *
     * @param string $code Код типа услуги (issue, emd, refund, exchange).
     * @return array|null Строка из БД или null.
     */
    private function getServiceTypeByCode($code)
    {
        foreach ($this->ruleEngine->getServiceTypes() as $st) {
            if (isset($st['code']) && $st['code'] === $code) {
                return $st;
            }
        }
        return null;
    }

    /**
     * Рассчитывает сервисный сбор по правилу (процент или фикс, с учётом мин/макс).
     *
     * @param float $amountWork Сумма услуги в рабочей валюте.
     * @param array $feeRule    Правило из getServiceFeeRule (fee_type, percent_value, fixed_value, min_value, max_value, currency).
     * @return float Сумма сервисного сбора в рабочей валюте.
     */
    private function calculateServiceFee($amountWork, array $feeRule)
    {
        if ($feeRule['fee_type'] === 'fixed') {
            $fee = isset($feeRule['fixed_value']) ? (float) $feeRule['fixed_value'] : 0.0;
            return $fee;
        }
        $percent = isset($feeRule['percent_value']) ? (float) $feeRule['percent_value'] : 0.0;
        $fee = $amountWork * $percent / 100.0;
        if (isset($feeRule['min_value']) && $feeRule['min_value'] !== null && $fee < (float) $feeRule['min_value']) {
            $fee = (float) $feeRule['min_value'];
        }
        if (isset($feeRule['max_value']) && $feeRule['max_value'] !== null && $fee > (float) $feeRule['max_value']) {
            $fee = (float) $feeRule['max_value'];
        }
        return $fee;
    }

    /**
     * Рассчитывает трансграничный сбор по одной услуге.
     *
     * Учитывает is_applicable, исключения по стране поставщика, спецправило no_transgran_standard
     * (в т.ч. override_by_kz_card), базу (amount_work + service_fee при include_service_fee),
     * минимумы в валюте расчёта и порог 1000.
     *
     * @param float       $amountWork        Сумма услуги в рабочей валюте.
     * @param float       $serviceFee       Сервисный сбор по этой услуге.
     * @param array|null  $transgranRule    Правило из getTransgranRule (id, percent_value, min_value_low, min_value_high, threshold, min_currency, is_applicable, include_service_fee).
     * @param float       $serviceAmount    Исходная сумма услуги (в invoice_currency) для сравнения с порогом.
     * @param string      $invoiceCurrency  Валюта счёта поставщика.
     * @param string      $settlementCurrency Валюта расчёта с поставщиком.
     * @param string      $supplierCountry  Страна поставщика.
     * @param int         $clientId         ID клиента.
     * @param string      $serviceTypeCode  Код типа услуги.
     * @param string      $transgranColumn   Колонка трансграна (standard, kz_rub, kz_usd_eur).
     * @param bool        $isKzCard         Является ли способ оплаты картой КЗ.
     * @return float Сумма трансграна в рабочей валюте (0 если не применяется).
     */
    private function calculateTransgran(
        $amountWork,
        $serviceFee,
        $transgranRule,
        $serviceAmount,
        $invoiceCurrency,
        $settlementCurrency,
        $supplierCountry,
        $clientId,
        $serviceTypeCode,
        $transgranColumn,
        $isKzCard
    ) {
        if ($transgranRule === null) {
            return 0.0;
        }
        if (isset($transgranRule['is_applicable']) && (int) $transgranRule['is_applicable'] === 0) {
            return 0.0;
        }
        if ($this->ruleEngine->checkTransgranException($transgranRule['id'], 'supplier_country', $supplierCountry)) {
            return 0.0;
        }
        $specialParams = $this->ruleEngine->checkSpecialRule($clientId, 'no_transgran_standard', $serviceTypeCode);
        if ($specialParams !== null && $transgranColumn === 'standard') {
            $overrideByKzCard = isset($specialParams['override_by_kz_card']) && $specialParams['override_by_kz_card'];
            if ($overrideByKzCard && $isKzCard) {
                // Считаем трансгран (карта КЗ — ограничение не применяется)
            } else {
                return 0.0;
            }
        }

        $includeFee = isset($transgranRule['include_service_fee']) && (int) $transgranRule['include_service_fee'] === 1;
        $base = $includeFee ? ($amountWork + $serviceFee) : $amountWork;
        $percent = isset($transgranRule['percent_value']) ? (float) $transgranRule['percent_value'] : 0.0;
        $transgranRaw = $base * $percent / 100.0;

        $minLow = isset($transgranRule['min_value_low']) ? $transgranRule['min_value_low'] : null;
        $minHigh = isset($transgranRule['min_value_high']) ? $transgranRule['min_value_high'] : null;
        $threshold = isset($transgranRule['threshold']) ? (float) $transgranRule['threshold'] : 1000.0;
        $minCurrency = isset($transgranRule['min_currency']) ? $transgranRule['min_currency'] : null;

        if ($minLow !== null && $minCurrency !== null && $minCurrency !== '') {
            $amountInSettlement = $this->converter->convertToSettlement($serviceAmount, $invoiceCurrency, $settlementCurrency);
            $minInMinCurrency = $amountInSettlement <= $threshold ? (float) $minLow : (float) $minHigh;
            $minInWorking = $this->converter->convertToWorking($minInMinCurrency, $minCurrency);
            if ($transgranRaw < $minInWorking) {
                $transgranRaw = $minInWorking;
            }
        }

        return $transgranRaw;
    }

    /**
     * Применяет правило «что больше»: оставляется только больший из сервисного сбора и трансграна.
     *
     * @param float $serviceFee Сервисный сбор до правила.
     * @param float $transgran  Трансгран до правила.
     * @param bool  $useMaxRule  Включено ли правило у клиента (use_max_rule = 1).
     * @return array [ service_fee_final, transgran_final, max_rule_winner ]. winner: 'service_fee' | 'transgran' | null.
     */
    private function applyMaxRule($serviceFee, $transgran, $useMaxRule)
    {
        if (!$useMaxRule) {
            return [$serviceFee, $transgran, null];
        }
        if ($transgran > $serviceFee) {
            return [0.0, $transgran, 'transgran'];
        }
        return [$serviceFee, 0.0, 'service_fee'];
    }

    /**
     * Рассчитывает общий эквайринг по заказу.
     *
     * Если у правила задан exclude_service_type, эквайринг по этой услуге не берётся;
     * база по остальным услугам — amount_work + service_fee + transgran по каждой.
     *
     * @param float       $totalServiceAmount Общая сумма услуг в рабочей валюте.
     * @param float       $totalServiceFee    Общий сервисный сбор.
     * @param float       $totalTransgran     Общий трансгран.
     * @param string      $paymentMethodCode  Код способа оплаты (invoice → 0).
     * @param array|null  $acquiringRule      Правило из getAcquiringRule (percent_value, exclude_service_type).
     * @param array       $servicesResult     Массив результатов по услугам (service_type_code, amount_work, service_fee, transgran).
     * @return float Сумма эквайринга в рабочей валюте.
     */
    private function calculateAcquiring(
        $totalServiceAmount,
        $totalServiceFee,
        $totalTransgran,
        $paymentMethodCode,
        $acquiringRule,
        array $servicesResult
    ) {
        if ($paymentMethodCode === 'invoice') {
            return 0.0;
        }
        if ($acquiringRule === null) {
            return 0.0;
        }
        $percent = isset($acquiringRule['percent_value']) ? (float) $acquiringRule['percent_value'] : 0.0;
        $excludeType = isset($acquiringRule['exclude_service_type']) ? $acquiringRule['exclude_service_type'] : null;

        if ($excludeType !== null && $excludeType !== '') {
            $acquiringTotal = 0.0;
            foreach ($servicesResult as $s) {
                if ($s['service_type_code'] === $excludeType) {
                    continue;
                }
                $base = $s['amount_work'] + $s['service_fee'] + $s['transgran'];
                $acquiringTotal += $base * $percent / 100.0;
            }
            return $acquiringTotal;
        }

        $base = $totalServiceAmount + $totalServiceFee + $totalTransgran;
        return $base * $percent / 100.0;
    }

    /**
     * Формирует текстовые формулы для отображения (сервисный сбор, трансгран, эквайринг).
     *
     * @param array  $servicesResult   Детализация по услугам (с amount_work, service_fee, transgran и т.д.).
     * @param array  $totals            Итоги (total_service_amount, total_service_fee, total_transgran, total_acquiring).
     * @param string $workingCurrency   Рабочая валюта.
     * @param float  $totalAcquiring    Общий эквайринг (уже посчитан).
     * @param array|null $acquiringRule Правило эквайринга (percent_value).
     * @return array Ключи: service_fee, transgran, acquiring — строки с формулами.
     */
    private function buildFormulas(
        array $servicesResult,
        array $totals,
        $workingCurrency,
        $totalAcquiring,
        $acquiringRule
    ) {
        $partsFee = [];
        foreach ($servicesResult as $s) {
            $name = $s['service_type_name'];
            $fee = $s['service_fee'];
            $feeType = isset($s['fee_formula_type']) ? $s['fee_formula_type'] : null;
            $feePercent = isset($s['fee_formula_percent']) ? $s['fee_formula_percent'] : null;
            $feeFixed = isset($s['fee_formula_fixed']) ? $s['fee_formula_fixed'] : null;
            $feeBase = isset($s['fee_formula_base']) ? $s['fee_formula_base'] : $s['amount_work'];

            if ($feeType === 'fixed' && $feeFixed !== null) {
                $partsFee[] = 'Сервисный сбор (' . $name . '): фикс ' . $this->formatAmount((float) $feeFixed, $workingCurrency) . ' = ' . $this->formatAmount($fee, $workingCurrency);
            } elseif ($feePercent !== null && $feeBase !== null) {
                $pct = (float) $feePercent;
                $partsFee[] = 'Сервисный сбор (' . $name . '): ' . $pct . '% от ' . $this->formatAmount($feeBase, $workingCurrency) . ' = ' . $this->formatAmount($fee, $workingCurrency);
            } else {
                $partsFee[] = $name . ': серв.сбор = ' . $this->formatAmount($fee, $workingCurrency);
            }
        }
        $partsFee[] = 'Сервисный сбор (итого): ' . $this->formatAmount($totals['total_service_fee'], $workingCurrency);
        $formulaServiceFee = implode("\n", $partsFee);

        $baseTransgran = $totals['total_service_amount'] + $totals['total_service_fee'];
        if ($baseTransgran > 0) {
            $pctTransgran = round($totals['total_transgran'] / $baseTransgran * 100, 2);
            $formulaTransgran = 'Трансгран: ' . $pctTransgran . '% от ' . $this->formatAmount($baseTransgran, $workingCurrency) . ' = ' . $this->formatAmount($totals['total_transgran'], $workingCurrency);
        } else {
            $formulaTransgran = 'Трансгран: 0% от ' . $this->formatAmount(0, $workingCurrency) . ' = ' . $this->formatAmount($totals['total_transgran'], $workingCurrency);
        }

        $baseAcq = $totals['total_service_amount'] + $totals['total_service_fee'] + $totals['total_transgran'];
        $pct = ($acquiringRule && isset($acquiringRule['percent_value'])) ? $acquiringRule['percent_value'] : 0;
        $formulaAcquiring = 'Эквайринг: ' . $pct . '% от ' . $this->formatAmount($baseAcq, $workingCurrency) . ' = ' . $this->formatAmount($totalAcquiring, $workingCurrency);

        return [
            'service_fee' => $formulaServiceFee,
            'transgran' => $formulaTransgran,
            'acquiring' => $formulaAcquiring,
        ];
    }

    /**
     * Форматирует сумму с валютой для отображения (например "5 000,00 ₽").
     *
     * @param float  $amount   Сумма.
     * @param string $currency Код валюты (RUB, EUR, USD и т.д.).
     * @return string Отформатированная строка.
     */
    private function formatAmount($amount, $currency)
    {
        $formatted = number_format((float) $amount, 2, ',', ' ');
        $symbol = $this->getCurrencySymbol($currency);
        return $formatted . ' ' . $symbol;
    }

    /**
     * Возвращает символ или код валюты для отображения.
     *
     * @param string $currency Код валюты.
     * @return string Символ (₽, €) или код валюты.
     */
    private function getCurrencySymbol($currency)
    {
        $c = strtoupper((string) $currency);
        if ($c === 'RUB') {
            return '₽';
        }
        if ($c === 'EUR') {
            return '€';
        }
        if ($c === 'USD') {
            return '$';
        }
        return $c;
    }
}
