<?php
/**
 * @file
 */

namespace BackupMigrate\Core\Tests\Environment;
use BackupMigrate\Core\Service\PearTarArchiver;
use BackupMigrate\Core\File\BackupFile;
use BackupMigrate\Core\File\WritableStreamBackupFile;
use BackupMigrate\Core\Service\TarArchiveWriter;
use BackupMigrate\Core\Tests\File\TempFileConsumerTestTrait;


/**
 * Class PearTarArchiveWriterTest
 * @package BackupMigrate\Core\Tests\Environment
 */
class TarArchiveWriterTest extends \PHPUnit_Framework_TestCase {
  use TempFileConsumerTestTrait;

  /**
   * @var TarArchiveWriter
   */
  protected $archiver;

  /**
   * @var array
   */
  protected $file_list;

  public function setUp() {
    $this->file_list =  [
        'item1.txt' => 'Hello, World 1!',
        'item2.txt' => 'Hello, World 2!',
        'item3.txt' => 'Hello, World 3!',
      ];
    // Add a file with a very long name
    $name = '';
    for ($i = 0; $i < 10; $i++) {
      $name .= 'abc1234567890';
    }
    $this->file_list[$name] = 'Hello, World 5!';

    $this->_setUpFiles([
      'tmp' => [],
      'files' => $this->file_list
    ]);

    $this->archiver = new TarArchiveWriter();
  }

  /**
   * @covers ::getFileExt
   */
  public function testGetFileExt() {
    $this->assertEquals('tar', $this->archiver->getFileExt());
  }

  /**
   * @covers ::setOutput
   * @covers ::addFile
   * @covers ::closeArchive
   */
  public function testArchiveFiles() {
    $output_file = tempnam('/tmp', 'test');
    $file = new WritableStreamBackupFile($output_file);
    $this->archiver->setArchive($file);

    $file_names = array_keys($this->file_list);

    foreach ($file_names as $filename) {
      $this->archiver->addFile('vfs://root/files/' . $filename, $filename);
    }
    $this->archiver->closeArchive();

    $tar_list = null;
    exec('tar tf ' . $output_file, $tar_list);

    // Make sure the files all exist with the correct names.
    $this->assertEquals($file_names, $tar_list);

    foreach ($this->file_list as $file_name => $contents) {
      $output = null;
      $output = exec('tar xfO ' . $output_file . ' ' . $file_name);
      $this->assertEquals($contents, $output);
    }
  }
}
