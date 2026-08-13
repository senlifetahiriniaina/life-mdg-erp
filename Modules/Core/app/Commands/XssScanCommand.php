<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Core\Services\XssPreventionService;
use Modules\Core\Services\OutputEncodingService;

/**
 * XssScanCommand: Scan application for XSS vulnerabilities
 *
 * Scans the codebase and configuration for potential XSS vulnerabilities.
 */
class XssScanCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'xss:scan {--module= : Scan specific module} {--verbose : Verbose output}';

    /**
     * The console command description.
     */
    protected $description = 'Scan application for potential XSS vulnerabilities';

    /**
     * XSS Prevention Service
     */
    private XssPreventionService $xssPreventionService;

    /**
     * Output Encoding Service
     */
    private OutputEncodingService $outputEncodingService;

    /**
     * Constructor
     */
    public function __construct(
        XssPreventionService $xssPreventionService,
        OutputEncodingService $outputEncodingService
    ) {
        parent::__construct();
        $this->xssPreventionService = $xssPreventionService;
        $this->outputEncodingService = $outputEncodingService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting XSS vulnerability scan...');

        $module = $this->option('module');
        $verbose = $this->option('verbose');

        $vulnerabilities = [];

        // Scan template files
        $this->info('Scanning template files for unsafe output...');
        $vulnerabilities = array_merge(
            $vulnerabilities,
            $this->scanTemplateFiles($module, $verbose)
        );

        // Scan configuration files
        $this->info('Scanning configuration files...');
        $vulnerabilities = array_merge(
            $vulnerabilities,
            $this->scanConfigFiles($module, $verbose)
        );

        // Scan controller files
        $this->info('Scanning controller files for unsafe output...');
        $vulnerabilities = array_merge(
            $vulnerabilities,
            $this->scanControllerFiles($module, $verbose)
        );

        // Summary
        $this->line('');
        if (empty($vulnerabilities)) {
            $this->info('No vulnerabilities found. XSS prevention is properly implemented.');
            Log::info('XSS scan completed: No vulnerabilities found');
            return 0;
        }

        $this->error('Found ' . count($vulnerabilities) . ' potential vulnerabilities:');
        $this->line('');

        foreach ($vulnerabilities as $vulnerability) {
            $this->warn('  - ' . $vulnerability);
        }

        Log::warning('XSS scan completed: Found vulnerabilities', [
            'count' => count($vulnerabilities),
            'vulnerabilities' => $vulnerabilities,
        ]);

        return 1;
    }

    /**
     * Scan template files for unsafe output
     *
     * @param string|null $module Module to scan
     * @param bool $verbose Verbose output
     * @return array Vulnerabilities found
     */
    private function scanTemplateFiles(?string $module, bool $verbose): array
    {
        $vulnerabilities = [];
        $basePath = base_path('Modules');

        if ($module) {
            $paths = [base_path("Modules/$module/resources/views")];
        } else {
            $paths = [base_path('resources/views')];
            if (is_dir($basePath)) {
                foreach (scandir($basePath) as $dir) {
                    if ($dir !== '.' && $dir !== '..') {
                        $viewPath = "$basePath/$dir/resources/views";
                        if (is_dir($viewPath)) {
                            $paths[] = $viewPath;
                        }
                    }
                }
            }
        }

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $files = glob("$path/**/*.blade.php", GLOB_RECURSIVE);

            foreach ($files as $file) {
                $content = file_get_contents($file);

                // Look for unsafe patterns
                // Pattern: {!! $var !!} without proper sanitization context
                if (preg_match_all('/{!!\s*(\$[a-zA-Z_][a-zA-Z0-9_]*)\s*!!}/', $content, $matches)) {
                    foreach ($matches[1] as $var) {
                        $vulnerability = "Potentially unsafe HTML output in $file: $var";
                        $vulnerabilities[] = $vulnerability;
                        if ($verbose) {
                            $this->warn("  Template: $file");
                            $this->warn("    Variable: $var");
                        }
                    }
                }

                // Pattern: echo without escaping
                if (preg_match_all('/echo\s+(\$[a-zA-Z_][a-zA-Z0-9_]*)/', $content, $matches)) {
                    foreach ($matches[1] as $var) {
                        $vulnerability = "Potentially unescaped echo in $file: $var";
                        $vulnerabilities[] = $vulnerability;
                    }
                }
            }
        }

        return $vulnerabilities;
    }

    /**
     * Scan configuration files for unsafe settings
     *
     * @param string|null $module Module to scan
     * @param bool $verbose Verbose output
     * @return array Vulnerabilities found
     */
    private function scanConfigFiles(?string $module, bool $verbose): array
    {
        $vulnerabilities = [];

        // Check output-encoding config
        $config = config('output-encoding', []);

        if (empty($config['allowed_protocols'])) {
            $vulnerabilities[] = 'No allowed protocols defined in output-encoding config';
        }

        if (empty($config['blocked_protocols'])) {
            $vulnerabilities[] = 'No blocked protocols defined in output-encoding config';
        }

        // Check HTML purifier config
        $htmlConfig = config('html-purifier', []);

        if (empty($htmlConfig['policies'])) {
            $vulnerabilities[] = 'No HTML purification policies defined';
        }

        return $vulnerabilities;
    }

    /**
     * Scan controller files for unsafe output
     *
     * @param string|null $module Module to scan
     * @param bool $verbose Verbose output
     * @return array Vulnerabilities found
     */
    private function scanControllerFiles(?string $module, bool $verbose): array
    {
        $vulnerabilities = [];
        $basePath = base_path('Modules');

        if ($module) {
            $paths = [base_path("Modules/$module/app/Http/Controllers")];
        } else {
            $paths = [base_path('app/Http/Controllers')];
            if (is_dir($basePath)) {
                foreach (scandir($basePath) as $dir) {
                    if ($dir !== '.' && $dir !== '..') {
                        $controllerPath = "$basePath/$dir/app/Http/Controllers";
                        if (is_dir($controllerPath)) {
                            $paths[] = $controllerPath;
                        }
                    }
                }
            }
        }

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $files = glob("$path/**/*.php", GLOB_RECURSIVE);

            foreach ($files as $file) {
                $content = file_get_contents($file);

                // Look for direct echo of user input
                if (preg_match_all('/echo\s+(\$.*?);/s', $content, $matches)) {
                    foreach ($matches[1] as $match) {
                        if (strpos($match, 'request()') !== false || strpos($match, 'input(') !== false) {
                            $vulnerability = "Potentially unsafe echo of user input in $file";
                            $vulnerabilities[] = $vulnerability;
                            if ($verbose) {
                                $this->warn("  Controller: $file");
                                $this->warn("    Expression: echo $match");
                            }
                        }
                    }
                }
            }
        }

        return $vulnerabilities;
    }
}
