@php($readonly = isset($role) && $role->is_system)
<div class="card-body">
@if($readonly)<div class="alert alert-info">Role sistem dikelola oleh konfigurasi aplikasi dan hanya dapat dilihat.</div>@endif
<div class="mb-3"><label class="form-label">Nama</label><input class="form-control" name="name" value="{{ old('name', $role->name ?? '') }}" required @readonly($readonly)></div>
<div class="mb-3"><label class="form-label">Slug</label><input class="form-control" name="slug" value="{{ old('slug', $role->slug ?? '') }}" required @readonly($readonly)><small class="form-hint">Huruf kecil, angka, dan tanda hubung.</small></div>
<div class="mb-4"><label class="form-label">Deskripsi</label><textarea class="form-control" name="description" rows="2" @readonly($readonly)>{{ old('description', $role->description ?? '') }}</textarea></div>
<h3 class="card-title">Permission</h3>
@php($selected = collect(old('permissions', isset($role) ? $role->permissions->pluck('id')->all() : []))->map(fn($id) => (string) $id)->all())
@foreach($permissionGroups as $group => $permissions)<fieldset class="mb-4"><legend class="h4 text-capitalize">{{ str_replace('-', ' ', $group) }}</legend><div class="row">@foreach($permissions as $permission)<div class="col-md-6"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(in_array((string) $permission->id, $selected, true)) @disabled($readonly)><span class="form-check-label"><code>{{ $permission->slug }}</code></span></label></div>@endforeach</div></fieldset>@endforeach
</div>
<div class="card-footer text-end"><a class="btn" href="{{ route('roles.index') }}">Kembali</a>@unless($readonly)<button class="btn btn-primary" type="submit">Simpan role</button>@endunless</div>
