<?php

namespace intelworx\dbvc;

/**
 * Description of DBMigrationRevision
 *
 * @author JosephT
 */
abstract class DBMigrationRevision {

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

    public function __toString() {
        return '[' . $this->file . ':md5:' . $this->checksum . ']';
    }

    public function toFieldArray() {
        return [$this->id, $this->file, $this->checksum, $this->title, date('Y-m-d H:i:s')];
    }

    /**
     * 
     * @param array $line
     * @return DBMigrationRevisionStored
     */
    public static function fromLine($line) {
        //$id, $file, $checksum, $file = null
        return new DBMigrationRevisionStored($line[0], $line[1], $line[2], $line[3]);
    }

    /**
     * Creates revision object from a file name.
     * @param string $file
     * @return null|DBMigrationRevisionFile
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

        return new DBMigrationRevisionFile($version, $baseName, self::computeChecksum($file), $description, file_get_contents($file));
    }

    /**
     * Computes checksum of a file
     * @param string $file the path to the file
     * @return string
     */
    public static function computeChecksum($file) {
        return md5_file($file);
    }

}

class DBMigrationRevisionFile extends DBMigrationRevision {

    private $baseChecksum;

    public function __construct($id, $file, $checksum, $title = null, $content = '') {
        parent::__construct($id, $file, $checksum, $title);
        $this->baseChecksum = self::computeBaseChecksum($content);
    }

    public function matches(DBMigrationRevisionStored $storedRevision) {
        return $this->file === $storedRevision->file &&
                ($this->checksum === $storedRevision->checksum || in_array($storedRevision->checksum, $this->baseChecksum));
    }

    /**
     * Computes checksums without Line ending complications.
     * @param string $content content of the original file.
     * @return string
     */
    public static function computeBaseChecksum($content) {
        static $eol = '/\r\n|\r|\n/';

        $checksums = array();
        //covert to linux EOL
        $checksums[] = md5(preg_replace($eol, "\n", $content));
        $checksums[] = md5(preg_replace($eol, "\r\n", $content));
        $checksums[] = md5(preg_replace($eol, "\r", $content));

        return $checksums;
    }

}

class DBMigrationRevisionStored extends DBMigrationRevision {
    
}
