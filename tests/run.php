<?php

namespace dokuwiki\Extension {
    trait TestPluginLanguage
    {
        public function getConf(string $key)
        {
            return $GLOBALS['testConfig'][$key] ?? null;
        }

        public function getLang(string $key): string
        {
            $language = $GLOBALS['testLanguage'] ?? 'en';
            $translations = [
                'en' => [
                    'invalid_id' => '[invalid vpsAdmin documentation ID]',
                    'invalid_managed' => '[invalid managed-page marker]',
                    'managed_source_tool' => 'Source on GitHub',
                    'managed_source_link' => 'source on GitHub',
                    'managed_test_link' => 'test suite source',
                    'managed_guide_page' => 'information:kb',
                    'managed_guide_link' => 'Contributing to the Knowledge Base',
                    'managed_edit_title' => 'This page is managed in a repository',
                    'managed_edit_warning' => 'Automated tests do not cover changes made only in this editor.',
                    'managed_source_label' => 'Source:',
                    'managed_test_label' => 'Tests:',
                    'managed_guide_label' => 'Editing guide:',
                    'managed_config_title' => 'Managed-page configuration error',
                    'managed_config_error' => 'The repository links for this page could not be created. Check the vpsadmindoc plugin configuration.',
                ],
                'cs' => [
                    'invalid_id' => '[neplatný identifikátor dokumentace vpsAdminu]',
                    'invalid_managed' => '[neplatná značka stránky spravované v repozitáři]',
                    'managed_source_tool' => 'Zdroj na GitHubu',
                    'managed_source_link' => 'zdroj na GitHubu',
                    'managed_test_link' => 'zdroj testů',
                    'managed_guide_page' => 'informace:jak_psat',
                    'managed_guide_link' => 'Jak přispívat do znalostní báze',
                    'managed_edit_title' => 'Stránka je spravovaná v repozitáři',
                    'managed_edit_warning' => 'Změny provedené pouze v tomto editoru se netestují automaticky.',
                    'managed_source_label' => 'Zdroj:',
                    'managed_test_label' => 'Testy:',
                    'managed_guide_label' => 'Postup úprav:',
                    'managed_config_title' => 'Chyba nastavení spravované stránky',
                    'managed_config_error' => 'Odkazy do repozitáře se nepodařilo vytvořit. Zkontroluj nastavení pluginu vpsadmindoc.',
                ],
            ];

            return $translations[$language][$key] ?? $key;
        }
    }

    abstract class SyntaxPlugin
    {
        use TestPluginLanguage;

        public $Lexer;
    }

    abstract class ActionPlugin
    {
        use TestPluginLanguage;

        abstract public function register(EventHandler $controller);
    }

    class Event
    {
        public $data;

        public function __construct($data)
        {
            $this->data = $data;
        }
    }

    class EventHandler
    {
        public array $hooks = [];

        public function register_hook($event, $when, $object, $method): void
        {
            $this->hooks[] = [$event, $when, $method];
        }
    }
}

namespace {
    const DOKU_LEXER_ENTER = 1;
    const DOKU_LEXER_UNMATCHED = 2;
    const DOKU_LEXER_EXIT = 3;
    const DOKU_LEXER_SPECIAL = 4;
    const METADATA_RENDER_USING_CACHE = 2;

    class Doku_Renderer
    {
        public string $doc = '';
        public array $meta = [];
        public bool $capture = true;

        public function cdata(string $value): void
        {
            $this->doc .= htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
    }

    class Doku_Handler
    {
    }

    function hsc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    function p_get_metadata($id, $key = '', $render = METADATA_RENDER_USING_CACHE)
    {
        return $GLOBALS['testMetadata'] ?? null;
    }

    function wl(string $id): string
    {
        return '/doku.php?id=' . rawurlencode($id);
    }

    require dirname(__DIR__) . '/managed.php';
    require dirname(__DIR__) . '/syntax.php';
    require dirname(__DIR__) . '/syntax/managed.php';
    require dirname(__DIR__) . '/action.php';

    function assertSame($expected, $actual, string $message): void
    {
        if ($expected !== $actual) {
            fwrite(STDERR, "$message\nExpected: " . var_export($expected, true)
                . "\nActual: " . var_export($actual, true) . "\n");
            exit(1);
        }
    }

