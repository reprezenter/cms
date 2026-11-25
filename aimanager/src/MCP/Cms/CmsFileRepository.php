<?php

namespace AIManager\MCP\Cms;

use RuntimeException;

class CmsFileRepository {

    private string $contentDir;
    private string $backupDir;
    private string $menuFile;

    public function __construct(
            string $cmsRoot = '../public',
            string $backupDir = '../storage/aimanager/backups/cms'
    ) {
        $this->contentDir = rtrim($cmsRoot, '/') . '/content';
        $this->backupDir = rtrim($backupDir, '/');
        $this->menuFile = $this->contentDir . '/data/menu.json';

        if (!is_dir($this->contentDir)) {
            throw new RuntimeException('CMS content directory does not exist.');
        }

        if (!is_dir($this->backupDir) &&
                !mkdir($this->backupDir, 0775, true) &&
                !is_dir($this->backupDir)
        ) {
            throw new RuntimeException('Cannot create backup directory.');
        }
    }

    /**
     * Lista podstron.
     */
    public function listPages(): array {
        $files = glob($this->contentDir . '/_*.phtml') ?: [];
        $pages = [];

        foreach ($files as $file) {
            $filename = basename($file, '.phtml');

            // Homepage: _.phtml
            // Other pages: _kontakt.phtml, _oferta.phtml
            $slug = ($filename === '_') ? 'home' : substr($filename, 1);

            $source = file_get_contents($file);

            if ($source === false) {
                continue;
            }

            $title = $slug === 'home' ? 'Strona główna' : $slug;

            if (preg_match(
                            '/\$title\s*=\s*[\'"]([^\'"]*)[\'"]\s*;/u',
                            $source,
                            $matches
                    )) {
                $title = $matches[1];
            }

            $pages[] = [
                'slug' => $slug,
                'title' => $title,
                'file' => basename($file)
            ];
        }

        return $pages;
    }

    /**
     * Odczyt całego pliku podstrony.
     */
    public function readPage(string $slug): array {
        $file = $this->getPagePath($slug);

        if (!is_file($file)) {
            throw new RuntimeException('Page does not exist: ' . $slug);
        }

        $content = file_get_contents($file);

        if ($content === false) {
            throw new RuntimeException('Cannot read page.');
        }

        return [
            'slug' => $slug,
            'file' => basename($file),
            'content' => $content
        ];
    }

    /**
     * Utworzenie nowej podstrony.
     */
    public function createPage(
            string $slug,
            string $title,
            string $description,
            string $html
    ): array {
        $file = $this->getPagePath($slug);

        if (is_file($file)) {
            throw new RuntimeException('Page already exists: ' . $slug);
        }

        $content = $this->buildPageContent(
                $title,
                $description,
                $html
        );

        $this->atomicWrite($file, $content);

        return [
            'success' => true,
            'slug' => $slug,
            'file' => basename($file)
        ];
    }

    /**
     * Aktualizacja istniejącej podstrony.
     */
    public function updatePage(
            string $slug,
            string $title,
            string $description,
            string $html
    ): array {
        $file = $this->getPagePath($slug);

        if (!is_file($file)) {
            throw new RuntimeException('Page does not exist: ' . $slug);
        }

        $this->createBackup($file);

        $content = $this->buildPageContent(
                $title,
                $description,
                $html
        );

        $this->atomicWrite($file, $content);

        return [
            'success' => true,
            'slug' => $slug,
            'file' => basename($file),
            'backup_created' => true
        ];
    }

    /**
     * Usunięcie podstrony.
     */
    public function deletePage(string $slug): array {
        if ($slug === '_') {
            throw new RuntimeException('Homepage cannot be deleted.');
        }

        $file = $this->getPagePath($slug);

        if (!is_file($file)) {
            throw new RuntimeException('Page does not exist.');
        }

        $backup = $this->createBackup($file);

        if (!unlink($file)) {
            throw new RuntimeException('Cannot delete page.');
        }

        return [
            'success' => true,
            'slug' => $slug,
            'backup' => basename($backup)
        ];
    }

