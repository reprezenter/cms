<?php

use AIManager\Repository\TaskRepository;
use AIManager\Repository\AgentRepository;
use AIManager\LLM\GeminiClient;
use AIManager\MCP\McpClient;
use AIManager\MCP\McpGeminiAdapter;
use AIManager\Agent\AgentRunner;

if (PHP_SAPI !== 'cli') {
    exit('CLI only.');
}
define('PUBLIC_PATH', __DIR__ . '/../..');

// Worker znajduje się w public/aimanager/bin
$baseDir = dirname(__DIR__);

require $baseDir . '/src/Repository/TaskRepository.php';
require $baseDir . '/src/Repository/AgentRepository.php';
require $baseDir . '/src/LLM/GeminiClient.php';
require $baseDir . '/src/MCP/McpClient.php';
require $baseDir . '/src/MCP/McpGeminiAdapter.php';
require $baseDir . '/src/Agent/AgentRunner.php';

$pdo = require $baseDir . '/bootstrap.php';
require __DIR__ . '/../../config.php';

$tasks = new TaskRepository($pdo);
$agents = new AgentRepository($pdo);

$gemini = new GeminiClient(
    $llm['gemini']['api_key'],
    $llm['gemini']['base_url'],
    $llm['gemini']['timeout']
);

$workerId = gethostname() . '-' . getmypid();
$once = in_array('--once', $argv, true);

echo "AI Manager Worker started.\n";
echo "Worker ID: {$workerId}\n";