    function assertContains(string $needle, string $haystack, string $message): void
    {
        if (!str_contains($haystack, $needle)) {
            fwrite(STDERR, "$message\nMissing: " . var_export($needle, true)
                . "\nActual: " . var_export($haystack, true) . "\n");
            exit(1);
        }
    }

    function assertNotContains(string $needle, string $haystack, string $message): void
    {
        if (str_contains($haystack, $needle)) {
            fwrite(STDERR, "$message\nUnexpected: " . var_export($needle, true)
                . "\nActual: " . var_export($haystack, true) . "\n");
            exit(1);
        }
    }

    assertSame(
        'member.public-keys.add',
        syntax_plugin_vpsadmindoc::parseId(
            '<vpsadmin-nav id="member.public-keys.add">'
        ),
        'double-quoted semantic ID parses'
    );
    assertSame(
        'vps.details.set-root-password',
        syntax_plugin_vpsadmindoc::parseId(
            "<vpsadmin-nav id='vps.details.set-root-password'>"
        ),
        'single-quoted semantic ID parses'
    );
    foreach ([
        '<vpsadmin-nav>',
        '<vpsadmin-nav id="Uppercase.invalid">',
        '<vpsadmin-nav id="valid.id" onclick="bad">',
        '<vpsadmin-nav id="valid..id">',
    ] as $invalid) {
        assertSame(null, syntax_plugin_vpsadmindoc::parseId($invalid), "reject $invalid");
    }

    $plugin = new syntax_plugin_vpsadmindoc();
    $handler = new Doku_Handler();
    $renderer = new Doku_Renderer();
    $plugin->render(
        'xhtml',
        $renderer,
        $plugin->handle(
            '<vpsadmin-nav id="member.public-keys.add">',
            DOKU_LEXER_ENTER,
            0,
            $handler
        )
    );
    $plugin->render(
        'xhtml',
        $renderer,
        $plugin->handle('<unsafe & text>', DOKU_LEXER_UNMATCHED, 42, $handler)
    );
    $plugin->render(
        'xhtml',
        $renderer,
        $plugin->handle('</vpsadmin-nav>', DOKU_LEXER_EXIT, 57, $handler)
    );
    assertSame(
        '<span class="vpsadmindoc-nav" data-vpsadmin-doc-id="member.public-keys.add">'
            . '&lt;unsafe &amp; text&gt;</span>',
        $renderer->doc,
        'XHTML output is marked and escaped'
    );

    $metadata = new Doku_Renderer();
    foreach (['member.public-keys.add', 'member.public-keys.add', 'vps.details'] as $id) {
        $plugin->render('metadata', $metadata, [DOKU_LEXER_ENTER, $id]);
    }
    assertSame(
        ['member.public-keys.add', 'vps.details'],
        $metadata->meta['vpsadmindoc']['navigation'],
        'metadata IDs are unique and ordered'
    );

    $invalidRenderer = new Doku_Renderer();
    $plugin->render('xhtml', $invalidRenderer, [DOKU_LEXER_ENTER, null]);
    $plugin->render('xhtml', $invalidRenderer, [DOKU_LEXER_UNMATCHED, 'visible body']);
    $plugin->render('xhtml', $invalidRenderer, [DOKU_LEXER_EXIT, '</vpsadmin-nav>']);
    assertSame(
        '<span class="vpsadmindoc-nav vpsadmindoc-nav--invalid"'
            . ' data-vpsadmin-doc-error="invalid-id">'
            . '<strong class="vpsadmindoc-nav__warning">'
            . '[invalid vpsAdmin documentation ID]</strong> visible body</span>',
        $invalidRenderer->doc,
        'invalid IDs produce a visible diagnostic'
    );

    $legacySourceUrl = 'https://github.com/vpsfreecz/vpsfree-kb-contracts/'
        . 'blob/master/contract/pages/manuals-vps-kvm.txt';
    $legacyTestUrl = 'https://github.com/vpsfreecz/vpsfree-kb-contracts/'
        . 'blob/master/tests/suite/kb/kvm.nix';
    $legacyTag = "<kb-managed\n  source=\"$legacySourceUrl\""
        . "\n  test=\"$legacyTestUrl\"\n/>";
    $legacyManaged = ['source' => $legacySourceUrl, 'test' => $legacyTestUrl];
    $managedTag = "<kb-managed\n  source=\"contract/pages/manuals-vps-kvm.txt\""
        . "\n  test=\"kb/kvm#*\"\n/>";
    $managed = [
        'source' => 'contract/pages/manuals-vps-kvm.txt',
        'test' => 'kb/kvm#*',
    ];

