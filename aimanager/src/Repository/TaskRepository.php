<?php

namespace AIManager\Repository;

use PDO;
use InvalidArgumentException;

class TaskRepository {

    private const STATUSES = [
        'queued',
        'running',
        'completed',
        'failed',
        'cancelled',
        'waiting_approval',
    ];

    public function __construct(
            private PDO $pdo
    ) {
        
    }

    /**
     * Tworzy nowe zadanie.
     */
    public function create(array $data): int {
        if (empty($data['agent_id'])) {
            throw new InvalidArgumentException('Agent ID is required.');
        }

        if (empty($data['type'])) {
            throw new InvalidArgumentException('Task type is required.');
        }

        $status = !empty($data['requires_approval']) ? 'waiting_approval' : 'queued';

        $stmt = $this->pdo->prepare("
            INSERT INTO tasks (
                agent_id,
                conversation_id,
                parent_task_id,
                type,
                status,
                priority,
                input_json,
                requires_approval,
                scheduled_at,
                max_attempts
            ) VALUES (
                :agent_id,
                :conversation_id,
                :parent_task_id,
                :type,
                :status,
                :priority,
                :input_json,
                :requires_approval,
                :scheduled_at,
                :max_attempts
            )
        ");

        $stmt->execute([
            'agent_id' => (int) $data['agent_id'],
            'conversation_id' => $data['conversation_id'] ?? null,
            'parent_task_id' => $data['parent_task_id'] ?? null,
            'type' => $data['type'],
            'status' => $status,
            'priority' => (int) ($data['priority'] ?? 0),
            'input_json' => json_encode(
                    $data['input'] ?? [],
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'requires_approval' => !empty($data['requires_approval']) ? 1 : 0,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'max_attempts' => max(1, (int) ($data['max_attempts'] ?? 3)),
        ]);

        $id = (int) $this->pdo->lastInsertId();

        $this->addEvent($id, 'created', 'Task created.');

        return $id;
    }

    /**
     * Pobiera zadanie po ID.
     */
    public function findById(int $id): ?array {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM tasks
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);

        $task = $stmt->fetch();

        return $task ?: null;
    }

    /**
     * Pobiera listę zadań.
     */
    public function findAll(
            ?string $status = null,
            ?int $agentId = null,
            int $limit = 100
    ): array {
        $sql = "SELECT * FROM tasks WHERE 1 = 1";
        $params = [];

        if ($status !== null) {
            $this->validateStatus($status);
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }

        if ($agentId !== null) {
            $sql .= " AND agent_id = :agent_id";
            $params['agent_id'] = $agentId;
        }

        $limit = max(1, min(500, $limit));

        $sql .= " ORDER BY priority DESC, created_at ASC LIMIT {$limit}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Zmienia status zadania.
     */
    public function setStatus(
            int $id,
            string $status,
            ?string $message = null
    ): bool {
        $this->validateStatus($status);

        $stmt = $this->pdo->prepare("
        UPDATE tasks
        SET
            status = :status,
            started_at = CASE
                WHEN :status_start = 'running' AND started_at IS NULL
                THEN NOW()
                ELSE started_at
            END,
            finished_at = CASE
                WHEN :status_finish IN ('completed', 'failed', 'cancelled')
                THEN NOW()
                WHEN :status_finish_running = 'running'
                THEN NULL
                ELSE finished_at
            END
        WHERE id = :id
    ");

        $stmt->execute([
            'status' => $status,
            'status_start' => $status,
            'status_finish' => $status,
            'status_finish_running' => $status,
            'id' => $id,
        ]);

        if ($stmt->rowCount() > 0) {
            $this->addEvent(
                    $id,
                    'status_changed',
                    $message ?? "Status changed to {$status}."
            );

            return true;
        }

        return false;
    }

    /**
     * Zapisuje wynik zadania.
     */
    public function complete(int $id, array $output): bool {
        $stmt = $this->pdo->prepare("
            UPDATE tasks
            SET
                status = 'completed',
                output_json = :output_json,
                finished_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'output_json' => json_encode(
                    $output,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'id' => $id,
        ]);

        if ($stmt->rowCount() > 0) {
            $this->addEvent($id, 'completed', 'Task completed.');

            return true;
        }

        return false;
    }

    /**
     * Oznacza zadanie jako nieudane.
     */
    public function fail(int $id, string $error): bool {
        $stmt = $this->pdo->prepare("
            UPDATE tasks
            SET
                status = 'failed',
                error_message = :error,
                finished_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'error' => $error,
            'id' => $id,
        ]);

        if ($stmt->rowCount() > 0) {
            $this->addEvent($id, 'failed', $error);

            return true;
        }

        return false;
    }

    /**
     * Zatwierdza zadanie oczekujące na akceptację.
     */
    public function approve(int $id, ?string $approvedBy = null): bool {
        $stmt = $this->pdo->prepare("
            UPDATE tasks
            SET
                status = 'queued',
                approved_by = :approved_by,
                approved_at = NOW()
            WHERE id = :id
              AND status = 'waiting_approval'
        ");

        $stmt->execute([
            'approved_by' => $approvedBy,
            'id' => $id,
        ]);

        if ($stmt->rowCount() > 0) {
            $this->addEvent($id, 'approved', 'Task approved.');

            return true;
        }

        return false;
    }

    /**
     * Pobiera historię zdarzeń zadania.
     */
    public function getEvents(int $taskId): array {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM task_events
            WHERE task_id = :task_id
            ORDER BY id ASC
        ");

        $stmt->execute(['task_id' => $taskId]);

        return $stmt->fetchAll();
    }

    /**
     * Dodaje zdarzenie do historii.
     */
    private function addEvent(
            int $taskId,
            string $eventType,
            string $message,
            array $details = []
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO task_events (
                task_id,
                event_type,
                message,
                details_json
            ) VALUES (
                :task_id,
                :event_type,
                :message,
                :details_json
            )
        ");

        $stmt->execute([
            'task_id' => $taskId,
            'event_type' => $eventType,
            'message' => $message,
            'details_json' => json_encode(
                    $details,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Waliduje status.
     */
    private function validateStatus(string $status): void {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException(
                            "Invalid task status: {$status}"
                    );
        }
    }

    /**
     * Pobiera i rezerwuje następne zadanie z kolejki.
     *
     * Dzięki transakcji i SKIP LOCKED wiele workerów
     * może bezpiecznie pobierać zadania równolegle.
     */
    public function claimNext(string $workerId): ?array {
        if (trim($workerId) === '') {
            throw new InvalidArgumentException('Worker ID is required.');
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
            SELECT t.id
            FROM tasks t
            INNER JOIN agents a ON a.id = t.agent_id
            WHERE t.status = 'queued'
              AND a.is_enabled = 1
              AND (t.scheduled_at IS NULL OR t.scheduled_at <= NOW())
              AND t.attempts < t.max_attempts
            ORDER BY t.priority DESC, t.created_at ASC
            LIMIT 1
            FOR UPDATE SKIP LOCKED
        ");

            $stmt->execute();

            $taskId = $stmt->fetchColumn();

            if (!$taskId) {
                $this->pdo->commit();
                return null;
            }

            $taskId = (int) $taskId;

            $update = $this->pdo->prepare("
            UPDATE tasks
            SET
                status = 'running',
                worker_id = :worker_id,
                locked_at = NOW(),
                started_at = COALESCE(started_at, NOW()),
                attempts = attempts + 1,
                updated_at = NOW()
            WHERE id = :id
        ");

            $update->execute([
                'worker_id' => $workerId,
                'id' => $taskId,
            ]);

            $this->addEvent(
                    $taskId,
                    'claimed',
                    "Task claimed by worker {$workerId}."
            );

            $this->pdo->commit();

            return $this->findById($taskId);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }
}
