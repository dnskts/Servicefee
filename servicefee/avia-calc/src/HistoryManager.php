<?php
/**
 * Менеджер истории расчётов — сохранение, чтение и удаление записей из calculation_history.
 *
 * Все данные входов и результатов хранятся в таблице в виде JSON.
 * При чтении поля input_data и result_data декодируются обратно в массивы.
 * Метод cleanup() поддерживает лимит записей (по умолчанию 500), удаляя самые старые.
 */

namespace App;

class HistoryManager
{
    /** @var Database Экземпляр для работы с БД */
    private $db;

    /** @var int Максимальное количество записей в истории (лишние удаляются в cleanup()) */
    private $limit = 500;

    /**
     * Создаёт менеджер истории с заданным подключением к БД.
     *
     * @param Database $db Экземпляр Database.
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Сохраняет новый расчёт в историю.
     *
     * Входные данные и результат сохраняются как JSON. После вставки вызывается cleanup(),
     * чтобы при превышении лимита удалить самые старые записи.
     *
     * @param array  $inputData  Входные данные расчёта (будут сохранены как JSON).
     * @param array  $resultData Результат расчёта (будут сохранены как JSON).
     * @param string $formulas   Текст формул (например, объединённые строки из result['formulas']).
     * @return void
     */
    public function save(array $inputData, array $resultData, $formulas = '')
    {
        $inputJson = json_encode($inputData, JSON_UNESCAPED_UNICODE);
        $resultJson = json_encode($resultData, JSON_UNESCAPED_UNICODE);
        $this->db->execute(
            'INSERT INTO calculation_history (input_data, result_data, formulas) VALUES (?, ?, ?)',
            [$inputJson, $resultJson, (string) $formulas]
        );
        $this->cleanup();
    }

    /**
     * Возвращает все записи истории, от новых к старым.
     *
     * Поля input_data и result_data декодируются из JSON в массивы.
     * Поле formulas возвращается как строка (как хранится в БД).
     *
     * @return array Массив записей: id, created_at, input_data (массив), result_data (массив), formulas (строка).
     */
    public function getAll()
    {
        $rows = $this->db->fetchAll(
            'SELECT id, created_at, input_data, result_data, formulas FROM calculation_history ORDER BY id DESC LIMIT ' . (int) $this->limit
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r['id'],
                'created_at' => $r['created_at'],
                'input_data' => $this->decodeJson($r['input_data'], []),
                'result_data' => $this->decodeJson($r['result_data'], []),
                'formulas' => $r['formulas'],
            ];
        }
        return $out;
    }

    /**
     * Возвращает одну запись по id.
     *
     * @param int $id ID записи.
     * @return array|null Массив с полями id, created_at, input_data, result_data, formulas или null, если не найдено.
     */
    public function getById($id)
    {
        $r = $this->db->fetchOne(
            'SELECT id, created_at, input_data, result_data, formulas FROM calculation_history WHERE id = ?',
            [(int) $id]
        );
        if (!$r) {
            return null;
        }
        return [
            'id' => (int) $r['id'],
            'created_at' => $r['created_at'],
            'input_data' => $this->decodeJson($r['input_data'], []),
            'result_data' => $this->decodeJson($r['result_data'], []),
            'formulas' => $r['formulas'],
        ];
    }

    /**
     * Удаляет одну запись по id.
     *
     * @param int $id ID записи.
     * @return bool true, если запись была удалена; false, если записи с таким id нет.
     */
    public function delete($id)
    {
        $affected = $this->db->execute('DELETE FROM calculation_history WHERE id = ?', [(int) $id]);
        return $affected > 0;
    }

    /**
     * Удаляет все записи из истории.
     *
     * @return bool Всегда true после выполнения запроса.
     */
    public function clearAll()
    {
        $this->db->execute('DELETE FROM calculation_history');
        return true;
    }

    /**
     * Проверяет количество записей и при превышении лимита удаляет самые старые.
     *
     * Если записей больше $limit (500), удаляются записи с наименьшим id (самые старые),
     * пока не останется ровно limit записей.
     *
     * @return void
     */
    public function cleanup()
    {
        $count = $this->getCount();
        if ($count <= $this->limit) {
            return;
        }
        $excess = $count - $this->limit;
        $this->db->execute(
            'DELETE FROM calculation_history WHERE id IN (SELECT id FROM calculation_history ORDER BY created_at ASC LIMIT ?)',
            [$excess]
        );
    }

    /**
     * Возвращает текущее количество записей в истории.
     *
     * @return int
     */
    public function getCount()
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS cnt FROM calculation_history');
        return isset($row['cnt']) ? (int) $row['cnt'] : 0;
    }

    /**
     * Декодирует JSON-строку в массив. При ошибке возвращает значение по умолчанию.
     *
     * @param string|null $json    Строка JSON.
     * @param mixed       $default Значение по умолчанию при ошибке декодирования.
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
