<?php

use dokuwiki\Extension\SyntaxPlugin;

class syntax_plugin_vpsadmindoc extends SyntaxPlugin
{
    private const ID_PATTERN = '[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*';

    public function getType(): string
    {
        return 'formatting';
    }

    public function getPType(): string
    {
        return 'normal';
    }

    public function getAllowedTypes(): array
    {
        return ['formatting', 'substition', 'disabled'];
    }

    public function getSort(): int
    {
        return 195;
    }

    public function connectTo($mode): void
    {
        $this->Lexer->addEntryPattern(
            '(?s)<vpsadmin-nav\b[^>]*>(?=.*?</vpsadmin-nav>)',
            $mode,
            'plugin_vpsadmindoc'
        );
    }

    public function postConnect(): void
    {
        $this->Lexer->addExitPattern('</vpsadmin-nav>', 'plugin_vpsadmindoc');
    }

    public function handle($match, $state, $pos, Doku_Handler $handler): array
    {
        if ($state === DOKU_LEXER_ENTER) {
            return [$state, self::parseId($match)];
        }

        return [$state, $match];
    }

    public function render($format, Doku_Renderer $renderer, $data): bool
    {
        [$state, $value] = $data;

        if ($format === 'xhtml') {
            return $this->renderXhtml($renderer, $state, $value);
        }

        if ($format === 'metadata') {
            return $this->renderMetadata($renderer, $state, $value);
        }

        if ($state === DOKU_LEXER_UNMATCHED) {
            $renderer->cdata($value);
        }

        return true;
    }

    public static function parseId(string $openingTag): ?string
    {
        $pattern = '/\A<vpsadmin-nav\s+id=(?:"(' . self::ID_PATTERN . ')"|\'('
            . self::ID_PATTERN . ')\')\s*>\z/';

        if (!preg_match($pattern, $openingTag, $matches)) {
            return null;
        }

        return $matches[1] !== '' ? $matches[1] : $matches[2];
    }

    private function renderXhtml(Doku_Renderer $renderer, int $state, ?string $value): bool
    {
        if ($state === DOKU_LEXER_ENTER) {
            if ($value === null) {
                $renderer->doc .= '<span class="vpsadmindoc-nav vpsadmindoc-nav--invalid"'
                    . ' data-vpsadmin-doc-error="invalid-id">'
                    . '<strong class="vpsadmindoc-nav__warning">'
                    . hsc($this->getLang('invalid_id'))
                    . '</strong> ';
            } else {
                $renderer->doc .= '<span class="vpsadmindoc-nav" data-vpsadmin-doc-id="'
                    . hsc($value)
                    . '">';
            }
        } elseif ($state === DOKU_LEXER_UNMATCHED) {
            $renderer->cdata($value);
        } elseif ($state === DOKU_LEXER_EXIT) {
            $renderer->doc .= '</span>';
        }

        return true;
    }

    private function renderMetadata(Doku_Renderer $renderer, int $state, ?string $value): bool
    {
        if ($state === DOKU_LEXER_ENTER) {
            if ($value === null) {
                $renderer->meta['vpsadmindoc']['errors'][] = 'invalid-id';
            } else {
                $ids = $renderer->meta['vpsadmindoc']['navigation'] ?? [];
                $ids[] = $value;
                $renderer->meta['vpsadmindoc']['navigation'] = array_values(array_unique($ids));
            }
        } elseif ($state === DOKU_LEXER_UNMATCHED && $renderer->capture) {
            $renderer->doc .= $value;
        }

        return true;
    }
}
