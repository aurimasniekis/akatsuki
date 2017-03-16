<?php

namespace Akatsuki\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AkatsukiCommand
 *
 * @package Akatsuki\Command
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
class AkatsukiCommand extends Command
{
    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('akatsuki')
            ->setDescription('PSR-4 namespace detector for PHPStorm IDE');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $output->writeln('<info>Akatsuki v' . $this->getApplication()->getVersion() . ' by Aurimas Niekis <aurimas@niekis.lt></info>');
        $output->writeln('');

        if (false === $this->checkRequirements()) {
            return 1;
        }

        $entries = $this->parseJson();
        if (false === $entries) {
            return 1;
        }

        $projectFile = $this->parseModules();
        $this->updateProjectFile($projectFile, $entries);

        $output->writeln('');
        $output->writeln('<info>Done.</info>');

        return 0;
    }

    private function checkRequirements()
    {
        if (false === file_exists($this->getComposerJsonFile())) {
            $this->output->writeln(
                sprintf(
                    '<error>`composer.json` file not found in "%s"',
                    getcwd()
                )
            );

            return false;
        }

        if (false === file_exists($this->getIdeaFolder())) {
            $this->output->writeln(
                sprintf(
                    '<error>`.idea` folder not found in "%s"',
                    getcwd()
                )
            );

            return false;
        }

        if (false === file_exists($this->getIdeaFolder() . '/modules.xml')) {
            $this->output->writeln(
                sprintf(
                    '<error>`.idea/modules.xml` file not found in "%s"',
                    getcwd()
                )
            );

            return false;
        }

        return true;
    }

    private function getComposerJsonFile()
    {
        return getcwd() . '/composer.json';
    }

    private function getIdeaFolder()
    {
        return getcwd() . '/.idea';
    }

    private function parseJson()
    {
        $content  = file_get_contents($this->getComposerJsonFile());
        $composer = json_decode($content, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            $this->output->writeln(
                sprintf(
                    '<error>Error parsing `composer.json` file: %s</error>',
                    json_last_error_msg()
                )
            );

            return false;
        }

        if (false === isset($composer['autoload']) && false === isset($composer['autoload-dev'])) {
            $this->output->writeln('<error>No `autoload` or `autoload-dev` found in `composer.json`</error>');

            return false;
        }

        $entries = [];
        if (isset($composer['autoload'])) {
            $entries = array_merge($entries, $this->processEntries($composer['autoload']));
        }

        if (isset($composer['autoload-dev'])) {
            $entries = array_merge($entries, $this->processEntries($composer['autoload-dev']));
        }

        if (count($entries) < 1) {
            $this->output->writeln('<error>No PSR-4 entries found in `autoload` or `autoload-dev`</error>');

            return false;
        }

        return $entries;
    }

    private function processEntries($autoload)
    {
        $entries = [];

        if (false === isset($autoload['psr-4'])) {
            return $entries;
        }

        foreach ($autoload['psr-4'] as $namespace => $folder) {
            $folder = preg_replace('/[^A-Z0-9]*(.*$)/', '$0', $folder);

            $entries[$folder] = $namespace;
        }

        return $entries;
    }

    private function parseModules()
    {
        $file = $this->getIdeaFolder() . '/modules.xml';
        $xml  = simplexml_load_file($file);

        $module = $xml->component->modules[0]->module;

        $fileUrl = (string)$module['fileurl'];

        return str_replace('file://$PROJECT_DIR$/.idea', '', $fileUrl);
    }

    private function updateProjectFile($projectFile, $entries)
    {
        $file = $this->getIdeaFolder() . $projectFile;

        if (false === file_exists($file)) {
            $this->output->writeln(
                sprintf(
                    '<error>`.idea/%s` file not found in "%s"',
                    $projectFile,
                    getcwd()
                )
            );
        }

        $xml     = simplexml_load_file($file);
        /** @var \SimpleXMLElement $content */
        $content = $xml->component->content;

        foreach ($content->sourceFolder as $sourceFolder) {
            $url = str_replace('file://$MODULE_DIR$/', '', (string)$sourceFolder['url']);

            if (true === isset($entries[$url])) {
                $this->output->writeln(
                    sprintf(
                        '<error>Folder `%s` already has prefix defined "%s"</error>',
                        $url,
                        $sourceFolder['packagePrefix']
                    )
                );

                unset($entries[$url]);
            }
        }

        foreach ($entries as $folder => $namespace) {
            $isTest = false;
            if (preg_match('/test/i', $namespace)) {
                $isTest = true;
            }

            $sourceFolder = $content->addChild('sourceFolder', null);
            $sourceFolder->addAttribute('url', 'file://$MODULE_DIR$/' . $folder);
            $sourceFolder->addAttribute('isTestSource', $isTest ? 'true' : 'false');
            $sourceFolder->addAttribute('packagePrefix', $namespace);

            $this->output->writeln(
                sprintf(
                    '<info>Adding folder "%s" with namespace "%s"</info>',
                    $folder,
                    $sourceFolder['packagePrefix']
                )
            );
        }

        file_put_contents($file, $xml->asXML());
    }
}