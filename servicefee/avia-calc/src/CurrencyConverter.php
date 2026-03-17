<?php
/**
 * Конвертер валют для калькулятора сборов АВИА.
 *
 * В системе используются три валютных поля:
 * - Валюта счёта поставщика (invoice_currency) — в ней вводится сумма услуги.
 * - Валюта расчёта с поставщиком (settlement_currency) — в ней фактически оплачивается счёт.
 * - Рабочая валюта (working_currency) — в ней ведутся все расчёты: RUB для всех клиентов кроме SDG, EUR для SDG.
 *
 * Два курса задаются пользователем вручную:
 * - Курс РС ТЛС (rate_rs_tls) — курс иностранной валюты к рублю; используется для клиентов не-SDG.
 * - Курс Казахстана (rate_kz) — коэффициент пересчёта в EUR; используется только для SDG (пользователь считает сам, например с finance.kapital.kz).
 */

namespace App;

class CurrencyConverter
{
    /** @var float Курс РС ТЛС: сколько рублей за 1 единицу иностранной валюты (например 1 EUR = 100 RUB → 100) */
    private $rateRsTls;

    /** @var float Курс Казахстана: коэффициент пересчёта в рабочую валюту EUR для SDG (пользователь вводит рассчитанный коэффициент) */
    private $rateKz;

    /** @var string Рабочая валюта расчётов: 'RUB' или 'EUR' (для SDG — EUR) */
    private $workingCurrency;

    /**
     * Создаёт конвертер с заданной рабочей валютой и курсами.
     *
     * @param string $workingCurrency Рабочая валюта: 'RUB' или 'EUR'. По умолчанию 'RUB'.
     * @param float  $rateRsTls       Курс РС ТЛС (иностранная валюта → RUB). По умолчанию 1.0 (без конвертации).
     * @param float  $rateKz          Курс Казахстана (коэффициент к EUR для SDG). По умолчанию 1.0.
     */
    public function __construct($workingCurrency = 'RUB', $rateRsTls = 1.0, $rateKz = 1.0)
    {
        $this->workingCurrency = strtoupper((string) $workingCurrency);
        $this->rateRsTls = (float) $rateRsTls;
        $this->rateKz = (float) $rateKz;
    }

    /**
     * Конвертирует сумму из указанной валюты в рабочую валюту.
     *
     * Логика:
     * - Если валюта источника совпадает с рабочей — сумма возвращается без изменений.
     * - Для рабочей валюты RUB: сумма × курс РС ТЛС (курс иностранной валюты к рублю).
     * - Для рабочей валюты EUR (SDG): сумма × курс Казахстана (пользователь вводит уже рассчитанный коэффициент пересчёта в EUR).
     *
     * @param float  $amount       Сумма в валюте источника.
     * @param string $fromCurrency Код валюты источника (RUB, EUR, USD, KZT и т.д.).
     * @return float Сумма в рабочей валюте.
     */
    public function convertToWorking($amount, $fromCurrency)
    {
        $from = strtoupper((string) $fromCurrency);
        if ($from === $this->workingCurrency) {
            return (float) $amount;
        }
        if ($this->workingCurrency === 'RUB') {
            return (float) $amount * $this->rateRsTls;
        }
        // Рабочая валюта EUR (SDG): коэффициент пересчёта в EUR задаётся пользователем
        return (float) $amount * $this->rateKz;
    }

    /**
     * Обратная конвертация: из рабочей валюты в указанную.
     *
     * Используется, когда нужно показать результат в валюте расчёта с поставщиком или в валюте счёта.
     *
     * @param float  $amount     Сумма в рабочей валюте.
     * @param string $toCurrency Код целевой валюты.
     * @return float Сумма в целевой валюте.
     */
    public function convertFromWorking($amount, $toCurrency)
    {
        $to = strtoupper((string) $toCurrency);
        if ($to === $this->workingCurrency) {
            return (float) $amount;
        }
        if ($this->workingCurrency === 'RUB') {
            return (float) $amount / $this->rateRsTls;
        }
        // Из EUR (SDG) в другую валюту
        return (float) $amount / $this->rateKz;
    }

    /**
     * Конвертирует сумму между двумя произвольными валютами.
     *
     * Сначала переводит в рабочую валюту, затем из рабочей в целевую. Так обеспечивается единая база расчётов.
     *
     * @param float  $amount       Сумма в валюте источника.
     * @param string $fromCurrency Код валюты источника.
     * @param string $toCurrency   Код целевой валюты.
     * @return float Сумма в целевой валюте.
     */
    public function convertBetween($amount, $fromCurrency, $toCurrency)
    {
        $from = strtoupper((string) $fromCurrency);
        $to = strtoupper((string) $toCurrency);
        if ($from === $to) {
            return (float) $amount;
        }
        $inWorking = $this->convertToWorking($amount, $fromCurrency);
        return $this->convertFromWorking($inWorking, $toCurrency);
    }

