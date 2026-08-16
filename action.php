<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;

require_once __DIR__ . '/managed.php';

class action_plugin_vpsadmindoc extends ActionPlugin
{
    private const EDITOR_ACTIONS = ['edit', 'locked', 'preview', 'source'];
    private const NEW_TAB_ATTRIBUTES = ' target="_blank" rel="noopener noreferrer"';
    private const WARNING_TITLE_ID = 'vpsadmindoc-managed-warning-title';

    public function register(EventHandler $controller): void
    {
        $controller->register_hook(
            'TEMPLATE_PAGETOOLS_DISPLAY',
            'BEFORE',
            $this,
            'handlePageTools'
        );
        $controller->register_hook(
            'TPL_CONTENT_DISPLAY',
            'BEFORE',
            $this,
            'handleContentDisplay'
        );
    }

    public function handlePageTools(Event $event, $param): void
    {
        if (($event->data['view'] ?? null) !== 'main' || !is_array($event->data['items'] ?? null)) {
            return;
        }

        $managed = $this->managedPage();
        if ($managed === null) {
            return;
        }

        $item = '<li><a href="' . hsc($managed['source']) . '"'
            . ' class="source urlextern vpsadmindoc-managed-source"'
            . ' title="' . hsc($this->getLang('managed_source_tool')) . '"'
            . self::NEW_TAB_ATTRIBUTES . '>'
            . '<span>' . hsc($this->getLang('managed_source_tool')) . '</span>'
            . '</a></li>';
        $event->data['items'] = self::insertAfterEdit($event->data['items'], $item);
    }

    public function handleContentDisplay(Event $event, $param): void
    {
        global $ACT;

        if (!in_array($ACT, self::EDITOR_ACTIONS, true) || !is_string($event->data)) {
            return;
        }

        $marker = $this->managedMarker();
        if ($marker === null) {
            return;
        }

        $managed = $this->resolveManagedPage($marker);
        if ($managed === null) {
            $event->data = $this->configurationWarning() . $event->data;
            return;
        }

        $source = '<a href="' . hsc($managed['source']) . '" class="urlextern"'
            . self::NEW_TAB_ATTRIBUTES . '>'
            . hsc($this->getLang('managed_source_link')) . '</a>';
        $test = '<a href="' . hsc($managed['test']) . '" class="urlextern"'
            . self::NEW_TAB_ATTRIBUTES . '>'
            . hsc($this->getLang('managed_test_link')) . '</a>';
        $guide = '<a href="' . hsc(wl($this->getLang('managed_guide_page'))) . '"'
            . ' class="wikilink1"' . self::NEW_TAB_ATTRIBUTES . '>'
            . hsc($this->getLang('managed_guide_link')) . '</a>';
        $selector = '';
        if (is_string($managed['test_selector'])) {
            $selector = ' <code class="vpsadmindoc-managed-warning__selector">'
                . hsc($managed['test_selector']) . '</code>';
        }

        $event->data = '<div class="vpsadmindoc-managed-warning" role="alert"'
            . ' aria-labelledby="' . self::WARNING_TITLE_ID . '">'
            . '<span class="vpsadmindoc-managed-warning__icon" aria-hidden="true">&#9888;</span>'
            . '<div class="vpsadmindoc-managed-warning__body">'
            . '<strong class="vpsadmindoc-managed-warning__title" id="'
            . self::WARNING_TITLE_ID . '">' . hsc($this->getLang('managed_edit_title'))
            . '</strong>'
            . '<p>' . hsc($this->getLang('managed_edit_warning')) . '</p>'
            . '<ul class="vpsadmindoc-managed-warning__links">'
            . '<li><span>' . hsc($this->getLang('managed_source_label')) . '</span> '
            . $source . '</li>'
            . '<li><span>' . hsc($this->getLang('managed_test_label')) . '</span> '
            . $test . $selector . '</li>'
            . '<li><span>' . hsc($this->getLang('managed_guide_label')) . '</span> '
            . $guide . '</li>'
            . '</ul></div></div>' . $event->data;
    }

    public static function insertAfterEdit(array $items, string $item): array
    {
        $result = [];
        $inserted = false;

        foreach ($items as $key => $value) {
            $result[$key] = $value;
            if ($key === 'edit') {
                $result['vpsadmindoc_source'] = $item;
                $inserted = true;
            }
        }

        if (!$inserted) {
            $result['vpsadmindoc_source'] = $item;
        }

        return $result;
    }

    private function managedPage(): ?array
    {
        $marker = $this->managedMarker();
        return $marker === null ? null : $this->resolveManagedPage($marker);
    }

    private function managedMarker(): ?array
    {
        global $ID;

        if (!$ID) {
            return null;
        }

        $markers = p_get_metadata($ID, 'vpsadmindoc managed', METADATA_RENDER_USING_CACHE);
        if (!is_array($markers) || count($markers) !== 1) {
            return null;
        }

        $managed = reset($markers);
        return VpsAdminDocManagedPage::isValid($managed) ? $managed : null;
    }

    private function resolveManagedPage(array $managed): ?array
    {
        return VpsAdminDocManagedRepository::resolve(
            $managed,
            (string)$this->getConf('managed_repository_url'),
            (string)$this->getConf('managed_repository_ref'),
            (string)$this->getConf('managed_repository_ref_file')
        );
    }

    private function configurationWarning(): string
    {
        return '<div class="vpsadmindoc-managed-warning vpsadmindoc-managed-warning--error"'
            . ' role="alert" aria-labelledby="' . self::WARNING_TITLE_ID . '">'
            . '<span class="vpsadmindoc-managed-warning__icon" aria-hidden="true">&#9888;</span>'
            . '<div class="vpsadmindoc-managed-warning__body">'
            . '<strong class="vpsadmindoc-managed-warning__title" id="'
            . self::WARNING_TITLE_ID . '">' . hsc($this->getLang('managed_config_title'))
            . '</strong><p>' . hsc($this->getLang('managed_config_error'))
            . '</p></div></div>';
    }
}
