<?php

declare(strict_types=1);

namespace Netgen\GitHooks\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Symfony\Component\Process\Process;
use function file_exists;
use function getcwd;

final class InstallHooksPlugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var \Composer\Composer
     */
    private $composer;

    /**
     * @var \Composer\IO\IOInterface
     */
    private $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'installHooks',
            ScriptEvents::POST_UPDATE_CMD => 'installHooks',
        ];
    }

    public function installHooks(Event $event): void
    {
        if (!$this->hasConfigFile() || !$this->hasGitRepo() || $this->isPluginDisabled()) {
            return;
        }

        $this->runInstallCommand();
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    private function runInstallCommand(): void
    {
        $process = new Process(
            [
                'php',
                'bin/captainhook',
                'install',
                '--force',
                $this->io->isDecorated() ? '--ansi' : '--no-ansi',
            ]
        );

        $process->run(
            function ($type, $line) {
                $this->io->write($line, false);
            }
        );
    }

    private function hasConfigFile(): bool
    {
        return file_exists(getcwd() . '/captainhook.json');
    }

    private function hasGitRepo(): bool
    {
        return file_exists(getcwd() . '/.git');
    }

    private function isPluginDisabled(): bool
    {
        $extra = $this->composer->getPackage()->getExtra();

        return (bool) ($extra['captainhook']['disable-plugin'] ?? false);
    }
}
