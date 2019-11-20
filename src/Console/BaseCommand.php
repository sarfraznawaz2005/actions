<?php

namespace Sarfraznawaz2005\Actions\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Sarfraznawaz2005\Actions\Exceptions\CommandException;

abstract class BaseCommand extends GeneratorCommand
{
    /**
     * Replace the class name for the given stub.
     *
     * @param string $stub
     * @param string $name
     * @return string
     */
    protected function replaceClass($stub, $name)
    {
        $class = str_replace($this->getNamespace($name) . '\\', '', $name);
        $class = trim($class, '\\/');

        return str_replace('DummyClass', $class, $stub);
    }

    /**
     * Get validated and normalized namespace option.
     *
     * @return string|null
     * @throws CommandException
     */
    protected function getValidatedAndNormalizedNamespaceOption()
    {
        $namespace = (string)$this->option('namespace');

        if (!$namespace) {
            return null;
        }

        $namespaceWithNormalizedSlashes = preg_replace('/[\/\\\]+/', '\\', $namespace);

        if (!preg_match('/^(\\\|(\\\?\w+)+)$/', $namespaceWithNormalizedSlashes)) {
            throw new CommandException('[' . $namespace . '] is not a valid namespace.');
        }

        return $namespaceWithNormalizedSlashes;
    }

    /**
     * Generate class names by specified name and actions.
     *
     * @param ActionsBag $bag
     * @param string $name
     * @return array
     */
    protected function generateClassNames(ActionsBag $bag, string $name): array
    {
        $name = Str::studly($name);

        if ($bag->isEmpty()) {
            return [$name];
        }

        return array_map(static function (string $action) use ($name) {
            return Str::studly($action) . $name;
        }, $bag->get());
    }

    /**
     * Get validated name argument.
     *
     * @return string
     * @throws CommandException
     */
    protected function getValidatedNameArgument(): string
    {
        $name = (string)$this->argument('name');

        if (!preg_match('/^\w+$/', $name)) {
            throw new CommandException('Name can\'t contain any non-word characters.');
        }

        return $name;
    }
}
