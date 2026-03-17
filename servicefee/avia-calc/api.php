<?php
/**
 * Единая точка входа для AJAX-запросов от фронтенда калькулятора сборов АВИА.
 *
 * Принимает GET/POST с параметром action и возвращает ответы в формате JSON.
 * При любой ошибке в ответе присутствует поле 'error'.
 */

// Запрет кэширования ответов
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Type: application/json; charset=utf-8');

// Подключение классов приложения (простая загрузка без автолоадера)
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/RuleEngine.php';
require_once __DIR__ . '/src/CurrencyConverter.php';
require_once __DIR__ . '/src/Calculator.php';
require_once __DIR__ . '/src/HistoryManager.php';
require_once __DIR__ . '/src/AdminManager.php';

/**
 * Отправляет JSON-ответ и завершает выполнение скрипта.
 *
 * @param array $data Данные для вывода (будут закодированы в JSON).
 */
function sendJson(array $data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Отправляет ответ с ошибкой и завершает выполнение.
 *
 * @param string $message Текст ошибки.
 */
function sendError($message)
{
    sendJson(['error' => $message]);
}

try {
    $db = \App\Database::getInstance();
    $db->initializeDatabase();
    $ruleEngine = new \App\RuleEngine($db);
    $historyManager = new \App\HistoryManager($db);
    $adminManager = new \App\AdminManager($db);
} catch (Exception $e) {
    sendError('Внутренняя ошибка: ' . $e->getMessage());
}

/**
 * Ответ админ-API: успех с данными.
 */
function sendAdminSuccess($data)
{
    sendJson(['success' => true, 'data' => $data]);
}

/**
 * Ответ админ-API: ошибка.
 */
function sendAdminError($message)
{
    sendJson(['success' => false, 'error' => $message]);
}

/**
 * Получает тело POST-запроса как массив (JSON). При ошибке парсинга возвращает null и вызов sendAdminError.
 */
function getAdminPostData()
{
    $raw = file_get_contents('php://input');
    if ($raw !== '' && $raw !== false) {
        $decoded = json_decode($raw, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            sendAdminError('Ошибка разбора JSON.');
        }
        return $decoded !== null ? $decoded : [];
    }
    return $_POST;
}

// Определение действия: из GET или POST
$action = isset($_GET['action']) ? trim($_GET['action']) : (isset($_POST['action']) ? trim($_POST['action']) : '');
if ($action === '') {
    // Для calculate тело может быть JSON в php://input
    $rawInput = file_get_contents('php://input');
    if ($rawInput !== '') {
        $decoded = json_decode($rawInput, true);
        if (is_array($decoded) && isset($decoded['action'])) {
            $action = trim((string) $decoded['action']);
        }
    }
}
if ($action === '') {
    sendError('Не указано действие (action).');
}

try {
switch ($action) {
    case 'get_initial_data':
        // GET: справочники для формы
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            sendError('Метод должен быть GET.');
        }
        $clients = $ruleEngine->getClients();
        $clientsOut = [];
        foreach ($clients as $c) {
            $clientsOut[] = [
                'id' => (int) $c['id'],
                'code' => $c['code'],
                'name' => $c['name'],
                'country' => $c['country'],
                'result_currency' => $c['result_currency'],
                'agent_hint' => $c['agent_hint'],
            ];
        }
        $serviceTypes = $ruleEngine->getServiceTypes();
        $serviceTypesOut = [];
        foreach ($serviceTypes as $st) {
            $serviceTypesOut[] = [
                'id' => (int) $st['id'],
                'code' => $st['code'],
                'name' => $st['name'],
            ];
        }
        $paymentMethods = $ruleEngine->getPaymentMethods();
        $paymentMethodsOut = [];
        foreach ($paymentMethods as $pm) {
            $paymentMethodsOut[] = [
                'id' => (int) $pm['id'],
                'code' => $pm['code'],
                'name' => $pm['name'],
            ];
        }
        sendJson([
            'clients' => $clientsOut,
            'service_types' => $serviceTypesOut,
            'payment_methods' => $paymentMethodsOut,
            'currencies' => ['RUB', 'EUR', 'USD', 'KZT'],
            'countries' => [
                ['code' => 'RU', 'name' => 'Россия'],
                ['code' => 'KZ', 'name' => 'Казахстан'],
            ],
        ]);
        break;

    case 'calculate':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendError('Метод должен быть POST.');
        }
        $input = [];
        $rawInput = file_get_contents('php://input');
        if ($rawInput !== '') {
            $decoded = json_decode($rawInput, true);
            if (is_array($decoded)) {
                $input = $decoded;
            }
        }
        if (empty($input)) {
            $input = $_POST;
        }
        // Валидация
        if (empty($input['client_code'])) {
            sendError('Не указан код клиента (client_code).');
        }
        $client = $ruleEngine->getClientByCode($input['client_code']);
        if (!$client) {
            sendError('Клиент с указанным кодом не найден.');
        }
        if (empty($input['payment_method_code'])) {
            sendError('Не указан способ оплаты (payment_method_code).');
        }
        $paymentMethods = $ruleEngine->getPaymentMethods();
        $paymentFound = false;
        foreach ($paymentMethods as $pm) {
            if (isset($pm['code']) && $pm['code'] === $input['payment_method_code']) {
                $paymentFound = true;
                break;
            }
        }
        if (!$paymentFound) {
            sendError('Способ оплаты с указанным кодом не найден.');
        }
        if (!isset($input['supplier_country']) || (string) $input['supplier_country'] === '') {
            sendError('Не указана страна поставщика (supplier_country).');
        }
        $allowedCountries = ['RU', 'KZ'];
        if (!in_array(strtoupper((string) $input['supplier_country']), $allowedCountries, true)) {
            sendError('Страна поставщика допускает только RU или KZ.');
        }
        if (!isset($input['settlement_currency']) || (string) $input['settlement_currency'] === '') {
            sendError('Не указана валюта расчёта (settlement_currency).');
        }
        $allowedCurrencies = ['RUB', 'EUR', 'USD', 'KZT'];
        if (!in_array(strtoupper((string) $input['settlement_currency']), $allowedCurrencies, true)) {
            sendError('Валюта расчёта допускает только RUB, EUR, USD или KZT.');
        }
        if (!isset($input['services']) || !is_array($input['services'])) {
            sendError('Список услуг (services) должен быть непустым массивом.');
        }
        if (count($input['services']) < 1) {
            sendError('Должна быть указана хотя бы одна услуга.');
        }
        $serviceTypes = $ruleEngine->getServiceTypes();
        $serviceTypeCodes = [];
        foreach ($serviceTypes as $st) {
            $serviceTypeCodes[] = $st['code'];
        }
        foreach ($input['services'] as $i => $svc) {
            if (empty($svc['service_type_code'])) {
                sendError('У услуги ' . ($i + 1) . ' не указан тип (service_type_code).');
            }
            if (!in_array($svc['service_type_code'], $serviceTypeCodes, true)) {
                sendError('У услуги ' . ($i + 1) . ' указан неизвестный тип услуги.');
            }
            if (!isset($svc['amount']) || (float) $svc['amount'] <= 0) {
                sendError('У услуги ' . ($i + 1) . ' сумма (amount) должна быть больше 0.');
            }
            if (empty($svc['invoice_currency'])) {
                sendError('У услуги ' . ($i + 1) . ' не указана валюта счёта (invoice_currency).');
            }
        }
        $rateRsTls = isset($input['rate_rs_tls']) ? (float) $input['rate_rs_tls'] : 1.0;
        $rateKz = isset($input['rate_kz']) ? (float) $input['rate_kz'] : 0.0;
        if ($rateRsTls <= 0) {
            sendError('Курс РС ТЛС (rate_rs_tls) должен быть больше 0.');
        }
        if (strtoupper((string) $client['result_currency']) === 'EUR' && $rateKz <= 0) {
            sendError('Для клиента SDG курс Казахстана (rate_kz) должен быть больше 0.');
        }
        $workingCurrency = isset($client['result_currency']) ? $client['result_currency'] : 'RUB';
        $converter = new \App\CurrencyConverter($workingCurrency, $rateRsTls, $rateKz);
        $calculator = new \App\Calculator($ruleEngine, $converter);
        try {
            $result = $calculator->calculate($input);
        } catch (\InvalidArgumentException $e) {
            sendError($e->getMessage());
        } catch (Exception $e) {
            sendError('Внутренняя ошибка: ' . $e->getMessage());
        }
        try {
            $formulasStr = isset($result['formulas']) && is_array($result['formulas'])
                ? implode("\n", $result['formulas'])
                : '';
            $historyManager->save($input, $result, $formulasStr);
        } catch (Exception $e) {
            // Не прерываем ответ: расчёт уже выполнен, ошибка только записи в историю
        }
        sendJson($result);
        break;

    case 'get_history':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            sendError('Метод должен быть GET.');
        }
        try {
            $history = $historyManager->getAll();
            sendJson(['history' => $history]);
        } catch (Exception $e) {
            sendError('Внутренняя ошибка: ' . $e->getMessage());
        }
        break;

    case 'clear_history':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendError('Метод должен быть POST.');
        }
        try {
            $historyManager->clearAll();
            sendJson(['success' => true]);
        } catch (Exception $e) {
            sendError('Внутренняя ошибка: ' . $e->getMessage());
        }
        break;

    case 'delete_history_item':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendError('Метод должен быть POST.');
        }
        $body = [];
        $rawInput = file_get_contents('php://input');
        if ($rawInput !== '') {
            $decoded = json_decode($rawInput, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }
        if (empty($body)) {
            $body = $_POST;
        }
        if (!isset($body['id']) || (int) $body['id'] <= 0) {
            sendError('Не указан корректный id записи для удаления.');
        }
        $id = (int) $body['id'];
        try {
            $deleted = $historyManager->delete($id);
            if (!$deleted) {
                sendError('Запись не найдена.');
            }
            sendJson(['success' => true]);
        } catch (Exception $e) {
            sendError('Внутренняя ошибка: ' . $e->getMessage());
        }
        break;

    // --- Админка ---
    case 'admin_get_clients':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            sendAdminError('Метод должен быть GET.');
        }
        try {
            sendAdminSuccess($adminManager->getClients());
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_get_client':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            sendAdminError('Метод должен быть GET.');
        }
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        try {
            $client = $adminManager->getClient($id);
            sendAdminSuccess($client);
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_create_client':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendAdminError('Метод должен быть POST.');
        }
        $postData = getAdminPostData();
        try {
            $newId = $adminManager->createClient($postData);
            sendAdminSuccess(['id' => $newId]);
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_update_client':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendAdminError('Метод должен быть POST.');
        }
        $postData = getAdminPostData();
        try {
            $adminManager->updateClient(isset($postData['id']) ? $postData['id'] : 0, $postData);
            sendAdminSuccess(true);
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_delete_client':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendAdminError('Метод должен быть POST.');
        }
        $postData = getAdminPostData();
        try {
            $adminManager->deleteClient(isset($postData['id']) ? $postData['id'] : 0);
            sendAdminSuccess(true);
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_toggle_client':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendAdminError('Метод должен быть POST.');
        }
        $postData = getAdminPostData();
        try {
            $newState = $adminManager->toggleClient(isset($postData['id']) ? $postData['id'] : 0);
            sendAdminSuccess(['is_active' => $newState]);
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_get_service_fee_rules':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            sendAdminError('Метод должен быть GET.');
        }
        $clientId = isset($_GET['client_id']) ? (int) $_GET['client_id'] : 0;
        try {
            sendAdminSuccess($adminManager->getServiceFeeRules($clientId));
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_create_service_fee_rule':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendAdminError('Метод должен быть POST.');
        }
        $postData = getAdminPostData();
        try {
            $newId = $adminManager->createServiceFeeRule($postData);
            sendAdminSuccess(['id' => $newId]);
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_update_service_fee_rule':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendAdminError('Метод должен быть POST.');
        }
        $postData = getAdminPostData();
        try {
            $adminManager->updateServiceFeeRule(isset($postData['id']) ? $postData['id'] : 0, $postData);
            sendAdminSuccess(true);
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_delete_service_fee_rule':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendAdminError('Метод должен быть POST.');
        }
        $postData = getAdminPostData();
        try {
            $adminManager->deleteServiceFeeRule(isset($postData['id']) ? $postData['id'] : 0);
            sendAdminSuccess(true);
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_get_transgran_rules':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            sendAdminError('Метод должен быть GET.');
        }
        $clientId = isset($_GET['client_id']) ? (int) $_GET['client_id'] : 0;
        try {
            sendAdminSuccess($adminManager->getTransgranRules($clientId));
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_create_transgran_rule':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendAdminError('Метод должен быть POST.');
        }
        $postData = getAdminPostData();
        try {
            $newId = $adminManager->createTransgranRule($postData);
            sendAdminSuccess(['id' => $newId]);
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_update_transgran_rule':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendAdminError('Метод должен быть POST.');
        }
        $postData = getAdminPostData();
        try {
            $adminManager->updateTransgranRule(isset($postData['id']) ? $postData['id'] : 0, $postData);
            sendAdminSuccess(true);
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_delete_transgran_rule':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendAdminError('Метод должен быть POST.');
        }
        $postData = getAdminPostData();
        try {
            $adminManager->deleteTransgranRule(isset($postData['id']) ? $postData['id'] : 0);
            sendAdminSuccess(true);
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_get_transgran_exceptions':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            sendAdminError('Метод должен быть GET.');
        }
        $ruleId = isset($_GET['transgran_rule_id']) ? (int) $_GET['transgran_rule_id'] : 0;
        try {
            sendAdminSuccess($adminManager->getTransgranExceptions($ruleId));
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_create_transgran_exception':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendAdminError('Метод должен быть POST.');
        }
        $postData = getAdminPostData();
        try {
            $newId = $adminManager->createTransgranException($postData);
            sendAdminSuccess(['id' => $newId]);
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_delete_transgran_exception':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendAdminError('Метод должен быть POST.');
        }
        $postData = getAdminPostData();
        try {
            $adminManager->deleteTransgranException(isset($postData['id']) ? $postData['id'] : 0);
            sendAdminSuccess(true);
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_get_acquiring_rules':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            sendAdminError('Метод должен быть GET.');
        }
        $clientId = isset($_GET['client_id']) ? (int) $_GET['client_id'] : 0;
        try {
            sendAdminSuccess($adminManager->getAcquiringRules($clientId));
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_create_acquiring_rule':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendAdminError('Метод должен быть POST.');
        }
        $postData = getAdminPostData();
        try {
            $newId = $adminManager->createAcquiringRule($postData);
            sendAdminSuccess(['id' => $newId]);
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_update_acquiring_rule':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendAdminError('Метод должен быть POST.');
        }
        $postData = getAdminPostData();
        try {
            $adminManager->updateAcquiringRule(isset($postData['id']) ? $postData['id'] : 0, $postData);
            sendAdminSuccess(true);
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_delete_acquiring_rule':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendAdminError('Метод должен быть POST.');
        }
        $postData = getAdminPostData();
        try {
            $adminManager->deleteAcquiringRule(isset($postData['id']) ? $postData['id'] : 0);
            sendAdminSuccess(true);
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_get_special_rules':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            sendAdminError('Метод должен быть GET.');
        }
        $clientId = isset($_GET['client_id']) ? (int) $_GET['client_id'] : 0;
        try {
            sendAdminSuccess($adminManager->getSpecialRules($clientId));
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_create_special_rule':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendAdminError('Метод должен быть POST.');
        }
        $postData = getAdminPostData();
        try {
            $newId = $adminManager->createSpecialRule($postData);
            sendAdminSuccess(['id' => $newId]);
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_update_special_rule':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendAdminError('Метод должен быть POST.');
        }
        $postData = getAdminPostData();
        try {
            $adminManager->updateSpecialRule(isset($postData['id']) ? $postData['id'] : 0, $postData);
            sendAdminSuccess(true);
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_delete_special_rule':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendAdminError('Метод должен быть POST.');
        }
        $postData = getAdminPostData();
        try {
            $adminManager->deleteSpecialRule(isset($postData['id']) ? $postData['id'] : 0);
            sendAdminSuccess(true);
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_get_service_types':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            sendAdminError('Метод должен быть GET.');
        }
        try {
            sendAdminSuccess($adminManager->getServiceTypes());
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    case 'admin_get_payment_methods':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            sendAdminError('Метод должен быть GET.');
        }
        try {
            sendAdminSuccess($adminManager->getPaymentMethods());
        } catch (Exception $e) {
            sendAdminError($e->getMessage());
        }
        break;

    default:
        sendError('Неизвестное действие.');
}
} catch (Exception $e) {
    sendError('Внутренняя ошибка: ' . $e->getMessage());
}
