[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](license.md)
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]

# Laravel Actions

Laravel package as an alternative to [single action controllers](https://laravel.com/docs/master/controllers#single-action-controllers) with support for web and api in single class. You can use *single class* to send appropriate web or api response automatically. It also provides easy way to validate request data.

Under the hood, they are normal Laravel controllers but with single public `__invoke` method. This means you can do anything that you do with controllers normally like calling `$this->middleware('foo')` or anything else.

## Why ##

 - Helps follow single responsibility principle (SRP)
 - It keeps our controllers and models skinny
 - Small dedicated action makes the code easier to test
 - Helps avoid code duplication eg different classes for web and api
 - Actions can be callable from multiple places in your app
 - Small dedicated classes really pay off in complex apps
 - Actions can be used as non-controller fashion like service classes
 - Expressive routes registration like `Route::get('/', HomeAction::class)`
 - Allows decorator pattern 

## Requirements ##

 - PHP >= 7
 - Laravel 5, 6

## Installation ##

Install via composer

```
composer require sarfraznawaz2005/actions
```

For Laravel < 5.5:

Add Service Provider to `config/app.php` in `providers` section

```php
Sarfraznawaz2005\Action\ServiceProvider::class,
```

That's it.

---

## Example Action ##

````php
class PostAction extends Action
{
    protected $service;

    public function __construct(FooService $service)
    {
        $this->service = $service;
    }

    /**
     * Define any validation rules.
     *
     * @return mixed
     */
    protected function rules(): array
    {
        return [];
    }

    /**
     * Perform the action.
     *
     * @return mixed
     */
    public function __invoke()
    {
        $this->service->bar();
    
        // more code

        return $this->sendResponse();
    }

    /**
     * Response to be returned in case of web request.
     *
     * @return mixed
     */
    protected function htmlResponse()
    {
        return 'hi';
    }

    /**
     * Response to be returned in case of API request.
     *
     * @return mixed
     */
    protected function jsonResponse()
    {
        return response()->json(null, Response::HTTP_OK);
    }
}
````

**Explanation**

 - In `rules()` method, you can store any validation rules for this action. You would normally need this for `store` or `update` operations. This method is optional and is run BEFORE `__invoke()` method.  
 
 - In `__invoke()` method, you write actual logic of the action. Actions are invokable classes that use `__invoke` magic function turning them into a `Callable` which allows them to be called as a `function`. The `__invoke()` method can be used for dependecy injection but constructor is recommended approach.
 
 - In `htmlResponse()` method, you write code that will be sent as HTML to browser.
 
 - In `jsonResponse()` method, you write code that will be returned as API response. Of course in real world app, you would use api resource/transformer in this method.

> **NOTE:** To send html or api response *automatically*, you must call `return $this->sendResponse()` from `__invoke()` method as shown above.

Under the hood, `sendResponse()` method checks if `Accept: application/json` header is present in request and if so it sends output from your `jsonResponse()` method otherwise from `htmlResponse()` method. 

## Usage ##

**As Controller Actions**

Primary usage of action classes is mapping them to routes so they are called automatically when visiting those routes:

````php
// routes/web.php

Route::get('post', '\App\Http\Actions\PostAction');
````

> <sup>*Note that the initial `\` here is important to ensure the namespace does not become `\App\Http\Controller\App\Http\Actions\PostAction`*</sup>

**As Callable Class**

````php
$postAction = new PostAction();
$postAction();
````

## Utility Properties ##

It is common requirement to send web or api response based on certain condition or to get validated data to be used later. Consider following action which is supposed to save post into database and send appropriate response to web and api:

````php
class PostAction extends Action
{
    /**
     * Define any validation rules.
     *
     * @return mixed
     */
    protected function rules(): array
    {
        return [
            'description' => 'required|min:5',
        ];
    }

    /**
     * Perform the action.
     *
     * @return mixed
     */
    public function __invoke(Post $post)
    {
        $this->isOkay = $post->create($this->validData);

        return $this->sendResponse();
    }

    /**
     * Response to be returned in case of web request.
     *
     * @return mixed
     */
    protected function htmlResponse()
    {
        if (!$this->isOkay) {
            return back()->withInput()->withErrors(['Unable to add post']);
        }

        flash('Post added successfully', 'success');

        return back();
    }

    /**
     * Response to be returned in case of API request.
     *
     * @return mixed
     */
    protected function jsonResponse()
    {
        if (!$this->isOkay) {
            return response()->json(['result' => false], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json(['result' => true], Response::HTTP_CREATED);
    }
}
````

 - In above code, we use action's built-in `$this->validData` property to get valid request data to save in our `Post` model after validation rules have passed in `rules()` method.

 - We then save result of `$post->create()` call into action's built-in `$this->isOkay` property so that we can show success/failure flash messages or api responses in response methods.

## Creating Actions ##

- Create an action

```bash
php artisan make:action ShowPost
```

> `ShowPost` action will be created

- Create actions for all resource actions (`index`, `show`, `create`, `store`, `edit`, `update`, `destroy`)

```bash
php artisan make:action Post --resource
```

> `IndexPost`, `ShowPost`, `CreatePost`, `StorePost`, `EditPost`, `UpdatePost`, `DestroyPost` actions will be created

- Create actions for all API actions (`create`, `edit` excluded)

```bash
php artisan make:action Post --api
```

> `IndexPost`, `ShowPost`, `StorePost`, `UpdatePost`, `DestroyPost` actions will be created

- Create actions by the specified actions

```bash
php artisan make:action Post --actions=show,destroy,approve
```

> `ShowPost`, `DestroyPost`, `ApprovePost` actions will be created

- Exclude specified actions

```bash
php artisan make:action Post --resource --except=index,show,edit
```

> `CreatePost`, `StorePost`, `UpdatePost`, `DestroyPost` actions will be created

- Specify namespace for actions creating (relative path)

```bash
php artisan make:action Post --resource --namespace=Post
```

> `IndexPost`, `ShowPost`, `CreatePost`, `StorePost`, `EditPost`, `UpdatePost`, `DestroyPost` actions will be created under `App\Http\Actions\Post` namespace in `app/Http/Actions/Post` directory

- Specify namespace for actions creating (absolute path)

```bash
php artisan make:action ActivateUser --namespace=\\App\\Foo\\Bar
```

> `ActivateUser` action will be created under `App\Foo\Bar` namespace in `app/Foo/Bar` directory

- Force create

```bash
php artisan make:action EditPost --force
```

> If `EditPost` action already exists, it will be overwritten by the new one

---
---

**NOTE** We strongly recommend to append `Action` suffix to all your action classes:

```bash
php artisan make:action ShowPostAction
```

## Registering Routes 

Here are several ways to register actions in routes:

#### In separate `actions.php` route file

- Create `routes/actions.php` file (you can choose any name, it's just an example)
- Define the "action" route group in `app/Providers/RouteServiceProvider.php`

> ##### With namespace auto prefixing

```php
// app/Providers/RouteServiceProvider.php

protected function mapActionRoutes()
{
    Route::middleware('web')
         ->namespace('App\Http\Actions')
         ->group(base_path('routes/actions.php'));
}
```

```php
// app/Providers/RouteServiceProvider.php

public function map()
{
    $this->mapApiRoutes();

    $this->mapWebRoutes();
    
    $this->mapActionRoutes();

    //
}
```

```php
// routes/actions.php

Route::get('/post/{post}', 'ShowPost');
```

> ##### Without namespace auto prefixing

```php
// app/Providers/RouteServiceProvider.php

protected function mapActionRoutes()
{
    Route::middleware('web')
         ->group(base_path('routes/actions.php'));
}
```

```php
// app/Providers/RouteServiceProvider.php

public function map()
{
    $this->mapApiRoutes();

    $this->mapWebRoutes();
    
    $this->mapActionRoutes();

    //
}
```

```php
// routes/actions.php

use App\Actions\ShowPost;

Route::get('/post/{post}', ShowPost::class); // pretty sweet, isn't it? 😍
```

#### In `web.php` route file

- Change the namespace for "web" group in `RouteServiceProvider.php`

```php
// app/Providers/RouteServiceProvider.php

protected function mapWebRoutes()
{
    Route::middleware('web')
         ->namespace('App\Http') // pay attention here
         ->group(base_path('routes/web.php'));
}
```

- Put actions and controllers in different route groups in `routes/web.php` file and prepend an appropriate namespace for each of them

```php
// routes/web.php

Route::group(['namespace' => 'Actions'], function () {
    Route::get('/posts/{post}', 'ShowPost');
    Route::delete('/posts/{post}', 'DestroyPost');
});

Route::group(['namespace' => 'Controllers'], function () {
    Route::get('/users', 'UserController@index');
    Route::get('/users/{user}', 'UserController@show');
});
```

## Credits

- [Sarfraz Ahmed][link-author]
- [All Contributors][link-contributors]

## License

Please see the [license file](license.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/sarfraznawaz2005/actions.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/sarfraznawaz2005/actions.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/sarfraznawaz2005/actions
[link-downloads]: https://packagist.org/packages/sarfraznawaz2005/actions
[link-author]: https://github.com/sarfraznawaz2005
[link-contributors]: https://github.com/sarfraznawaz2005/actions/graphs/contributors
