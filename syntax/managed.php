<?php

use dokuwiki\Extension\SyntaxPlugin;

require_once dirname(__DIR__) . '/managed.php';

class syntax_plugin_vpsadmindoc_managed extends SyntaxPlugin
{
    public function getType(): string
    {
        return 'substition';
    }

    public function getPType(): string
    {
        return 'block';
    }

    public function getSort(): int
    {
        return 194;
    }

    public function connectTo($mode): void
    {
        $this->Lexer->addSpecialPattern(
            '(?s)<kb-managed\b[^>]*>',
            $mode,
            'plugin_vpsadmindoc_managed'
        );
    }

    public function handle($match, $state, $pos, Doku_Handler $handler): ?array
    {
        return VpsAdminDocManagedPage::parseTag($match);
    }

    public function render($format, Doku_Renderer $renderer, $data): bool
    {
        if ($format === 'xhtml') {
            if (!VpsAdminDocManagedPage::isValid($data)) {
                $renderer->doc .= '<div class="vpsadmindoc-managed vpsadmindoc-managed--invalid"'
                    . ' data-vpsadmin-doc-error="invalid-managed-page">'
                    . '<strong class="vpsadmindoc-managed__warning">'
                    . hsc($this->getLang('invalid_managed'))
                    . '</strong></div>';
            }

            return true;
        }

        if ($format === 'metadata') {
            if (VpsAdminDocManagedPage::isValid($data)) {
                $renderer->meta['vpsadmindoc']['managed'][] = $data;
            } else {
                $renderer->meta['vpsadmindoc']['errors'][] = 'invalid-managed-page';
            }

            return true;
        }

        return true;
    }
}
