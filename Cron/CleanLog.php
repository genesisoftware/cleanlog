<?php

namespace Genesisoft\CleanLog\Cron;

use DateInterval;
use DateTime;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Psr\Log\LoggerInterface;

class CleanLog
{
    protected $logger;

    /**
     * @var DirectoryList
     */
    private $directoryList;

    public function __construct(LoggerInterface $logger, DirectoryList $directoryList)
    {
        $this->logger = $logger;
        $this->directoryList = $directoryList;
    }

    /**
     * Clean logs
     *
     * @return void
     */
    public function execute()
    {
        try {
            $this->cleanFiles();
            $this->cleanReport();
        } catch (FileSystemException $e) {
            $this->logger->error("CleanLog - {$e->getMessage()}", $e->getTrace());
        }
    }

    function cleanFile($fileName)
    {
        if (!is_writable($fileName)) {
            $this->logger->info("The file {$fileName} is not writable");
            return;
        }

        $arr = file($fileName);

        $time = new DateTime();
        $time->sub(new DateInterval('P15D'));
        $date = $time->format('Y-m-d');

        foreach ($arr as $key => $item) {
            if (strstr($item, $date) !== false) {
                break;
            }

            preg_match_all('~\[(\d{4})[-](\d{2})[-]+(\d{2})~', $item, $datas);

            if (isset($datas[0][0]) == false) {
                continue;
            }

            $data_line = str_replace("[", "", $datas[0][0]);
            if (strtotime($date) > strtotime($data_line)) {
                unset($arr[$key]);
            }
        }

        if (!$fp = fopen($fileName, 'w+')) {
            $this->logger->error("Cannot open file ($fileName)");
            return;
        }

        if ($fp) {
            foreach ($arr as $line) {
                fwrite($fp, $line);
            }
            fclose($fp);
        }
    }

    /**
     * Clean reports
     *
     * @return void
     * @throws FileSystemException
     */
    private function cleanReport()
    {
        $dir_var = $this->directoryList->getPath('var');
        $path = "{$dir_var}/report/";

        if (!file_exists($path)) {
            return;
        }

        $dir = dir($path);

        $time = new DateTime();
        $time->sub(new DateInterval('P15D'));
        $date_clean = $time->format('Y-m-d');

        if ($dir) {
            while (($item = $dir->read()) !== false) {
                if ($item == '.' || $item == '..') {
                    continue;
                }

                $date_file = date("Y-m-d", filemtime($path . $item));
                if (strtotime($date_clean) > strtotime($date_file)) {
                    unlink($path . $item);
                }
            }
            $dir->close();
        }
    }

    /**
     * Clean files
     *
     * @return void
     * @throws FileSystemException
     */
    private function cleanFiles()
    {
        $dir_log = $this->directoryList->getPath('log');

        if (!file_exists($dir_log)) {
            return;
        }

        $dir = dir("{$dir_log}/");
        if ($dir) {
            while (($item = $dir->read()) !== false) {
                if ($item == '.' || $item == '..') {
                    continue;
                }
                $this->cleanFile("{$dir_log}/{$item}");
            }
            $dir->close();
        }
    }
}
