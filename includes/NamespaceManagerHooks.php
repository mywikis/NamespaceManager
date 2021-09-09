<?php
/**
 * NamespaceManager
 * Hooks
 * @author Jeffrey Wang for MyWikis LLC
 */

class NamespaceManagerHooks {
    /**
     * Sets up namespace configuration options
     * In MediaWiki, the configuration must be done _before_ the namespaces themselves are actually
     * initialized, believe it or not.
     * i.e. This is called before the CanonicalNamespaces hook is called.
     */
    public static function onMediaWikiServices(&$services) {
        global $wgCapitalLinkOverrides,
            $wgCapitalLinks,
            $wgContentNamespaces,
            // $wgExtraSignatureNamespaces,
            // $wgNamespaceContentModels,
            $wgNamespaceAliases,
            $wgNamespaceProtection,
            $wgNamespacesWithSubpages,
            $wgNamespacesToBeSearchedDefault,
            $wgNonincludableNamespaces,
            $wgVisualEditorAvailableNamespaces;
        
        $data = NamespaceManager::loadNamespaceData();
        
        if (!isset($data) || $data === false) {
            return;
        }

        // Using JSON configuration, set MediaWiki config variables
        foreach ($data as $namespaceDefinition) {
            if (isset($namespaceDefinition['id'])) {
                $id = $namespaceDefinition['id'];
                $name = $namespaceDefinition['name'];
            } else {
                wfDebugLog('NamespaceManager', 'Invalid namespace number, cannot proceed.');
            }
            // Is content namespace
            if ($namespaceDefinition['content'] === true) {
                $wgContentNamespaces[] = $id;
            }

            // Is in search results by default
            $wgNamespacesToBeSearchedDefault[$id] = $namespaceDefinition['searchdefault'] ?? false;
            $wgNamespacesToBeSearchedDefault[$id + 1] = $namespaceDefinition['talksearchdefault'] ?? false;

            // Supports subpages
            $wgNamespacesWithSubpages[$id] = $namespaceDefinition['subpages'] ?? false;
            $wgNamespacesWithSubpages[$id + 1] = $namespaceDefinition['talksubpages'] ?? false;

            // Is includable
            if (isset($namespaceDefinition['includable']) && $namespaceDefinition['includable'] === false) {
                $wgNonincludableNamespaces[] = $id;
            }
            if (isset($namespaceDefinition['talkincludable']) && $namespaceDefinition['talkincludable'] === false) {
                $wgNonincludableNamespaces[] = $id + 1;
            }

            // Is enabled for VisualEditor
            if (isset($namespaceDefinition['visualeditor']) && $namespaceDefinition['visualeditor'] === true) {
                $wgVisualEditorAvailableNamespaces[$name] = true;
            }

            // Aliases for non-talk
            if (isset($namespaceDefinition['aliases']) && !empty($namespaceDefinition['aliases'])) {
                foreach ($namespaceDefinition['aliases'] as $alias) {
                    $alias = NamespaceManager::prepareNamespaceName($alias);
                    $wgNamespaceAliases[$alias] = $id;
                }
            }
            // Aliases for talk
            if (isset($namespaceDefinition['talkaliases']) && !empty($namespaceDefinition['talkaliases'])) {
                foreach ($namespaceDefinition['talkaliases'] as $alias) {
                    $alias = NamespaceManager::prepareNamespaceName($alias);
                    $wgNamespaceAliases[$alias] = $id + 1;
                }
            }

            // Edit permissions for non-talk
            if (isset($namespaceDefinition['editpermissions']) && !empty($namespaceDefinition['editpermissions'])) {
                $wgNamespaceProtection[$id] = $namespaceDefinition['editpermissions'] ?? [];
            }
            // Edit permissions for talk
            if (isset($namespaceDefinition['talkeditpermissions']) && !empty($namespaceDefinition['talkeditpermissions'])) {
                $wgNamespaceProtection[$id + 1] = $namespaceDefinition['talkeditpermissions'] ?? [];
            }
        }
    }

    /**
     * Adds the namespaces themselves
     */
    public static function onCanonicalNamespaces(array &$namespaces) {
        $data = NamespaceManager::loadNamespaceData();

        // If namespace defn file doesn't exist, skip silently rather than crashing MediaWiki
        if ($data === false) {
            return;
        }

        foreach ($data as $namespaceDefinition) {
            if (isset($namespaceDefinition['id'])) {
                $id = $namespaceDefinition['id'];
            } else {
                // This will probably never run unless onMediaWikiServices somehow
                // runs after onCanonicalNamespaces in the future
                wfDebugLog('NamespaceManager', 'Invalid namespace number. Cannot proceed.');
                return;
            }
            if ($id % 2 != 0) {
                wfDebugLog('NamespaceManager', 'Non-talk namespace must be even. Cannot proceed.');
                return;
            }
            if ($id < 3000 || $id > 4999) {
                wfDebugLog('NamespaceManager', 'Cannot assign a custom namespace with an ID outside of the range 3000-4999, inclusive. Cannot proceed.');
                return;
            }
            if (isset($namespaceDefinition['name'])) {
                $namespaceName = $namespaceDefinition['name'];
            } else {
                wfDebugLog('NamespaceManager', 'Invalid namespace name definition. Cannot proceed.');
                return;
            }
            $namespaceName = NamespaceManager::prepareNamespaceName($namespaceName);
            $namespaces[$id] = $namespaceName;
            $namespaces[$id + 1] = isset($namespaceDefinition['talkname'])
                                    ? $namespaceDefinition['talkname']
                                    : $namespaceName . '_talk';
        }
    }
}
