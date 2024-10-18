<?php

namespace VickyMaulana\BuatCrud\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BuatCrud extends Command
{
    // Signature untuk menjalankan command
    protected $signature = 'buatcrud {name} {--fields=}';
    protected $description = 'Generate CRUD dengan fitur-fitur lanjutan seperti custom fields, update sidebar, dan validasi';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // Ambil nama model yang diminta tanpa pluralisasi
        $name = $this->argument('name');
        if (!ctype_alpha($name)) {
            $this->error('Nama model hanya boleh berisi huruf.');
            return;
        }

        $lowerCaseName = Str::kebab($name);

        // Mendapatkan fields dari option --fields
        $fields = $this->option('fields');
        $fieldArray = $this->prosesFields($fields);

        // Generate Model dan Migration dengan custom fields
        $this->generateModelDanMigration($name, $fieldArray);

        // Generate Controller dengan resource
        Artisan::call('make:controller', [
            'name' => $name . 'Controller',
            '--resource' => true,
            '--model' => $name,
        ]);
        $this->info('Resource Controller berhasil dibuat.');

        // Update isi Controller agar mengarahkan ke views
        $this->generateController($name);

        // Generate Form Request untuk validasi
        $this->generateFormRequest($name, $fieldArray);

        // Generate Policy untuk model
        Artisan::call('make:policy', [
            'name' => $name . 'Policy',
            '--model' => $name,
        ]);
        $this->info('Policy untuk ' . $name . ' berhasil dibuat.');

        // Generate views dengan template Blade
        $this->buatViews($name, $fieldArray);

        // Menambahkan route ke web.php
        $this->updateRoutes($name, $lowerCaseName);

        // Menambahkan menu ke sidebar setelah konfirmasi
        if ($this->confirm('Apakah Anda ingin menambahkan link sidebar untuk ' . $lowerCaseName . ' ?')) {
            $this->updateSidebar($lowerCaseName);
        }

