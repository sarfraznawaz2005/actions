<?php

namespace Sarfraznawaz2005\Actions\Console;

use Sarfraznawaz2005\Actions\Exceptions\CommandException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class MakeClassCommand extends BaseCommand
{
    protected $name = 'make:class';
    protected $description = 'Creates a new plain class';

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the class'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['namespace', null, InputOption::VALUE_REQUIRED, 'The namespace for generated class.'],
            ['force', 'f', InputOption::VALUE_NONE, 'Override existing class.'],
        ];
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle()
    {
        try {

            $bag = new ActionsBag;

            $classNames = $this->generateClassNames($bag, $this->getValidatedNameArgument());

            $finalNamespace = $this->getFinalNamespace();

            foreach ($classNames as $className) {
                $fullClassName = $finalNamespace . '\\' . $className;

                $type = $className . ' class';

                $path = $this->getPath($fullClassName);

                if ((!$this->hasOption('force') ||
                        !$this->option('force')) &&
                    $this->alreadyExists($fullClassName)) {
                    $this->error($type . ' already exists!');

                    continue;
                }

                $this->makeDirectory($path);

                $this->files->put($path, $this->buildClass($fullClassName));

                $this->info($type . ' created successfully.');
            }
        } catch (CommandException $exception) {
            $this->error($exception->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Get final namespace determined by default and specified by user namespaces.
     *
     * @return string
     * @throws CommandException
     */
    protected function getFinalNamespace(): string
    {
        $defaultNamespace = $this->laravel->getNamespace() . 'Actions';

        if (($namespaceOption = $this->getValidatedAndNormalizedNamespaceOption()) !== null) {
            if (starts_with($namespaceOption, '\\')) {
                return $namespaceOption;
            }

            return $defaultNamespace . '\\' . $namespaceOption;
        }

        return $defaultNamespace;
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return __DIR__ . '/stubs/class.stub';
    }

    /**
     * Replace the namespace for the given stub.
     *
     * @param string $stub
     * @param string $name
     * @return $this
     */
    protected function replaceNamespace(&$stub, $name)
    {
        $stub = str_replace('DummyNamespace', $this->getNamespace($name), $stub);

        return $this;
    }
}
