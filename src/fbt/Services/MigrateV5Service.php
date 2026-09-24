<?php

namespace fbt\Services;

use fbt\Exceptions\FbtException;

/**
 * Migrates translation files from fbt v4 to v5.
 *
 * In v5, the hashes of the source strings are encoded in base64 by default (like
 * upstream fbt), while v4 used the hex encoding. The md5 digest itself doesn't
 * change for the same text and description, so translations can be re-keyed
 * without the original source strings.
 *
 * Strings whose text or description changed in v5 (e.g. inner strings of
 * implicit parameters, whose descriptions are now computed like in upstream fbt)
 * have to be translated again. They are reported when the source strings
 * collected by v5 are provided.
 *
 * Supported translation file formats:
 *   - {"<locale>": {"fb-locale": "<locale>", "translations": {...}}, ...}
 *   - {"fb-locale": "<locale>", "translations": {...}}
 *   - {"phrases": [...], "translationGroups": [{"fb-locale": "<locale>", "translations": {...}}, ...]}
 */
class MigrateV5Service
{
    /** @var array<string, true>|null */
    private $sourceHashes = null;
    /** @var array|null */
    private $sourcePhrases = null;

    /**
     * @param string|null $sourcePath - `.source_strings.json` collected by fbt v5
     *
     * @throws FbtException
     */
    public function __construct(?string $sourcePath = null)
    {
        if ($sourcePath === null) {
            return;
        }

        $sourceStrings = self::readJson($sourcePath);
        $this->sourcePhrases = $sourceStrings['phrases'] ?? [];
        $this->sourceHashes = [];
        foreach ($this->sourcePhrases as $phrase) {
            if (! isset($phrase['hashToLeaf'])) {
                throw new FbtException("Source strings of fbt v5 were expected (missing `hashToLeaf`): $sourcePath");
            }
            foreach (array_keys($phrase['hashToLeaf']) as $hash) {
                $this->sourceHashes[(string)$hash] = true;
            }
        }
    }

    /**
     * Re-keys the translations of the given files (in place).
     *
     * @param string[] $files
     *
     * @return array{migrated: int, unknown: array<string, string[]>, untranslated: array<string, string[]>}
     * @throws FbtException
     */
    public function migrateFiles(array $files, bool $pretty = true): array
    {
        $report = ['migrated' => 0, 'translated' => []];

        foreach ($files as $file) {
            $json = self::readJson($file);
            $json = $this->migrateTranslationFile($json, $report);

            $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
            if ($pretty) {
                $flags |= JSON_PRETTY_PRINT;
            }
            file_put_contents($file, json_encode($json, $flags) . ($pretty ? PHP_EOL : ''));
        }

        $result = ['migrated' => $report['migrated'], 'unknown' => [], 'untranslated' => []];
        if ($this->sourceHashes !== null) {
            foreach ($report['translated'] as $locale => $hashes) {
                $unknown = array_keys(array_diff_key($hashes, $this->sourceHashes));
                $untranslated = array_keys(array_diff_key($this->sourceHashes, $hashes));
                if ($unknown) {
                    $result['unknown'][$locale] = array_map('strval', $unknown);
                }
                if ($untranslated) {
                    $result['untranslated'][$locale] = array_map('strval', $untranslated);
                }
            }
        }

        return $result;
    }

    /**
     * @param array $json - contents of a translation file
     * @param array $report
     */
    public function migrateTranslationFile(array $json, array &$report): array
    {
        if (isset($json['translationGroups'])) {
            foreach ($json['translationGroups'] as $index => $group) {
                $json['translationGroups'][$index] = $this->migrateGroup($group, $report);
            }
            if ($this->sourcePhrases !== null) {
                $json['phrases'] = $this->sourcePhrases;
            }

            return $json;
        }

        if (isset($json['fb-locale'])) {
            return $this->migrateGroup($json, $report);
        }

        foreach ($json as $locale => $group) {
            if (is_array($group) && isset($group['translations'])) {
                $json[$locale] = $this->migrateGroup($group, $report);
            }
        }

        return $json;
    }

    private function migrateGroup(array $group, array &$report): array
    {
        $locale = $group['fb-locale'] ?? '';
        $translations = [];

        foreach ($group['translations'] ?? [] as $hash => $translation) {
            $newHash = self::migrateHash((string)$hash);
            if ($newHash !== (string)$hash) {
                $report['migrated']++;
            }

            if ($translation !== null) {
                $report['translated'][$locale][$newHash] = true;
            }

            $translations[$newHash] = $translation;
        }

        $group['translations'] = $translations;

        return $group;
    }

    /**
     * Converts an md5 hash in hex (v4 default) to base64 (v5 default).
     * Other hashes are returned as-is.
     */
    public static function migrateHash(string $hash): string
    {
        return preg_match('/^[0-9a-f]{32}$/', $hash) === 1
            ? base64_encode(hex2bin($hash))
            : $hash;
    }

    /**
     * @throws FbtException
     */
    private static function readJson(string $path): array
    {
        if (! is_file($path)) {
            throw new FbtException("File does not exist: $path");
        }

        $json = json_decode(file_get_contents($path), true);
        if (! is_array($json)) {
            throw new FbtException("Invalid JSON file: $path");
        }

        return $json;
    }
}