        $this->info('CRUD dengan fitur lanjutan berhasil dibuat.');
    }

    protected function prosesFields($fields)
    {
        if (empty($fields)) {
            return [];
        }

        $fieldArray = explode(',', $fields);
        $parsedFields = [];
        foreach ($fieldArray as $field) {
            $parts = explode(':', $field);
            if (count($parts) !== 2) {
                $this->error("Format field salah untuk '{$field}'. Harus 'nama:tipe'.");
                continue;
            }
            [$name, $type] = $parts;
            $parsedFields[] = ['name' => trim($name), 'type' => trim($type)];
        }
        return $parsedFields;
    }

    protected function generateModelDanMigration($name, $fields)
    {
        Artisan::call('make:model', [
            'name' => $name,
            '-m' => true,
        ]);
        $this->info('Model dan Migration berhasil dibuat.');

        // Update file migration dengan custom fields
        $migrationFiles = glob(database_path('migrations/*_create_' . Str::snake(Str::plural($name)) . '_table.php'));
        if (empty($migrationFiles)) {
            $this->error('File migration tidak ditemukan.');
            return;
        }
        $migrationFile = array_shift($migrationFiles);
        $migrationContent = File::get($migrationFile);

        $migrationFields = '';
        $fillableFields = [];

        if (is_array($fields) && count($fields) > 0) {
            foreach ($fields as $field) {
                $migrationFields .= "\$table->" . $field['type'] . "('" . $field['name'] . "');\n\t\t\t";
                $fillableFields[] = "'" . $field['name'] . "'";
            }
        }

        $migrationContent = str_replace(
            '$table->id();',
            '$table->id();' . "\n\t\t\t" . $migrationFields,
            $migrationContent
        );
        File::put($migrationFile, $migrationContent);
        $this->info('Migration berhasil diperbarui dengan fields.');

        // Update Model fillable fields
        $modelPath = app_path('Models/' . $name . '.php');
        $fillable = implode(", ", $fillableFields);
        $modelContent = <<<MODEL
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class $name extends Model
{
    use HasFactory;

    protected \$fillable = [$fillable];
}
MODEL;

        File::put($modelPath, $modelContent);
        $this->info('Model ' . $name . ' berhasil diperbarui.');
    }

    protected function generateFormRequest($name, $fields)
    {
        // Generate form request untuk validasi
        Artisan::call('make:request', [
            'name' => $name . 'Request',
        ]);
        $this->info('Form Request untuk ' . $name . ' berhasil dibuat.');

        // Tambahkan validasi sederhana ke form request
        $formRequestPath = app_path('Http/Requests/' . $name . 'Request.php');
        $formRequestContent = File::get($formRequestPath);

        $rules = '';
        if (is_array($fields) && count($fields) > 0) {
            foreach ($fields as $field) {
                $rules .= "'{$field['name']}' => 'required',\n\t\t\t";
            }
        }

        $formRequestContent = str_replace(
            'return [];',
            "return [\n\t\t\t" . $rules . "\n\t\t];",
            $formRequestContent
        );
        File::put($formRequestPath, $formRequestContent);
        $this->info('Validasi berhasil ditambahkan ke form request.');
    }

    protected function generateController($name)
    {
        $controllerPath = app_path('Http/Controllers/' . $name . 'Controller.php');
        $lowerCaseName = Str::kebab($name);
        $modelName = ucfirst($name);
        $requestName = $name . 'Request';

        $controllerTemplate = <<<CONTROLLER
<?php

namespace App\Http\Controllers;

use App\Models\\$modelName;
use App\Http\Requests\\$requestName;
use Illuminate\Http\Request;

class {$name}Controller extends Controller
{
    public function index()
    {
        // Ambil semua data dari model $modelName
        \$items = $modelName::all();

        // Kirim data ke view
        return view('$lowerCaseName.index', compact('items'));
    }

    public function create()
    {
        \$item = null;
        return view('$lowerCaseName.create', compact('item'));
    }

    public function store($requestName \$request)
    {
        // Logika untuk menyimpan data
        \$data = \$request->validated();
        $modelName::create(\$data);
        return redirect()->route('$lowerCaseName.index')->with('success', '$modelName berhasil ditambahkan.');
    }

    public function show(\$id)
    {
        \$item = $modelName::findOrFail(\$id);
        return view('$lowerCaseName.show', compact('item'));
    }

    public function edit(\$id)
    {
        \$item = $modelName::findOrFail(\$id);
        return view('$lowerCaseName.edit', compact('item'));
    }

    public function update($requestName \$request, \$id)
    {
        // Logika untuk update data
        \$data = \$request->validated();
        \$item = $modelName::findOrFail(\$id);
        \$item->update(\$data);
        return redirect()->route('$lowerCaseName.index')->with('success', '$modelName berhasil diperbarui.');
    }

    public function destroy(\$id)
    {
        // Logika untuk menghapus data
        \$item = $modelName::findOrFail(\$id);
        \$item->delete();
        return redirect()->route('$lowerCaseName.index')->with('success', '$modelName berhasil dihapus.');
    }
}
CONTROLLER;

        File::put($controllerPath, $controllerTemplate);
        $this->info('Controller ' . $name . ' berhasil diperbarui.');
    }

    protected function buatViews($name, $fieldArray)
    {
        $lowerCaseName = Str::kebab($name);

        // Path untuk menyimpan views
        $path = resource_path('views/' . $lowerCaseName);

        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }

        // Generate table headers and data cells berdasarkan fields
        $headerFields = '';
        $fieldTemplate = '';
        if (is_array($fieldArray) && count($fieldArray) > 0) {
            foreach ($fieldArray as $field) {
                $headerFields .= "<th>" . ucfirst($field['name']) . "</th>\n";
                $fieldTemplate .= "<td>{{ \$item->{$field['name']} }}</td>\n";
            }
            $columnCount = count($fieldArray) + 1; // +1 untuk kolom Actions
        } else {
            // Contoh default jika tidak ada field
            $headerFields = "<th>Example Column</th>\n";
            $fieldTemplate = "<td><!-- Masukkan field data Anda di sini, contoh: {{ \$item->nama }} --></td>\n";
            $columnCount = 2; // Example Column + Actions column
        }

        // Template untuk index.blade.php
        $templateIndex = <<<BLADE
@extends('layouts.app')

@section('title', '{$name} List')

@push('style')
<!-- CSS Libraries -->
@endpush

