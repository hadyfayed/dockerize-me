<?php

namespace DockerizeMe\Commands;

use Cocur\Slugify\Slugify;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class DockerizeMe extends Command
{
    const INVALID_PHP_VERSION = 1;
    const DOCKER_FOLDER_EXISTS = 2;
    const DOCKER_COMPOSER_FILE_EXISTS = 3;

    /**
     * @var Slugify
     */
    private $slugifier;

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    public function __construct($name = null)
    {
        $this->slugifier = new Slugify();
        parent::__construct($name);
    }

    protected function configure()
    {
        $position = explode(DIRECTORY_SEPARATOR, getcwd());
        $this->setName('dockerize-me')
            ->setDescription('Add some docker magic to your PHP application')
            ->setHelp('This command adds a docker-compose file to your projects and the Dockerfile associated.')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Project name (will be slugified)', $this->slugifier->slugify(end($position)))
            ->addOption('php', null, InputOption::VALUE_REQUIRED, 'Wanted php version (7.0 | 7.1)', '7.1')
            ->addOption('mysql', null, InputOption::VALUE_REQUIRED, 'Wanted mysql version', '5.7')
            ->addOption('redis', null, InputOption::VALUE_REQUIRED, 'Wanted redis version', '3.2')
            ->addOption('node', null, InputOption::VALUE_REQUIRED, 'Wanted node version', 'latest');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;


        $projectName = $this->slugifier->slugify($input->getOption('name'));
        $phpVersion = $input->getOption('php');
        $mysqlVersion = $input->getOption('mysql');
        $redisVersion = $input->getOption('redis');
        $nodeVersion = $input->getOption('node');

        $this->ensurePHPVersion($phpVersion);
        $this->ensureFilesDoesntExists();

        $this->sayHello();
        $this->showSelectedVersions($projectName, $phpVersion, $mysqlVersion, $redisVersion, $nodeVersion);
        $this->askContinue($input, $output);
        $this->copyStubs($projectName, $phpVersion, $mysqlVersion, $nodeVersion, $redisVersion);
        $this->infoln("We're ready! You can now `./dcp up` !");
    }

    private function ensurePHPVersion($phpVersion)
    {
        $possibilities = ["7.0", "7.1"];
        foreach ($possibilities as $possibility) if ($phpVersion === $possibility) {
            return;
        }

        $this->errorln("PHP version must be one of the following : [7.0|7.1]");
        exit(self::INVALID_PHP_VERSION);
    }

    private function ensureFilesDoesntExists()
    {
        if (is_dir('./docker')) {
            $this->errorln("It looks like a docker/ folder already exists. Please make a backup and delete it before using me!");
            exit(self::DOCKER_FOLDER_EXISTS);

        }
        if (is_file('./docker-compose.yml')) {
            $this->errorln("It looks like a docker-compose.yml file already exists. Please make a backup and delete it before using me!");
            exit(self::DOCKER_COMPOSER_FILE_EXISTS);
        }
    }

    protected function sayHello()
    {
        $this->infoln("Hi ! It looks like you want to give some docker magic to your project!");
        $this->infoln("let me help you with that!");
        $this->infoln("");
        $this->infoln("We're going to use the current stack:");
    }

    /**
     * @param $projectName
     * @param $phpVersion
     * @param $mysqlVersion
     * @param $redisVersion
     * @param $nodeVersion
     */
    protected function showSelectedVersions($projectName, $phpVersion, $mysqlVersion, $redisVersion, $nodeVersion)
    {
        $this->writeVersion("Project name", $projectName);
        $this->writeVersion("PHP", $phpVersion);
        $this->writeVersion("MySQL", $mysqlVersion);
        $this->writeVersion("Redis", $redisVersion);
        $this->writeVersion("NodeJS", $nodeVersion);

        $this->output->writeln("");
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function askContinue(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion("<info>Is that ok?</info> <comment>[Y/n]</comment>\n", true);
        if (!$helper->ask($input, $output, $question)) {
            $this->infoln("Please call me with `-h` to see available options");
            exit(0);
        }
    }

    /**
     * @param $projectName
     * @param $phpVersion
     * @param $mysqlVersion
     * @param $nodeVersion
     * @param $redisVersion
     */
    protected function copyStubs($projectName, $phpVersion, $mysqlVersion, $nodeVersion, $redisVersion)
    {
        $this->info("Working...");
        $path = realpath(__DIR__ . '/../../stubs');
        $target = realpath('.');
        $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($objects as $name => $object) {
            if ($object->isDir()) {
                mkdir($target . DIRECTORY_SEPARATOR . $objects->getSubPathName());
            } elseif ($object->isFile()) {
                $content = str_replace(
                    ["{#PROJET_NAME#}", "{#PHP_VERSION#}", "{#MYSQL_VERSION#}", "{#NODE_VERSION#}", "{#REDIS_VERSION#}"],
                    [$projectName, $phpVersion, $mysqlVersion, $nodeVersion, $redisVersion],
                    file_get_contents($name)
                );

                file_put_contents($target . DIRECTORY_SEPARATOR . $objects->getSubPathName(), $content);
                unset($content);
            }
        }
        $this->infoln(" done.");

        if (!chmod($target . DIRECTORY_SEPARATOR . 'dcp', 0755)) {
            $this->infoln('Warning: please set chmod 755 on ' . $target . DIRECTORY_SEPARATOR . 'dcp !');
        }
    }


    private function info($str)
    {
        $this->output->write('<info>' . $str . '</info>');
    }

    private function infoln($str)
    {
        $this->output->writeln('<info>' . $str . '</info>');
    }

    private function errorln($str)
    {
        $this->output->writeln('<error>' . $str . '</error>');
    }

    private function writeVersion($tool, $version)
    {
        $this->output->writeln("\t<info>" . $tool . ":</info> <comment>" . $version . "</comment>");
    }
}