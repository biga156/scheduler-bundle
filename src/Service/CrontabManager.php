<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Service;

use Symfony\Component\Process\Process;

class CrontabManager
{
    private const MARKER = '# caeligo-scheduler';

    public function __construct(
        private readonly string $phpBinary,
        private readonly string $projectDir,
    ) {
    }

    public function getStatus(): string
    {
        if (!$this->isCrontabAvailable()) {
            return 'UNSUPPORTED';
        }

        $entry = $this->getInstalledEntry();

        return $entry !== null ? 'INSTALLED' : 'NOT_INSTALLED';
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function install(): array
    {
        if (!$this->isCrontabAvailable()) {
            return ['success' => false, 'message' => 'Crontab is not available on this system.'];
        }

        $existing = $this->getInstalledEntry();
        if ($existing !== null) {
            return ['success' => true, 'message' => 'Scheduler crontab entry is already installed.'];
        }

        $currentEntries = $this->readCurrentCrontab();
        $newLine = $this->buildCrontabLine();
        $entries = $currentEntries !== '' ? $currentEntries . "\n" . $newLine . "\n" : $newLine . "\n";

        $result = $this->writeCrontab($entries);
        if (!$result) {
            return ['success' => false, 'message' => 'Failed to write crontab entry.'];
        }

        // Verify
        $verify = $this->getInstalledEntry();
        if ($verify === null) {
            return ['success' => false, 'message' => 'Crontab entry was written but verification failed.'];
        }

        return ['success' => true, 'message' => 'Scheduler crontab entry installed successfully.'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function uninstall(): array
    {
        if (!$this->isCrontabAvailable()) {
            return ['success' => false, 'message' => 'Crontab is not available on this system.'];
        }

        $currentEntries = $this->readCurrentCrontab();
        if ($currentEntries === '') {
            return ['success' => true, 'message' => 'No crontab entries to remove.'];
        }

        $lines = explode("\n", $currentEntries);
        $filteredLines = array_filter($lines, function (string $line): bool {
            return !str_contains($line, self::MARKER) && !str_contains($line, 'caeligo:scheduler:run');
        });

        $newContent = implode("\n", $filteredLines);
        $newContent = trim($newContent);

        if ($newContent === '') {
            // Remove the entire crontab
            $process = new Process(['crontab', '-r']);
            $process->run();
        } else {
            $this->writeCrontab($newContent . "\n");
        }

        return ['success' => true, 'message' => 'Scheduler crontab entry removed successfully.'];
    }

    public function getInstalledEntry(): ?string
    {
        $content = $this->readCurrentCrontab();
        if ($content === '') {
            return null;
        }

        foreach (explode("\n", $content) as $line) {
            if (str_contains($line, self::MARKER) || str_contains($line, 'caeligo:scheduler:run')) {
                return trim($line);
            }
        }

        return null;
    }

    public function buildCrontabLine(): string
    {
        return \sprintf(
            '* * * * * (cd %s && %s bin/console caeligo:scheduler:run) >> /dev/null 2>&1 %s',
            escapeshellarg($this->projectDir),
            escapeshellarg($this->phpBinary),
            self::MARKER,
        );
    }

    private function isCrontabAvailable(): bool
    {
        $process = new Process(['which', 'crontab']);
        $process->run();

        return $process->isSuccessful();
    }

    private function readCurrentCrontab(): string
    {
        $process = new Process(['crontab', '-l']);
        $process->run();

        if (!$process->isSuccessful()) {
            return '';
        }

        return trim($process->getOutput());
    }

    private function writeCrontab(string $content): bool
    {
        $process = new Process(['crontab', '-']);
        $process->setInput($content);
        $process->run();

        return $process->isSuccessful();
    }
}