@section('content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1>{$name} List</h1>
            <div class="section-header-button">
                <a href="{{ route('{$lowerCaseName}.create') }}" class="btn btn-primary">Tambah {$name}</a>
            </div>
        </div>

        <div class="section-body">
            <div class="card">
                <div class="card-header">
                    <h4>{$name} List</h4>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    {$headerFields}
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (\$items as \$item)
                                    <tr>
                                        {$fieldTemplate}
                                        <td>
                                            <a href="{{ route('{$lowerCaseName}.edit', \$item->id) }}" class="btn btn-warning btn-sm">Edit</a>
                                            <form action="{{ route('{$lowerCaseName}.destroy', \$item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                                @if(count(\$items) == 0)
                                    <tr>
                                        <td colspan="{$columnCount}">Data tidak tersedia.</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<!-- JS Libraries -->

<!-- Page Specific JS File -->
@endpush
BLADE;

        // Template untuk create.blade.php dan edit.blade.php (form yang dapat digunakan ulang)
        $formFields = '';
        if (is_array($fieldArray) && count($fieldArray) > 0) {
            foreach ($fieldArray as $field) {
                $formFields .= <<<HTML
            <div class="form-group">
                <label>{{ ucfirst('{$field['name']}') }}</label>
                <input type="text" class="form-control" name="{$field['name']}" value="{{ old('{$field['name']}', \$item ? \$item->{$field['name']} : '') }}" required>
            </div>

HTML;
            }
        } else {
            $formFields = <<<HTML
            <div class="form-group">
                <!-- Contoh field: ganti sesuai kebutuhan -->
                <label>Nama</label>
                <input type="text" class="form-control" name="nama" value="{{ old('nama', \$item ? \$item->nama : '') }}" required>
            </div>

HTML;
        }

        // Template untuk create.blade.php dan edit.blade.php
        $templateForm = <<<BLADE
@extends('layouts.app')

@section('title')
    @if(\$item)
        Edit {$name}
    @else
        Add {$name}
    @endif
@endsection

@push('style')
<!-- CSS Libraries -->
@endpush

@section('content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1>@yield('title')</h1>
        </div>

        <div class="section-body">
            <div class="card">
                <div class="card-body">
                    <form action="{{ \$item ? route('{$lowerCaseName}.update', \$item->id) : route('{$lowerCaseName}.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @if(\$item) @method('PUT') @endif
                        {$formFields}
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="{{ route('{$lowerCaseName}.index') }}" class="btn btn-secondary">Back</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<!-- JS Libraries -->

<!-- Page Specific JS File -->
@endpush
BLADE;

        // Membuat views: index, create, edit
        File::put($path . '/index.blade.php', $templateIndex);
        File::put($path . '/create.blade.php', $templateForm);
        File::put($path . '/edit.blade.php', $templateForm);

        $this->info('Views untuk ' . $name . ' berhasil dibuat.');
    }

    protected function updateRoutes($name, $lowerCaseName)
    {
        // Namespace controller lengkap
        $controllerName = "App\\Http\\Controllers\\" . $name . "Controller";

        // Route CRUD individual dengan nama model singular
        $routes = "\nRoute::resource('$lowerCaseName', $controllerName::class);\n";

        // Path file routes/web.php
        $routePath = base_path('routes/web.php');

        // Mendapatkan konten file route
        $routeFile = File::get($routePath);

        // Mencari blok middleware auth
        $middlewareGroup = "Route::middleware(['auth'])->group(function () {";

        // Cek apakah blok middleware auth ada
        if (strpos($routeFile, $middlewareGroup) !== false) {
            // Tambahkan route di dalam blok middleware auth
            $newRouteFile = str_replace($middlewareGroup, $middlewareGroup . $routes, $routeFile);
            File::put($routePath, $newRouteFile);
            $this->info('Route untuk ' . $lowerCaseName . ' berhasil ditambahkan ke dalam grup middleware auth.');
        } else {
            // Jika blok middleware auth tidak ditemukan, tambahkan di luar
            File::append($routePath, $routes);
            $this->warn('Route untuk ' . $lowerCaseName . ' ditambahkan di luar middleware auth.');
        }
    }

    protected function updateSidebar($lowerCaseName)
    {
        $sidebarPath = resource_path('views/components/sidebar.blade.php');

        if (File::exists($sidebarPath)) {
            // Kode menu sidebar yang akan ditambahkan
            $sidebarMenu = <<<SIDEBAR
<li class="{{ Request::is('{$lowerCaseName}*') ? 'active' : '' }}">
    <a class="nav-link" href="{{ route('{$lowerCaseName}.index') }}">
        <i class="fas fa-database"></i> <span>{{ ucfirst('{$lowerCaseName}') }}</span>
    </a>
</li>
SIDEBAR;

            // Mencari tempat untuk menyisipkan, setelah "menu-header Starter" di sidebar
            $sidebarContent = File::get($sidebarPath);
            $menuHeader = "<li class=\"menu-header\">Starter</li>";

            if (strpos($sidebarContent, $menuHeader) !== false) {
                // Sisipkan menu sidebar sebelum bagian "Starter"
                $newSidebarContent = str_replace($menuHeader, $sidebarMenu . "\n" . $menuHeader, $sidebarContent);
                File::put($sidebarPath, $newSidebarContent);
                $this->info('Link untuk ' . $lowerCaseName . ' berhasil ditambahkan ke sidebar.');
            } else {
                // Jika tidak menemukan "Starter", tambahkan di akhir
                File::append($sidebarPath, "\n" . $sidebarMenu);
                $this->warn('Tidak dapat menemukan bagian "Starter" di sidebar. Menu ditambahkan di akhir.');
            }
        } else {
            $this->error('File sidebar tidak ditemukan.');
        }
    }
}
