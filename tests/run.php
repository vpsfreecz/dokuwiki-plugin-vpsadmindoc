<?php

namespace dokuwiki\Extension {
    trait TestPluginLanguage
    {
        public function getLang(string $key): string
        {
            $language = $GLOBALS['testLanguage'] ?? 'en';
            $translations = [
                'en' => [
                    'invalid_id' => '[invalid vpsAdmin documentation ID]',
                    'invalid_managed' => '[invalid managed-page marker]',
                    'managed_source_tool' => 'Source on GitHub',
                    'managed_source_link' => 'canonical source on GitHub',
                    'managed_test_link' => 'automated test',
                    'managed_edit_warning' => 'This page is managed in a repository. Do not edit it directly in the KB; change the %s and verify the %s instead.',
                ],
                'cs' => [
                    'invalid_id' => '[neplatný identifikátor dokumentace vpsAdminu]',
                    'invalid_managed' => '[neplatná značka stránky spravované v repozitáři]',
                    'managed_source_tool' => 'Zdroj na GitHubu',
                    'managed_source_link' => 'kanonický zdroj na GitHubu',
                    'managed_test_link' => 'automatický test',
                    'managed_edit_warning' => 'Tato stránka je spravována v repozitáři. Neupravujte ji přímo v KB; změňte místo toho %s a ověřte %s.',
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

    $sourceUrl = 'https://github.com/vpsfreecz/vpsfree-kb-contracts/'
        . 'blob/master/contract/pages/manuals-vps-kvm.txt';
    $testUrl = 'https://github.com/vpsfreecz/vpsfree-kb-contracts/'
        . 'blob/master/tests/suite/kb/kvm.nix';
    $managedTag = "<kb-managed\n  source=\"$sourceUrl\"\n  test=\"$testUrl\"\n/>";
    $managed = ['source' => $sourceUrl, 'test' => $testUrl];

    assertSame(
        $managed,
        VpsAdminDocManagedPage::parseTag($managedTag),
        'managed-page marker parses'
    );
    foreach ([
        '<kb-managed />',
        "<kb-managed source=\"$sourceUrl\" test=\"javascript:alert(1)\" />",
        "<kb-managed source=\"$sourceUrl\" source=\"$sourceUrl\" test=\"$testUrl\" />",
        "<kb-managed source=\"$sourceUrl\" test=\"$testUrl\" extra=\"bad\" />",
        "<kb-managed source=\"https://github.com/a/b/blob/master/../secret\" test=\"$testUrl\" />",
    ] as $invalid) {
        assertSame(null, VpsAdminDocManagedPage::parseTag($invalid), "reject $invalid");
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
    assertContains($sourceUrl, $toolsEvent->data['items']['vpsadmindoc_source'], 'source tool URL');
    assertContains('Source on GitHub', $toolsEvent->data['items']['vpsadmindoc_source'], 'source tool label');

    $GLOBALS['ACT'] = 'show';
    $contentEvent = new \dokuwiki\Extension\Event('<p>article</p>');
    $action->handleContentDisplay($contentEvent, null);
    assertSame('<p>article</p>', $contentEvent->data, 'normal article view has no body notice');

    $GLOBALS['ACT'] = 'edit';
    $action->handleContentDisplay($contentEvent, null);
    assertContains('vpsadmindoc-managed-warning', $contentEvent->data, 'editor warning is present');
    assertContains($sourceUrl, $contentEvent->data, 'editor warning links the source');
    assertContains($testUrl, $contentEvent->data, 'editor warning links the test');

    $GLOBALS['testLanguage'] = 'cs';
    $GLOBALS['ACT'] = 'preview';
    $czechEvent = new \dokuwiki\Extension\Event('<p>náhled</p>');
    $action->handleContentDisplay($czechEvent, null);
    assertContains('Tato stránka je spravována', $czechEvent->data, 'Czech preview warning is localized');

    $GLOBALS['testMetadata'] = [];
    $unmanagedTools = new \dokuwiki\Extension\Event([
        'view' => 'main',
        'items' => ['edit' => '<li>edit</li>'],
    ]);
    $action->handlePageTools($unmanagedTools, null);
    assertSame(['edit'], array_keys($unmanagedTools->data['items']), 'unmanaged page has no source tool');

    echo "plugin checks passed\n";
}
