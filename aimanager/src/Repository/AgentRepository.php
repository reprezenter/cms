<?php

namespace AIManager\Repository;

use PDO;
use InvalidArgumentException;

class AgentRepository
{
    public function __construct(
        private PDO $pdo
    ) {}

    /**
     * Tworzy nowego agenta.
     */
    public function create(array $data): int
    {
        $this->validateRequired($data);

        $sql = "
            INSERT INTO agents (
                name,
                slug,
                description,
                provider,
                model,
                system_prompt,
                tools_json,
                settings_json
            ) VALUES (
                :name,
                :slug,
                :description,
                :provider,
                :model,
                :system_prompt,
                :tools_json,
                :settings_json
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->prepareData($data));

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Aktualizuje istniejącego agenta.
     */
    public function update(int $id, array $data): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid agent ID.');
        }

        $this->validateRequired($data);

        $sql = "
            UPDATE agents SET
                name = :name,
                slug = :slug,
                description = :description,
                provider = :provider,
                model = :model,
                system_prompt = :system_prompt,
                tools_json = :tools_json,
                settings_json = :settings_json
            WHERE id = :id
        ";

        $params = $this->prepareData($data);
        $params['id'] = $id;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Usuwa agenta.
     */
    public function delete(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid agent ID.');
        }

        $stmt = $this->pdo->prepare(
            "DELETE FROM agents WHERE id = :id"
        );

        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Włącza lub wyłącza agenta.
     */
    public function setEnabled(int $id, bool $enabled): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid agent ID.');
        }

        $stmt = $this->pdo->prepare("
            UPDATE agents
            SET is_enabled = :enabled
            WHERE id = :id
        ");

        $stmt->execute([
            'enabled' => $enabled ? 1 : 0,
            'id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Pobiera wszystkich agentów.
     */
    public function findAll(bool $enabledOnly = false): array
    {
        $sql = "SELECT * FROM agents";

        if ($enabledOnly) {
            $sql .= " WHERE is_enabled = 1";
        }

        $sql .= " ORDER BY id DESC";

        return $this->pdo->query($sql)->fetchAll();
    }

    /**
     * Pobiera agenta po ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM agents WHERE id = :id LIMIT 1"
        );

        $stmt->execute(['id' => $id]);

        $agent = $stmt->fetch();

        return $agent ?: null;
    }

    /**
     * Pobiera agenta po slug.
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM agents WHERE slug = :slug LIMIT 1"
        );

        $stmt->execute(['slug' => $slug]);

        $agent = $stmt->fetch();

        return $agent ?: null;
    }

    /**
     * Przygotowuje dane do INSERT / UPDATE.
     */
    private function prepareData(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'slug' => trim($data['slug']),
            'description' => $data['description'] ?? null,
            'provider' => $data['provider'] ?? 'gemini',
            'model' => trim($data['model']),
            'system_prompt' => $data['system_prompt'] ?? null,
            'tools_json' => json_encode(
                $data['tools'] ?? [],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'settings_json' => json_encode(
                $data['settings'] ?? [],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * Waliduje wymagane dane.
     */
    private function validateRequired(array $data): void
    {
        foreach (['name', 'slug', 'model'] as $field) {
            if (
                !isset($data[$field]) ||
                trim((string) $data[$field]) === ''
            ) {
                throw new InvalidArgumentException(
                    "Field '{$field}' is required."
                );
            }
        }
    }
}