    assertSame(
        $managed,
        VpsAdminDocManagedPage::parseTag($managedTag),
        'relative managed-page marker parses'
    );
    assertSame(
        $legacyManaged,
        VpsAdminDocManagedPage::parseTag($legacyTag),
        'legacy managed-page marker remains supported'
    );
    assertSame(
        'tests/suite/kb/firewall.nix',
        VpsAdminDocManagedPage::testSource('kb/firewall#*'),
        'test selector maps to its suite source'
    );
    foreach ([
        '<kb-managed />',
        "<kb-managed source=\"$legacySourceUrl\" test=\"kb/kvm#*\" />",
        '<kb-managed source="contract/pages/page.txt" test="javascript:alert(1)" />',
        '<kb-managed source="contract/pages/page.txt" test="kb/kvm#specific" />',
        '<kb-managed source="contract/pages/page.txt" test="KB/kvm#*" />',
        '<kb-managed source="../page.txt" test="kb/kvm#*" />',
        '<kb-managed source="contract//page.txt" test="kb/kvm#*" />',
        '<kb-managed source="contract/pages/page.txt?raw=1" test="kb/kvm#*" />',
        "<kb-managed source=\"$legacySourceUrl\" source=\"$legacySourceUrl\""
            . " test=\"$legacyTestUrl\" />",
        "<kb-managed source=\"$legacySourceUrl\" test=\"$legacyTestUrl\" extra=\"bad\" />",
        "<kb-managed source=\"https://github.com/a/b/blob/master/../secret\""
            . " test=\"$legacyTestUrl\" />",
    ] as $invalid) {
        assertSame(null, VpsAdminDocManagedPage::parseTag($invalid), "reject $invalid");
    }

    $repositoryUrl = 'https://github.com/vpsfreecz/vpsfree-kb-contracts';
    $masterManaged = [
        'source' => $repositoryUrl . '/blob/master/contract/pages/manuals-vps-kvm.txt',
        'test' => $repositoryUrl . '/blob/master/tests/suite/kb/kvm.nix',
        'test_selector' => 'kb/kvm#*',
    ];
    assertSame(
        $masterManaged,
        VpsAdminDocManagedRepository::resolve($managed, $repositoryUrl, 'master', ''),
        'relative marker resolves against the configured master branch'
    );
    $staticCommitResolution = VpsAdminDocManagedRepository::resolve(
        $managed,
        $repositoryUrl,
        str_repeat('c', 40),
        ''
    );
    assertContains(
        '/blob/' . str_repeat('c', 40) . '/',
        $staticCommitResolution['source'],
        'relative marker accepts a static immutable commit'
    );
    assertSame(
        [
            'source' => $legacySourceUrl,
            'test' => $legacyTestUrl,
            'test_selector' => 'kb/kvm#*',
        ],
        VpsAdminDocManagedRepository::resolve($legacyManaged, '', 'invalid', '/missing'),
        'legacy marker does not depend on repository configuration'
    );

    $firstRef = str_repeat('a', 40);
    $secondRef = str_repeat('b', 40);
    $refFile = tempnam(sys_get_temp_dir(), 'vpsadmindoc-ref-');
    if ($refFile === false) {
        fwrite(STDERR, "could not create temporary ref file\n");
        exit(1);
    }
    file_put_contents($refFile, $firstRef . "\n");
    $firstResolution = VpsAdminDocManagedRepository::resolve(
        $managed,
        $repositoryUrl,
        'master',
        $refFile
    );
    assertContains('/blob/' . $firstRef . '/', $firstResolution['source'], 'ref file overrides static ref');
    file_put_contents($refFile, $secondRef . "\n");
    $secondResolution = VpsAdminDocManagedRepository::resolve(
        $managed,
        $repositoryUrl,
        'master',
        $refFile
    );
    assertContains(
        '/blob/' . $secondRef . '/',
        $secondResolution['source'],
        'updated ref file is read on the next request'
    );
    assertNotContains(
        '/blob/' . $firstRef . '/',
        $secondResolution['source'],
        'updated ref file is not cached'
    );
    file_put_contents($refFile, "main\n");
    assertSame(
        null,
        VpsAdminDocManagedRepository::resolve($managed, $repositoryUrl, 'master', $refFile),
        'invalid ref file fails closed instead of using the static ref'
    );
    file_put_contents($refFile, " master\n");
    assertSame(
        null,
        VpsAdminDocManagedRepository::resolve($managed, $repositoryUrl, 'master', $refFile),
        'ref file does not accept surrounding spaces'
    );
    unlink($refFile);