    /**
     * Odczyt menu.
     */
    public function getMenu(): array {
        if (!is_file($this->menuFile)) {
            throw new RuntimeException('Menu JSON does not exist.');
        }

        $json = file_get_contents($this->menuFile);
        $menu = json_decode($json, true);

        if (!is_array($menu)) {
            throw new RuntimeException('Invalid menu JSON.');
        }

        return $menu;
    }

    /**
     * Aktualizacja menu głównego i stopki.
     */
    public function updateMenu(array $menu): array {
        foreach (['main', 'footer'] as $name) {
            if (!isset($menu[$name]) || !is_array($menu[$name])) {
                throw new RuntimeException(
                                'Missing or invalid menu: ' . $name
                        );
            }

            foreach ($menu[$name] as $item) {
                if (
                        !is_array($item) ||
                        empty($item['title']) ||
                        empty($item['url'])
                ) {
                    throw new RuntimeException(
                                    'Every menu item needs title and url.'
                            );
                }

                $url = trim((string) $item['url']);

                if (
                        preg_match('/[\x00-\x20]/', $url) ||
                        preg_match('/^(javascript|data|vbscript):/i', $url)
                ) {
                    throw new RuntimeException('Invalid menu URL.');
                }
            }
        }

        if (!is_file($this->menuFile)) {
            throw new RuntimeException('Menu JSON does not exist.');
        }

        $this->createBackup($this->menuFile);

        $json = json_encode(
                $menu,
                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_THROW_ON_ERROR
        );

        $this->atomicWrite($this->menuFile, $json . PHP_EOL);

        return [
            'success' => true,
            'main_items' => count($menu['main']),
            'footer_items' => count($menu['footer'])
        ];
    }

    /**
     * Buduje bezpieczny plik PHTML.
     */

/**
 * Buduje plik PHTML z metadanymi SEO i treścią strony.
 */
private function buildPageContent(
        string $title,
        string $description,
        string $html
): string {
    if (trim($title) === '') {
        throw new RuntimeException('Page title cannot be empty.');
    }

    if (strlen($html) > 200000) {
        throw new RuntimeException('Page content is too large.');
    }

    $content = trim($html);

    if ($content === '') {
        throw new RuntimeException('Page content cannot be empty.');
    }

    // Metadane SEO generuje CMS na podstawie parametrów.
    $seoHeader = "<?php\n"
            . '$title = ' . var_export($title, true) . ";\n"
            . '$description = ' . var_export($description, true) . ";\n"
            . "?>\n\n";

    return $seoHeader . $content . "\n";
}


    /**
     * Walidacja sluga i ścieżki.
     */
    private function getPagePath(string $slug): string {
        // Homepage
        if ($slug === 'home') {
            return $this->contentDir . '/_.phtml';
        }

        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,79}$/', $slug)) {
            throw new RuntimeException('Invalid page slug.');
        }

        return $this->contentDir . '/_' . $slug . '.phtml';
    }

    /**
     * Kopia zapasowa pliku.
     */
    private function createBackup(string $file): string {
        $filename = basename($file);

        $backup = $this->backupDir . '/'
                . date('Ymd_His')
                . '_'
                . bin2hex(random_bytes(3))
                . '_'
                . $filename;

        if (!copy($file, $backup)) {
            throw new RuntimeException('Cannot create backup.');
        }

        return $backup;
    }

    /**
     * Atomowy zapis pliku.
     */
    private function atomicWrite(string $file, string $content): void {
        $directory = dirname($file);

        $tempFile = tempnam($directory, '.cms_');

        if ($tempFile === false) {
            throw new RuntimeException('Cannot create temporary file.');
        }

        try {
            if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
                throw new RuntimeException('Cannot write temporary file.');
            }

            chmod($tempFile, 0664);

            if (!rename($tempFile, $file)) {
                throw new RuntimeException('Cannot replace file.');
            }
        } finally {
            if (is_file($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
