<?php

declare(strict_types=1);

namespace Netgen\GitHooks\Action;

use CaptainHook\App\Config;
use CaptainHook\App\Console\IO;
use SebastianFeldmann\Cli\Processor\ProcOpen as Processor;
use SebastianFeldmann\Git\Repository;
use function escapeshellarg;
use function preg_match;

final class JSPrettier extends Action
{
    protected const ERROR_MESSAGE = 'Committed JS code did not pass prettier. Please run prettier on your files before committing them.';

    protected function doExecute(Config $config, IO $io, Repository $repository, Config\Action $action): void
    {
        $changedJsFiles = $repository->getIndexOperator()->getStagedFilesOfType('js');
        if (empty($changedJsFiles)) {
            return;
        }

        $excludedFiles = $action->getOptions()->get('excluded_files');

        $prettierCommand = $action->getOptions()->get('prettier_command', 'yarn prettier');
        $prettierOptions = $action->getOptions()->get('prettier_options', '--check');

        $io->write('Running prettier on files:', true, IO::VERBOSE);
        foreach ($changedJsFiles as $file) {
            if ($this->shouldSkipFileCheck($file, $excludedFiles)) {
                continue;
            }

            $result = $this->lintFile($file, $prettierCommand, $prettierOptions);

            $io->write($result['output'], true);

            if ($result['success'] !== true) {
                $this->throwError($action, $io);
            }
        }
    }

    protected function shouldSkipFileCheck(string $file, array $excludedFiles): bool
    {
        foreach ($excludedFiles as $excludedFile) {
            // File definition using regexp
            if ($excludedFile[0] === '/') {
                if (preg_match($excludedFile, $file)) {
                    return true;
                }

                continue;
            }
            if ($excludedFile === $file) {
                return true;
            }
        }

        return false;
    }

    protected function lintFile(string $file, string $prettierCommand, string $prettierOptions): array
    {
        $process = new Processor();
        $result = $process->run($prettierCommand.' '.$prettierOptions.'  '.escapeshellarg($file));

        return [
            'success' => $result->isSuccessful(),
            'output' => $result->getStdOut(),
        ];
    }
}
