# BuatCrud - Laravel CRUD Generator Package

This package provides an easy-to-use Artisan command to generate a complete CRUD (Create, Read, Update, Delete) system in Laravel projects. It supports advanced features such as custom fields, automatic view generation, route management, sidebar integration, and request validation.

## Features
- **Customizable CRUD Generation**: Easily define custom fields and generate the necessary migration, model, controller, and views.
- **Form Request Validation**: Automatically includes form requests for input validation.
- **Blade Templates for Views**: Generates views for listing, creating, editing, and showing records.
- **Sidebar Integration**: Offers an option to integrate the generated CRUD into your Laravel project's sidebar.
- **Automatic Route Handling**: Adds routes for the generated CRUD operations in `web.php`.
- **Laravel Policy Generation**: Generates policies to handle authorization for the generated model.

## Installation

To install the package in your Laravel project, you can add it via Composer:

```bash
composer require vickymaulana/buatcrud
```

Alternatively, you can manually add it to your `composer.json`:

```json
{
    "require": {
        "vickymaulana/buatcrud": "^1.0"
    }
}
```

Then, run:

```bash
composer update
```

### Service Provider

Once installed, the package will automatically register its service provider using Laravel's auto-discovery feature. However, if you need to manually register the service provider, you can do so in the `config/app.php` file:

```php
'providers' => [
    // Other Service Providers
    VickyMaulana\BuatCrud\BuatCrudServiceProvider::class,
],
```

## Usage

After installing the package, you can use the following Artisan command to generate CRUD operations for any model:

```bash
php artisan buatcrud {name} --fields={field1:type1,field2:type2,...}
```

- `{name}`: The name of the model for which you want to generate CRUD (e.g., `Product`).
- `--fields`: Specify fields and their types in the format `field:type`. Types can include typical Laravel migration types like `string`, `text`, `integer`, `decimal`, etc.

#### Example:

```bash
php artisan buatcrud Product --fields="name:string,price:decimal,description:text"
```

This command will generate:
- A model and migration for `Product`.
- A resource controller for CRUD operations.
- Blade templates for listing, creating, editing, and viewing the product.
- Form request validation rules.
- Optional sidebar link (with confirmation).

## Package Structure

- **src/**: Contains the package source code, including the service provider and console command.
- **BuatCrudServiceProvider**: Registers the package and makes the command available.
- **Commands/BuatCrud.php**: The Artisan command logic that generates the CRUD files.

## Requirements

- Laravel 11+
- PHP 8.2+

## License

The BuatCrud package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Author

- **Vicky Maulana**  
  GitHub: [vickymaulana](https://github.com/vickymaulana)  
  Email: [vickymaulanna@gmail.com](mailto:vickymaulanna@gmail.com)

Feel free to contribute by submitting pull requests, reporting issues, or suggesting features.