[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](license.md)
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]

# Laravel Actions

Laravel package as an alternative to [single action controllers](https://laravel.com/docs/master/controllers#single-action-controllers) with support for web/html and api in single class. You can use *single* class called *Action* to send appropriate web or api response *automatically*. It also provides easy way to validate request data.

Under the hood, action classes are normal Laravel controllers but with single public `__invoke` method. This means you can do anything that you do with controllers normally like calling `$this->middleware('foo')` or anything else.


## Table of Contents

- [Why](#why)
- [Requirements](#requirements)
- [Installation](#installation)
- [Example Action Class](#example-action-class)
- [Usage](#usage)
- [Send Web or API Response Automatically](#send-web-or-api-response-automatically)
- [Validation](#validation)
- [Utility Methods and Properties](#utility-methods-and-properties)
- [Creating Actions](#creating-actions)
- [Registering Routes](#registering-routes)
- [Bonus: Creating Plain Classes](#bonus-creating-plain-classes)

## Why ##

 - Helps follow single responsibility principle (SRP)
 - Helps keep controllers and models skinny
 - Small dedicated class makes the code easier to test
 - Helps avoid code duplication eg different classes for web and api
 - Action classes can be callable from multiple places in your app
 - Small dedicated classes really pay off in complex apps
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


That's it.

---

## Example Action Class ##

````php
class PublishPostAction extends Action
{
    /**
     * Define any validation rules.
     */
    protected $rules = [];

    /**
     * Perform the action.
     *
     * @return mixed
     */
    public function __invoke()
    {
        // code
    }
}
````

In `__invoke()` method, you write actual logic of the action. Actions are invokable classes that use `__invoke` magic function turning them into a `Callable` which allows them to be called as function.

## Usage ##

**As Controller Actions**

Primary usage of action classes is mapping them to routes so they are called automatically when visiting those routes:

````php
// routes/web.php

Route::get('post', '\App\Http\Actions\PublishPostAction');

// or

Route::get('post', '\\' . PublishPostAction::class);
````

> <sup>*Note that the initial `\` here is important to ensure the namespace does not become `\App\Http\Controller\App\Http\Actions\PublishPostAction`*</sup>

**As Callable Classes**

````php
$action = new PublishPostAction();
$action();
````

## Send Web or API Response Automatically ##

If you need to serve both web and api responses from same/single action class, you need to define `html()` and `json()` method in your action class:

````php
class TodosListAction extends Action
{
    protected $todos;

    public function __invoke(Todo $todos)
    {
        $this->todos = $todos->all();
    }

    protected function html()
    {
        return view()->with('todos', $this->todos);
    }

    protected function json()
    {
        return TodosResource::collection($this->todos);
    }
}
````

With these two methods present, the package will *automatically* send appropriate response. Browsers will receive output from `html()` method and other devices will receive output from `json()` method.

Under the hood, we check if `Accept: application/json` header is present in request and if so it sends output from your `json()` method otherwise from `html()` method. 

You can change this api/json detection mechanism by implementing `isApi()` method, it must return `boolean` value:

````php
class TodosListAction extends Action
{
    protected $todos;

    public function __invoke(Todo $todos)
    {
        $this->todos = $todos->all();
    }

    protected function html()
    {
        return view('index')->with('todos', $this->todos);
    }

    protected function json()
    {
        return TodosResource::collection($this->todos);
    }
        
    public function isApi()
    {
        return request()->expectsJson() && !request()->acceptsHtml();
    }

}
````

**Using Action Classes for API Requests Only**

Simply return `true` from `isApi` method and use `json` method.

**Using Action Classes for Web/Browser Requests Only**

This is default behaviour, you can simply return your HTML/blade views from within `__invoke` or `html` method if you use it.

## Validation ##

You can perform input validation for your `store` and `update` methods, simply use `protected $rules = []` property in your action class:

````php
class TodoStoreAction extends Action
{
    protected $rules = [
        'title' => 'required|min:5'
    ];
    
    public function __invoke(Todo $todo)
    {
        $todo->fill(request()->all());
    
        return $todo->save();
    }
}
````

In this case, validation will be performed before `__invoke` method is called and if it fails, you will be automatically redirected back to previous form page with `$errors` filled with validation errors.

> **Tip:** Because validation is performed before `__invoke` method is called, using `request()->all()` will always give you valid data in `__invoke` method which is why it's used in above example.

**Custom Validation Messages**

To implement custom validation error messages for your rules, simply use `protected $messages = []` property.

## Utility Methods and Properties ##

Consider following action which is supposed to save todo/task into database and send appropriate response to web and api:

````php
class TodoStoreAction extends Action
{
    protected $rules = [
        'title' => 'required|min:2'
    ];

    public function __invoke(Todo $todo)
    {
        return $this->create($todo);
    }

    protected function html()
    {
        if (!$this->result) {
            return back()->withInput()->withErrors($this->errors);
        }

        session()->flash('success', self::MESSAGE_ADD);
        return back();
    }

    protected function json()
    {
        if (!$this->result) {
            return response()->json(['result' => false], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json(['result' => true], Response::HTTP_CREATED);
    }
}
````

There are few things to notice above that package provides out of the box:

 - Inside `__invoke` method, we used `$this->create` method as shorthand/quick way to create a new todo record. Similarly, `$this->update` and `$this->delete` methods can also be used. They all return `boolean` value. They all also accept optional callback:

````php
return $this->create($todo, function ($result) {
    if ($result) {
        flash(self::MESSAGE_ADD, 'success');
    } else {
        flash(self::MESSAGE_FAIL, 'danger');
    }
});
````


Using these methods is not required though.
 
 - If you return something from `__invoke` method, it gets stored into `$this->result` variable automatically. In this case, boolean result of todo creation was saved into it. We then used this variable as convenience in `html` and `json` methods to decide what response to send in case of success/failure.
 
 - Any validation errors are saved in `$this->errors` variable which can be used as needed.

 - In `html()` method, we have used `self::MESSAGE_ADD` which comes from parent action class. Similar, `self::MESSAGE_UPDATE`, `self::MESSAGE_DELETE` and `self::MESSAGE_FAIL` can also be used.



> **Tip:** You can choose to not use any utility methods/properties/validations offered by this package which is completely fine. Remember, action classes are normal Laravel controllers you can use however you like.


## Creating Actions ##

![Screen](https://github.com/sarfraznawaz2005/actions/blob/master/screen.png?raw=true)

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

## Bonus: Creating Plain Classes

The package also provides `make:class` console command to create plain classes:

```bash
php artisan make:class FooBar
```

`FooBar` class will be created under `app/Actions` folder:

````php
namespace App\Actions;

class FooBar
{
    /**
     * Perform the action.
     *
     * @return mixed
     */
    public function execute()
    {
        //
    }
}
````


Note that these are plain old PHP classes you can use for any purpose. *Ideally*, they should not be dependent on Laravel framework or any other framework and should have single public method as api such as `execute` and any more private/protected methods needed for that class to work. This will allow you to use them across different projects and frameworks. You can also think of them as service classes.


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
