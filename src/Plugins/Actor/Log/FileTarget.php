<?php

declare(strict_types=1);

namespace Yew\Plugins\Actor\Log;

use Yew\Yew;
use Yew\Framework\Exception\InvalidConfigException;
use Yew\Framework\Helpers\FileHelper;
use Yew\Framework\Log\LogRuntimeException;

/**
 * Writes log messages to a file, with size-based rotation.
 *
 * When the active file exceeds {@see maxFileSize} it is rotated (copied or
 * renamed, see {@see rotateByCopy}) and history is capped at {@see maxLogFiles}.
 */
class FileTarget extends Target
{
    public ?string $logFile = null;

    public string $logFileName = '';

    /**
     * Optional absolute directory for the log file. When set, it takes
     * precedence over the framework runtime path (useful outside a booted app).
     */
    public ?string $logDir = null;

    public bool $enableRotation = true;

    public int $maxFileSize = 1024; // in KB

    public int $maxLogFiles = 5;

    public ?int $fileMode = null;

    public int $dirMode = 0775;

    /**
     * Rotate by copy+truncate (windows-friendly, tailer-safe) rather than rename.
     */
    public bool $rotateByCopy = true;

    public function __construct(string $logFileName = '', ?string $logDir = null, int $exportInterval = 2)
    {
        $this->logFileName = $logFileName;
        $this->logDir = $logDir;
        $this->exportInterval = $exportInterval;

        $this->resolveLogFile();
    }

    /**
     * Resolve the absolute log file path.
     *
     * Priority: explicit $logDir (testing/standalone) → already-set $logFile
     * (alias-aware) → framework runtime path. Idempotent, so it is safe to call
     * from both the constructor and {@see init()}.
     */
    private function resolveLogFile(): void
    {
        if ($this->logDir !== null) {
            $this->logFile = rtrim($this->logDir, '/\\') . DIRECTORY_SEPARATOR . $this->logFileName . '.log';
        } elseif ($this->logFile === null) {
            $this->logFile = Yew::$app->getRuntimePath() . '/logs/actors/' . $this->logFileName . '.log';
        } else {
            $this->logFile = Yew::getAlias($this->logFile);
        }
    }

    public function init(): void
    {
        parent::init();

        // When built via the DI container (array config), the constructor may
        // not have had the name yet — re-resolve now that properties are set.
        if ($this->logFile === null || $this->logDir !== null) {
            $this->resolveLogFile();
        }

        $this->maxLogFiles = max(1, $this->maxLogFiles);
        $this->maxFileSize = max(1, $this->maxFileSize);
    }

    /**
     * @throws InvalidConfigException if the log file cannot be opened for writing
     * @throws LogRuntimeException if the message cannot be fully written
     */
    public function export(): void
    {
        if (strpos($this->logFile, '://') === false || strncmp($this->logFile, 'file://', 7) === 0) {
            FileHelper::createDirectory(dirname($this->logFile), $this->dirMode, true);
        }

        $text = implode("\n", array_map([$this, 'formatMessage'], $this->messages)) . "\n";

        if ($this->enableRotation && $this->fileExceedsMaxSize()) {
            $this->rotateFiles();
            $this->writeAll($text); // rotation already truncated the file
            return;
        }

        $fp = $this->open();
        try {
            $this->writeLocked($fp, $text);
        } finally {
            @fclose($fp);
        }
    }

    private function fileExceedsMaxSize(): bool
    {
        clearstatcache();
        return @filesize($this->logFile) > $this->maxFileSize * 1024;
    }

    private function open()
    {
        $fp = @fopen($this->logFile, 'a');
        if ($fp === false) {
            throw new InvalidConfigException("Unable to append to log file: {$this->logFile}");
        }
        return $fp;
    }

    private function writeLocked($fp, string $text): void
    {
        @flock($fp, LOCK_EX);
        try {
            $this->writeAll($text, $fp);
        } finally {
            @flock($fp, LOCK_UN);
        }
    }

    /**
     * Write the full text, either to an open handle or (after rotation) straight
     * to the file. Throws if the write is not fully completed.
     */
    private function writeAll(string $text, $fp = null): void
    {
        $written = $fp !== null
            ? @fwrite($fp, $text)
            : @file_put_contents($this->logFile, $text, FILE_APPEND | LOCK_EX);

        if ($written === false) {
            $error = error_get_last();
            throw new LogRuntimeException(
                "Unable to export log through file ({$this->logFile})!: " . ($error['message'] ?? 'unknown')
            );
        }
        if ($written < strlen($text)) {
            throw new LogRuntimeException(
                "Unable to export whole log through file ({$this->logFile})! Wrote {$written} out of " . strlen($text) . ' bytes.'
            );
        }
        if ($this->fileMode !== null) {
            @chmod($this->logFile, $this->fileMode);
        }
    }

    protected function rotateFiles(): void
    {
        $file = $this->logFile;
        for ($i = $this->maxLogFiles; $i >= 0; --$i) {
            $rotateFile = $file . ($i === 0 ? '' : '.' . $i);
            if (!is_file($rotateFile)) {
                continue;
            }
            if ($i === $this->maxLogFiles) {
                @unlink($rotateFile); // drop the oldest
                continue;
            }
            $newFile = $file . '.' . ($i + 1);
            $this->rotateByCopy
                ? $this->copyTruncate($rotateFile, $newFile)
                : @rename($rotateFile, $newFile);
            if ($i === 0) {
                $this->truncate($rotateFile);
            }
        }
    }

    private function copyTruncate(string $from, string $to): void
    {
        @copy($from, $to);
        if ($this->fileMode !== null) {
            @chmod($to, $this->fileMode);
        }
        $this->truncate($from);
    }

    private function truncate(string $file): void
    {
        if ($fp = @fopen($file, 'a')) {
            @ftruncate($fp, 0);
            @fclose($fp);
        }
    }
}
