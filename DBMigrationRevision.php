<?php

namespace intelworx\dbvc;

/**
 * Description of DBMigrationRevision
 *
 * @author JosephT
 */
class DBMigrationRevision {

    protected $id;
    protected $title;
    protected $file;
    protected $checksum;

    public function __construct($id, $file, $checksum, $title = null) {
        $this->id = intval($id);
        $this->title = $title;
        $this->file = $file;
        $this->checksum = $checksum;
    }

    public function getVersionId() {
        return $this->id;
    }

    public function getId() {
        return $this->id;
    }

    public function getTitle() {
        return $this->title;
    }

    public function getFile() {
        return $this->file;
    }

    public function getChecksum() {
        return $this->checksum;
    }

    public function equals(DBMigrationRevision $revision) {
        return $this->file === $revision->file && $this->checksum === $revision->checksum;
    }

    public function __toString() {
        return '[' . $this->file . ':md5:' . $this->checksum . ']';
    }

    public function toFieldArray() {
        return [$this->id, $this->file, $this->checksum, $this->title, date('Y-m-d H:i:s')];
    }

    /**
     * 
     * @param array $line
     * @return \DBMigrationRevision
     */
    public static function fromLine($line) {
        //$id, $file, $checksum, $file = null
        return new DBMigrationRevision($line[0], $line[1], $line[2], $line[3]);
    }

    /**
     * Creates revision object from a file name.
     * @param type $file
     * @return null|\DBMigrationRevision
     */
    public static function fromFile($file) {
        $baseName = basename($file);
        $matches = array();
        if (!preg_match('/^(\d+)_(.+)\.sql$/i', $baseName, $matches)) {
            DBMigration::el('skipping file', $file, 'it doesn\'t match pattern');
            return null;
        }

        $version = $matches[1];
        $description = preg_replace('/[^a-zA-Z0-9]+/', ' ', $matches[2]);

        return new DBMigrationRevision($version, $baseName, self::computeChecksum($file), $description);
    }

    public static function computeChecksum($file) {
        return md5_file($file);
    }

}
