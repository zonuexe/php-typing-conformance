<?php

declare(strict_types=1);

namespace Conformance\Lsp;

/**
 * The language servers the probe run launches, with the flags and settings
 * that hold them to the suite's target PHP line (see the README section on
 * the measured PHP version — each server gets its knob set here or in the
 * config file copied into the workspace).
 *
 * Membership differs from LanguageServerCatalog on purpose: that table
 * records what every known server claims about itself, this one lists the
 * servers this machine launches headless — every claims-table row plus
 * Phan, whose server has a measurement here before it has been researched
 * for the claims table.
 *
 * How each of the last three is obtained: devsense-php-ls comes from npm
 * (its own vendor-bin namespace — phpy bundles a separately versioned copy
 * of the same engine and must not be reused for this). php-lsp and
 * phpantom ship as per-platform binaries on GitHub releases, fetched by
 * `make install-php-lsp` / `make install-phpantom` into
 * vendor-bin/<tool>/bin/; neither is on Packagist or npm.
 */
final class LspServerCatalog
{
    /** @return list<LspServer> */
    public static function all(string $projectRoot, string $lspDir): array
    {
        $intelephenseJs = $projectRoot . '/vendor-bin/intelephense/node_modules/intelephense/lib/intelephense.js';

        return [
            new LspServer(
                tool: 'intelephense',
                command: ['node', $intelephenseJs, '--stdio'],
                // clearCache keeps runs order-independent, same as the
                // diagnostics client; the storagePath is set per run.
                initializationOptions: ['clearCache' => true],
                settings: [
                    'intelephense' => [
                        'environment' => ['phpVersion' => '8.5.0'],
                        'files' => ['associations' => ['*.php'], 'maxSize' => 5000000],
                        'diagnostics' => ['enable' => true, 'run' => 'onType'],
                    ],
                ],
                packageJsonPath: $projectRoot . '/vendor-bin/intelephense/node_modules/intelephense/package.json',
            ),
            new LspServer(
                tool: 'psalm',
                command: [
                    $projectRoot . '/vendor-bin/psalm/vendor/bin/psalm-language-server',
                    '-c', '{workspace}/psalm.xml',
                    '-r', '{workspace}',
                ],
                configFiles: ['psalm.xml' => $lspDir . '/config/psalm.xml'],
                versionCommand: [$projectRoot . '/vendor-bin/psalm/vendor/bin/psalm-language-server', '--version'],
            ),
            new LspServer(
                tool: 'phpactor',
                // Inlay hints are Phpactor's own, off by default, and turned on
                // by configuration rather than by an extension, so the run asks
                // for them. `types` stays off even once hints are enabled, and
                // it is the kind this suite is about: the inferred type at a
                // binding, the same information a completion list draws on.
                command: [
                    $projectRoot . '/vendor-bin/phpactor/vendor/bin/phpactor',
                    'language-server',
                    '--config-extra=' . json_encode([
                        'language_server_worse_reflection.inlay_hints.enable' => true,
                        'language_server_worse_reflection.inlay_hints.types' => true,
                    ], JSON_THROW_ON_ERROR),
                ],
                versionCommand: [$projectRoot . '/vendor-bin/phpactor/vendor/bin/phpactor', '--version'],
            ),
            new LspServer(
                tool: 'devsense-php-ls',
                // Plain stdio with no transport flag; the .bin shim is a JS
                // wrapper around the native per-platform binary.
                command: ['node', $projectRoot . '/vendor-bin/devsense-php-ls/node_modules/.bin/devsense-php-ls'],
                packageJsonPath: $projectRoot . '/vendor-bin/devsense-php-ls/node_modules/devsense-php-ls/package.json',
            ),
            new LspServer(
                tool: 'phpantom',
                command: [$projectRoot . '/vendor-bin/phpantom/bin/phpantom_lsp', '--stdio'],
                versionCommand: [$projectRoot . '/vendor-bin/phpantom/bin/phpantom_lsp', '--version'],
            ),
            new LspServer(
                tool: 'php-lsp',
                command: [$projectRoot . '/vendor-bin/php-lsp/bin/php-lsp'],
                versionCommand: [$projectRoot . '/vendor-bin/php-lsp/bin/php-lsp', '--version'],
            ),
            new LspServer(
                tool: 'phan',
                command: [
                    $projectRoot . '/vendor-bin/phan/vendor/bin/phan',
                    '--language-server-on-stdin',
                    '--language-server-enable-hover',
                    '--language-server-enable-go-to-definition',
                    '--language-server-enable-completion',
                    '--allow-polyfill-parser',
                    '--language-server-allow-missing-pcntl',
                ],
                configFiles: ['.phan/config.php' => $lspDir . '/config/phan/config.php'],
                versionCommand: [$projectRoot . '/vendor-bin/phan/vendor/bin/phan', '--version'],
            ),
        ];
    }
}
