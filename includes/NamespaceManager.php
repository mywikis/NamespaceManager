<?php
/**
 * NamespaceManager
 * Main
 * @author Jeffrey Wang for MyWikis LLC
 */

class NamespaceManager {
    /**
     * Retrieves JSON files and returns JSON array
     * @return associated_array if success
     *         false if failure
    */
    public static function loadNamespaceData() {
        $fileContents = NamespaceManager::loadNamespaceDataRaw();
        $data = json_decode($fileContents, true);
        return $data ?? false;
    }

    /**
     * Retrieves JSON files and returns file contents as string
     * @return string with JSON file contents if success
     *         false if failure
    */
    public static function loadNamespaceDataRaw() {
        global $wgDBname, $wgNamespaceManagerDataPath;

        $wgNamespaceManagerDataPath = str_replace('$1', $wgDBname, $wgNamespaceManagerDataPath);

        // Absolute vs. relative path
        $filepath = substr($wgNamespaceManagerDataPath, 0, 1) === '/'
            ? $wgNamespaceManagerDataPath
            : __DIR__ . '/../'. $wgNamespaceManagerDataPath;
        
        if (!file_exists($filepath)) {
            return false;
        }

        $fileContents = file_get_contents($filepath);
        
        return $fileContents;
    }

    /**
     * Attempts to save array or object into JSON file
     * @return boolean true if succeeded, false if failed
     */
    public static function saveNamespaceData($data) {
        $fileContents = json_encode($data);
        if ($fileContents === false) {
            return false;
        }
        $status = NamespaceManager::saveNamespaceDataRaw($fileContents);
        return $status;
    }

    /**
     * Attempts to save string into JSON file
     * @return boolean true if succeeded, false if failed
     */
    public static function saveNamespaceDataRaw($fileContents) {
        global $wgDBname, $wgNamespaceManagerDataPath;

        if (!NamespaceManager::verifyIsJson($fileContents)) {
            return false;
        }

        $wgNamespaceManagerDataPath = str_replace('$1', $wgDBname, $wgNamespaceManagerDataPath);

        // Absolute vs. relative path
        $filepath = substr($wgNamespaceManagerDataPath, 0, 1) === '/'
            ? $wgNamespaceManagerDataPath
            : __DIR__ . '/../'. $wgNamespaceManagerDataPath;
        
        $status = file_put_contents($filepath, $fileContents);

        return $status !== false;
    }

    public static function verifyIsJson($candidateStr) {
        $result = json_decode($candidateStr);

        return $result !== null;
    }

    /**
     * Changes the pretty namespace name specification to the technical version
     * For instance, "A Namespace" becomes "A_Namespace" and "A Namespace talk" becomes
     * "A_Namespace_talk"
     */
    public static function prepareNamespaceName($namespaceStr) {
        str_replace(' ', '_', $namespaceStr);
        return $namespaceStr;
    }
}