<?php

namespace Sarfraznawaz2005\Actions;

use BadMethodCallException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Symfony\Component\HttpFoundation\Response;

abstract class Action extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $rules = [];
    protected $validData = [];

    /*
     * @var $isOkay bool
     */
    protected $isOkay = true;

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

        $this->checkValidation();

        return call_user_func_array([$this, $method], $parameters);
    }

    /**
     * Response to be returned in case of web request.
     *
     * @return mixed
     */
    abstract protected function htmlResponse();

    /**
     * Response to be returned in case of API request.
     *
     * @return mixed
     */
    abstract protected function jsonResponse();

    /**
     * Sends response based on client eg html or json.
     *
     * @return mixed
     */
    protected function sendResponse()
    {
        if ($this->isApi()) {
            return $this->jsonResponse();
        }

        return $this->htmlResponse();
    }

    /**
     * Checks if current request is api by looking at accept json header.
     *
     * @return bool
     */
    protected function isApi(): bool
    {
        return request()->expectsJson() && !request()->acceptsHtml();
    }

    /**
     * Checks validation rules against request input and stores result in validated variable.
     */
    protected function checkValidation()
    {
        if (method_exists($this, 'rules')) {
            $this->rules = $this->rules();

            if (!empty($this->rules)) {
                $this->validData = $this->validate(request(), $this->rules);
            }
        }
    }
}