do {
    $taskId = null;
    $mcp = null;

    try {
        $task = $tasks->claimNext($workerId);

        if ($task === null) {
            if ($once) {
                echo "No tasks available.\n";
                break;
            }

            sleep(3);
            continue;
        }

        $taskId = (int) $task['id'];

        echo "\nProcessing task #{$taskId}\n";

        $agent = $agents->findById((int) $task['agent_id']);

        if (!$agent || !(bool) $agent['is_enabled']) {
            throw new RuntimeException('Agent not found or disabled.');
        }

        $settings = json_decode(
            $agent['settings_json'] ?: '{}',
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $input = json_decode(
            $task['input_json'] ?: '{}',
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        switch ($task['type']) {

            case 'site.build':

                $topic = trim($input['topic'] ?? '');
                $language = $input['language'] ?? 'pl';
                $instructions = $input['instructions'] ?? '';

                if ($topic === '') {
                    throw new RuntimeException('Website topic is required.');
                }

                $userPrompt = <<<PROMPT
ZADANIE: Zbuduj kompletną stronę internetową na podstawie istniejącego szablonu CMS.

TEMAT STRONY:
{$topic}

JĘZYK:
{$language}

DODATKOWE INSTRUKCJE:
{$instructions}

Zbuduj całą witrynę samodzielnie, wykonując operacje bezpośrednio
w CMS za pomocą narzędzi MCP.

ETAP 1 — ANALIZA:
1. Użyj cms_list_pages.
2. Użyj cms_get_menu.
3. Odczytaj strony home, o-nas, oferta, katalog i kontakt.
4. Poznaj istniejącą strukturę HTML i sposób prezentacji treści.
5. Zachowaj mechanizmy działania CMS.

ETAP 2 — PLAN:
Zaprojektuj:
- stronę główną,
- O nas,
- Ofertę,
- Katalog lub Tematy,
- Kontakt,
- trzy dodatkowe podstrony z artykułami.

ETAP 3 — PRZEBUDOWA:
1. Przebuduj stronę główną.
2. Przebuduj o-nas, oferta, katalog i kontakt.
3. Zachowaj ich dotychczasowe adresy URL.
4. Utwórz trzy nowe podstrony z artykułami.
5. Używaj cms_update_page do aktualizacji.
6. Używaj cms_create_page do tworzenia.
7. Po zapisaniu każdej strony odczytaj ją przez cms_read_page i sprawdź, czy treść została poprawnie zapisana.

ETAP 4 — MENU:
1. Pobierz aktualne menu.
2. Przebuduj menu główne pod nową tematykę.
3. Dodaj odnośniki do nowych artykułów.
4. Zachowaj prawidłową strukturę JSON.
5. Nie zmieniaj menu stopki bez potrzeby.
6. Zapisz menu przez cms_update_menu.


ETAP 5 — JAKOŚĆ:
- Pisz profesjonalne, unikalne treści dopasowane do tematyki witryny.
- Używaj HTML z nagłówkami h1, h2 i h3.
- Dla każdej strony przygotuj osobny, atrakcyjny tytuł SEO i opis SEO.
- Przekazuj tytuł i opis SEO w parametrach title oraz description narzędzi CMS.
- CMS automatycznie umieszcza metadane SEO na początku pliku PHTML.
- W parametrze html przekazuj właściwą zawartość strony: HTML oraz opcjonalne fragmenty PHP.
- Nie powtarzaj deklaracji \$title i \$description w parametrze html.
- Zachowuj istniejące klasy CSS i strukturę komponentów szablonu.
- Nie usuwaj elementów odpowiedzialnych za działanie CMS.
- Generuj poprawny HTML, a nie kod HTML zapisany jako tekst JSON.
- Atrybuty HTML zapisuj ze zwykłymi cudzysłowami, np. href="/adres.html".
- Nigdy nie używaj sekwencji \" w atrybutach HTML.
- Wszystkie linki wewnętrzne zapisuj jako adresy absolutne względem domeny, np. /nazwa-strony.html.
- Linki muszą prowadzić do faktycznie istniejących stron CMS.
- Możesz używać PHP w plikach PHTML, jeśli jest potrzebny do działania szablonu.
- Zachowuj poprawną składnię PHP.
- Nie używaj niepotrzebnego kodu PHP.
- Nie generuj kodu PHP służącego do wykonywania poleceń systemowych, modyfikowania plików lub dostępu do konfiguracji serwera.
- Nie umieszczaj znaczników PHP wewnątrz atrybutów HTML.
- Poprawny przykład linku:
  <a href="/nazwa-strony.html">Zobacz artykuł</a>

OGRANICZENIA:
- Nie modyfikuj login, logout, rejestracja ani ciasteczka.
- Nie modyfikuj plików systemowych CMS.
- Nie usuwaj istniejących stron.
- Nie zmieniaj konfiguracji serwera.
- Nie pytaj o zatwierdzenie zmian.
- Nie twórz duplikatów stron.
- Jeśli slug jest zajęty, wybierz inny.

ZASADA:
Nie kończ zadania po przygotowaniu planu.
Nie kończ po utworzeniu samej strony głównej.
Wszystkie zaplanowane strony i menu muszą zostać faktycznie
zapisane za pomocą narzędzi MCP.

Na końcu ponownie sprawdź listę stron i menu.

Podsumuj wykonane operacje, adresy URL i ewentualne błędy.
PROMPT;

                break;

            case 'content.create':

                $topic = $input['topic'] ?? 'Temat nie został określony';
                $language = $input['language'] ?? 'pl';

                $userPrompt = "
Przygotuj profesjonalny artykuł.
Temat: {$topic}
Język: {$language}
Dodatkowe instrukcje: " .
                    ($input['instructions'] ?? 'Brak') . "
Zwróć gotowy artykuł.
";

                break;

            default:
                throw new RuntimeException(
                    'Unsupported task type: ' . $task['type']
                );
        }

        // Połącz z MCP.
        echo "Connecting to MCP...\n";

        $mcp = new McpClient(
            $baseDir . '/bin/cms-mcp.php',
            30
        );

        $mcp->connect();

        $adapter = new McpGeminiAdapter($mcp);

        // Instrukcje systemowe agenta.
        $systemPrompt = (
            $agent['system_prompt']
                ?: 'Jesteś pomocnym asystentem AI.'
        ) . "\n\n"
            . "Zarządzasz treścią strony CMS przez narzędzia MCP. "
            . "Samodzielnie wykonuj operacje potrzebne do realizacji zadania. "
            . "Nie pytaj użytkownika o zatwierdzenie operacji. "
            . "Nie twierdź, że coś zostało zapisane, jeżeli narzędzie "
            . "nie potwierdziło wykonania operacji.";

        $runner = new AgentRunner(
            $gemini,
            $adapter,
            (int) ($settings['max_iterations'] ?? 25)
        );

        // Uruchom agenta.
        echo "Starting agent...\n";

        $result = $runner->run(
            $agent['model'],
            $systemPrompt,
            $userPrompt,
            [
                'temperature' => $settings['temperature'] ?? 0.7,
                'max_output_tokens' => $settings['max_output_tokens'] ?? 4096,
                'function_calling_mode' => 'AUTO',
            ]
        );

        if (!$result['success']) {
            throw new RuntimeException(
                $result['error'] ?? 'Agent execution failed.'
            );
        }

        // Zapisz wynik zadania.
        $tasks->complete($taskId, [
            'success' => true,
            'text' => $result['text'],
            'model' => $result['model'],
            'iterations' => $result['iterations'],
            'tool_calls' => $result['tool_calls'],
            'usage' => $result['usage'],
            'finish_reason' => $result['finish_reason'],
        ]);

        $totalTokens = 0;

        foreach ($result['usage'] as $usage) {
            $totalTokens += (int) ($usage['totalTokenCount'] ?? 0);
        }

        echo "Task #{$taskId} completed.\n";
        echo "Model: {$result['model']}\n";
        echo "Iterations: {$result['iterations']}\n";
        echo "Tool calls: {$result['tool_calls']}\n";
        echo "Total tokens: {$totalTokens}\n";

        echo "\nAgent response:\n";
        echo $result['text'] . "\n";

    } catch (Throwable $e) {

        echo "ERROR: " . $e->getMessage() . "\n";

        if ($taskId !== null) {
            try {
                $tasks->fail($taskId, $e->getMessage());
            } catch (Throwable $failure) {
                echo "Could not save failure: "
                    . $failure->getMessage() . "\n";
            }
        }

    } finally {

        if ($mcp instanceof McpClient) {
            $mcp->close();
        }
    }

    unset($task, $taskId, $mcp);

} while (!$once);

echo "\nWorker stopped.\n";
