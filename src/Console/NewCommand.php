<?php
namespace Acabin\Imagist\Console;

use RuntimeException;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NewCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Create a new Imagist application.')
            ->addArgument('name', InputArgument::REQUIRED);
    }
    /**
     * Execute the command.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->verifyApplicationDoesntExist(
            $directory = getcwd() . '/' . $input->getArgument('name'),
            $output
        );

        mkdir($directory, 0755, true);
        $composer = $this->findComposer();
        $commands = [
            "$composer require composer/composer:^1.0@alpha acabin/imagist",
        ];
        if(!empty($commands)) {
        	$this->process($commands, $directory, $output);
        }
        mkdir($directory . "/public", 0755, true);
        copy(ROOT_PATH . '/public/index.php', $directory . "/public/index.php");

        $output->writeln('<comment>Here we are! Now you can have a cached composer!</comment>');

    }
    protected function process($commands, $directory, $output)
    {
    	$process = new Process(implode(' && ', $commands), $directory, null, null, null);
    	$process->run(function ($type, $line) use ($output) {
    	    $output->write($line);
    	});
    }
    /**
     * Verify that the application does not already exist.
     *
     * @param  string  $directory
     * @return void
     */
    protected function verifyApplicationDoesntExist($directory, OutputInterface $output)
    {
        if (is_dir($directory)) {
            throw new RuntimeException('Application already exists!');
        }
    }
    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        if (file_exists(getcwd() . '/composer.phar')) {
            return '"' . PHP_BINARY . '" composer.phar';
        }
        return 'composer';
    }
}