    /**
     * Конвертирует сумму в валюту расчёта с поставщиком (settlement_currency).
     *
     * Используется для сравнения суммы услуги с порогами трансграна (минимумы 70/120, порог 1000), которые заданы в валюте расчёта.
     *
     * @param float  $amount              Сумма в валюте источника (например invoice_currency).
     * @param string $fromCurrency       Валюта источника.
     * @param string $settlementCurrency Валюта расчёта с поставщиком.
     * @return float Сумма в валюте расчёта с поставщиком.
     */
    public function convertToSettlement($amount, $fromCurrency, $settlementCurrency)
    {
        return $this->convertBetween($amount, $fromCurrency, $settlementCurrency);
    }

    /**
     * Определяет, нужно ли показывать результат в двух валютах (мультивалютный результат).
     *
     * Мультивалютный вывод нужен, если валюта расчёта с поставщиком или хотя бы одна валюта счёта в заказе отличается от рабочей валюты.
     *
     * @param string $settlementCurrency Валюта расчёта с поставщиком.
     * @param array  $invoiceCurrencies   Массив валют счетов по всем услугам в заказе (например ['RUB'], ['EUR', 'USD']).
     * @return bool true — показывать результат в двух валютах; false — достаточно одной (рабочей).
     */
    public function needsMultiCurrency($settlementCurrency, array $invoiceCurrencies = [])
    {
        $settlement = strtoupper((string) $settlementCurrency);
        if ($settlement !== $this->workingCurrency) {
            return true;
        }
        foreach ($invoiceCurrencies as $inv) {
            if (strtoupper((string) $inv) !== $this->workingCurrency) {
                return true;
            }
        }
        return false;
    }

    /**
     * Возвращает вторую валюту для мультивалютного отображения результата.
     *
     * Приоритет: сначала валюта расчёта с поставщиком (если не совпадает с рабочей), иначе первая попавшаяся валюта счёта, отличная от рабочей.
     *
     * @param string $settlementCurrency Валюта расчёта с поставщиком.
     * @param array  $invoiceCurrencies   Массив валют счетов по услугам в заказе.
     * @return string|null Код второй валюты или null, если мультивалютный вывод не нужен (всё в рабочей валюте).
     */
    public function getSecondCurrency($settlementCurrency, array $invoiceCurrencies = [])
    {
        $settlement = strtoupper((string) $settlementCurrency);
        if ($settlement !== $this->workingCurrency) {
            return $settlement;
        }
        foreach ($invoiceCurrencies as $inv) {
            $c = strtoupper((string) $inv);
            if ($c !== $this->workingCurrency) {
                return $c;
            }
        }
        return null;
    }

    /**
     * Округляет сумму до двух знаков после запятой (копейки/центы).
     *
     * @param float $amount Сумма.
     * @return float Сумма, округлённая до 0.01.
     */
    public function round($amount)
    {
        return round((float) $amount, 2);
    }

    /**
     * Возвращает рабочую валюту (RUB или EUR).
     *
     * @return string
     */
    public function getWorkingCurrency()
    {
        return $this->workingCurrency;
    }

    /**
     * Возвращает текущий курс РС ТЛС.
     *
     * @return float
     */
    public function getRateRsTls()
    {
        return $this->rateRsTls;
    }

    /**
     * Возвращает текущий курс Казахстана (для SDG).
     *
     * @return float
     */
    public function getRateKz()
    {
        return $this->rateKz;
    }

    /**
     * Устанавливает рабочую валюту (RUB или EUR).
     *
     * @param string $currency Код валюты: 'RUB' или 'EUR'.
     * @return void
     */
    public function setWorkingCurrency($currency)
    {
        $this->workingCurrency = strtoupper((string) $currency);
    }

    /**
     * Устанавливает курс РС ТЛС (иностранная валюта → RUB).
     *
     * @param float $rate Новое значение курса.
     * @return void
     */
    public function setRateRsTls($rate)
    {
        $this->rateRsTls = (float) $rate;
    }

    /**
     * Устанавливает курс Казахстана (коэффициент пересчёта в EUR для SDG).
     *
     * @param float $rate Новое значение курса.
     * @return void
     */
    public function setRateKz($rate)
    {
        $this->rateKz = (float) $rate;
    }

    /**
     * Заглушка: получение курса РС ТЛС из Битрикс 24.
     *
     * TODO: реализовать получение курса из Битрикс 24 (API или интеграция).
     * Позволит в будущем подставлять курс автоматически без переписывания класса.
     *
     * @return float|null Текущая реализация всегда возвращает null (курс не подтягивается).
     */
    public function fetchRateFromBitrix()
    {
        return null;
    }
}
