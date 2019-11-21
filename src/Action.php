<?php

namespace Sarfraznawaz2005\Actions;

use BadMethodCallException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Symfony\Component\HttpFoundation\Response;

abstract class Action extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    const MESSAGE_ADD = 'Added Successfully';
    const MESSAGE_UPDATE = 'Updated Successfully';
    const MESSAGE_DELETE = 'Deleted Successfully';

    /**
     * validation rules for action
     *
     * @var array
     */
    protected $rules = [];

    /**
     * validation errors
     *
     * @var MessageBag
     */
    protected $errors = null;

    /**
     * custom messages for validation errors
     *
     * @var array
     */
    protected $messages = [];

    /**
     * Returns true/false based on if request type is API
     *
     * @var bool
     */
    protected $isApi = false;

    /**
     * Stores result of __invoke call
     *
     * @var mixed
     */
    protected $result = false;

    /**
     * Execute the action.
     *
     * @param string $method
     * @param array $parameters
     * @return Response
     */
    public function callAction($method, $parameters)
    {
        if ($method !== '__invoke') {
            throw new BadMethodCallException('Only __invoke method can be called on action.');
        }

        $this->validate();

        $this->result = call_user_func_array([$this, $method], $parameters);

        if (method_exists($this, 'html') || method_exists($this, 'json')) {
            return $this->sendResponse();
        }

        return $this->result;
    }

    /**
     * Sends response based on client eg html or json.
     *
     * @return mixed
     */
    private function sendResponse()
    {
        if (method_exists($this, 'json')) {
            $this->isApi = $this->expectsApi();

            if (method_exists($this, 'isApi')) {
                $this->isApi = $this->isApi();
            }

            if ($this->isApi) {
                return $this->json();
            }
        }

        if (method_exists($this, 'html')) {
            return $this->html();
        }

        return $this->result;
    }

    /**
     * Checks if current request is api by looking at accept json header.
     *
     * @return bool
     */
    private function expectsApi(): bool
    {
        return request()->expectsJson() && !request()->acceptsHtml();
    }

    /**
     * Checks validation rules against request input.
     *
     * @return mixed
     */
    private function validate()
    {
        if (!empty($this->rules)) {
            $validator = Validator::make(request()->all(), $this->rules, $this->messages);

            if ($validator->fails()) {
                $this->errors = $validator->errors();
            }

            return $validator->validate();
        }
    }

    /**
     * Creates new DB record for given model
     *
     * @param Model $model
     * @param Callable $callback
     * @return bool
     */
    protected function create(Model $model, Callable $callback = null): bool
    {
        if ($this->save($model)) {

            if (!is_null($callback) && is_callable($callback)) {
                $callback();
            }

            return true;
        }

        return false;
    }

    /**
     * Updates record for given model
     *
     * @param Model $model
     * @param Callable $callback
     * @return bool
     */
    protected function update(Model $model, Callable $callback = null): bool
    {
        if ($this->save($model)) {

            if (!is_null($callback) && is_callable($callback)) {
                $callback();
            }

            return true;
        }

        return false;
    }

    /**
     * Deletes record for given model
     *
     * @param Model $model
     * @param Callable $callback
     * @return bool
     * @throws \Exception
     */
    protected function delete(Model $model, Callable $callback = null): bool
    {
        if ($model->delete()) {

            if (!is_null($callback) && is_callable($callback)) {
                $callback();
            }

            return true;
        }

        return false;
    }

    /**
     * Saves record for given model
     *
     * @param Model $model
     * @return bool
     */
    private function save(Model $model): bool
    {
        $model->fill(request()->all());

        return $model->save();
    }
}
