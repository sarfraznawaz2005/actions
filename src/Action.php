<?php

namespace Sarfraznawaz2005\Actions;

use BadMethodCallException;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;

abstract class Action extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    use InteractsWithQueue;

    // these can be used by the user-created actions
    public const MESSAGE_CREATE = 'Added Successfully';
    public const MESSAGE_UPDATE = 'Updated Successfully';
    public const MESSAGE_DELETE = 'Deleted Successfully';
    public const MESSAGE_FAIL = 'Operation Failed';

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
    protected $errors;

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
     * requests items to ignore in validation or when creating/updating a model.
     *
     * @var array
     */
    protected $ignored = [];

    /**
     * The validated data
     *
     * @var array
     */
    protected $data = [];

    /**
     * Execute the action.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     * @throws ValidationException
     */
    public function callAction($method, $parameters)
    {
        if ($method !== '__invoke') {
            throw new BadMethodCallException('Only __invoke method can be called on action.');
        }

        // see if we need to ignore/remove items specified via ignored property
        if ($this->ignored) {
            foreach ($this->ignored as $ignored) {
                request()->request->remove($ignored);
            }
        }

        if (method_exists($this, 'transform')) {
            $this->transformRequest($this->transform(request()));
        }

        // validate request data
        $validated = $this->validate();

        if ($validated instanceof \Illuminate\Validation\Validator) {
            if (request()->ajax() || request()->wantsJson()) {
                return $this->errors;
            }

            return redirect()->back()->withInput()->withErrors($validated);
        }

        $this->result = call_user_func_array([$this, $method], $parameters);

        if (method_exists($this, 'html') || method_exists($this, 'json')) {
            return $this->sendResponse();
        }

        return $this->result;
    }

    /**
     * Called as job.
     *
     * @param array $parameters
     * @return mixed
     */
    public function handle(array $parameters = [])
    {
        $this->result = call_user_func_array([$this, '__invoke'], $parameters);

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
                return $this->json($this->result);
            }
        }

        if (method_exists($this, 'html')) {
            return $this->html($this->result);
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
        return request()->wantsJson() && !request()->acceptsHtml();
    }

    /**
     * Transforms requests data
     *
     * @param array $data
     * @return void
     */
    private function transformRequest(array $data): void
    {
        request()->merge($data);
    }

    /**
     * Checks validation rules against request input.
     *
     * @return bool|\Illuminate\Contracts\Validation\Validator|\Illuminate\Validation\Validator
     * @throws ValidationException
     */
    private function validate()
    {
        if (!empty($this->rules)) {
            $validator = Validator::make(request()->all(), $this->rules, $this->messages);

            if ($validator->fails()) {
                $this->errors = $validator->errors();
                return $validator;
            }

            $this->data = $validator->validated();
        }

        return true;
    }

    /**
     * Creates new DB record for given model
     *
     * @param Model $model
     * @param callable|null $callback
     * @return bool
     */
    protected function create(Model $model, callable $callback = null): bool
    {
        return $this->save($model, $callback);
    }

    /**
     * Updates record for given model
     *
     * @param Model $model
     * @param callable|null $callback
     * @return bool
     */
    protected function update(Model $model, callable $callback = null): bool
    {
        return $this->save($model, $callback);
    }

    /**
     * Deletes record for given model
     *
     * @param Model $model
     * @param callable|null $callback
     * @return bool|null
     * @throws Exception
     */
    protected function delete(Model $model, callable $callback = null): ?bool
    {
        $result = $model->delete();

        if ($callback !== null && is_callable($callback)) {
            $callback($result);
        }

        return $result;
    }

    /**
     * Saves record for given model
     *
     * @param Model $model
     * @param callable|null $callback
     * @return bool
     */
    private function save(Model $model, callable $callback = null): bool
    {
        $model->fill($this->data ?: request()->all());

        $result = $model->save();

        if ($callback !== null && is_callable($callback)) {
            $callback($result);
        }

        return $result;
    }
}

