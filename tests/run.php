<?php

namespace dokuwiki\Extension {
    abstract class SyntaxPlugin
    {
        public $Lexer;

        public function getLang(string $key): string
        {
            return $key === 'invalid_id' ? '[invalid vpsAdmin documentation ID]' : $key;
        }
    }
}

namespace {
    const DOKU_LEXER_ENTER = 1;
    const DOKU_LEXER_UNMATCHED = 2;
    const DOKU_LEXER_EXIT = 3;

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

    require dirname(__DIR__) . '/syntax.php';

    function assertSame($expected, $actual, string $message): void
    {
        if ($expected !== $actual) {
            fwrite(STDERR, "$message\nExpected: " . var_export($expected, true)
                . "\nActual: " . var_export($actual, true) . "\n");
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

    echo "plugin checks passed\n";
}
