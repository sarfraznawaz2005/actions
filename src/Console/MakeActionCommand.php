<?php

namespace Sarfraznawaz2005\Actions\Console;

use Illuminate\Support\Str;
use Sarfraznawaz2005\Actions\Action;
use Sarfraznawaz2005\Actions\Exceptions\CommandException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class MakeActionCommand extends BaseCommand
{
    protected $name = 'make:action';
    protected $description = 'Creates a new action';

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the class'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['resource', 'r', InputOption::VALUE_NONE, 'Generate actions for all resource actions.'],
            ['api', 'a', InputOption::VALUE_NONE, 'Exclude the create and edit actions.'],
            ['actions', null, InputOption::VALUE_REQUIRED, 'Generate specified actions separated by comma.'],
            ['except', null, InputOption::VALUE_REQUIRED, 'Exclude specified actions separated by coma.'],
            ['namespace', null, InputOption::VALUE_REQUIRED, 'The namespace for generated action(s).'],
            ['force', 'f', InputOption::VALUE_NONE, 'Override existing action(s).'],
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

            $this->processResourceOption($bag);
            $this->processActionsOption($bag);
            $this->processExceptOption($bag);
            $this->processApiOption($bag);

            $classNames = $this->generateClassNames($bag, $this->getValidatedNameArgument());

            $finalNamespace = $this->getFinalNamespace();

            $this->line("\n");

            $created = false;

            foreach ($classNames as $className) {
                $fullClassName = $finalNamespace . '\\' . $className;

                $type = $className . ' action';

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

                $created = true;
            }

            if ($created) {
                $this->printRoutes($classNames);
            }

        } catch (CommandException $exception) {
            $this->error($exception->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Prints routes for generated actions
     *
     * @param $classNames
     * @return string
     * @throws CommandException
     */
    protected function printRoutes($classNames)
    {
        $routes = '';
        $namespace = $this->getFinalNamespace();
        $actionName = $this->getValidatedNameArgument();

        $actionNameRoutePlural = strtolower(Str::plural($actionName));
        $actionNameRouteSingular = strtolower(Str::singular($actionName));

        $actionMappings = [
            'index' => 'get',
            'show' => 'get',
            'create' => 'get',
            'store' => 'post',
            'edit' => 'get',
            'update' => 'put',
            'destroy' => 'delete',
        ];

        $routeMappings = [
            'index' => $actionNameRoutePlural,
            'show' => $actionNameRoutePlural . '/{' . $actionNameRouteSingular . '}',
            'create' => "$actionNameRoutePlural/create",
            'store' => $actionNameRoutePlural,
            'edit' => $actionNameRoutePlural . '/{' . $actionNameRouteSingular . '}/edit',
            'update' => $actionNameRoutePlural . '/{' . $actionNameRouteSingular . '}',
            'destroy' => $actionNameRoutePlural . '/{' . $actionNameRouteSingular . '}',
        ];

        // remove action name
        $methods = array_map(static function ($value) use ($actionName) {
            return $value . '|' . strtolower(str_replace($actionName, '', $value));
        }, $classNames);

        foreach ($methods as $methodDetails) {
            list($action, $method) = explode('|', $methodDetails);

            $verb = $actionMappings[$method] ?? 'get';
            $route = $routeMappings[$method] ?? $actionNameRoutePlural . '/' . $method;
            $route = rtrim($route, '/');
            $path = ltrim($namespace . '\\' . $action, '\\');

            $routes .= 'Route::' . $verb . "('" . $route . "', '\\" . $path . "')->name('" . $actionNameRoutePlural . ($method ? '.' . $method : '') . "');" . "\n";
        }

        if ($routes) {
            $this->line("\n");
            $this->alert('You may use following routes:');
            $this->info($routes);
        }
    }

    /**
     * Get final namespace determined by default and specified by user namespaces.
     *
     * @return string
     * @throws CommandException
     */
    protected function getFinalNamespace(): string
    {
        $defaultNamespace = $this->laravel->getNamespace() . 'Http\\Actions';

        if (($namespaceOption = $this->getValidatedAndNormalizedNamespaceOption()) !== null) {
            if (Str::startsWith($namespaceOption, '\\')) {
                return $namespaceOption;
            }

            return $defaultNamespace . '\\' . $namespaceOption;
        }

        return $defaultNamespace;
    }

    /**
     * Process --resource option.
     *
     * @param ActionsBag $bag
     * @return void
     */
    protected function processResourceOption(ActionsBag $bag)
    {
        if ($this->option('resource')) {
            foreach (['index', 'show', 'create', 'store', 'edit', 'update', 'destroy'] as $action) {
                $bag->addIfNotExists($action);
            }
        }
    }

    /**
     * Process --actions option.
     *
     * @param ActionsBag $bag
     * @return void
     * @throws CommandException
     */
    protected function processActionsOption(ActionsBag $bag)
    {
        if ($actions = (string)$this->option('actions')) {
            foreach (explode(',', $actions) as $action) {
                $bag->addIfNotExists(
                    $this->getValidatedAndNormalizedActionName($action)
                );
            }
        }
    }

    /**
     * Process --except option.
     *
     * @param ActionsBag $bag
     * @return void
     * @throws CommandException
     */
    protected function processExceptOption(ActionsBag $bag)
    {
        if ($except = (string)$this->option('except')) {
            foreach (explode(',', $except) as $action) {
                $bag->deleteIfExists(
                    $this->getValidatedAndNormalizedActionName($action)
                );
            }
        }
    }

    /**
     * Process an --api option.
     *
     * @param ActionsBag $bag
     * @return void
     */
    protected function processApiOption(ActionsBag $bag)
    {
        if ($this->option('api')) {
            foreach (['index', 'show', 'store', 'update', 'destroy'] as $action) {
                $bag->addIfNotExists($action);
            }
        }
    }

    /**
     * Get validated and normalized action name.
     *
     * @param string $action
     * @return string
     * @throws CommandException
     */
    protected function getValidatedAndNormalizedActionName(string $action): string
    {
        if (preg_match('/^\w+$/', $action)) {
            return Str::snake($action);
        }

        throw new CommandException('[' . $action . '] is not a valid action name.');
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return __DIR__ . '/stubs/action.stub';
    }

    /**
     * Get the class name of the base action.
     *
     * @return string
     */
    protected function getBaseActionClassName(): string
    {
        return Action::class;
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
        $stub = str_replace(
            ['DummyNamespace', 'DummyBaseActionNamespace'],
            [$this->getNamespace($name), $this->getBaseActionClassName()],
            $stub
        );

        return $this;
    }
}