    foreach ([
        ['http://github.com/vpsfreecz/vpsfree-kb-contracts', 'master', ''],
        ['https://example.test/vpsfreecz/vpsfree-kb-contracts', 'master', ''],
        ['https://github.com/vpsfreecz/vpsfree-kb-contracts/issues', 'master', ''],
        ['https://github.com/vpsfreecz/..', 'master', ''],
        [$repositoryUrl, 'main', ''],
        [$repositoryUrl, strtoupper($firstRef), ''],
        [$repositoryUrl, 'master', 'relative/ref'],
        [$repositoryUrl, 'master', '/missing/ref'],
    ] as [$url, $ref, $file]) {
        assertSame(
            null,
            VpsAdminDocManagedRepository::resolve($managed, $url, $ref, $file),
            "invalid repository configuration is rejected: $url $ref $file"
        );
    }

    $managedPlugin = new syntax_plugin_vpsadmindoc_managed();
    $managedRenderer = new Doku_Renderer();
    $managedData = $managedPlugin->handle(
        $managedTag,
        DOKU_LEXER_SPECIAL,
        0,
        new Doku_Handler()
    );
    $managedPlugin->render('xhtml', $managedRenderer, $managedData);
    assertSame('', $managedRenderer->doc, 'valid managed marker renders no XHTML');
    $managedPlugin->render('metadata', $managedRenderer, $managedData);
    assertSame(
        [$managed],
        $managedRenderer->meta['vpsadmindoc']['managed'],
        'managed marker records source and test metadata'
    );

    $invalidManagedRenderer = new Doku_Renderer();
    $managedPlugin->render('xhtml', $invalidManagedRenderer, null);
    assertContains(
        'data-vpsadmin-doc-error="invalid-managed-page"',
        $invalidManagedRenderer->doc,
        'invalid managed marker renders a visible diagnostic'
    );

    $action = new action_plugin_vpsadmindoc();
    $handler = new \dokuwiki\Extension\EventHandler();
    $action->register($handler);
    assertSame(
        [
            ['TEMPLATE_PAGETOOLS_DISPLAY', 'BEFORE', 'handlePageTools'],
            ['TPL_CONTENT_DISPLAY', 'BEFORE', 'handleContentDisplay'],
        ],
        $handler->hooks,
        'managed-page action hooks are registered'
    );

    $GLOBALS['ID'] = 'manuals:vps:kvm';
    $GLOBALS['testConfig'] = [
        'managed_repository_url' => $repositoryUrl,
        'managed_repository_ref' => 'master',
        'managed_repository_ref_file' => '',
    ];
    $GLOBALS['testMetadata'] = [$managed];
    $toolsEvent = new \dokuwiki\Extension\Event([
        'view' => 'main',
        'items' => [
            'edit' => '<li>edit</li>',
            'revisions' => '<li>revisions</li>',
        ],
    ]);
    $action->handlePageTools($toolsEvent, null);
    assertSame(
        ['edit', 'vpsadmindoc_source', 'revisions'],
        array_keys($toolsEvent->data['items']),
        'GitHub source tool follows the edit tool'
    );
    assertContains(
        $masterManaged['source'],
        $toolsEvent->data['items']['vpsadmindoc_source'],
        'source tool resolves the configured URL'
    );
    assertContains('Source on GitHub', $toolsEvent->data['items']['vpsadmindoc_source'], 'source tool label');
    assertContains('target="_blank"', $toolsEvent->data['items']['vpsadmindoc_source'], 'source tool opens in new tab');
    assertContains(
        'rel="noopener noreferrer"',
        $toolsEvent->data['items']['vpsadmindoc_source'],
        'source tool protects the opener'
    );

    $GLOBALS['ACT'] = 'show';
    $contentEvent = new \dokuwiki\Extension\Event('<p>article</p>');
    $action->handleContentDisplay($contentEvent, null);
    assertSame('<p>article</p>', $contentEvent->data, 'normal article view has no body notice');

    foreach (['edit', 'locked', 'preview', 'source'] as $editorAction) {
        $GLOBALS['ACT'] = $editorAction;
        $editorEvent = new \dokuwiki\Extension\Event('<p>article</p>');
        $action->handleContentDisplay($editorEvent, null);
        assertContains(
            'vpsadmindoc-managed-warning',
            $editorEvent->data,
            "$editorAction view includes the managed-page warning"
        );
    }

    $GLOBALS['ACT'] = 'edit';
    $contentEvent = new \dokuwiki\Extension\Event('<p>article</p>');
    $action->handleContentDisplay($contentEvent, null);
    assertContains('role="alert"', $contentEvent->data, 'editor warning is announced as an alert');
    assertContains('aria-labelledby="vpsadmindoc-managed-warning-title"', $contentEvent->data, 'editor warning has an accessible title');
    assertContains('aria-hidden="true">&#9888;</span>', $contentEvent->data, 'editor warning has a decorative warning icon');
    assertContains($masterManaged['source'], $contentEvent->data, 'editor warning links the source');
    assertContains($masterManaged['test'], $contentEvent->data, 'editor warning links the test source');
    assertContains('<code class="vpsadmindoc-managed-warning__selector">kb/kvm#*</code>', $contentEvent->data, 'editor warning shows the runnable test selector');
    assertContains(
        '/doku.php?id=information%3Akb',
        $contentEvent->data,
        'editor warning links the English editing guide'
    );
    assertContains(
        'Automated tests do not cover changes made only in this editor.',
        $contentEvent->data,
        'editor warning explains test coverage for direct edits'
    );
    assertNotContains('overwrite', $contentEvent->data, 'editor warning does not imply an accidental overwrite');
    assertNotContains('Do not edit', $contentEvent->data, 'editor warning does not prohibit editing');
    assertSame(3, substr_count($contentEvent->data, 'target="_blank"'), 'all editor links open in new tabs');
    assertSame(
        3,
        substr_count($contentEvent->data, 'rel="noopener noreferrer"'),
        'all editor links protect the opener'
    );

    $GLOBALS['testLanguage'] = 'cs';
    $GLOBALS['ACT'] = 'preview';
    $czechEvent = new \dokuwiki\Extension\Event('<p>náhled</p>');
    $action->handleContentDisplay($czechEvent, null);
    assertContains('Stránka je spravovaná', $czechEvent->data, 'Czech preview warning is localized');
    assertContains(
        'Změny provedené pouze v tomto editoru se netestují automaticky.',
        $czechEvent->data,
        'Czech preview explains test coverage for direct edits'
    );
    assertNotContains('přepsat', $czechEvent->data, 'Czech preview does not imply an accidental overwrite');
    assertContains(
        '/doku.php?id=informace%3Ajak_psat',
        $czechEvent->data,
        'Czech preview links the localized editing guide'
    );
    assertNotContains('Neupravujte', $czechEvent->data, 'Czech preview does not prohibit editing');

    $GLOBALS['testLanguage'] = 'en';
    $GLOBALS['testConfig']['managed_repository_url'] = 'https://example.test/unsafe';
    $GLOBALS['ACT'] = 'locked';
    $configErrorEvent = new \dokuwiki\Extension\Event('<p>locked</p>');
    $action->handleContentDisplay($configErrorEvent, null);
    assertContains('vpsadmindoc-managed-warning--error', $configErrorEvent->data, 'invalid configuration has a prominent diagnostic');
    assertContains('Managed-page configuration error', $configErrorEvent->data, 'configuration diagnostic has a clear title');
    assertNotContains('example.test', $configErrorEvent->data, 'invalid configuration is not reflected into HTML');

    $legacyTools = new \dokuwiki\Extension\Event([
        'view' => 'main',
        'items' => ['edit' => '<li>edit</li>'],
    ]);
    $GLOBALS['testMetadata'] = [$legacyManaged];
    $action->handlePageTools($legacyTools, null);
    assertContains(
        $legacySourceUrl,
        $legacyTools->data['items']['vpsadmindoc_source'],
        'legacy source tool remains available with invalid new-style configuration'
    );

    $GLOBALS['testMetadata'] = [];
    $unmanagedTools = new \dokuwiki\Extension\Event([
        'view' => 'main',
        'items' => ['edit' => '<li>edit</li>'],
    ]);
    $action->handlePageTools($unmanagedTools, null);
    assertSame(['edit'], array_keys($unmanagedTools->data['items']), 'unmanaged page has no source tool');

    echo "plugin checks passed\n";
}
